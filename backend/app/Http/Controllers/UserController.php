<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Alumni;
use App\Models\AdminLog;
use App\Mail\AccountApprovedEmail;
use App\Mail\AccountRejectedEmail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    /**
     * Get all users with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if ($request->has('role') && $request->role === 'mentor') {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            // Create cache key based on query parameters
            $cacheKey = 'users_' . md5(json_encode($request->all()));

            $hasVerificationStatusColumn = Schema::hasColumn('users', 'lcba_verification_status');
            $hasEmployeeFlagColumn = Schema::hasColumn('users', 'is_lcba_employee_faculty');
            $buildUsersQuery = function () use ($request) {
                // Fetch ALL users of the requested role (or non-mentors) into memory first.
                // We MUST do this because fields like first_name, program, etc. are now encrypted
                // and cannot be searched or filtered directly in the SQL query.
                $query = User::query()->where('role', '!=', 'mentor');
                
                if ($request->has('role') && $request->role) {
                    $query->where('role', $request->role);
                }

                $users = $query->get();

                // Apply Search and Filters in memory using Laravel Collections
                if ($request->has('search') || $request->has('program') || $request->has('batch')) {
                    $search = $request->has('search') ? strtolower($request->search) : null;
                    $program = $request->has('program') ? $request->program : null;
                    $batch = $request->has('batch') ? $request->batch : null;

                    $users = $users->filter(function ($user) use ($search, $program, $batch) {
                        $matchSearch = true;
                        if ($search) {
                            $firstName = strtolower($user->first_name ?? '');
                            $lastName = strtolower($user->last_name ?? '');
                            $email = strtolower($user->email ?? '');
                            $headline = strtolower($user->headline ?? '');
                            
                            $matchSearch = str_contains($firstName, $search) || 
                                           str_contains($lastName, $search) || 
                                           str_contains($email, $search) || 
                                           str_contains($headline, $search);
                        }

                        $matchProgram = true;
                        if ($program) {
                            $matchProgram = ($user->program === $program);
                        }

                        $matchBatch = true;
                        if ($batch) {
                            $matchBatch = ($user->batch === $batch);
                        }

                        return $matchSearch && $matchProgram && $matchBatch;
                    });
                }

                // Reset keys since filter generates a non-sequential array
                return $users->values();
            };

            // Check cache first (15 minute cache)
            try {
                $users = Cache::remember($cacheKey, 900, $buildUsersQuery);
            } catch (\Exception $cacheError) {
                $users = $buildUsersQuery();
            }

            if ($hasVerificationStatusColumn && $hasEmployeeFlagColumn) {
                $users = $users->map(function ($user) {
                    if ($user instanceof User && empty($user->lcba_verification_status)) {
                        $user->lcba_verification_status = $user->is_lcba_employee_faculty ? 'verified' : 'rejected';
                    }
                    return $user;
                });
            }

            $includeLegacyAlumni = false;
            if ($request->has('role') && $request->role) {
                $includeLegacyAlumni = $request->role === 'alumni';
            } else {
                $includeLegacyAlumni = !$users->contains(fn ($user) => ($user->role ?? null) === 'alumni');
            }

            if ($includeLegacyAlumni && Schema::hasTable('alumni')) {
                $legacyQuery = Alumni::query();

                if ($request->has('search') && $request->search) {
                    $search = $request->search;
                    $legacyQuery->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('current_job', 'like', "%{$search}%")
                          ->orWhere('industry', 'like', "%{$search}%")
                          ->orWhere('course', 'like', "%{$search}%");
                    });
                }

                if ($request->has('program') && $request->program) {
                    $legacyQuery->where('course', $request->program);
                }

                if ($request->has('batch') && $request->batch) {
                    $legacyQuery->where('graduation_year', $request->batch);
                }

                $legacyAlumni = $legacyQuery->get()->map(function ($alumni) {
                    return [
                        'id' => $alumni->alumni_id,
                        'first_name' => $alumni->first_name,
                        'last_name' => $alumni->last_name,
                        'email' => $alumni->email,
                        'role' => 'alumni',
                        'program' => $alumni->course,
                        'batch' => $alumni->graduation_year ? (string) $alumni->graduation_year : null,
                        'headline' => $alumni->current_job ? $alumni->current_job . ' | ' . $alumni->course : null,
                        'industry' => $alumni->industry,
                        'current_job_title' => $alumni->current_job,
                        'bio' => $alumni->bio,
                        'skills' => [],
                        'profile_picture' => $alumni->profile_photo,
                    ];
                });

                $users = $users->concat($legacyAlumni)->values();
            }

            // Apply limit and exclude if requested
            if ($request->has('exclude')) {
                $excludeId = $request->exclude;
                $users = $users->filter(fn($u) => $u->id != $excludeId)->values();
            }

            $usersArray = $users->filter(function ($user) {
                // Gracefully skip any user that throws a decryption error on ANY field
                try {
                    if ($user instanceof \App\Models\User) {
                        // Calling toArray() forces Eloquent to cast all attributes.
                        // If any encrypted field contains plaintext, this will throw DecryptException.
                        $user->toArray();
                    }
                    return true;
                } catch (\Throwable $e) {
                    return false;
                }
            })->values();

            if ($request->has('limit')) {
                $limit = (int) $request->limit;
                if ($limit > 0) {
                    $usersArray = $usersArray->take($limit)->values();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $usersArray
            ]);
        } catch (\Exception $e) {
            if (!($request->has('role') && $request->role && $request->role !== 'alumni') && Schema::hasTable('alumni')) {
                try {
                    $legacyQuery = Alumni::query();

                    if ($request->has('search') && $request->search) {
                        $search = $request->search;
                        $legacyQuery->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('current_job', 'like', "%{$search}%")
                              ->orWhere('industry', 'like', "%{$search}%")
                              ->orWhere('course', 'like', "%{$search}%");
                        });
                    }

                    if ($request->has('program') && $request->program) {
                        $legacyQuery->where('course', $request->program);
                    }

                    if ($request->has('batch') && $request->batch) {
                        $legacyQuery->where('graduation_year', $request->batch);
                    }

                    $legacyAlumni = $legacyQuery->get()->map(function ($alumni) {
                        return [
                            'id' => $alumni->alumni_id,
                            'first_name' => $alumni->first_name,
                            'last_name' => $alumni->last_name,
                            'email' => $alumni->email,
                            'role' => 'alumni',
                            'program' => $alumni->course,
                            'batch' => $alumni->graduation_year ? (string) $alumni->graduation_year : null,
                            'headline' => $alumni->current_job ? $alumni->current_job . ' | ' . $alumni->course : null,
                            'industry' => $alumni->industry,
                            'current_job_title' => $alumni->current_job,
                            'bio' => $alumni->bio,
                            'skills' => [],
                            'profile_picture' => $alumni->profile_photo,
                        ];
                    });

                    return response()->json([
                        'success' => true,
                        'data' => $legacyAlumni
                    ]);
                } catch (\Exception $legacyError) {
                }
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific user by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Only allow users to update their own profile unless they're admin
            if (Auth::id() != $user->id && Auth::user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validated = $request->validate([
                'first_name' => 'sometimes|string|max:50',
                'middle_name' => 'nullable|string|max:50',
                'last_name' => 'sometimes|string|max:50',
                'suffix' => 'nullable|string|max:20',
                'birthdate' => 'nullable|date',
                'headline' => 'nullable|string|max:100',
                'bio' => 'nullable|string',
                // Contact & Socials
                'linkedin_url' => 'nullable|url|max:255',
                'portfolio_url' => 'nullable|url|max:255',
                // Education
                'program' => 'nullable|string|max:255',
                'batch' => 'nullable|string|max:20',
                'highest_educational_attainment' => 'nullable|in:elementary,high_school,senior_high,bachelors,masters,doctorate',
                // Location
                'city' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                // Career
                'current_job_title' => 'nullable|string|max:100',
                'industry' => 'nullable|string|max:100',
                'experience_level' => 'nullable|string|max:50',
                'employment_status' => 'nullable|in:employed_full_time,employed_part_time,self_employed,in_study,unemployed_looking,unemployed_not_looking',
                'employment_sector' => 'nullable|in:public_government,private,ngo_nonprofit',
                'years_of_experience' => 'nullable|numeric|min:0|max:100',
                'salary_range' => 'nullable|string|max:100',
                // Skills
                'skills' => 'nullable|array',
                'skills.*' => 'string|max:100',
                // Career Preferences
                'work_setup_preferences' => 'nullable|array',
                'work_setup_preferences.*' => 'in:on_site,hybrid,remote',
                'employment_type_preferences' => 'nullable|array',
                'employment_type_preferences.*' => 'in:full_time,part_time,contract,internship',
                'industries_of_interest' => 'nullable|array',
                'industries_of_interest.*' => 'string|max:100',
                // LCBA Employee/Faculty
                'is_lcba_employee_faculty' => 'nullable|boolean',
            ]);

            // Update skills and increment usage counts
            if (isset($validated['skills'])) {
                foreach ($validated['skills'] as $skillName) {
                    $skill = \App\Models\SkillsTaxonomy::firstOrCreate(
                        ['name' => $skillName],
                        ['category' => null]
                    );
                    $skill->incrementUsage();
                }
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Profile updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unique filter values for alumni directory
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $users = collect();
            try {
                $users = User::where('role', 'alumni')->get();
            } catch (\Exception $e) {
                // Ignore query error, fallback handles legacy
            }

            $cities = $users->pluck('city')->filter()->unique()->sort()->values();
            $industries = $users->pluck('industry')->filter()->unique()->sort()->values();
            $programs = $users->pluck('program')->filter()->unique()->sort()->values();
            $batches = $users->pluck('batch')->filter()->unique()->sort()->values();

            if ($cities->isEmpty() && $industries->isEmpty() && $programs->isEmpty() && $batches->isEmpty() && Schema::hasTable('alumni')) {
                $cities = collect();
                $industries = Alumni::whereNotNull('industry')
                    ->distinct()
                    ->pluck('industry')
                    ->filter()
                    ->sort()
                    ->values();
                $programs = Alumni::whereNotNull('course')
                    ->distinct()
                    ->pluck('course')
                    ->filter()
                    ->sort()
                    ->values();
                $batches = Alumni::whereNotNull('graduation_year')
                    ->distinct()
                    ->pluck('graduation_year')
                    ->map(fn ($year) => (string) $year)
                    ->filter()
                    ->sort()
                    ->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'cities' => $cities,
                    'industries' => $industries,
                    'programs' => $programs,
                    'batches' => $batches,
                ]
            ]);
        } catch (\Exception $e) {
            if (Schema::hasTable('alumni')) {
                try {
                    $industries = Alumni::whereNotNull('industry')
                        ->distinct()
                        ->pluck('industry')
                        ->filter()
                        ->sort()
                        ->values();
                    $programs = Alumni::whereNotNull('course')
                        ->distinct()
                        ->pluck('course')
                        ->filter()
                        ->sort()
                        ->values();
                    $batches = Alumni::whereNotNull('graduation_year')
                        ->distinct()
                        ->pluck('graduation_year')
                        ->map(fn ($year) => (string) $year)
                        ->filter()
                        ->sort()
                        ->values();

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'cities' => collect(),
                            'industries' => $industries,
                            'programs' => $programs,
                            'batches' => $batches,
                        ]
                    ]);
                } catch (\Exception $legacyError) {
                }
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAdminLogs(Request $request): JsonResponse
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $limit = (int) $request->get('limit', 100);
            if ($limit < 1) {
                $limit = 1;
            }
            if ($limit > 500) {
                $limit = 500;
            }

            $query = AdminLog::query()
                ->with('user')
                ->orderByDesc('created_at');

            if ($request->has('from') && $request->from) {
                $query->whereDate('created_at', '>=', $request->from);
            }

            $logs = $query->limit($limit)->get()->map(function ($log) {
                $actor = $log->user;
                $actorName = $actor
                    ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) . ' (' . ($actor->role ?? 'user') . ')'
                    : 'Unknown';

                $targetUser = null;
                if ($log->model_type === 'User' && $log->model_id && $log->model_id !== $log->user_id) {
                    $target = \App\Models\User::find($log->model_id);
                    if ($target) {
                        $targetUser = [
                            'id'    => $target->id,
                            'name'  => trim(($target->first_name ?? '') . ' ' . ($target->last_name ?? '')),
                            'email' => $target->email,
                            'role'  => $target->role,
                        ];
                    }
                }

                $details = null;
                if ($log->details) {
                    $details = json_decode($log->details, true);
                }

                return [
                    'id'          => $log->log_id ?? $log->id,
                    'action'      => $log->action ?? 'activity',
                    'description' => $log->action ?? 'activity',
                    'actor_id'    => $log->user_id,
                    'actor_name'  => $actorName,
                    'target_user' => $targetUser,
                    'model_type'  => $log->model_type,
                    'model_id'    => $log->model_id,
                    'ip_address'  => $log->ip_address,
                    'user_agent'  => $log->user_agent,
                    'details'     => $details,
                    'created_at'  => $log->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a user (admin only)
     */
    public function approveUser(Request $request, $id): JsonResponse
    {
        try {
            $authUser = Auth::user();
            
            // Check if user is admin
            if (!$authUser || $authUser->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::findOrFail($id);

            // Prevent modifying admin accounts
            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $user->is_verified = true;
            $user->save();

            try {
                Cache::forget('users_*');
            } catch (\Throwable $e) {
            }

            // Send approval email to user
            try {
                Mail::to($user->email)->send(new AccountApprovedEmail($user));
            } catch (\Exception $e) {
                Log::error('Failed to send approval email: ' . $e->getMessage());
            }

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' approved user #' . $user->id . ' (' . $targetName . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'  => 'admin_approve_user',
                    'target_email' => $user->email,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User approved successfully.',
                'user'    => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a user (admin only)
     */
    public function rejectUser(Request $request, $id): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $reason = $request->input('reason', 'Your registration did not meet our verification requirements.');

            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new AccountRejectedEmail($user, $reason));
                } catch (\Throwable $e) {
                    Log::error('Failed to send rejection email: ' . $e->getMessage());
                }
            }

            $user->delete();

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' rejected & removed user ' . $targetName . ' (' . $user->email . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'  => 'admin_reject_user',
                    'reason'       => $reason,
                    'target_email' => $user->email,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User rejected and removed.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function approveEmployeeVerification(Request $request, $id): JsonResponse
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $action = $request->input('action');
            $hasEmployeeFlag = Schema::hasColumn('users', 'is_lcba_employee_faculty');
            $hasVerificationStatus = Schema::hasColumn('users', 'lcba_verification_status');
            if ($action === 'remove') {
                $updates = [];
                if ($hasEmployeeFlag) {
                    $updates['is_lcba_employee_faculty'] = false;
                }
                if ($hasVerificationStatus) {
                    $updates['lcba_verification_status'] = 'rejected';
                }
                if (!empty($updates)) {
                    $user->fill($updates);
                    $user->save();
                }

                $adminName = Auth::user()->full_name ?? trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? ''));
                $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                AdminLog::create([
                    'user_id'    => Auth::id(),
                    'action'     => trim($adminName) . ' revoked employee/faculty badge from ' . $targetName . ' (' . $user->email . ')',
                    'model_type' => 'User',
                    'model_id'   => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'details'    => json_encode([
                        'action_type'              => 'revoke_employee_badge',
                        'is_lcba_employee_faculty' => $user->is_lcba_employee_faculty,
                        'lcba_verification_status' => $user->lcba_verification_status
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Employee or faculty badge marked as unverified.',
                    'user' => $user
                ]);
            }

            $updates = [];
            if ($hasEmployeeFlag) {
                $updates['is_lcba_employee_faculty'] = true;
            }
            if ($hasVerificationStatus) {
                $updates['lcba_verification_status'] = 'verified';
            }
            if (!empty($updates)) {
                $user->fill($updates);
                $user->save();
            }

            $adminName = Auth::user()->full_name ?? trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            AdminLog::create([
                'user_id'    => Auth::id(),
                'action'     => trim($adminName) . ' verified employee/faculty badge for ' . $targetName . ' (' . $user->email . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'              => 'verify_employee_badge',
                    'is_lcba_employee_faculty' => $user->is_lcba_employee_faculty,
                    'lcba_verification_status' => $user->lcba_verification_status
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee or faculty badge marked as verified.',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function rejectEmployeeVerification(Request $request, $id): JsonResponse
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $updates = [];
            if (Schema::hasColumn('users', 'is_lcba_employee_faculty')) {
                $updates['is_lcba_employee_faculty'] = false;
            }
            if (Schema::hasColumn('users', 'lcba_verification_status')) {
                $updates['lcba_verification_status'] = 'rejected';
            }
            if (!empty($updates)) {
                $user->fill($updates);
                $user->save();
            }

            $notificationMessage = 'Your Employee ID submission was marked as unverified. Please ensure the document is clear and valid.';
            if ($user->email) {
                try {
                    Mail::raw($notificationMessage, function ($message) use ($user) {
                        $message->to($user->email)
                            ->subject('Employee ID Verification Rejected');
                    });
                } catch (\Throwable $e) {
                    Log::error('Failed to send employee verification rejection email: ' . $e->getMessage());
                }
            }

            $adminName = Auth::user()->full_name ?? trim((Auth::user()->first_name ?? '') . ' ' . (Auth::user()->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            AdminLog::create([
                'user_id'    => Auth::id(),
                'action'     => trim($adminName) . ' rejected employee ID verification for ' . $targetName . ' (' . $user->email . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'              => 'reject_employee_verification',
                    'is_lcba_employee_faculty' => $user->is_lcba_employee_faculty,
                    'lcba_verification_status' => $user->lcba_verification_status,
                    'reason'                   => $request->input('reason'),
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee or faculty badge marked as unverified.',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeeIdImage($id)
    {
        $authUser = Auth::user();
        if (!$authUser || !in_array($authUser->role, ['admin', 'staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);
            if (!$user->lcba_employee_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee ID image not found.'
                ], 404);
            }

            $storagePath = storage_path('app/employee_ids/' . $user->lcba_employee_id);
            $publicPath = public_path('uploads/employee_ids/' . $user->lcba_employee_id);

            if (!file_exists($storagePath) && file_exists($publicPath)) {
                $storageDir = storage_path('app/employee_ids');
                if (!file_exists($storageDir)) {
                    mkdir($storageDir, 0755, true);
                }
                @rename($publicPath, $storagePath);
            }

            if (!file_exists($storagePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee ID image not found.'
                ], 404);
            }

            return response()->file($storagePath);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $rawIsActive = $request->input('is_active');
            $isActive = filter_var($rawIsActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid is_active value.'
                ], 422);
            }

            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $user->is_active = $isActive;
            if ($isActive) {
                $user->status = 'active';
            } elseif ($user->status !== 'suspended') {
                $user->status = 'inactive';
            }
            $user->save();

            try {
                Cache::forget('users_*');
            } catch (\Throwable $e) {
            }

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $actionLabel = $isActive ? 'activated' : 'deactivated';
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' ' . $actionLabel . ' account of ' . $targetName . ' (' . $user->email . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type' => 'update_user_status',
                    'is_active'   => $user->is_active,
                    'status'      => $user->status
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated.',
                'data' => $user
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateRole(Request $request, $id): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $newRole = $request->input('role');
        if (!$newRole || !in_array($newRole, ['admin', 'alumni'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role value.'
            ], 422);
        }

        $adminPassword = trim((string) $request->input('admin_password', ''));
        $storedPassword = (string) ($authUser->password ?? '');
        if ($adminPassword === '' || $storedPassword === '') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin password.'
            ], 403);
        }
        $hashInfo = Hash::info($storedPassword);
        if (($hashInfo['algo'] ?? 0) === 0) {
            if (!hash_equals($storedPassword, $adminPassword)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid admin password.'
                ], 403);
            }
        } elseif (!Hash::check($adminPassword, $storedPassword)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin password.'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->id === $authUser->id && $newRole !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove your own admin privileges.'
                ], 403);
            }

            if ($user->role === $newRole) {
                return response()->json([
                    'success' => true,
                    'message' => 'User role already set.',
                    'data' => $user
                ]);
            }

            $previousRole = $user->role;
            $user->role = $newRole;
            $user->save();

            try {
                Cache::forget('users_*');
            } catch (\Throwable $e) {
            }

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $actionLabel = $newRole === 'admin' ? 'granted admin privileges to' : 'removed admin privileges from';
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' ' . $actionLabel . ' ' . $targetName . ' (' . $user->email . ')',
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'   => 'update_user_role',
                    'previous_role' => $previousRole,
                    'new_role'      => $newRole,
                    'target_email'  => $user->email,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User role updated.',
                'data' => $user
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function importCsv(Request $request): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $validated = $request->validate([
                'file' => 'required|file'
            ]);

            $file = $validated['file'];

            // Validate file extension manually (avoids requiring php_fileinfo for MIME detection)
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'txt'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Please upload a .csv file.'
                ], 422);
            }

            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to read CSV file.'
                ], 422);
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                return response()->json([
                    'success' => false,
                    'message' => 'CSV header row is missing.'
                ], 422);
            }

            $normalizedHeaders = array_map(fn ($h) => strtolower(trim($h ?? '')), $headers);
            $required = ['email', 'first_name', 'last_name', 'program', 'batch', 'highest_educational_attainment'];
            foreach ($required as $req) {
                if (!in_array($req, $normalizedHeaders, true)) {
                    fclose($handle);
                    return response()->json([
                        'success' => false,
                        'message' => 'CSV is missing required column: ' . $req
                    ], 422);
                }
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            $hasIsActive = Schema::hasColumn('users', 'is_active');
            $hasStatus = Schema::hasColumn('users', 'status');
            $hasProgram = Schema::hasColumn('users', 'program');
            $hasBatch = Schema::hasColumn('users', 'batch');
            $hasHeadline = Schema::hasColumn('users', 'headline');
            $hasIndustry = Schema::hasColumn('users', 'industry');
            $hasExperience = Schema::hasColumn('users', 'experience_level');
            $hasHighestEd = Schema::hasColumn('users', 'highest_educational_attainment');
            $hasRole = Schema::hasColumn('users', 'role');

            while (($row = fgetcsv($handle)) !== false) {
                $row = array_pad($row, count($normalizedHeaders), '');
                $data = [];
                foreach ($normalizedHeaders as $i => $key) {
                    $data[$key] = isset($row[$i]) ? trim($row[$i]) : '';
                }

                $email = $data['email'] ?? '';
                if ($email === '') {
                    $skipped++;
                    continue;
                }

                $firstName = $data['first_name'] ?? '';
                $lastName = $data['last_name'] ?? '';
                $program = $data['program'] ?? '';
                $batch = $data['batch'] ?? '';
                $highestEd = $data['highest_educational_attainment'] ?? '';

                if ($firstName === '' || $lastName === '') {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = $email . ': first_name and last_name are required.';
                    }
                    continue;
                }

                if ($program === '') {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = $email . ': program is required.';
                    }
                    continue;
                }

                if ($batch === '') {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = $email . ': batch is required.';
                    }
                    continue;
                }

                if ($highestEd === '') {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = $email . ': highest_educational_attainment is required.';
                    }
                    continue;
                }

                $existing = User::where('email', $email)->first();
                if ($existing && $existing->role === 'admin') {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];

                if ($hasRole) {
                    $payload['role'] = 'alumni';
                }
                if ($hasProgram) {
                    $payload['program'] = $program;
                }
                if ($hasBatch) {
                    $payload['batch'] = $batch;
                }
                if ($hasHighestEd) {
                    $payload['highest_educational_attainment'] = $highestEd;
                }
                if ($hasHeadline && array_key_exists('headline', $data)) {
                    $payload['headline'] = $data['headline'];
                }
                if ($hasIndustry && array_key_exists('industry', $data)) {
                    $payload['industry'] = $data['industry'];
                }
                if ($hasExperience && array_key_exists('experience_level', $data)) {
                    $payload['experience_level'] = $data['experience_level'];
                }

                if (!$existing) {
                    $payload['email'] = $email;
                    $payload['password'] = Hash::make(Str::random(12));
                    if ($hasIsActive) {
                        $payload['is_active'] = true;
                    }
                    if ($hasStatus) {
                        $payload['status'] = 'active';
                    }
                }

                try {
                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        User::create($payload);
                        $created++;
                    }
                } catch (\Throwable $rowError) {
                    $skipped++;
                    if (count($errors) < 5) {
                        $errors[] = $email . ': ' . $rowError->getMessage();
                    }
                }
            }

            fclose($handle);

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' imported alumni CSV (' . $created . ' created, ' . $updated . ' updated, ' . $skipped . ' skipped)',
                'model_type' => 'User',
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type' => 'import_alumni_csv',
                    'created'     => $created,
                    'updated'     => $updated,
                    'skipped'     => $skipped
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'CSV import completed.',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify admin accounts.'
                ], 403);
            }

            $mode = $request->input('mode', 'temporary');
            if (!in_array($mode, ['temporary', 'email'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset mode.'
                ], 422);
            }

            $tempPassword = $mode === 'temporary'
                ? trim((string) $request->input('temp_password', ''))
                : Str::random(12);

            if (!$tempPassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Temporary password is required.'
                ], 422);
            }

            $user->password = Hash::make($tempPassword);
            $user->save();

            try {
                Cache::forget('users_*');
            } catch (\Throwable $e) {
            }

            $adminName = $authUser->full_name ?? trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
            $targetName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            AdminLog::create([
                'user_id'    => $authUser->id,
                'action'     => trim($adminName) . ' reset password for ' . $targetName . ' (' . $user->email . ')' . ($mode === 'email' ? ' via email link' : ' with temporary password'),
                'model_type' => 'User',
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'details'    => json_encode([
                    'action_type'  => 'admin_reset_password',
                    'mode'         => $mode,
                    'target_email' => $user->email,
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => $mode === 'email' ? 'Password reset link sent.' : 'Password reset successfully.',
                'data' => [
                    'user_id' => $user->id
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
