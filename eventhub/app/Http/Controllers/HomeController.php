<?php

namespace App\Http\Controllers;

use App\Models\Vendor;

class HomeController extends Controller
{
    public function index()
    {
        $vendors = Vendor::approved()
            ->with(['packages', 'images', 'reviews'])
            ->orderByDesc('featured')
            ->orderByDesc('rating')
            ->get();

        // Build the JS `window.vendors` payload the profile/quote modals expect,
        // keyed by slug, mirroring the original static structure.
        $vendorScript = $vendors->mapWithKeys(fn (Vendor $v) => [
            $v->slug => [
                'id'         => $v->slug,
                'name'       => $v->name,
                'category'   => $v->category,
                'location'   => $v->location,
                'rating'     => $v->ratingLabel(),
                'reviews'    => (string) $v->reviews_count,
                'experience' => $v->experience,
                'events'     => $v->events_count,
                'phone'      => $v->phone,
                'cover'      => $v->cover,
                'logo'       => $v->logo,
                'about'      => $v->about,
                'services'   => $v->services ?? [],
                'eventTypes' => $v->event_types ?? [],
                'facebookUrl' => $v->facebook_url,
                'packages'   => $v->packages->map(fn ($p) => [
                    'name'  => $p->name,
                    'price' => $p->price,
                    'items' => $p->items ?? [],
                ])->values(),
                'portfolio'  => $v->images->pluck('url')->values(),
                'customerReviews' => $v->reviews->map(fn ($r) => [$r->author, $r->stars, $r->body])->values(),
            ],
        ]);

        return view('home', [
            'vendors'       => $vendors,
            'vendorScript'  => $vendorScript,
            'categories'    => $vendors->pluck('category')->unique()->sort()->values(),
            'locations'     => $vendors->pluck('location')->unique()->sort()->values(),
        ]);
    }
}
