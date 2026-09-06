<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = ['user_id', 'name', 'event_date'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorProfiles(): BelongsToMany
    {
        return $this->belongsToMany(VendorProfile::class, 'event_vendor');
    }
}
