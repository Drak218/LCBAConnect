<?php

namespace App\Http\Controllers;

use App\Models\JobPost;
use App\Models\User;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobPostController extends Controller
{
    /**
     * Get all job posts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = JobPost::with('poster:id,first_name,last_name,middle_name');

            // Filter by status - only show approved jobs for non-admin users
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                $query->where('status', 'approved');
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%");
                });
            }

            // Location filter
            if ($request->has('location') && $request->location) {
                $query->where('location', 'like', "%{$request->location}%");
            }

            // Work type filter
            if ($request->has('work_type') && $request->work_type) {
                $query->where('work_type', $request->work_type);
            }

            // Industry filter
            if ($request->has('industry') && $request->industry) {
                $query->where('industry', $request->industry);
            }

            // Experience level filter
            if ($request->has('experience_level') && $request->experience_level !== '') {
                $experienceLevel = trim($request->experience_level);
                if (preg_match('/^\d+\s*\+$/', $experienceLevel)) {
                    $minValue = (int) $experienceLevel;
                    $query->where('experience_level', '>=', $minValue);
                } elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $experienceLevel, $matches)) {
                    $minValue = (int) $matches[1];
                    $maxValue = (int) $matches[2];
                    $query->whereBetween('experience_level', [$minValue, $maxValue]);
                } elseif (is_numeric($experienceLevel)) {
                    $query->where('experience_level', (int) $experienceLevel);
                }
            }

            $jobs = $query->orderBy('created_at', 'desc')
                         ->get();

            return response()->json([
                'success' => true,
                'data' => $jobs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific job post
     */
    public function show($id): JsonResponse
    {
        try {
            $query = JobPost::with('poster:id,first_name,last_name,middle_name');
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                $query->where('status', 'approved');
            }
            $job = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $job
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
    }

    /**
     * Create a new job post
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:200',
                'description' => 'required|string',
                'company_name' => 'nullable|string|max:150',
                'location' => 'nullable|string|max:150',
                'work_type' => 'nullable|string|max:50',
                'required_skills' => 'nullable|array',
                'preferred_skills' => 'nullable|array',
                'industry' => 'nullable|string|max:100',
                'experience_level' => 'nullable|integer|min:0|max:100',
                'salary_range' => 'nullable|string|max:50',
                'application_link' => 'nullable|url|max:500',
                'is_active' => 'boolean',
            ]);

            $user = Auth::user();
            $isAdmin = $user && $user->role === 'admin';

            $job = JobPost::create([
                'posted_by_admin' => $isAdmin,
                'user_id'         => Auth::id(),
                'status'          => $isAdmin ? 'approved' : 'pending',
                ...$validated
            ]);

            // Load poster relationship
            $job->load('poster:id,first_name,last_name,middle_name');

            // Audit log
            AdminLog::create([
                'user_id'    => Auth::id(),
                'action'     => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) . ' posted job "' . $job->title . '"' . ($job->company_name ? ' at ' . $job->company_name : '') . ($isAdmin ? '' : ' (pending approval)'),
                'model_type' => 'JobPost',
                'model_id'   => $job->job_id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode(['action_type' => 'create_job_post', 'title' => $job->title, 'status' => $job->status]),
            ]);

            return response()->json([
                'success' => true,
                'data' => $job,
                'message' => $isAdmin ? 'Job post created successfully' : 'Job post submitted and pending admin approval'
            ], 201);
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

            $job = JobPost::with('poster')->findOrFail($id);
            $action = $validated['action'];
            $admin = Auth::user();
            $targetUser = $job->user_id ? User::find($job->user_id) : null;
            $actionLabel = match ($action) {
                'remove_content' => 'Remove Content',
                'suspend_user' => 'Suspend User',
                'issue_warning' => 'Issue Warning',
                default => 'Moderate',
            };

            if ($action === 'remove_content') {
                if (Schema::hasColumn('job_posts', 'is_active')) {
                    $job->is_active = false;
                }
                if (Schema::hasColumn('job_posts', 'status')) {
                    $job->status = 'archived';
                }
                if (Schema::hasColumn('job_posts', 'is_active') || Schema::hasColumn('job_posts', 'status')) {
                    $job->save();
                }
            }

            if ($action === 'suspend_user') {
                $this->suspendUser($targetUser);
            }

            if ($action === 'issue_warning') {
                $this->issueWarning($targetUser, $admin);
            }

            $entityId = $action === 'suspend_user' ? ($targetUser?->id ?? $job->job_id) : $job->job_id;
            $modelType = $action === 'suspend_user' ? 'User' : 'JobPost';
            $this->logFeedAction($request, $admin, $actionLabel, $entityId, $modelType, [
                'action' => $action,
                'job_id' => $job->job_id,
                'target_user_id' => $targetUser?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Action completed successfully',
                'data' => $job->fresh('poster')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unique filter values for jobs directory
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $user = Auth::user();
            $baseQuery = JobPost::query();
            if (!$user || $user->role !== 'admin') {
                $baseQuery->where('status', 'approved');
            }

            $locations = (clone $baseQuery)->whereNotNull('location')
                ->distinct()
                ->pluck('location')
                ->filter()
                ->sort()
                ->values();

            $industries = (clone $baseQuery)->whereNotNull('industry')
                ->distinct()
                ->pluck('industry')
                ->filter()
                ->sort()
                ->values();

            $workTypes = (clone $baseQuery)->whereNotNull('work_type')
                ->distinct()
                ->pluck('work_type')
                ->filter()
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'locations' => $locations,
                    'industries' => $industries,
                    'work_types' => $workTypes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
        $resourceTitle = $details['title'] ?? $details['job_title'] ?? null;
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
