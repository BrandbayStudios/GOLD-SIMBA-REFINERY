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

    public function priceRange(): string
    {
        if ($this->min_price && $this->max_price) {
            return zmw($this->min_price, 0).' – '.zmw($this->max_price, 0);
        }
        if ($this->min_price) {
            return 'From '.zmw($this->min_price, 0);
        }

        return 'Quoted on request';
    }
}
