<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operation extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description'];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
