<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payme_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('payme_id')->index();
            $table->integer('state')->default(1);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('time');
            $table->json('account')->nullable();

            $table->timestamps();

            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payme_transactions');
    }
};
