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
        Schema::create('olympiads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('subject_id')
                ->nullable()
                ->constrained('subjects')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('price');
            $table->dateTime('start_date');
            $table->string('location_name');
            $table->string('location_address')->nullable();
            $table->string('logo')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index('subject_id');
            $table->index('start_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olympiads');
    }
};
