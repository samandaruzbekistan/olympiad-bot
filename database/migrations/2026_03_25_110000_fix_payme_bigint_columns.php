<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `payme_transactions` MODIFY `create_time` VARCHAR(32) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE `payme_transactions` MODIFY `perform_time` VARCHAR(32) NULL");
        DB::statement("ALTER TABLE `payme_transactions` MODIFY `cancel_time` VARCHAR(32) NULL");
        DB::statement("ALTER TABLE `payme_transactions` MODIFY `payme_time` VARCHAR(32) NOT NULL DEFAULT '0'");
    }

    public function down(): void
    {
    }
};
