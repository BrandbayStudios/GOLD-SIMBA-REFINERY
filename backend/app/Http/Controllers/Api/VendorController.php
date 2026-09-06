<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorProfileResource;
use App\Models\VendorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = VendorProfile::query()->approved()->with('category');

        if ($q = $request->string('q')->trim()->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('business_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($category = $request->string('category')->trim()->toString()) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category));
        }

        if ($city = $request->string('city')->trim()->toString()) {
            $query->where('city', 'like', "%{$city}%");
        }

        if ($request->filled('min_price')) {
            $query->where('price_from', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price_from', '<=', (float) $request->input('max_price'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_avg', '>=', (float) $request->input('min_rating'));
        }

        if ($source = $request->string('source')->trim()->toString()) {
            $query->where('source', $source);
        }

        if ($request->boolean('featured_only')) {
            $query->where('is_featured', true);
        }

        // Near-me: haversine distance filtering + ordering
        $lat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->input('lng') : null;
        $radiusKm = (float) $request->input('radius_km', 50);

        if ($lat !== null && $lng !== null) {
            // CASE-based clamp to [-1, 1] instead of LEAST/GREATEST (not portable across MySQL/SQLite)
            // guards against acos() returning NULL from floating-point rounding at ~0km distance.
            $haversine = "(6371 * acos(
                CASE
                    WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) > 1 THEN 1
                    WHEN (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))) < -1 THEN -1
                    ELSE (cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))
                END
            ))";

            // whereRaw (not having()) on the repeated expression — SQLite rejects a HAVING
            // clause referring to a select alias once paginate() wraps this in a count subquery.
            // $radiusKm is interpolated as a literal (already cast to float above, so this is
            // not injectable) rather than bound: SQLite's type-affinity rules make a bound TEXT
            // parameter compare as always-greater than a computed REAL, so a bound "<= ?" here
            // silently matched every row regardless of distance.
            $query->selectRaw('vendor_profiles.*, '.$haversine.' AS distance_km', [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat])
                ->whereNotNull('latitude')->whereNotNull('longitude')
                ->whereRaw($haversine.' <= '.$radiusKm, [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat]);
        }

        switch ($request->string('sort')->toString()) {
            case 'rating':
                $query->orderByDesc('rating_avg');
                break;
            case 'price_low':
                $query->orderBy('price_from');
                break;
            case 'price_high':
                $query->orderByDesc('price_from');
                break;
            case 'name':
                $query->orderBy('business_name');
                break;
            default:
                if ($lat !== null && $lng !== null) {
                    $query->orderBy('distance_km');
                } else {
                    $query->orderByDesc('is_featured')->orderByDesc('rating_avg');
                }
        }

        $vendors = $query->paginate((int) $request->input('per_page', 12))->withQueryString();

        return VendorProfileResource::collection($vendors);
    }

    public function show(string $vendor)
    {
        $query = VendorProfile::approved()
            ->with(['category', 'portfolioMedia', 'packages' => fn ($p) => $p->where('is_active', true), 'reviews' => fn ($r) => $r->where('status', 'published')->with('user')->latest()]);

        $model = ctype_digit($vendor)
            ? $query->findOrFail($vendor)
            : $query->where('slug', $vendor)->firstOrFail();

        $model->increment('view_count');

        return new VendorProfileResource($model);
    }
}
