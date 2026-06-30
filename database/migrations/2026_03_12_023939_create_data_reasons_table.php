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
        Schema::create('data_reasons', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('data_id');
            $table->unsignedBigInteger('feedback_reason_id');
            $table->text('feedback')->nullable();
            $table->foreign('data_id')->references('id')->on('data');
            $table->foreign('feedback_reason_id')->references('id')->on('feedback_reasons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_reasons');
    }
};
