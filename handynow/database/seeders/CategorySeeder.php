<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Live reference data: the service catalogue with indicative ZMW price ranges (editable in Admin → Categories). */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            ['Roadside Assistance', 'truck', 'Towing, jump-starts, tyres and fuel – wherever you are stuck.', '/images/roadside.webp', [
                ['Vehicle towing', 350, 800, 3500, 'Flatbed or wheel-lift towing to a garage or home of your choice.'],
                ['Breakdown recovery', 300, 600, 2500, 'Recovery of vehicles that cannot be driven, including off-road and ditch recovery.'],
                ['Battery jump-start', 100, 150, 350, 'Get your vehicle started with a professional jump-start and battery check.'],
                ['Flat tyre replacement', 100, 150, 300, 'We fit your spare wheel safely at the roadside.'],
                ['Fuel delivery', 150, 200, 400, 'Emergency petrol or diesel delivered to you (fuel charged at pump price).'],
                ['Vehicle lockout assistance', 150, 250, 600, 'Locked out? Non-damaging entry by trained technicians.'],
                ['Emergency tyre repair', 100, 120, 350, 'Puncture plugging and inflation on site.'],
            ]],
            ['Electrical Services', 'bolt', 'Power failures, wiring, DB boards, generators and solar.', '/images/electrical.webp', [
                ['Emergency electrical repairs', 200, 300, 1500, 'Urgent fixes for sparks, burning smells and dangerous faults.'],
                ['Power failure troubleshooting', 200, 250, 900, 'Find and fix the cause of a total or partial power outage.'],
                ['Electrical wiring', 200, 500, 8000, 'New installations, rewiring and extra plug or light points.'],
                ['DB board repairs', 200, 400, 2500, 'Distribution board faults, tripping and upgrades.'],
                ['Circuit breaker replacement', 150, 250, 900, 'Replacement of faulty breakers and earth-leakage units.'],
                ['Generator installation and repairs', 250, 500, 6000, 'Servicing, repairs and changeover switch installation.'],
                ['Solar system installation and repairs', 250, 800, 25000, 'Panels, inverters and batteries – design, install and repair.'],
            ]],
            ['Plumbing Services', 'droplet', 'Burst pipes, leaks, blockages, pumps and tanks.', '/images/plumbing.webp', [
                ['Burst pipe repairs', 200, 300, 1500, 'Stop the water and repair burst or cracked pipes fast.'],
                ['Blocked drainage', 200, 300, 1800, 'Clearing blocked sinks, drains, sewer lines and manholes.'],
                ['Toilet repairs', 150, 200, 900, 'Leaking cisterns, blocked toilets and faulty flush mechanisms.'],
                ['Tap and shower repairs', 150, 150, 700, 'Dripping taps, mixers and shower heads repaired or replaced.'],
                ['Water pump repairs', 200, 400, 3000, 'Borehole and booster pump diagnosis and repair.'],
                ['Water tank installation', 250, 800, 5000, 'Tank stands, tank installation and plumbing connections.'],
                ['Emergency water leaks', 200, 250, 1200, 'Locate and fix hidden or major leaks before damage spreads.'],
            ]],
            ['Gas Services', 'flame', 'LPG refills, delivery, installation and leak checks.', null, [
                ['LPG gas cylinder replacement', 50, 150, 900, 'Exchange your empty cylinder (gas charged at current price).'],
                ['Gas delivery', 50, 100, 1200, 'LPG cylinders delivered to your home or business.'],
                ['Gas stove installation', 150, 250, 800, 'Safe installation and connection of gas stoves and hobs.'],
                ['Gas leak inspection', 200, 250, 700, 'Urgent leak detection and repair. If you smell gas, leave the building first.'],
                ['Gas appliance maintenance', 150, 250, 900, 'Servicing of gas heaters, geysers and cookers.'],
            ]],
            ['Mechanical Services', 'cog', 'Diagnostics, servicing, brakes and on-site breakdown repairs.', '/images/mechanical.webp', [
                ['Vehicle diagnostics', 200, 250, 800, 'Computer diagnostics to pinpoint warning lights and faults.'],
                ['Engine troubleshooting', 250, 400, 3500, 'Overheating, misfires, oil leaks and starting problems.'],
                ['Vehicle servicing', 200, 800, 3500, 'Minor and major services at your home or office.'],
                ['Brake repairs', 200, 400, 2500, 'Pads, discs, fluid and brake system repairs.'],
                ['Mechanical breakdown assistance', 300, 400, 2500, 'A mobile mechanic comes to you to get you moving again.'],
                ['Industrial machinery repairs', 500, 1500, 20000, 'Repairs to industrial, agricultural and plant machinery.'],
            ]],
            ['Carpentry', 'hammer', 'Doors, furniture, cabinets and roofing timber.', null, [
                ['Door repairs', 150, 200, 1200, 'Hinges, frames, locks and sagging or broken doors.'],
                ['Furniture repairs', 150, 250, 2000, 'Chairs, tables, beds and wardrobes restored.'],
                ['Cabinet installation', 200, 800, 10000, 'Kitchen and bedroom cabinets fitted.'],
                ['Roofing timber repairs', 250, 800, 8000, 'Trusses, purlins and fascia boards repaired or replaced.'],
                ['Custom furniture', 0, 1500, 25000, 'Made-to-measure furniture to your design.'],
                ['General woodwork', 150, 200, 3000, 'Shelving, skirting, partitions and other woodwork.'],
            ]],
            ['Welding & Metalwork', 'spark', 'Gates, burglar bars, steel doors and fabrication.', null, [
                ['Emergency welding', 250, 300, 2500, 'Mobile welding for urgent structural or security repairs.'],
                ['Gate repairs', 200, 300, 2500, 'Sliding and swing gates, rollers, tracks and hinges.'],
                ['Burglar bar installation', 200, 800, 8000, 'Window and door burglar bars fabricated and fitted.'],
                ['Metal fabrication', 0, 1000, 30000, 'Custom steel fabrication for homes and businesses.'],
                ['Steel door repairs', 200, 300, 2000, 'Security doors, frames and locks repaired.'],
                ['Structural metalwork', 500, 3000, 50000, 'Steel structures, carports and roof trusses.'],
            ]],
            ['Additional Services', 'wrench', 'Locksmiths, AC, refrigeration, painting, cleaning and more.', '/images/electrical-solar.webp', [
                ['Locksmith services', 150, 200, 900, 'Lockouts, lock changes and key cutting.'],
                ['Air conditioning repairs', 250, 400, 3500, 'AC servicing, regassing and repairs.'],
                ['Refrigeration repairs', 250, 350, 3000, 'Fridges, freezers and cold rooms.'],
                ['Painting', 0, 800, 20000, 'Interior and exterior painting.'],
                ['Roofing', 300, 1000, 30000, 'Leak repairs, re-roofing and gutters.'],
                ['Glass and aluminium repairs', 200, 300, 5000, 'Broken windows, sliding doors and shopfronts.'],
                ['Cleaning services', 0, 300, 3000, 'Home, office and post-construction cleaning.'],
                ['Pest control', 200, 350, 2500, 'Cockroaches, termites, rodents and fumigation.'],
                ['General handyman services', 150, 200, 1500, 'Small repairs and odd jobs around the home.'],
                ['Appliance repairs', 200, 250, 1800, 'Washing machines, stoves, microwaves and more.'],
                ['Moving and furniture transportation', 300, 800, 6000, 'Trucks and helpers for moving homes and offices.'],
            ]],
        ];

        foreach ($catalogue as $i => [$name, $icon, $short, $image, $services]) {
            $cat = Category::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'icon' => $icon, 'short_description' => $short, 'image' => $image,
                'description' => $short, 'is_active' => true, 'is_emergency' => true, 'sort_order' => $i,
            ]);
            foreach ($services as [$sname, $callout, $min, $max, $desc]) {
                $cat->services()->updateOrCreate(['slug' => Str::slug($sname)], [
                    'name' => $sname, 'description' => $desc, 'callout_fee' => $callout, 'min_price' => $min, 'max_price' => $max, 'is_active' => true,
                ]);
            }
        }

        foreach (Setting::DEFAULTS as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }
    }
}
