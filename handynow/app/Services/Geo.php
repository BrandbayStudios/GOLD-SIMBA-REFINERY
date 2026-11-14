<?php

namespace App\Services;

class Geo
{
    /** Great-circle distance in kilometres (Haversine). */
    public static function distanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /** Rough urban ETA: 30 km/h average plus 5 minutes to get moving. */
    public static function etaMinutes(?float $km): ?int
    {
        return $km === null ? null : (int) ceil(5 + ($km / 30) * 60);
    }
}
