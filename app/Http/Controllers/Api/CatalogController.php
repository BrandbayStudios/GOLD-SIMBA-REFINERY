<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CatalogController extends Controller
{
    public function categories()
    {
        return response()->json(['data' => Category::active()->with('activeServices')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon, 'description' => $c->short_description,
            'services' => $c->activeServices->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'callout_fee' => $s->callout_fee,
                'min_price' => $s->min_price, 'max_price' => $s->max_price, 'currency' => 'ZMW',
            ]),
        ])]);
    }
}
