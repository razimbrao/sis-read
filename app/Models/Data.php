<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Data extends Model
{
    use HasFactory;

    protected $fillable = [
        'data',
        'searched_at',
        'finished',
        'stars',
        'time',
        'grupo',
        'flags',
        'participante',
    ];

    protected $casts = [
        'flags' => 'array',
    ];

    public function feedbackReasons(): BelongsToMany
    {
        return $this->belongsToMany(FeedbackReason::class, 'data_reasons', 'data_id', 'feedback_reason_id')
            ->withPivot('feedback') // Permite acessar a coluna de texto depois
            ->withTimestamps();     // Preenche created_at e updated_at da tabela pivot
    }
}
