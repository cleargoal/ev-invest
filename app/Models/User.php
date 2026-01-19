<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\MoneyCast;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a specific role or one of the given roles
     *
     * @param string|array $roles
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles, true);
        }
        return $this->role === $roles;
    }

    /**
     * Assign a role to the user
     *
     * @param string $role
     * @return void
     */
    public function assignRole(string $role): void
    {
        $this->role = $role;
        $this->save();
    }

    /**
     * Get the user's role (with default fallback)
     *
     * @param mixed $value
     * @return string
     */
    public function getRoleAttribute($value): string
    {
        return $value ?? 'investor';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('admin');
        }

        return true;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function lastContribution(): HasOne
    {
        return $this->hasOne(Contribution::class)->latestOfMany();
    }

    public function firstContribution(): HasOne
    {
        return $this->hasOne(Contribution::class)->oldestOfMany();
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
