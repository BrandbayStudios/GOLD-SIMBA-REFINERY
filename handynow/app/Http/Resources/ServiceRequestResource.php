<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $unlocked = $this->contactUnlocked();
        $mine = $user && $user->isProvider() && $this->provider_id === $user->id;
        $showContact = ! $user?->isProvider() || ($mine && $unlocked);

        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'priority' => $this->priority,
            'category' => ['id' => $this->category_id, 'name' => $this->category?->name],
            'service' => $this->service ? ['id' => $this->service_id, 'name' => $this->service->name] : null,
            'description' => $this->description,
            'contact_name' => $showContact ? $this->contact_name : null,
            'contact_phone' => $showContact ? $this->contact_phone : null,
            'contact_unlocked' => $unlocked,
            'pricing' => [
                'fixed_price' => $this->fixed_price,
                'route_km' => $this->route_km,
                'destination' => $this->dest_address ? ['address' => $this->dest_address, 'latitude' => $this->dest_latitude, 'longitude' => $this->dest_longitude] : null,
                'booking_fee' => ['amount' => $this->booking_fee_amount, 'status' => $this->booking_fee_status],
            ],
            'variations' => $this->whenLoaded('variations', fn () => $this->variations->map(fn ($v) => ['id' => $v->id, 'amount' => $v->amount, 'reason' => $v->reason, 'status' => $v->status])),
            'address' => $this->address,
            'directions' => $this->directions,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'photos' => $this->photoUrls(),
            'currency' => 'ZMW',
            'agreed_amount' => $this->agreed_amount,
            'payment_status' => $this->payment_status,
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'name' => $this->provider->name,
                'phone' => $unlocked || $user?->isAdmin() ? $this->provider->phone : null,
                'rating' => $this->provider->providerProfile?->rating_avg,
            ] : null),
            'quotations' => $this->whenLoaded('quotations', fn () => $this->quotations->map(fn ($q) => [
                'id' => $q->id,
                'provider' => ['id' => $q->provider_id, 'name' => $q->provider?->name, 'rating' => $q->provider?->providerProfile?->rating_avg],
                'amount' => $q->amount,
                'callout_fee' => $q->callout_fee,
                'total' => $q->total(),
                'eta_minutes' => $q->eta_minutes,
                'message' => $q->message,
                'status' => $q->status,
            ])),
            'timeline' => $this->whenLoaded('statusLogs', fn () => $this->statusLogs->map(fn ($l) => [
                'status' => $l->status, 'label' => $l->label(), 'note' => $l->note, 'at' => $l->created_at->toIso8601String(),
            ])),
            'is_demo' => $this->is_demo,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
