<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Correção do usuário sobre uma estimativa do sistema (escrutabilidade, docs/plano-escrutabilidade.md).
 */
class Correction extends Model
{
    public const ACOES = ['corrigir', 'desfazer'];

    public const ALVOS = ['nivel', 'meta', 'tipos', 'todas'];

    protected $fillable = [
        'searched_at',
        'user_id',
        'acao',
        'alvo',
        'chave_rea',
        'repositorio',
        'titulo',
        'valor_anterior',
        'valor_novo',
        'faixa_anterior',
        'faixa_nova',
        'itens_afetados',
        'grupo',
    ];

    protected $casts = [
        'valor_anterior' => 'array',
        'valor_novo' => 'array',
    ];
}
