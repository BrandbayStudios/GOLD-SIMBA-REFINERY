<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['city' => 'Lusaka', 'latitude' => -15.3875, 'longitude' => 28.3228],
            ['city' => 'Ndola', 'latitude' => -12.9587, 'longitude' => 28.6366],
            ['city' => 'Kitwe', 'latitude' => -12.8024, 'longitude' => 28.2132],
            ['city' => 'Livingstone', 'latitude' => -17.8419, 'longitude' => 25.8543],
            ['city' => 'Kabwe', 'latitude' => -14.4469, 'longitude' => 28.4464],
            ['city' => 'Chingola', 'latitude' => -12.5288, 'longitude' => 27.8687],
            ['city' => 'Solwezi', 'latitude' => -12.1799, 'longitude' => 26.3959],
        ];

        foreach ($cities as $c) {
            Location::updateOrCreate(['city' => $c['city']], $c + ['is_active' => true]);
        }
    }
}
