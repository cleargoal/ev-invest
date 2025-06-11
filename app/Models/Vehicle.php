<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'cost', 'produced', 'mileage', 'price', 'plan_sale', 'created_at', 'sale_date', 'profit', 'sale_duration'];

    protected $casts = [
        'price' => MoneyCast::class,
        'cost' => MoneyCast::class,
        'plan_sale' => MoneyCast::class,
        'profit' => MoneyCast::class,
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
