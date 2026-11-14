<?php

namespace App\Services;

use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Finds eligible providers for a request: approved, online, active account, offering the category,
 * and within range (the smaller of the provider's own radius and the platform radius).
 * When the request has no GPS pin, providers whose operating areas mention the address are matched instead.
 */
class ProviderMatcher
{
    /** @return Collection<int, array{profile: ProviderProfile, distance: ?float}> */
    public function candidates(ServiceRequest $request, int $limit = 15): Collection
    {
        $platformRadius = (float) Setting::get('match_radius_km', 30);

        return ProviderProfile::available()
            ->with('user')
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $request->category_id))
            ->get()
            ->map(function (ProviderProfile $p) use ($request) {
                return ['profile' => $p, 'distance' => Geo::distanceKm($request->latitude, $request->longitude, $p->latitude(), $p->longitude())];
            })
            ->filter(function (array $c) use ($request, $platformRadius) {
                if ($c['distance'] !== null) {
                    return $c['distance'] <= min($platformRadius, (float) $c['profile']->service_radius_km);
                }

                return $this->areaMatches($c['profile'], $request->address);
            })
            ->sortBy(fn ($c) => [$c['distance'] ?? 9999, -$c['profile']->rating_avg])
            ->take($limit)
            ->values();
    }

    private function areaMatches(ProviderProfile $p, string $address): bool
    {
        $areas = $p->operating_areas ?? [];
        if (! $areas) {
            return false;
        }
        $address = mb_strtolower($address);
        foreach ($areas as $area) {
            if ($area !== '' && str_contains($address, mb_strtolower(trim($area)))) {
                return true;
            }
        }

        return false;
    }
}
