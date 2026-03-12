<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

$failedIds = [7, 8, 15, 23, 24, 30, 31, 32, 33];
$users = User::whereIn('id', $failedIds)->get();

$encryptedFields = [
    'first_name', 'last_name', 'middle_name', 'suffix', 
    'bio', 'city', 'municipality', 'country', 
    'linkedin_url', 'portfolio_url', 'program', 'batch',
    'highest_educational_attainment', 'experience_level', 'industry',
    'skills', 'career_interests',
    'phone_number', 'salary_range', 'contact_number', 'address', 'location'
];

foreach ($failedIds as $id) {
    echo "Checking User ID: $id\n";
    $rawUser = DB::table('users')->where('id', $id)->first();
    
    $fixes = [];
    foreach ($encryptedFields as $field) {
        if (!property_exists($rawUser, $field)) continue;
        
        $value = $rawUser->$field;
        if ($value === null || $value === '') continue;

        try {
            $decrypted = Crypt::decryptString($value);
            // It's fine
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            echo "  - Field '$field' is corrupt or plaintext. Re-encrypting...\n";
            // If it's pure JSON array for skills/career_interests, format it
            if (in_array($field, ['skills', 'career_interests'])) {
                if (is_string($value) && (str_starts_with($value, '[') || $value === 'null')) {
                    // It's still plaintext JSON
                    $fixes[$field] = Crypt::encryptString($value);
                } else {
                    // It's something else entirely, maybe raw PHP array casted to string?
                    $fixes[$field] = Crypt::encryptString(json_encode([])); // Fallback to avoid complete breaking
                }
            } else {
                // Regular string field
                $fixes[$field] = Crypt::encryptString((string)$value);
            }
        }
    }
    
    if (!empty($fixes)) {
        DB::table('users')->where('id', $id)->update($fixes);
        echo "  -> Saved fixes for User ID $id.\n";
    }
}

echo "Done fixing!\n";
