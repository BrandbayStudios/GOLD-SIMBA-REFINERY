<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_PROVIDER = 'provider';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'status', 'avatar', 'address', 'city', 'is_demo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isProvider(): bool
    {
        return $this->role === self::ROLE_PROVIDER;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function customerRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'customer_id');
    }

    public function providerJobs(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'provider_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'provider_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'provider_id');
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => route('admin.dashboard'),
            self::ROLE_PROVIDER => route('provider.dashboard'),
            default => route('customer.dashboard'),
        };
    }

    public function avatarUrl(): string
    {
        if ($this->avatar) {
            return str_starts_with($this->avatar, 'http') || str_starts_with($this->avatar, '/')
                ? $this->avatar
                : asset('storage/'.$this->avatar);
        }

        return 'https://ui-avatars.com/api/?background=101D35&color=FF8A24&bold=true&name='.urlencode($this->name);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
