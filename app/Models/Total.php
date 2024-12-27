<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Total extends Model
{
    use HasFactory;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actualTotalAttribute(): float|int
    {
        return $this::orderBy('id', 'desc')->first()->amount/100;
    }
}
