<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas buscas no mesmo segundo não podem compartilhar a chave `searched_at`: os jobs e as outras tabelas
 * se ligam à busca por ela (FindREA::findInApi tenta de novo com +1 segundo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->unique('searched_at');
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropUnique(['searched_at']);
        });
    }
};
