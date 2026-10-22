<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'services' => 'array',
        'event_types' => 'array',
        'verified' => 'boolean',
        'featured' => 'boolean',
        'rating' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(VendorPackage::class)->orderBy('sort');
    }

    public function images(): HasMany
    {
        return $this->hasMany(VendorImage::class)->orderBy('sort');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VendorReview::class);
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    /** Display helper: "4.9" or "New" when unrated. */
    public function ratingLabel(): string
    {
        return $this->rating ? number_format($this->rating, 1) : 'New';
    }

    /**
     * Fold a new star rating into the denormalised aggregates using a running
     * average, preserving the vendor's existing baseline reviews_count.
     */
    public function registerRating(int $stars): void
    {
        $oldCount = (int) $this->reviews_count;
        $oldAvg = (float) ($this->rating ?? 0);
        $newCount = $oldCount + 1;

        $this->rating = round((($oldAvg * $oldCount) + $stars) / $newCount, 1);
        $this->reviews_count = $newCount;
        $this->save();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
