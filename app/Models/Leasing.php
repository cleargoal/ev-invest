<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leasing extends Model
{
    use HasFactory;

    protected $casts = [
        'price' => MoneyCast::class,
    ];
    protected $fillable = ['month', 'year', 'price', 'created_at'];

}
