<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProviderProfile extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'provider_type', 'nrc_number', 'business_reg_number', 'nrc_document', 'business_document',
        'bio', 'years_experience', 'whatsapp', 'operating_areas', 'service_radius_km', 'base_latitude', 'base_longitude',
        'current_latitude', 'current_longitude', 'location_updated_at', 'is_online', 'verification_status', 'verification_notes',
        'verified_at', 'verified_by', 'rating_avg', 'rating_count', 'jobs_completed', 'avg_response_minutes',
    ];

    protected function casts(): array
    {
        return [
            'operating_areas' => 'array',
            'is_online' => 'boolean',
            'verified_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'base_latitude' => 'float',
            'base_longitude' => 'float',
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'rating_avg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_provider');
    }

    public function isApproved(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function displayName(): string
    {
        return $this->business_name ?: $this->user->name;
    }

    /** Best known position: live location if fresh, otherwise base location. */
    public function latitude(): ?float
    {
        return $this->current_latitude ?? $this->base_latitude;
    }

    public function longitude(): ?float
    {
        return $this->current_longitude ?? $this->base_longitude;
    }

    public function scopeAvailable($q)
    {
        return $q->where('verification_status', 'approved')
            ->where('is_online', true)
            ->whereHas('user', fn ($u) => $u->where('status', 'active'));
    }
}
