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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                ->constrained('registrations')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('ticket_number')->unique();

            $table->string('qr_code')->nullable();

            $table->boolean('checked_in')->default(false);

            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

