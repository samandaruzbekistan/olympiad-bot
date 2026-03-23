<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olympiad_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('olympiads', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->nullable()
                ->after('id')
                ->constrained('olympiad_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('olympiads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });

        Schema::dropIfExists('olympiad_types');
    }
};
