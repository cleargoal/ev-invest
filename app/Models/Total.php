<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Constants\FinancialConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Total extends Model
{
    use HasFactory;

    protected $fillable = ['payment_id', 'amount', 'created_at'];

    protected $casts = [
        'amount' => MoneyCast::class,
    ];


    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actualTotalAttribute(): float|int
    {
        return $this::orderBy('id', 'desc')->first()->amount / FinancialConstants::CENTS_PER_DOLLAR;
    }
}
