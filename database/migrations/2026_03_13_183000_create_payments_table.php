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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedInteger('amount');

            $table->string('payment_system')->nullable(); // click, payme, etc

            $table->string('transaction_id')->nullable();

            $table->string('status')->default('pending'); // pending, success, failed

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('registration_id');
            $table->index('status');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

