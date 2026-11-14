<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'short_description', 'description', 'image', 'is_emergency', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_emergency' => 'boolean', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true)->orderBy('name');
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(ProviderProfile::class, 'category_provider');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
