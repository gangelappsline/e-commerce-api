<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $guard_name = 'api';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'avatar', 'is_active', 'last_login_at', 'default_role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_user')
            ->using(BusinessUser::class)
            ->withPivot(['role', 'permissions', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function validateForPassportPasswordGrant(string $password): bool
    {
        return $this->is_active && Hash::check($password, $this->getAuthPassword());
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }
}
