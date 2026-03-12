<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BirthdayController extends Controller
{
    /**
     * Helper to safely parse birthdate
     */
    private function parseBirthdate($birthdate)
    {
        if (!$birthdate) return null;
        try {
            return \Carbon\Carbon::parse($birthdate);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get alumni celebrating birthdays today
     */
    public function getBirthdaysToday(): JsonResponse
    {
        try {
            $today = now();
            
            // Cannot use SQL queries on encrypted fields, must fetch all and filter in memory
            $users = User::where('role', 'alumni')->whereNotNull('birthdate')->get();

            $birthdays = $users->filter(function ($user) use ($today) {
                // Ensure the string decrypts properly
                try {
                    $birthdate = $this->parseBirthdate($user->birthdate);
                    if (!$birthdate) return false;
                    return $birthdate->month === $today->month && $birthdate->day === $today->day;
                } catch (\Throwable $e) {
                    return false;
                }
            })->values();

            // Filter based on privacy settings
            $visibleBirthdays = $birthdays->filter(function ($user) {
                return $user->canViewField('birthdate', Auth::user());
            })->values();

            return response()->json([
                'success' => true,
                'data' => $visibleBirthdays
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch birthdays',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get alumni celebrating birthdays this week
     */
    public function getBirthdaysThisWeek(): JsonResponse
    {
        try {
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();

            $users = User::where('role', 'alumni')->whereNotNull('birthdate')->get();

            $weekBirthdays = $users->filter(function ($user) use ($startOfWeek, $endOfWeek) {
                try {
                    $birthdate = $this->parseBirthdate($user->birthdate);
                    if (!$birthdate) return false;
                    
                    $birthdayThisYear = now()->setMonth($birthdate->month)->setDay($birthdate->day);
                    return $birthdayThisYear->between($startOfWeek, $endOfWeek);
                } catch (\Throwable $e) {
                    return false;
                }
            });

            // Filter based on privacy settings
            $visibleBirthdays = $weekBirthdays->filter(function ($user) {
                return $user->canViewField('birthdate', Auth::user());
            })->values();

            // Sort by date (month and day)
            $sorted = $visibleBirthdays->sortBy(function ($user) {
                return $this->parseBirthdate($user->birthdate)->format('m-d');
            })->values();

            return response()->json([
                'success' => true,
                'data' => $sorted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch birthdays this week',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming birthdays (next 30 days)
     */
    public function getUpcomingBirthdays(): JsonResponse
    {
        try {
            $today = now();
            $next30Days = now()->addDays(30);

            $users = User::where('role', 'alumni')->whereNotNull('birthdate')->get();

            // Filter birthdays within next 30 days
            $upcomingBirthdays = $users->filter(function ($user) use ($today, $next30Days) {
                try {
                    $birthdate = $this->parseBirthdate($user->birthdate);
                    if (!$birthdate) return false;
                    
                    $birthdayThisYear = now()->setMonth($birthdate->month)->setDay($birthdate->day);
                    
                    // If birthday already passed this year, use next year
                    if ($birthdayThisYear->isPast() && !$birthdayThisYear->isToday()) {
                        $birthdayThisYear->addYear();
                    }
                    
                    return $birthdayThisYear->between($today, $next30Days);
                } catch (\Throwable $e) {
                    return false;
                }
            });

            // Filter based on privacy settings
            $visibleBirthdays = $upcomingBirthdays->filter(function ($user) {
                return $user->canViewField('birthdate', Auth::user());
            })->values();

            // Sort by upcoming date
            $sorted = $visibleBirthdays->sortBy(function ($user) {
                $birthdate = $this->parseBirthdate($user->birthdate);
                $birthdayThisYear = now()->setMonth($birthdate->month)->setDay($birthdate->day);
                if ($birthdayThisYear->isPast() && !$birthdayThisYear->isToday()) {
                    $birthdayThisYear->addYear();
                }
                return $birthdayThisYear->timestamp;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $sorted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming birthdays',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
