<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public static array $categories = [
        ['name' => 'Venues & Event Centres', 'slug' => 'venues', 'icon' => 'fa-solid fa-landmark'],
        ['name' => 'MCs & DJs', 'slug' => 'mcs-djs', 'icon' => 'fa-solid fa-microphone'],
        ['name' => 'Photographers & Videographers', 'slug' => 'photography', 'icon' => 'fa-solid fa-camera'],
        ['name' => 'Choreographers', 'slug' => 'choreographers', 'icon' => 'fa-solid fa-music'],
        ['name' => 'Car Hire & Car Decoration', 'slug' => 'cars', 'icon' => 'fa-solid fa-car'],
        ['name' => 'Makeup Artists', 'slug' => 'makeup', 'icon' => 'fa-solid fa-palette'],
        ['name' => 'Barbers & Hairdressers', 'slug' => 'barbers', 'icon' => 'fa-solid fa-scissors'],
        ['name' => 'Tailors & Fashion Designers', 'slug' => 'tailors', 'icon' => 'fa-solid fa-shirt'],
        ['name' => 'Matrons', 'slug' => 'matrons', 'icon' => 'fa-solid fa-crown'],
        ['name' => 'Caterers & Chefs', 'slug' => 'catering', 'icon' => 'fa-solid fa-utensils'],
        ['name' => 'Cakes', 'slug' => 'cakes', 'icon' => 'fa-solid fa-cake-candles'],
        ['name' => 'Chairs & Tables', 'slug' => 'chairs-tables', 'icon' => 'fa-solid fa-chair'],
        ['name' => 'Tents', 'slug' => 'tents', 'icon' => 'fa-solid fa-campground'],
        ['name' => 'Pots & Chafing Dishes', 'slug' => 'pots-chafing-dishes', 'icon' => 'fa-solid fa-fire-burner'],
        ['name' => 'Sound & Lighting', 'slug' => 'sound', 'icon' => 'fa-solid fa-volume-high'],
        ['name' => 'Event Decorators', 'slug' => 'decor', 'icon' => 'fa-solid fa-star'],
        ['name' => 'Wedding Planners', 'slug' => 'planners', 'icon' => 'fa-solid fa-ring'],
        ['name' => 'Other Event Services', 'slug' => 'other', 'icon' => 'fa-solid fa-ellipsis'],
    ];

    public function run(): void
    {
        foreach (self::$categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], [
                'name' => $cat['name'],
                'icon' => $cat['icon'],
                'is_active' => true,
            ]);
        }
    }
}
