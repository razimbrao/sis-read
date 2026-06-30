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
        Schema::create('search_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('searched_at'); 
            $table->string('repository'); 
            $table->string('profile');   // 📊 Nível Educacional
            $table->string('interest');  // 📊 Área do Pensamento Computacional
            $table->string('meta')->nullable(); // 📊 Qual meta disparou (ou null)
            
            $table->float('total_time')->default(0);
            $table->json('breakdown')->nullable(); // 📊 Radiografia do funil
            $table->float('api_time')->default(0);
            $table->float('ollama_time')->default(0);
            $table->integer('api_calls')->default(0);
            $table->integer('ollama_calls')->default(0);
            $table->integer('items_returned')->default(0);
            $table->integer('items_filtered')->default(0);
            $table->integer('timeouts_errors')->default(0);
            $table->integer('ollama_errors')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_metrics');
    }
};
