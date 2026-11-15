<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O contexto da busca (o que o SisREAd usou sobre o participante) fica na linha `data`, para a busca
 * voltar depois de um F5 ou de uma aba fechada (URL ?busca=<id>).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->json('contexto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropColumn('contexto');
        });
    }
};
