<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = ['category_id', 'name', 'slug', 'description', 'callout_fee', 'min_price', 'max_price', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'callout_fee' => 'decimal:2', 'min_price' => 'decimal:2', 'max_price' => 'decimal:2'];
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
    public function priceRange(): string
    {
        return $this->min_price ? 'Starting at '.zmw($this->min_price, 0) : 'Quoted on request';
    }
}
