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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('phone')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->foreignId('region_id')
                ->constrained('regions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('district_id')
                ->constrained('districts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('school')->nullable();
            $table->unsignedInteger('grade')->nullable();
            $table->timestamps();

            $table->index(['region_id', 'district_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
