<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount(['vendorProfiles' => fn (Builder $q) => $q->approved()])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}
