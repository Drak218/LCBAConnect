<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change birthdate from DATE to TEXT to support encrypted storage
        if (Schema::hasColumn('users', 'birthdate')) {
            DB::statement('ALTER TABLE users MODIFY birthdate TEXT NULL');
        }

        // Change salary_range from ENUM to TEXT to support encrypted storage
        if (Schema::hasColumn('users', 'salary_range')) {
            DB::statement('ALTER TABLE users MODIFY salary_range TEXT NULL');
        }

        // Change phone_number from VARCHAR to TEXT to support encrypted storage
        if (Schema::hasColumn('users', 'phone_number')) {
            DB::statement('ALTER TABLE users MODIFY phone_number TEXT NULL');
        }
    }

    public function down(): void
    {
        // Revert - note: any encrypted values will become invalid
        if (Schema::hasColumn('users', 'birthdate')) {
            DB::statement('ALTER TABLE users MODIFY birthdate DATE NULL');
        }
        if (Schema::hasColumn('users', 'salary_range')) {
            DB::statement("ALTER TABLE users MODIFY salary_range ENUM(
                'prefer_not_to_say','20000-39999','40000-59999',
                '60000-79999','80000-99999','100000+'
            ) NULL");
        }
        if (Schema::hasColumn('users', 'phone_number')) {
            DB::statement('ALTER TABLE users MODIFY phone_number VARCHAR(20) NULL');
        }
    }
};
