<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A ★/☆ string built from the numeric rating, for display. */
    public function starsLabel(): string
    {
        $rating = max(0, min(5, (int) $this->rating));

        return $this->stars ?: str_repeat('★', $rating).str_repeat('☆', 5 - $rating);
    }
}
