<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payme_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('perform_time')->nullable()->after('time');
            $table->unsignedBigInteger('cancel_time')->nullable()->after('perform_time');
            $table->unsignedSmallInteger('reason')->nullable()->after('cancel_time');
        });
    }

    public function down(): void
    {
        Schema::table('payme_transactions', function (Blueprint $table) {
            $table->dropColumn(['perform_time', 'cancel_time', 'reason']);
        });
    }
};
