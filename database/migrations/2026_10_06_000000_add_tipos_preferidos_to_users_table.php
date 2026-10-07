<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos preferidos salvos na conta (docs/plano-escrutabilidade.md §15).
 * `tipos_preferidos` null = o usuário não salvou preferência e valem os tipos dos colaboradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('tipos_preferidos')->nullable();
            $table->boolean('incluir_tipos_colaboradores')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tipos_preferidos', 'incluir_tipos_colaboradores']);
        });
    }
};
