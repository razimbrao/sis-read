<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Métricas do MetaClassifier: classificações reaproveitadas do cache e REAs com meta não avaliada.
     * As colunas ollama_* continuam valendo para qualquer provedor de LLM.
     */
    public function up(): void
    {
        Schema::table('search_metrics', function (Blueprint $table) {
            $table->integer('llm_cache_hits')->default(0);
            $table->integer('llm_nao_avaliados')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('search_metrics', function (Blueprint $table) {
            $table->dropColumn(['llm_cache_hits', 'llm_nao_avaliados']);
        });
    }
};
