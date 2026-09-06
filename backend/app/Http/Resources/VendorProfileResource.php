<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'business_name' => $this->business_name,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
                'icon' => $this->category?->icon,
            ]),
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'city' => $this->city,
            'area' => $this->area,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_km' => $this->when(isset($this->distance_km), fn () => round((float) $this->distance_km, 1)),
            'logo_path' => $this->logo_path,
            'cover_path' => $this->cover_path,
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,
            'website_url' => $this->website_url,
            'price_from' => $this->price_from,
            'price_unit' => $this->price_unit,
            'status' => $this->status,
            'source' => $this->source,
            'is_verified' => $this->source === 'platform' && $this->status === 'approved',
            'is_featured' => (bool) $this->is_featured,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'response_time_minutes' => $this->response_time_minutes,
            'portfolio' => PortfolioMediaResource::collection($this->whenLoaded('portfolioMedia')),
            'packages' => PackageResource::collection($this->whenLoaded('packages')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at,
        ];
    }
}
