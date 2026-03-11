<?php

namespace App\Http\Controllers;

use App\Mail\UserRegistrationNotification;
use App\Models\AdminLog;
use App\Models\User;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Cloudinary\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     * Note: Admin role is not available for public registration
     * Only 'alumni' role is allowed
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'program' => $validated['program'] ?? null,
            'batch' => $validated['batch'] ?? null,
            'highest_educational_attainment' => $validated['highest_educational_attainment'] ?? null,
            'is_verified' => false, // New users require admin approval
        ]);

        // Send email notification to all admins about new registration
        try {
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new UserRegistrationNotification($user));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send registration notification: ' . $e->getMessage());
        }
        
        return response()->json([
            'message' => 'Registration successful. Your account is pending verification by our administrators.',
            'user' => $user,
            'requires_verification' => true
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $storedPassword = $user->password;
        $hashInfo = Hash::info($storedPassword);
        if (($hashInfo['algo'] ?? 0) === 0) {
            if (!hash_equals((string) $storedPassword, (string) $validated['password'])) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }
        } else {
            if (!Hash::check($validated['password'], $storedPassword)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }
        }

        if ($user->role !== 'admin' && $user->status === 'suspended') {
            return response()->json([
                'message' => "You're Account has been suspended"
            ], 403);
        }
        
        if ($user->role !== 'admin' && ($user->is_active === false || $user->status === 'inactive')) {
            return response()->json([
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        // Check if user is verified (admins bypass this check)
        if ($user->role !== 'admin' && Schema::hasColumn('users', 'is_verified') && !$user->is_verified) {
            return response()->json([
                'message' => 'Your account is pending verification by our administrators. Please check back later or contact support.',
                'requires_verification' => true
            ], 403);
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'message' => 'Token storage not initialized.'
            ], 500);
        }

        $tokenName = $request->string('device_name')->toString() ?: 'web';
        $token = $user->createToken($tokenName, $this->tokenAbilitiesFor($user))->plainTextToken;
        $warning = null;
        if (Schema::hasTable('user_warnings')) {
            $query = DB::table('user_warnings')->where('user_id', $user->id);
            if (Schema::hasColumn('user_warnings', 'is_acknowledged')) {
                $query->where('is_acknowledged', false);
            } elseif (Schema::hasColumn('user_warnings', 'is_read')) {
                $query->where('is_read', false);
            }
            $warning = $query->orderBy('created_at', 'desc')->first();
        }

        $responsePayload = [
            'message' => 'Login successful',
            'user' => $user,
            'role' => $user->role,
            'warning' => $warning ? [
                'id' => $warning->id,
                'message' => $warning->warning_message ?? $warning->message ?? null
            ] : null
        ];
        $responsePayload['token'] = $token;

        $this->logAuthEvent($user, 'login');

        return response()->json($responsePayload);
    }

    public function pendingWarning(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if ($user->role === 'admin') {
            return response()->json(['warning' => null]);
        }

        $warning = null;
        if (Schema::hasTable('user_warnings')) {
            $query = DB::table('user_warnings')->where('user_id', $user->id);
            if (Schema::hasColumn('user_warnings', 'is_acknowledged')) {
                $query->where('is_acknowledged', false);
            } elseif (Schema::hasColumn('user_warnings', 'is_read')) {
                $query->where('is_read', false);
            }
            $warning = $query->orderBy('created_at', 'desc')->first();
        }

        return response()->json([
            'warning' => $warning ? [
                'id' => $warning->id,
                'message' => $warning->warning_message ?? $warning->message ?? null
            ] : null
        ]);
    }

    public function acknowledgeWarning(Request $request, $warningId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (!Schema::hasTable('user_warnings')) {
            return response()->json(['message' => 'Warning system not available.'], 404);
        }

        $query = DB::table('user_warnings')
            ->where('id', $warningId)
            ->where('user_id', $user->id);
        if (Schema::hasColumn('user_warnings', 'is_acknowledged')) {
            $query->where('is_acknowledged', false);
        } elseif (Schema::hasColumn('user_warnings', 'is_read')) {
            $query->where('is_read', false);
        }
        $warning = $query->first();

        if (!$warning) {
            return response()->json(['message' => 'Warning not found.'], 404);
        }

        $updatePayload = ['updated_at' => now()];
        if (Schema::hasColumn('user_warnings', 'is_acknowledged')) {
            $updatePayload['is_acknowledged'] = true;
        } elseif (Schema::hasColumn('user_warnings', 'is_read')) {
            $updatePayload['is_read'] = true;
        }
        DB::table('user_warnings')
            ->where('id', $warningId)
            ->update($updatePayload);

        $ackMessage = 'User #' . $user->id . ' acknowledged warning #' . $warningId;
        AdminLog::create([
            'user_id' => $user->id,
            'action' => $ackMessage,
            'model_type' => 'UserWarning',
            'model_id' => $warningId,
            'details' => json_encode([
                'user_id' => $user->id,
                'warning_id' => $warningId
            ]),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        if ($user) {
            $this->logAuthEvent($user, 'logout');
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    private function tokenAbilitiesFor(User $user): array
    {
        return $user->role === 'admin' ? ['admin'] : ['user'];
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'headline' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            // Contact & Socials
            'phone_number' => 'nullable|string|max:20|regex:/^[0-9]+$/',
            'linkedin_url' => 'nullable|url|max:255',
            'portfolio_url' => 'nullable|url|max:255',
            // Education
            'program' => 'nullable|string|max:255',
            'batch' => 'nullable|string|max:20',
            'highest_educational_attainment' => 'nullable|in:elementary,high_school,senior_high,bachelors,masters,doctorate',
            // Location
            'city' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            // Career
            'current_job_title' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'experience_level' => 'nullable|string|max:50',
            'employment_status' => 'nullable|in:employed_full_time,employed_part_time,self_employed,in_study,unemployed_looking,unemployed_not_looking',
            'employment_sector' => 'nullable|in:public_government,private,ngo_nonprofit',
            'years_of_experience' => 'nullable|numeric|min:0|max:100',
            'salary_range' => 'nullable|string|max:100',
            // Skills (array of standardized skill names)
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            // Career Preferences
            'work_setup_preferences' => 'nullable|array',
            'work_setup_preferences.*' => 'in:on_site,hybrid,remote',
            'employment_type_preferences' => 'nullable|array',
            'employment_type_preferences.*' => 'in:full_time,part_time,contract,internship',
            'industries_of_interest' => 'nullable|array',
            'industries_of_interest.*' => 'string|max:100',
            // Visibility Settings
            'visibility_settings' => 'nullable|array',
            // Privacy Settings
            'privacy_settings' => 'nullable|array',
            'is_lcba_employee_faculty' => 'nullable|boolean',
            'lcba_employee_id_photo' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update skills and increment usage counts
        if ($request->has('skills')) {
            $skills = $request->input('skills', []);
            foreach ($skills as $skillName) {
                $skill = \App\Models\SkillsTaxonomy::firstOrCreate(
                    ['name' => $skillName],
                    ['category' => null]
                );
                $skill->incrementUsage();
            }
        }

        $updateData = $request->only([
            'first_name', 'middle_name', 'last_name', 'suffix',
            'birthdate', 'email', 'headline', 'bio',
            'public_email', 'phone_number', 'linkedin_url', 'portfolio_url',
            'program', 'batch', 'highest_educational_attainment',
            'city', 'municipality', 'country',
            'current_job_title', 'industry', 'experience_level',
            'employment_status', 'employment_sector', 'years_of_experience', 'salary_range',
            'skills',
            'work_setup_preferences', 'employment_type_preferences', 'industries_of_interest',
            'privacy_settings',
            'is_lcba_employee_faculty'
        ]);

        $hasEmployeeFlagColumn = Schema::hasColumn('users', 'is_lcba_employee_faculty');
        $hasEmployeeIdColumn = Schema::hasColumn('users', 'lcba_employee_id');
        $hasVerificationStatusColumn = Schema::hasColumn('users', 'lcba_verification_status');

        if (!$hasEmployeeFlagColumn) {
            unset($updateData['is_lcba_employee_faculty']);
        }

        if ($request->has('is_lcba_employee_faculty') && !$request->boolean('is_lcba_employee_faculty')) {
            if ($hasEmployeeIdColumn && $user->lcba_employee_id) {
                $storageOldPath = storage_path('app/employee_ids/' . $user->lcba_employee_id);
                $publicOldPath = public_path('uploads/employee_ids/' . $user->lcba_employee_id);
                if (file_exists($storageOldPath)) {
                    unlink($storageOldPath);
                }
                if (file_exists($publicOldPath)) {
                    unlink($publicOldPath);
                }
            }
            if ($hasEmployeeIdColumn) {
                $updateData['lcba_employee_id'] = null;
            }
            if ($hasVerificationStatusColumn) {
                $updateData['lcba_verification_status'] = null;
            }
        }

        if ($request->hasFile('lcba_employee_id_photo') && $hasEmployeeIdColumn) {
            $uploadPath = storage_path('app/employee_ids');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            if ($user->lcba_employee_id) {
                $storageOldPath = storage_path('app/employee_ids/' . $user->lcba_employee_id);
                $publicOldPath = public_path('uploads/employee_ids/' . $user->lcba_employee_id);
                if (file_exists($storageOldPath)) {
                    unlink($storageOldPath);
                }
                if (file_exists($publicOldPath)) {
                    unlink($publicOldPath);
                }
            }

            $file = $request->file('lcba_employee_id_photo');
            $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);

            $updateData['lcba_employee_id'] = $filename;
            if ($hasVerificationStatusColumn) {
                $updateData['lcba_verification_status'] = 'pending';
            }
            if ($hasEmployeeFlagColumn) {
                $updateData['is_lcba_employee_faculty'] = true;
            }
        }

        $user->update($updateData);

        // Re-hash plaintext password if it was stored unhashed (legacy data)
        $hashInfo = Hash::info($user->password ?? '');
        if (($hashInfo['algo'] ?? 0) === 0 && $user->password) {
            $user->password = Hash::make($user->password);
            $user->saveQuietly();
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        $validated = $request->validated();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    private function logAuthEvent(User $user, string $action): void
    {
        if (!Schema::hasTable('admin_logs')) {
            return;
        }

        AdminLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'model_type' => 'User',
            'model_id' => $user->id,
            'details' => json_encode([
                'email' => $user->email,
                'role' => $user->role,
            ]),
        ]);
    }

    /**
     * Accept community guidelines (for first-time login)
     */
    public function acceptGuidelines(Request $request)
    {
        $user = $request->user();

        // Set guidelines accepted timestamp
        $user->guidelines_accepted_at = now();
        
        // Set first login timestamp if this is the first time
        if ($user->first_login_at === null) {
            $user->first_login_at = now();
        }
        
        $user->save();

        return response()->json([
            'message' => 'Guidelines accepted successfully',
            'user' => $user->fresh()
        ]);
    }

    private function cloudinary(): ?Cloudinary
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return null;
        }

        return new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cloudinary = $this->cloudinary();
        if (!$cloudinary) {
            return response()->json([
                'message' => 'Cloudinary configuration is missing.'
            ], 500);
        }

        $user = $request->user();

        if ($user->profile_picture_public_id) {
            try {
                $cloudinary->uploadApi()->destroy($user->profile_picture_public_id, ['invalidate' => true]);
            } catch (\Exception) {
            }
        } elseif ($user->profile_picture && !str_starts_with($user->profile_picture, 'http://') && !str_starts_with($user->profile_picture, 'https://')) {
            $oldPath = public_path('uploads/profile_pictures/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $file = $request->file('profile_picture');
        $upload = $cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'profile_pictures',
            'resource_type' => 'image',
            'transformation' => [
                [
                    'width' => 300,
                    'height' => 300,
                    'crop' => 'fill',
                    'gravity' => 'auto',
                    'quality' => 'auto',
                    'fetch_format' => 'auto',
                ],
            ],
        ]);

        $user->profile_picture = $upload['secure_url'] ?? null;
        $user->profile_picture_public_id = $upload['public_id'] ?? null;
        $user->save();

        return response()->json([
            'message' => 'Profile picture uploaded successfully',
            'profile_picture_url' => $user->profile_picture,
            'user' => $user->fresh()
        ]);
    }

    public function deleteProfilePicture(Request $request)
    {
        $user = $request->user();
        $cloudinary = $this->cloudinary();

        if ($user->profile_picture_public_id && $cloudinary) {
            try {
                $cloudinary->uploadApi()->destroy($user->profile_picture_public_id, ['invalidate' => true]);
            } catch (\Exception) {
            }
        } elseif ($user->profile_picture && !str_starts_with($user->profile_picture, 'http://') && !str_starts_with($user->profile_picture, 'https://')) {
            $oldPath = public_path('uploads/profile_pictures/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $user->profile_picture = null;
        $user->profile_picture_public_id = null;
        $user->save();

        return response()->json([
            'message' => 'Profile picture deleted successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Create admin user (for system administrators only)
     * This method should be used by existing admins to create new admin accounts
     */
    public function createAdmin(Request $request)
    {
        // Check if the requesting user is an admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Only admins can create admin accounts.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'program' => 'nullable|string|max:255',
            'batch' => 'nullable|integer|min:1990|max:' . date('Y'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin', // Force admin role
            'program' => $request->program,
            'batch' => $request->batch,
        ]);

        return response()->json([
            'message' => 'Admin user created successfully',
            'user' => $user
        ], 201);
    }
}
