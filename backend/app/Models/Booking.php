<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'vendor_profile_id', 'user_id', 'package_id', 'enquiry_id',
        'customer_name', 'event_date', 'amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'amount' => 'float',
        ];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
