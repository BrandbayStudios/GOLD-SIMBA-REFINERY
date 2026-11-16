<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * Pricing rules in one place.
 *  - open:   providers quote any amount (the default).
 *  - capped: providers quote freely up to the service's ceiling (max price).
 *  - fixed:  one price set by HANDY NOW, no quotes; the first eligible provider to accept takes the job.
 *            A per-km rate can be added for services that need a drop-off point (towing).
 * The booking fee is a platform fee paid in the app once a provider is confirmed.
 */
class PricingService
{
    /** Straight-line distance understates the road distance a tow truck drives. */
    public const ROAD_FACTOR = 1.3;

    public function routeKm(?float $lat, ?float $lng, ?float $destLat, ?float $destLng): ?float
    {
        $km = Geo::distanceKm($lat, $lng, $destLat, $destLng);

        return $km === null ? null : round($km * self::ROAD_FACTOR, 1);
    }

    /** The customer's price for a fixed-price service, or null when the service is not fixed-price. */
    public function fixedPrice(?Service $service, ?float $routeKm = null): ?float
    {
        if (! $service || $service->pricing_mode !== 'fixed' || ! $service->fixed_price) {
            return null;
        }
        $extra = 0.0;
        if ($service->price_per_km && $routeKm !== null) {
            $extra = max(0, $routeKm - (float) $service->included_km) * (float) $service->price_per_km;
        }

        return (float) round((float) $service->fixed_price + $extra);
    }

    /** Booking fee in ZMW for this service (service override first, then the platform default). 0 = no fee. */
    public function bookingFee(?Service $service): float
    {
        $fee = $service?->booking_fee !== null ? (float) $service->booking_fee : (float) Setting::get('booking_fee', 0);

        return max(0.0, round($fee, 2));
    }

    /** Highest total a provider may quote, or null when there is no ceiling. */
    public function ceiling(?Service $service): ?float
    {
        return $service && $service->pricing_mode === 'capped' && $service->max_price ? (float) $service->max_price : null;
    }

    public function assertQuoteAllowed(?Service $service, float $total): void
    {
        $ceiling = $this->ceiling($service);
        if ($ceiling !== null && round($total, 2) > $ceiling) {
            throw ValidationException::withMessages(['amount' => "The price ceiling for {$service->name} is ".zmw($ceiling, 0).' (work plus call-out). Please quote that or less.']);
        }
    }

    /** How much extra a provider may add on site: a share of the agreed price, and never above a service's ceiling. */
    public function variationRoom(float $agreed, float $alreadyAdded, ?Service $service): float
    {
        $room = $agreed * ((float) Setting::get('variation_limit_percent', 50)) / 100 - $alreadyAdded;
        if (($ceiling = $this->ceiling($service)) !== null) {
            $room = min($room, $ceiling - $agreed - $alreadyAdded);
        }

        return max(0.0, round($room, 2));
    }
}
