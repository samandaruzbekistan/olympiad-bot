<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olympiad_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olympiad_id')->constrained('olympiads')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['olympiad_id', 'subject_id']);
        });

        if (Schema::hasColumn('olympiads', 'subject_id')) {
            DB::table('olympiads')->whereNotNull('subject_id')->orderBy('id')->chunk(100, function ($olympiads) {
                foreach ($olympiads as $row) {
                    DB::table('olympiad_subject')->insert([
                        'olympiad_id' => $row->id,
                        'subject_id' => $row->subject_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
            Schema::table('olympiads', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
                $table->dropIndex(['subject_id']);
            });
            Schema::table('olympiads', function (Blueprint $table) {
                $table->dropColumn('subject_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('olympiads', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('description')->constrained('subjects')->nullOnDelete();
            $table->index('subject_id');
        });
        $rows = DB::table('olympiad_subject')->orderBy('olympiad_id')->get();
        $seen = [];
        foreach ($rows as $row) {
            if (empty($seen[$row->olympiad_id])) {
                DB::table('olympiads')->where('id', $row->olympiad_id)->update(['subject_id' => $row->subject_id]);
                $seen[$row->olympiad_id] = true;
            }
        }
        Schema::dropIfExists('olympiad_subject');
    }
};
