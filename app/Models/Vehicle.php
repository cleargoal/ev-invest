<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'cost', 'produced', 'mileage', 'price', 'plan_sale', 'created_at', 'sale_date'];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
