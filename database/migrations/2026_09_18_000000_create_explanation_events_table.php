<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explanation_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('searched_at')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('acao', 30);
            $table->string('repositorio', 50)->nullable();
            $table->string('titulo')->nullable();
            $table->string('faixa', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explanation_events');
    }
};
