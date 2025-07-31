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
     * Scope to filter out cancelled vehicles
     */
    public function scopeNotCancelled($query)
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Scope to get only cancelled vehicles
     */
    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_at');
    }

    /**
     * Scope to get only sold vehicles (not cancelled)
     */
    public function scopeSold($query)
    {
        return $query->whereNotNull('sale_date')->whereNull('cancelled_at');
    }

    /**
     * Check if the vehicle sale is cancelled
     */
    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }

    /**
     * Check if the vehicle is sold and not cancelled
     */
    public function isSold(): bool
    {
        return !is_null($this->sale_date) && is_null($this->cancelled_at);
    }
}
