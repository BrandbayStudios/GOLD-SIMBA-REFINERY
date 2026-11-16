<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'callout_fee', 'min_price', 'max_price', 'pricing_mode', 'fixed_price', 'price_per_km',
        'included_km', 'needs_destination', 'booking_fee', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'needs_destination' => 'boolean', 'callout_fee' => 'decimal:2', 'min_price' => 'decimal:2', 'max_price' => 'decimal:2',
            'fixed_price' => 'decimal:2', 'price_per_km' => 'decimal:2', 'included_km' => 'float', 'booking_fee' => 'decimal:2'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Public price label: only the starting price is shown, never an upper bound. */
    public function isFixed(): bool
    {
        return $this->pricing_mode === 'fixed' && $this->fixed_price > 0;
    }

    public function priceRange(): string
    {
        if ($this->isFixed()) {
            $km = $this->price_per_km ? ' + '.zmw($this->price_per_km, 0).'/km after '.rtrim(rtrim(number_format($this->included_km, 1), '0'), '.').' km' : '';

            return 'Fixed price '.zmw($this->fixed_price, 0).$km;
        }

        return $this->min_price ? 'Starting at '.zmw($this->min_price, 0) : 'Quoted on request';
    }
}
