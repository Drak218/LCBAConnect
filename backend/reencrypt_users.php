<?php
/**
 * Re-encrypt all users who have plaintext (unencrypted) data.
 * Run this ONCE via: php artisan tinker --execute="require 'reencrypt_users.php';"
 *
 * Laravel's encrypted casts will automatically handle encryption on save.
 * Any user whose fields already contain valid encrypted values will be
 * transparently decrypted and re-encrypted (safe to run multiple times).
 */

use App\Models\User;
use Illuminate\Support\Facades\Log;

$encryptedFields = [
    'first_name', 'last_name', 'middle_name', 'suffix',
    'birthdate', 'phone_number', 'salary_range', 'bio',
    'city', 'municipality', 'country',
    'linkedin_url', 'portfolio_url',
    'program', 'batch', 'highest_educational_attainment',
    'experience_level', 'industry',
    'skills', 'career_interests',
];

$users = User::all();
$total   = $users->count();
$success = 0;
$skipped = 0;
$failed  = 0;

echo "Processing {$total} users...\n";

foreach ($users as $user) {
    try {
        // Try reading each encrypted field.
        // If any field throws a DecryptException, that field is still plaintext.
        $needsSave = false;
        $plainValues = [];

        foreach ($encryptedFields as $field) {
            $rawValue = $user->getRawOriginal($field);
            if ($rawValue === null || $rawValue === '') {
                continue; // nothing to encrypt
            }

            try {
                // Attempt decryption — if it succeeds, it's already encrypted
                $user->$field; // triggers cast
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Field is plaintext — capture the raw value for re-saving
                $plainValues[$field] = $rawValue;
                $needsSave = true;
            }
        }

        if ($needsSave) {
            // Temporarily bypass casts to set plaintext values,
            // then let Eloquent re-save them through the encrypted cast.
            foreach ($plainValues as $field => $raw) {
                $user->$field = $raw; // setter triggers the 'encrypted' cast
            }
            $user->saveQuietly(); // saves without firing model events
            echo "  [OK] User #{$user->id} ({$user->email}) re-encrypted " . count($plainValues) . " fields: " . implode(', ', array_keys($plainValues)) . "\n";
            $success++;
        } else {
            $skipped++;
        }
    } catch (\Throwable $e) {
        $failed++;
        echo "  [FAIL] User #{$user->id} ({$user->email}): " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
echo "  Re-encrypted: {$success}\n";
echo "  Already OK (skipped): {$skipped}\n";
echo "  Failed: {$failed}\n";
