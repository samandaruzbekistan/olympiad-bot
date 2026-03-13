<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('olympiad_id')
                ->constrained('olympiads')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('status')->default('pending');

            $table->string('payment_status')->default('pending');

            $table->string('ticket_number')->unique()->nullable();

            $table->timestamps();

            $table->index(['user_id', 'olympiad_id']);
            $table->index('status');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};

