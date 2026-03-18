<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert Telegram IDs to string to avoid MySQL integer range/type issues.
        // We drop and recreate indexes explicitly to keep uniqueness (users) and performance.
        DB::statement('ALTER TABLE users DROP INDEX users_telegram_id_unique');
        DB::statement('ALTER TABLE users MODIFY telegram_id VARCHAR(32) NOT NULL');
        DB::statement('ALTER TABLE users ADD UNIQUE INDEX users_telegram_id_unique (telegram_id)');

        DB::statement('ALTER TABLE bot_sessions DROP INDEX bot_sessions_telegram_id_index');
        DB::statement('ALTER TABLE bot_sessions MODIFY telegram_id VARCHAR(32) NOT NULL');
        DB::statement('ALTER TABLE bot_sessions ADD INDEX bot_sessions_telegram_id_index (telegram_id)');
    }

    public function down(): void
    {
        // Rollback to unsigned BIGINT (original schema).
        DB::statement('ALTER TABLE users DROP INDEX users_telegram_id_unique');
        DB::statement('ALTER TABLE users MODIFY telegram_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE users ADD UNIQUE INDEX users_telegram_id_unique (telegram_id)');

        DB::statement('ALTER TABLE bot_sessions DROP INDEX bot_sessions_telegram_id_index');
        DB::statement('ALTER TABLE bot_sessions MODIFY telegram_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bot_sessions ADD INDEX bot_sessions_telegram_id_index (telegram_id)');
    }
};

