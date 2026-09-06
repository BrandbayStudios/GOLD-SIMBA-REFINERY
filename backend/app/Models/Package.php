<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    protected $fillable = [
        'vendor_profile_id', 'name', 'price', 'unit',
        'guest_capacity', 'description', 'is_popular', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }
}
