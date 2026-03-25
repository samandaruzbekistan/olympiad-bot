<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename `time` → `payme_time` if not done yet
        $hasTime = Schema::hasColumn('payme_transactions', 'time');
        $hasPaymeTime = Schema::hasColumn('payme_transactions', 'payme_time');

        if ($hasTime && ! $hasPaymeTime) {
            Schema::table('payme_transactions', function (Blueprint $table) {
                $table->renameColumn('time', 'payme_time');
            });
        }

        // 2. Force all ms-timestamp columns to BIGINT UNSIGNED
        DB::statement('ALTER TABLE `payme_transactions` MODIFY `payme_time` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `payme_transactions` MODIFY `create_time` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `payme_transactions` MODIFY `perform_time` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `payme_transactions` MODIFY `cancel_time` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        $hasPaymeTime = Schema::hasColumn('payme_transactions', 'payme_time');

        if ($hasPaymeTime) {
            Schema::table('payme_transactions', function (Blueprint $table) {
                $table->renameColumn('payme_time', 'time');
            });
        }
    }
};
