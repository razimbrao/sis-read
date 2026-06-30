<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Data;

class FeedbackReason extends Model
{
    use HasFactory;

    public function data(): BelongsToMany
    {
        return $this->belongsToMany(Data::class, 'data_reasons', 'feedback_reason_id', 'data_id')
                    ->withPivot('feedback') // Allows you to read the user's comment from the pivot
                    ->withTimestamps();     // Ensures created_at and updated_at are handled
    }
}
