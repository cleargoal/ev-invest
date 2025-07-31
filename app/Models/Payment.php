<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount', 'user_id', 'operation_id', 'confirmed', 'created_at',
        'is_cancelled', 'cancelled_at', 'cancelled_by'
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'confirmed' => 'boolean',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function total(): HasOne
    {
        return $this->hasOne(Total::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scope to filter out cancelled payments
     */
    public function scopeNotCancelled($query)
    {
        return $query->where('is_cancelled', false);
    }

    /**
     * Scope to get only cancelled payments
     */
    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }

    /**
     * Scope to get active payments (confirmed and not cancelled)
     */
    public function scopeActive($query)
    {
        return $query->where('confirmed', true)->where('is_cancelled', false);
    }

    /**
     * Check if the payment is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->is_cancelled;
    }

    /**
     * Check if the payment is active (confirmed and not cancelled)
     */
    public function isActive(): bool
    {
        return $this->confirmed && !$this->is_cancelled;
    }
}
