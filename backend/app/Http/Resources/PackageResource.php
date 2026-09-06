<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'unit' => $this->unit,
            'guest_capacity' => $this->guest_capacity,
            'description' => $this->description,
            'is_popular' => (bool) $this->is_popular,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
