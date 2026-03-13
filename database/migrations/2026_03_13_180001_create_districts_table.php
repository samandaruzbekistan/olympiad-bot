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
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')
                ->constrained('regions')
                ->cascadeOnDelete()
                ->index();
            $table->string('name_uz');
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->timestamps();

            $table->index('name_uz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
        });

        Schema::dropIfExists('districts');
    }
};

