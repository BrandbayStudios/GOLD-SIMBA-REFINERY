<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProfile extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'business_name', 'slug', 'description',
        'phone', 'whatsapp', 'email', 'city', 'area', 'address',
        'latitude', 'longitude', 'logo_path', 'cover_path',
        'facebook_url', 'instagram_url', 'tiktok_url', 'website_url',
        'price_from', 'price_unit', 'status', 'source',
        'is_featured', 'featured_until', 'rating_avg', 'rating_count',
        'response_time_minutes', 'view_count',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'price_from' => 'float',
            'rating_avg' => 'float',
            'is_featured' => 'boolean',
            'featured_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function portfolioMedia(): HasMany
    {
        return $this->hasMany(PortfolioMedia::class)->orderBy('sort_order');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function favouritedBy(): HasMany
    {
        return $this->hasMany(Favourite::class);
    }

    public function businessClaims(): HasMany
    {
        return $this->hasMany(BusinessClaim::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_vendor');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function recalculateRating(): void
    {
        $agg = $this->reviews()->where('status', 'published')->selectRaw('avg(rating) as avg_rating, count(*) as total')->first();
        $this->update([
            'rating_avg' => round((float) $agg->avg_rating, 2),
            'rating_count' => (int) $agg->total,
        ]);
    }
}
