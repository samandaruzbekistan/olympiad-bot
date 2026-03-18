<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->string('transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('registration_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
