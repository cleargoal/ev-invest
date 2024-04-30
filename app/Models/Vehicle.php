<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'cost', 'produced', 'mileage', 'price', ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
