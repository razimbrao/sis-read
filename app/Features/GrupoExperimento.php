<?php

namespace App\Features;

use App\Experimento\Experimento;

/**
 * Grupo do participante no experimento de explicabilidade (feature rica do Pennant).
 *
 * O Pennant grava o valor resolvido por escopo (usuário ou visitante), então o grupo é estável.
 * Só é resolvido aqui quem ainda não tem grupo: link e comando artisan gravam o valor direto.
 */
class GrupoExperimento
{
    public string $name = 'grupo-experimento';

    public function resolve(mixed $scope): string
    {
        return app(Experimento::class)->grupoInicial();
    }
}
