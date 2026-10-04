<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->timestamp('searched_at')->nullable()->index();
            $table->foreignId('user_id')->nullable();
            $table->string('acao', 20);
            $table->string('alvo', 20);
            $table->string('chave_rea', 20)->nullable();
            $table->string('repositorio', 50)->nullable();
            $table->string('titulo')->nullable();
            $table->json('valor_anterior')->nullable();
            $table->json('valor_novo')->nullable();
            $table->string('faixa_anterior', 20)->nullable();
            $table->string('faixa_nova', 20)->nullable();
            $table->unsignedInteger('itens_afetados')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
