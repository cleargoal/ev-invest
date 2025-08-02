<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'cost', 'produced', 'mileage', 'price', 'plan_sale', 
        'created_at', 'sale_date', 'profit', 'sale_duration',
        'cancelled_at', 'cancellation_reason', 'cancelled_by'
    ];

    protected $casts = [
        'price' => MoneyCast::class,
        'cost' => MoneyCast::class,
        'plan_sale' => MoneyCast::class,
        'profit' => MoneyCast::class,
        'cancelled_at' => 'datetime',
        'sale_date' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scope to filter out cancelled vehicles that still have sale data
     */
    public function scopeNotCancelled($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('cancelled_at')
              ->orWhere(function ($subQ) {
                  // Include cancelled vehicles that were "unsold" (no sale data)
                  $subQ->whereNotNull('cancelled_at')
                       ->whereNull('sale_date');
              });
        });
    }

    /**
     * Scope to get only cancelled vehicles that still have sale data
     */
    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at')
                    ->whereNotNull('sale_date');
    }

    /**
     * Scope to get only sold vehicles (not cancelled)
     */
    public function scopeSold($query)
    {
        return $query->whereNotNull('sale_date')->whereNull('cancelled_at');
    }

    /**
     * Check if the vehicle sale is cancelled (and still has sale data)
     */
    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at) && !is_null($this->sale_date);
    }

    /**
     * Check if the vehicle is sold and not cancelled
     */
    public function isSold(): bool
    {
        return !is_null($this->sale_date) && is_null($this->cancelled_at);
    }

    /**
     * Check if the vehicle was unsold (cancelled but sale data cleared)
     */
    public function isUnsold(): bool
    {
        return !is_null($this->cancelled_at) && is_null($this->sale_date);
    }
}
