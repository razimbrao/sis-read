<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupo do experimento de explicabilidade em cada busca, evento, correção e feedback
 * (docs/feature-flags.md). Registros anteriores ficam com `grupo` nulo.
 *
 * search_metrics não ganha coluna: é gravada pelos jobs e se liga à busca por `searched_at`,
 * de onde o grupo vem (data.grupo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->string('grupo', 20)->nullable()->index();
            $table->json('flags')->nullable();
            $table->string('participante', 64)->nullable()->index();
        });

        foreach (['explanation_events', 'corrections', 'data_reasons'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->string('grupo', 20)->nullable();
            });
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->string('grupo', 20)->nullable();
            $table->string('participante', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropIndex(['grupo']);
            $table->dropIndex(['participante']);
            $table->dropColumn(['grupo', 'flags', 'participante']);
        });

        foreach (['explanation_events', 'corrections', 'data_reasons'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('grupo');
            });
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropColumn(['grupo', 'participante']);
        });
    }
};
