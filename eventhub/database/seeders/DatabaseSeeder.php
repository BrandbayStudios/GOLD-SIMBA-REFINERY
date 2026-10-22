<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@eventhub.test'],
            [
                'name' => 'Demo Customer',
                'phone' => '260970000000',
                'role' => 'customer',
                'password' => Hash::make('password'),
            ]
        );

        $this->call(VendorSeeder::class);

        // Demo vendor account, attached to an existing listing so the vendor
        // dashboard has real data to show.
        $vendorUser = User::updateOrCreate(
            ['email' => 'vendor@eventhub.test'],
            [
                'name' => 'Demo Vendor',
                'phone' => '260970000001',
                'role' => 'vendor',
                'password' => Hash::make('password'),
            ]
        );

        Vendor::where('slug', 'royal-gardens')->update([
            'user_id' => $vendorUser->id,
            'contact_person' => 'Demo Vendor',
            'email' => 'vendor@eventhub.test',
        ]);
    }
}
