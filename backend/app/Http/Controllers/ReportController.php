<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\AdminLog;
use App\Models\JobPost;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    /**
     * Submit a new report
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reported_entity_type' => 'required|in:job_post,user,post,comment,event',
                'reported_entity_id' => 'required|integer',
                'reason' => 'required|in:spam,inappropriate_content,scam_fraud,harassment,false_information,other',
                'description' => 'nullable|string|max:1000',
            ]);

            $report = Report::create([
                'reporter_user_id' => Auth::id(),
                'reported_entity_type' => $validated['reported_entity_type'],
                'reported_entity_id' => $validated['reported_entity_id'],
                'reason' => $validated['reason'],
                'description' => $validated['description'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => $report
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all reports (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $status = $request->get('status', 'all');
            
            $query = Report::with(['reporter:id,first_name,last_name,email']);
            
            if ($status !== 'all') {
                $query->where('status', $status);
            }
            
            $reports = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific report (Admin only)
     */
    public function show($id): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $report = Report::with(['reporter', 'reviewer'])->findOrFail($id);
            
            // Get the reported entity details
            $reportedEntity = $report->getReportedEntity() ?? $this->findReportedEntity($report);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'report' => $report,
                    'reported_entity' => $reportedEntity
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update report status (Admin only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,under_review,resolved,dismissed',
                'admin_notes' => 'nullable|string|max:1000',
            ]);

            $report = Report::findOrFail($id);
            
            $report->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? $report->admin_notes,
                'reviewed_by_admin_id' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report updated successfully',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve a report (Admin only)
     */
    public function resolve(Request $request, $id): JsonResponse
    {
        if (!$this->authorizeAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $validated = $request->validate([
                'action' => 'required|in:remove_content,suspend_user,issue_warning',
            ]);

            $report = Report::findOrFail($id);
            $reportedEntity = $report->getReportedEntity() ?? $this->findReportedEntity($report);
            $action = $validated['action'];
            $admin = Auth::user();
            $targetUser = $this->resolveTargetUser($report, $reportedEntity);
            $actionLabel = match ($action) {
                'remove_content' => 'Remove Content',
                'suspend_user' => 'Suspend User',
                'issue_warning' => 'Issue Warning',
                default => 'Resolved',
            };

            if ($action === 'remove_content') {
                if ($report->reported_entity_type === 'job_post' && $reportedEntity instanceof JobPost) {
                    if (Schema::hasColumn('job_posts', 'is_active')) {
                        $reportedEntity->is_active = false;
                    }
                    if (Schema::hasColumn('job_posts', 'status')) {
                        $reportedEntity->status = 'archived';
                    }
                    if (Schema::hasColumn('job_posts', 'is_active') || Schema::hasColumn('job_posts', 'status')) {
                        $reportedEntity->save();
                    }
                } elseif ($report->reported_entity_type === 'post' && $reportedEntity instanceof Post) {
                    if (Schema::hasTable('comments')) {
                        $reportedEntity->comments()->delete();
                    }
                    if (Schema::hasTable('likes')) {
                        $reportedEntity->likes()->delete();
                    }
                    $reportedEntity->delete();
                } elseif ($report->reported_entity_type === 'comment' && $reportedEntity instanceof Comment) {
                    $reportedEntity->delete();
                } elseif ($report->reported_entity_type === 'event' && $reportedEntity instanceof Event) {
                    if (Schema::hasColumn('events', 'status')) {
                        $reportedEntity->status = 'archived';
                        $reportedEntity->save();
                    } else {
                        $reportedEntity->delete();
                    }
                } elseif ($targetUser) {
                    if (Schema::hasColumn('users', 'is_active')) {
                        $targetUser->is_active = false;
                    }
                    if (Schema::hasColumn('users', 'status')) {
                        $targetUser->status = 'suspended';
                    }
                    if (Schema::hasColumn('users', 'is_active') || Schema::hasColumn('users', 'status')) {
                        $targetUser->save();
                    }
                }
            }

            if ($action === 'suspend_user') {
                if ($targetUser) {
                    if (Schema::hasColumn('users', 'is_active')) {
                        $targetUser->is_active = false;
                    }
                    if (Schema::hasColumn('users', 'status')) {
                        $targetUser->status = 'suspended';
                    }
                    if (Schema::hasColumn('users', 'is_active') || Schema::hasColumn('users', 'status')) {
                        $targetUser->save();
                    }
                }
            }

            if ($action === 'issue_warning') {
                if ($targetUser) {
                    $warningMessage = 'You have received a formal warning regarding your recent post. Further violations may lead to permanent account suspension.';
                    if (Schema::hasTable('user_warnings')) {
                        $warningPayload = ['created_at' => now(), 'updated_at' => now()];
                        if (Schema::hasColumn('user_warnings', 'user_id')) {
                            $warningPayload['user_id'] = $targetUser->id;
                        }
                        if (Schema::hasColumn('user_warnings', 'issued_by_admin_id')) {
                            $warningPayload['issued_by_admin_id'] = Auth::id();
                        } elseif (Schema::hasColumn('user_warnings', 'admin_id')) {
                            $warningPayload['admin_id'] = Auth::id();
                        }
                        if (Schema::hasColumn('user_warnings', 'report_id')) {
                            $warningPayload['report_id'] = $report->id;
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
                            $warningId = DB::table('user_warnings')->insertGetId($warningPayload);
                            if (Schema::hasTable('admin_logs')) {
                                $adminName = $admin ? trim(($admin->full_name ?? trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')))) : '';
                                $issuanceMessage = ($adminName !== '' ? 'Admin ' . $adminName : 'Admin') . ' issued warning to User #' . $targetUser->id . ' for Report #' . $report->id;
                                $logPayload = [];
                                if (Schema::hasColumn('admin_logs', 'user_id')) $logPayload['user_id'] = Auth::id();
                                if (Schema::hasColumn('admin_logs', 'action')) $logPayload['action'] = $issuanceMessage;
                                if (Schema::hasColumn('admin_logs', 'model_type')) $logPayload['model_type'] = 'UserWarning';
                                if (Schema::hasColumn('admin_logs', 'model_id')) $logPayload['model_id'] = $warningId;
                                if (Schema::hasColumn('admin_logs', 'details')) {
                                    $logPayload['details'] = json_encode([
                                        'report_id' => $report->id,
                                        'target_user_id' => $targetUser->id
                                    ]);
                                }
                                if (Schema::hasColumn('admin_logs', 'created_at')) $logPayload['created_at'] = now();
                                if (Schema::hasColumn('admin_logs', 'updated_at')) $logPayload['updated_at'] = now();
                                if (count($logPayload) > 0) {
                                    AdminLog::create($logPayload);
                                }
                            }
                        }
                    }
                }
            }

            $report->update([
                'status' => 'resolved',
                'reviewed_by_admin_id' => Auth::id(),
                'reviewed_at' => now(),
                'admin_notes' => $actionLabel
            ]);

            $adminName = $admin ? trim(($admin->full_name ?? trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')))) : 'Admin';
            
            $reporter = User::find($report->reporter_user_id);
            $reporterName = $reporter ? trim(($reporter->first_name ?? '') . ' ' . ($reporter->last_name ?? '')) : 'Unknown User';
            $targetName = 'Unknown Entity';
            if ($targetUser) {
                $targetName = trim(($targetUser->first_name ?? '') . ' ' . ($targetUser->last_name ?? ''));
                if ($report->reported_entity_type !== 'user') $targetName .= ' (' . str_replace('_', ' ', $report->reported_entity_type) . ')';
            } elseif ($report->reported_entity_type === 'job_post' && $reportedEntity instanceof JobPost) {
                $targetName = 'Job Post "' . $reportedEntity->title . '"';
            } elseif ($report->reported_entity_type === 'event' && $reportedEntity instanceof Event) {
                $targetName = 'Event "' . $reportedEntity->title . '"';
            }

            $logMessage = $adminName . ' resolved report #' . $report->id . ' (' . $reporterName . ' reported ' . $targetName . ') via ' . $actionLabel;
            
            if (Schema::hasTable('admin_logs')) {
                $logPayload = [];
                if (Schema::hasColumn('admin_logs', 'user_id')) $logPayload['user_id'] = Auth::id();
                if (Schema::hasColumn('admin_logs', 'action')) $logPayload['action'] = $logMessage;
                if (Schema::hasColumn('admin_logs', 'model_type')) $logPayload['model_type'] = 'Report';
                if (Schema::hasColumn('admin_logs', 'model_id')) $logPayload['model_id'] = $report->id;
                if (Schema::hasColumn('admin_logs', 'details')) {
                    $logPayload['details'] = json_encode([
                        'action' => $action,
                        'reported_entity_type' => $report->reported_entity_type,
                        'reported_entity_id' => $report->reported_entity_id,
                        'target_user_id' => $targetUser?->id,
                        'reporter_user_id' => $report->reporter_user_id
                    ]);
                }
                if (Schema::hasColumn('admin_logs', 'created_at')) $logPayload['created_at'] = now();
                if (Schema::hasColumn('admin_logs', 'updated_at')) $logPayload['updated_at'] = now();
                if (count($logPayload) > 0) {
                    AdminLog::create($logPayload);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Report resolved successfully',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function resolveTargetUser(Report $report, $reportedEntity): ?User
    {
        if ($report->reported_entity_type === 'user' && $reportedEntity instanceof User) {
            return $reportedEntity;
        }
        if ($report->reported_entity_type === 'job_post' && $reportedEntity instanceof JobPost) {
            return $reportedEntity->user_id ? User::find($reportedEntity->user_id) : null;
        }
        if ($report->reported_entity_type === 'post' && $reportedEntity instanceof Post) {
            return $reportedEntity->user_id ? User::find($reportedEntity->user_id) : null;
        }
        if ($report->reported_entity_type === 'comment' && $reportedEntity instanceof Comment) {
            return $reportedEntity->user_id ? User::find($reportedEntity->user_id) : null;
        }
        if ($report->reported_entity_type === 'event' && $reportedEntity instanceof Event) {
            return $reportedEntity->created_by ? User::find($reportedEntity->created_by) : null;
        }
        return null;
    }

    private function findReportedEntity(Report $report)
    {
        $entityId = $report->reported_entity_id;
        return match ($report->reported_entity_type) {
            'job_post' => Schema::hasTable('job_posts') ? JobPost::where('job_id', $entityId)->first() : null,
            'user' => Schema::hasTable('users') ? User::find($entityId) : null,
            'post' => Schema::hasTable('posts') ? Post::where('post_id', $entityId)->first() : null,
            'comment' => Schema::hasTable('comments') ? Comment::where('comment_id', $entityId)->first() : null,
            'event' => Schema::hasTable('events') ? Event::find($entityId) : null,
            default => null,
        };
    }

    private function authorizeAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'admin';
    }
}
