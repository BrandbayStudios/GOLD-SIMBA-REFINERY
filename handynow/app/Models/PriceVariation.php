<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Extra cost a provider finds on site. The customer must approve it before it is added to the price. */
class PriceVariation extends Model
{
    protected $fillable = ['service_request_id', 'provider_id', 'amount', 'reason', 'status', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
