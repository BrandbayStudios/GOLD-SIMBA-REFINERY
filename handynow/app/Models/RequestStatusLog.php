<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusLog extends Model
{
    protected $fillable = ['service_request_id', 'user_id', 'status', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return ServiceRequest::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }
}
