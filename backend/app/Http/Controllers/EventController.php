<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Event::with(['creator:id,first_name,last_name,email'])
                ->withCount([
                    'rsvps as attendees_count' => function ($query) {
                        $query->where('status', 'going');
                    },
                    'rsvps as interested_count' => function ($query) {
                        $query->where('status', 'interested');
                    },
                ]);
            if (Schema::hasColumn('events', 'status')) {
                $query->where('status', '!=', 'archived');
            }
            $user = Auth::user();
            if ((!$user || $user->role !== 'admin') && Schema::hasColumn('events', 'status')) {
                $query->where('status', 'active');
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Date range filter
            if ($request->has('upcoming') && $request->upcoming) {
                $query->where('start_date', '>=', now());
            }

            $events = $query->orderBy('start_date', 'asc')->get();
            $userId = Auth::id();
            $userRsvps = $userId ? EventRsvp::where('user_id', $userId)->pluck('status', 'event_id') : collect();

            $events = $events->map(function ($event) use ($userRsvps) {
                $status = $userRsvps->get($event->id);
                $event->user_rsvp = $status === 'not_going' ? null : $status;
                return $event;
            });

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $query = Event::with(['creator:id,first_name,last_name,email'])
                ->withCount([
                    'rsvps as attendees_count' => function ($query) {
                        $query->where('status', 'going');
                    },
                    'rsvps as interested_count' => function ($query) {
                        $query->where('status', 'interested');
                    },
                ]);
            if (Schema::hasColumn('events', 'status')) {
                $query->where('status', '!=', 'archived');
            }
            $user = Auth::user();
            if ((!$user || $user->role !== 'admin') && Schema::hasColumn('events', 'status')) {
                $query->where('status', 'active');
            }
            $event = $query->findOrFail($id);
            $userId = Auth::id();
            if ($userId) {
                $status = EventRsvp::where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->value('status');
                $event->user_rsvp = $status === 'not_going' ? null : $status;
            }

            return response()->json([
                'success' => true,
                'data' => $event
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:100',
                'description' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'location' => 'required|string|max:255',
                'link' => 'nullable|url|max:2048',
                'type' => 'nullable|string|max:50',
            ]);

            $event = Event::create([
                'created_by' => Auth::id(),
                'status' => 'active',
                ...$validated
            ]);
            AdminLog::create([
                'user_id'    => Auth::id(),
                'action'     => trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? '')) . ' created event "' . $event->title . '"',
                'model_type' => 'Event',
                'model_id'   => $event->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode(['action_type' => 'create_event', 'event_title' => $event->title]),
            ]);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Event created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function rsvp(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:going,interested,not_going',
            ]);
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }
            $event = Event::findOrFail($id);

            $existing = EventRsvp::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing && $existing->status === $validated['status']) {
                return response()->json([
                    'success' => false,
                    'message' => 'RSVP already recorded',
                ], 409);
            }

            try {
                if ($existing) {
                    $existing->update(['status' => $validated['status']]);
                } else {
                    EventRsvp::create([
                        'event_id' => $event->id,
                        'user_id' => $userId,
                        'status' => $validated['status'],
                    ]);
                }
            } catch (QueryException $queryException) {
                if ((string) $queryException->getCode() === '23000') {
                    $existing = EventRsvp::where('event_id', $event->id)
                        ->where('user_id', $userId)
                        ->first();

                    if ($existing && $existing->status === $validated['status']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'RSVP already recorded',
                        ], 409);
                    }

                    if ($existing) {
                        $existing->update(['status' => $validated['status']]);
                    } else {
                        throw $queryException;
                    }
                } else {
                    throw $queryException;
                }
            }

            $statusLabel = match ($validated['status']) {
                'going'      => 'is going to',
                'interested' => 'is interested in',
                'not_going'  => 'is not going to',
                default      => 'RSVPed to',
            };
            AdminLog::create([
                'user_id'    => $userId,
                'action'     => trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? '')) . ' ' . $statusLabel . ' event "' . $event->title . '"',
                'model_type' => 'Event',
                'model_id'   => $event->id,
                'details'    => json_encode(['action_type' => 'rsvp_event', 'rsvp_status' => $validated['status'], 'event_title' => $event->title]),
            ]);

            $attendeesCount = EventRsvp::where('event_id', $event->id)
                ->where('status', 'going')
                ->count();
            $interestedCount = EventRsvp::where('event_id', $event->id)
                ->where('status', 'interested')
                ->count();

            $userRsvp = $validated['status'] === 'not_going' ? null : $validated['status'];

            return response()->json([
                'success' => true,
                'message' => 'RSVP recorded successfully',
                'data' => [
                    'attendees_count' => $attendeesCount,
                    'interested_count' => $interestedCount,
                    'user_rsvp' => $userRsvp,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleFeatured($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $event = Event::findOrFail($id);
            $event->is_featured = !$event->is_featured;
            $event->save();

            AdminLog::create([
                'user_id'    => $user->id,
                'action'     => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) . ($event->is_featured ? ' featured' : ' unfeatured') . ' event "' . $event->title . '"',
                'model_type' => 'Event',
                'model_id'   => $event->id,
                'ip_address' => request()->ip(),
                'details'    => json_encode(['action_type' => $event->is_featured ? 'feature_event' : 'unfeature_event', 'event_title' => $event->title]),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $event->id,
                    'is_featured' => $event->is_featured,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function moderate(Request $request, $id): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $validated = $request->validate([
                'action' => 'required|in:remove_content,suspend_user,issue_warning',
            ]);

            $event = Event::with('creator')->findOrFail($id);
            $action = $validated['action'];
            $admin = Auth::user();
            $targetUser = $event->created_by ? User::find($event->created_by) : null;
            $actionLabel = match ($action) {
                'remove_content' => 'Remove Content',
                'suspend_user' => 'Suspend User',
                'issue_warning' => 'Issue Warning',
                default => 'Moderate',
            };

            if ($action === 'remove_content') {
                if (Schema::hasColumn('events', 'status')) {
                    $event->status = 'archived';
                    $event->save();
                } else {
                    $event->delete();
                }
            }

            if ($action === 'suspend_user') {
                $this->suspendUser($targetUser);
            }

            if ($action === 'issue_warning') {
                $this->issueWarning($targetUser, $admin);
            }

            $entityId = $action === 'suspend_user' ? ($targetUser?->id ?? $event->id) : $event->id;
            $modelType = $action === 'suspend_user' ? 'User' : 'Event';
            $this->logFeedAction($request, $admin, $actionLabel, $entityId, $modelType, [
                'action' => $action,
                'event_id' => $event->id,
                'target_user_id' => $targetUser?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Action completed successfully',
                'data' => $event->fresh('creator')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function authorizeAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'admin';
    }

    private function suspendUser(?User $user): void
    {
        if (!$user) {
            return;
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $user->is_active = false;
        }
        if (Schema::hasColumn('users', 'status')) {
            $user->status = 'suspended';
        }
        if (Schema::hasColumn('users', 'is_active') || Schema::hasColumn('users', 'status')) {
            $user->save();
        }
    }

    private function issueWarning(?User $targetUser, $admin): void
    {
        if (!$targetUser || !Schema::hasTable('user_warnings')) {
            return;
        }
        $warningMessage = 'You have received a formal warning regarding your recent post. Further violations may lead to permanent account suspension.';
        $warningPayload = ['created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('user_warnings', 'user_id')) {
            $warningPayload['user_id'] = $targetUser->id;
        }
        if (Schema::hasColumn('user_warnings', 'issued_by_admin_id')) {
            $warningPayload['issued_by_admin_id'] = Auth::id();
        } elseif (Schema::hasColumn('user_warnings', 'admin_id')) {
            $warningPayload['admin_id'] = Auth::id();
        }
        if (Schema::hasColumn('user_warnings', 'warning_message')) {
            $warningPayload['warning_message'] = $warningMessage;
        } elseif (Schema::hasColumn('user_warnings', 'message')) {
            $warningPayload['message'] = $warningMessage;
        }
        if (Schema::hasColumn('user_warnings', 'is_acknowledged')) {
            $warningPayload['is_acknowledged'] = false;
        } elseif (Schema::hasColumn('user_warnings', 'is_read')) {
            $warningPayload['is_read'] = false;
        }
        if (array_key_exists('user_id', $warningPayload) && (array_key_exists('warning_message', $warningPayload) || array_key_exists('message', $warningPayload))) {
            DB::table('user_warnings')->insert($warningPayload);
        }
    }

    private function logFeedAction(Request $request, $admin, string $actionLabel, int $entityId, string $modelType, array $details = []): void
    {
        if (!Schema::hasTable('admin_logs')) {
            return;
        }
        $adminName = $admin ? trim(($admin->full_name ?? trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')))) : 'Admin';
        $resourceTitle = $details['event_title'] ?? $details['title'] ?? null;
        $resourceLabel = $resourceTitle ? '"' . $resourceTitle . '"' : 'resource #' . $entityId;
        $logMessage = trim($adminName) . ' performed ' . $actionLabel . ' on ' . strtolower($modelType) . ' ' . $resourceLabel;
        $logPayload = [];
        if (Schema::hasColumn('admin_logs', 'user_id')) $logPayload['user_id'] = Auth::id();
        if (Schema::hasColumn('admin_logs', 'action')) $logPayload['action'] = $logMessage;
        if (Schema::hasColumn('admin_logs', 'model_type')) $logPayload['model_type'] = $modelType;
        if (Schema::hasColumn('admin_logs', 'model_id')) $logPayload['model_id'] = $entityId;
        if (Schema::hasColumn('admin_logs', 'ip_address')) $logPayload['ip_address'] = $request->ip();
        if (Schema::hasColumn('admin_logs', 'user_agent')) $logPayload['user_agent'] = $request->userAgent();
        if (Schema::hasColumn('admin_logs', 'details')) $logPayload['details'] = json_encode($details);
        if (Schema::hasColumn('admin_logs', 'created_at')) $logPayload['created_at'] = now();
        if (Schema::hasColumn('admin_logs', 'updated_at')) $logPayload['updated_at'] = now();
        if (count($logPayload) > 0) {
            AdminLog::create($logPayload);
        }
    }
}
