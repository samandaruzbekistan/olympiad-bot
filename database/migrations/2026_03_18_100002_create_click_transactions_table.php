<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('click_transactions', function (Blueprint $table) {
            $table->id();

            $table->string('click_trans_id')->index();
            $table->string('click_paydoc_id')->nullable();
            $table->unsignedBigInteger('merchant_trans_id');
            $table->decimal('amount', 12, 2);
            $table->integer('status')->default(0);
            $table->string('sign_time');

            $table->timestamps();

            $table->index('merchant_trans_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_transactions');
    }
};
