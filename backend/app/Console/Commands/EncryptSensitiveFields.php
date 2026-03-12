<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptSensitiveFields extends Command
{
    protected $signature = 'users:encrypt-fields';
    protected $description = 'Encrypt/re-encrypt sensitive fields using encryptString (strips any PHP serialization wrapper)';

    protected array $fields = ['phone_number', 'birthdate', 'salary_range'];

    public function handle(): int
    {
        $this->info('Re-encrypting sensitive user fields without PHP serialization wrapper...');

        $users = DB::table('users')->select(array_merge(['id'], $this->fields))->get();
        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $toUpdate = [];

            foreach ($this->fields as $field) {
                $raw = $user->$field ?? null;
                if ($raw === null || $raw === '') {
                    $skipped++;
                    continue;
                }

                // Try to decrypt and get the real plain value
                $plainValue = null;

                try {
                    $decrypted = Crypt::decryptString($raw);
                    // Check if the decrypted value itself is still a PHP-serialized string
                    if ($this->isSerialized($decrypted)) {
                        $plainValue = unserialize($decrypted);
                        // Re-encryption needed
                    } else {
                        // Already clean — confirm it's correct and skip
                        $skipped++;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Not encrypted with encryptString — try Crypt::decrypt (with serialization)
                    try {
                        $decrypted = Crypt::decrypt($raw);
                        $plainValue = is_string($decrypted) && $this->isSerialized($decrypted)
                            ? unserialize($decrypted)
                            : $decrypted;
                    } catch (\Throwable $e2) {
                        // Not encrypted at all — use as plaintext
                        $plainValue = $raw;
                    }
                }

                if ($plainValue === null || $plainValue === '') {
                    $skipped++;
                    continue;
                }

                $toUpdate[$field] = Crypt::encryptString((string) $plainValue);
                $this->line("  [$field] user #{$user->id}: re-encrypted to plain value");
            }

            if (!empty($toUpdate)) {
                DB::table('users')->where('id', $user->id)->update($toUpdate);
                $updated++;
            }
        }

        $this->info("Done. Updated: {$updated} user(s). Skipped: {$skipped} field(s).");

        return Command::SUCCESS;
    }

    private function isSerialized(string $value): bool
    {
        return preg_match('/^(s|i|d|b|a|O|C):\d+:/', $value) === 1;
    }
}
