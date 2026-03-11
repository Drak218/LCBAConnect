<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashPlaintextPasswords extends Command
{
    protected $signature = 'users:hash-passwords';
    protected $description = 'Find all users with plaintext (unhashed) passwords and re-hash them';

    public function handle(): int
    {
        $this->info('Scanning for users with plaintext passwords...');

        $users = User::all();
        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if (!$user->password) {
                $skipped++;
                continue;
            }

            $hashInfo = Hash::info($user->password);

            // algo === 0 means it is NOT a valid bcrypt/argon hash (plaintext)
            if (($hashInfo['algo'] ?? 0) === 0) {
                $user->password = Hash::make($user->password);
                $user->saveQuietly();
                $updated++;
                $this->line("  Re-hashed password for user #{$user->id} ({$user->email})");
            } else {
                $skipped++;
            }
        }

        $this->info("Done. Re-hashed: {$updated} user(s). Already hashed: {$skipped} user(s).");

        return Command::SUCCESS;
    }
}
