<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public const ICONS = ['truck', 'bolt', 'droplet', 'flame', 'cog', 'hammer', 'spark', 'key', 'snowflake', 'paint', 'home', 'sparkles', 'bug', 'wrench', 'box', 'window'];

    public function index()
    {
        $categories = Category::withCount(['services', 'requests', 'providers'])->orderBy('sort_order')->get();

        return view('admin.categories.index', ['categories' => $categories, 'icons' => self::ICONS]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(Category::class, $data['name']);
        Category::create($data);

        return back()->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        $category->load(['services' => fn ($q) => $q->orderBy('name')]);

        return view('admin.categories.edit', ['category' => $category, 'icons' => self::ICONS]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request));

        return back()->with('success', 'Category updated.');
    }

    public function storeService(Request $request, Category $category)
    {
        $data = $this->serviceData($request);
        $data['slug'] = $this->uniqueSlug(Service::class, $data['name']);
        $category->services()->create($data);

        return back()->with('success', 'Service added.');
    }

    public function updateService(Request $request, Service $service)
    {
        $service->update($this->serviceData($request));

        return back()->with('success', 'Service "'.$service->name.'" updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'icon' => ['required', Rule::in(self::ICONS)],
            'short_description' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        return $data + ['is_active' => $request->boolean('is_active'), 'is_emergency' => $request->boolean('is_emergency')];
    }

    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'callout_fee' => 'nullable|numeric|min:0|max:100000',
            'min_price' => 'nullable|numeric|min:0|max:1000000',
            'max_price' => 'nullable|numeric|min:0|max:1000000|gte:min_price',
        ]);
        $data['callout_fee'] = $data['callout_fee'] ?? 0;

        return $data + ['is_active' => $request->boolean('is_active')];
    }

    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
