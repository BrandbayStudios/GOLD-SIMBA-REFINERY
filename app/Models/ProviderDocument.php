<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDocument extends Model
{
    public const TYPES = [
        'nrc_front' => 'NRC – front',
        'nrc_back' => 'NRC – back',
        'selfie' => 'Selfie holding your NRC',
        'business_cert' => 'Business registration (PACRA)',
        'licence' => 'Licence / permit',
        'certificate' => 'Certificate / qualification',
        'other' => 'Other document',
    ];

    /** Types where a provider has exactly one document (a new upload replaces the old one). */
    public const SINGLE = ['nrc_front', 'nrc_back', 'selfie', 'business_cert'];

    protected $fillable = ['provider_profile_id', 'type', 'category_id', 'title', 'number', 'expires_at', 'path', 'original_name', 'mime', 'size',
        'status', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function label(): string
    {
        if ($this->type === 'licence' && $this->category) {
            return 'Licence – '.$this->category->name;
        }

        return $this->title ?: (self::TYPES[$this->type] ?? ucfirst($this->type));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday();
    }

    public function expiresSoon(int $days = 30): bool
    {
        return $this->expires_at !== null && ! $this->isExpired() && $this->expires_at->lte(now()->addDays($days));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
