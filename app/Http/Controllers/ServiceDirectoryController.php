<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\Geo;
use Illuminate\Http\Request;

class ServiceDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $categories = Category::active()->with('activeServices')->get();
        $results = null;
        if ($q !== '') {
            $like = '%'.$q.'%';
            $results = Service::with('category')->where('is_active', true)
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)))
                ->orderBy('name')->get();
        }

        return view('services.index', compact('categories', 'q', 'results'));
    }

    public function category(Category $category)
    {
        abort_unless($category->is_active, 404);
        $category->load('activeServices');
        $providers = $category->providers()->where('verification_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with('user')->orderByDesc('is_online')->orderByDesc('rating_avg')->take(6)->get();

        return view('services.category', compact('category', 'providers'));
    }

    public function service(Category $category, Service $service)
    {
        abort_unless($service->is_active && $category->is_active, 404);
        $related = $category->activeServices()->where('id', '!=', $service->id)->get();

        return view('services.show', compact('category', 'service', 'related'));
    }

    public function providers(Request $request)
    {
        $categories = Category::active()->get();
        $query = ProviderProfile::where('verification_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user', 'categories']);
        if ($slug = $request->query('category')) {
            $query->whereHas('categories', fn ($c) => $c->where('slug', $slug));
        }
        if ($request->boolean('online')) {
            $query->where('is_online', true);
        }
        $providers = $query->orderByDesc('is_online')->orderByDesc('rating_avg')->paginate(12)->withQueryString();

        return view('services.providers', compact('providers', 'categories'));
    }

    public function provider(User $user)
    {
        $profile = $user->providerProfile;
        abort_unless($user->isProvider() && $profile && $profile->isApproved() && ! $user->isSuspended(), 404);
        $profile->load('categories');
        $reviews = $user->reviewsReceived()->with('customer')->latest()->take(10)->get();

        return view('services.provider', compact('user', 'profile', 'reviews'));
    }

    /** JSON: approved providers with distance from a point, for the map and "featured" distance display. */
    public function nearby(Request $request)
    {
        $data = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'category' => 'nullable|string|max:80',
        ]);
        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        $query = ProviderProfile::where('verification_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user', 'categories']);
        if (! empty($data['category'])) {
            $query->whereHas('categories', fn ($c) => $c->where('slug', $data['category'])->orWhere('categories.id', $data['category']));
        }

        $items = $query->get()->map(function (ProviderProfile $p) use ($lat, $lng) {
            $d = Geo::distanceKm($lat, $lng, $p->latitude(), $p->longitude());

            return [
                'id' => $p->user_id,
                'name' => $p->displayName(),
                'online' => $p->is_online,
                'rating' => $p->rating_avg,
                'categories' => $p->categories->pluck('name'),
                'lat' => $p->latitude(),
                'lng' => $p->longitude(),
                'distance_km' => $d,
                'eta_minutes' => $d !== null ? Geo::etaMinutes($d) : $p->avg_response_minutes,
                'url' => route('providers.show', $p->user_id),
            ];
        })->sortBy(fn ($p) => $p['distance_km'] ?? 9999)->values();

        return response()->json(['data' => $items]);
    }
}
