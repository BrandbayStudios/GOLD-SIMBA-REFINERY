<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = ['service_request_id', 'provider_id', 'amount', 'callout_fee', 'eta_minutes', 'message', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'callout_fee' => 'decimal:2'];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function total(): float
    {
        return (float) $this->amount + (float) $this->callout_fee;
    }
}
