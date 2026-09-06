<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    protected $fillable = [
        'vendor_profile_id', 'user_id', 'guest_name', 'guest_phone',
        'event_date', 'guest_count', 'message', 'status',
    ];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }
}
