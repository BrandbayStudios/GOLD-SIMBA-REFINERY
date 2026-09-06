<?php

namespace Database\Seeders;

use App\Models\Advertisement;
use App\Models\Booking;
use App\Models\BusinessClaim;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Favourite;
use App\Models\Package;
use App\Models\PortfolioMedia;
use App\Models\Review;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // --- Admin ---
        $admin = User::updateOrCreate(['email' => 'admin@eventhub.zm'], [
            'name' => 'EventHub Admin',
            'password' => $password,
            'role' => 'admin',
            'phone' => '+260971000000',
        ]);

        // --- Customers ---
        $customerNames = [
            'Chileshe Banda' => 'chileshe@example.test',
            'Natasha Mumba' => 'natasha@example.test',
            'Mwansa Phiri' => 'mwansa@example.test',
            'Bwalya Chilufya' => 'bwalya@example.test',
            'Given Zulu' => 'given@example.test',
        ];
        $customers = [];
        foreach ($customerNames as $name => $email) {
            $customers[$name] = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => 'customer',
                'phone' => '+2609' . rand(70000000, 79999999),
            ]);
        }

        $catId = fn (string $slug) => Category::where('slug', $slug)->value('id');

        // --- Vendors ---
        $vendorDefs = [
            [
                'ref' => 'v1', 'business_name' => 'Silverleaf Gardens', 'category' => 'venues',
                'email' => 'hello@silverleafgardens.zm', 'city' => 'Lusaka', 'area' => 'Kabulonga, Lusaka',
                'address' => 'Plot 14B, Kabulonga Road, Lusaka', 'latitude' => -15.4067, 'longitude' => 28.3350,
                'price_from' => 8500, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => false, 'rating_avg' => 4.8, 'rating_count' => 96,
                'description' => "Silverleaf Gardens is a premium outdoor and indoor event venue in Kabulonga, Lusaka, offering a beautifully landscaped garden setting for weddings, corporate functions and private parties. Our venue comfortably hosts up to 500 guests with dedicated parking, backup power and an in-house event coordination team.",
                'facebook_url' => 'https://facebook.com/silverleafgardens', 'instagram_url' => 'https://instagram.com/silverleaf_gardens',
                'cover' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=1600&auto=format&fit=crop',
                'logo' => 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?q=80&w=300&auto=format&fit=crop',
                'gallery' => [
                    'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?q=80&w=500&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?q=80&w=500&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=500&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1478146059778-26028b07395a?q=80&w=500&auto=format&fit=crop',
                ],
                'packages' => [
                    ['name' => 'Garden Basic', 'price' => 8500, 'guest_capacity' => 'Up to 150', 'is_popular' => false],
                    ['name' => 'Signature Wedding', 'price' => 16000, 'guest_capacity' => 'Up to 350', 'is_popular' => true],
                    ['name' => 'Grand Celebration', 'price' => 24000, 'guest_capacity' => 'Up to 500', 'is_popular' => false],
                ],
            ],
            [
                'ref' => 'v2', 'business_name' => 'DJ Mwansa Live', 'category' => 'mcs-djs',
                'email' => 'bookings@djmwansa.zm', 'city' => 'Lusaka', 'area' => 'Rhodes Park, Lusaka',
                'latitude' => -15.4123, 'longitude' => 28.3100,
                'price_from' => 3200, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => false, 'rating_avg' => 4.9, 'rating_count' => 154,
                'description' => 'High-energy MC and DJ services for weddings, parties and corporate events across Lusaka.',
                'cover' => 'https://images.unsplash.com/photo-1571266028243-e4c8f8b2f3a6?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1571266028243-e4c8f8b2f3a6?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Standard DJ Set', 'price' => 3200, 'guest_capacity' => 'Any size', 'is_popular' => true]],
            ],
            [
                'ref' => 'v3', 'business_name' => 'Chanda Lens Studio', 'category' => 'photography',
                'email' => 'info@chandalens.zm', 'city' => 'Lusaka', 'area' => 'Woodlands, Lusaka',
                'latitude' => -15.4300, 'longitude' => 28.3200,
                'price_from' => 4500, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'rating_avg' => 4.7, 'rating_count' => 82,
                'description' => 'Wedding and event photography & videography with a modern, editorial style.',
                'cover' => 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Full Day Coverage', 'price' => 4500, 'guest_capacity' => null, 'is_popular' => true]],
            ],
            [
                'ref' => 'v4', 'business_name' => "Mama Beulah's Kitchen", 'category' => 'catering',
                'city' => 'Lusaka', 'area' => 'Chelston, Lusaka', 'latitude' => -15.3700, 'longitude' => 28.3800,
                'price_from' => 150, 'price_unit' => 'per plate', 'status' => 'approved', 'source' => 'google_places',
                'rating_avg' => 4.6, 'rating_count' => 41,
                'description' => 'Home-style Zambian and continental catering for events of all sizes.',
                'cover' => 'https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1555244162-803834f70033?q=80&w=500&auto=format&fit=crop'],
                'packages' => [],
                'no_user' => true,
            ],
            [
                'ref' => 'v5', 'business_name' => 'Golden Aisle Decor', 'category' => 'decor',
                'city' => 'Lusaka', 'area' => 'Ibex Hill, Lusaka', 'latitude' => -15.4200, 'longitude' => 28.3600,
                'price_from' => 6000, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => true, 'featured_until' => now()->addMonths(4), 'rating_avg' => 5.0, 'rating_count' => 203,
                'description' => 'Elegant, gold-accented event decor for weddings and premium celebrations.',
                'cover' => 'https://images.unsplash.com/photo-1478146059778-26028b07395a?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1478146059778-26028b07395a?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Signature Décor Package', 'price' => 6000, 'is_popular' => true]],
            ],
            [
                'ref' => 'v6', 'business_name' => 'Bliss Weddings Zambia', 'category' => 'planners',
                'city' => 'Lusaka', 'area' => 'Kabulonga, Lusaka', 'latitude' => -15.4050, 'longitude' => 28.3300,
                'price_from' => 12000, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => true, 'featured_until' => now()->addMonths(5), 'rating_avg' => 4.9, 'rating_count' => 177,
                'description' => 'Full-service wedding planning from concept to execution across Zambia.',
                'cover' => 'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Full Wedding Planning', 'price' => 12000, 'is_popular' => true]],
            ],
            [
                'ref' => 'v7', 'business_name' => 'Sweet Nectar Cakes', 'category' => 'cakes',
                'city' => 'Lusaka', 'area' => 'Roma, Lusaka', 'latitude' => -15.3800, 'longitude' => 28.3500,
                'price_from' => 900, 'price_unit' => 'per tier', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => true, 'featured_until' => now()->addMonths(3), 'rating_avg' => 4.8, 'rating_count' => 68,
                'description' => 'Custom celebration cakes for weddings, birthdays and corporate events.',
                'cover' => 'https://images.unsplash.com/photo-1535141192574-5d4897c12636?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1535141192574-5d4897c12636?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => '3-Tier Celebration Cake', 'price' => 900, 'unit' => 'per tier', 'is_popular' => true]],
            ],
            [
                'ref' => 'v8', 'business_name' => 'Zambezi Sound & Light', 'category' => 'sound',
                'city' => 'Lusaka', 'area' => 'Northmead, Lusaka', 'latitude' => -15.4000, 'longitude' => 28.3150,
                'price_from' => 2800, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'is_featured' => true, 'featured_until' => now()->addMonths(2), 'rating_avg' => 4.7, 'rating_count' => 59,
                'description' => 'Professional sound systems and lighting rigs for events of any scale.',
                'cover' => 'https://images.unsplash.com/photo-1470229538611-16ba8c7ffbd7?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1470229538611-16ba8c7ffbd7?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Standard Sound & Lighting Rig', 'price' => 2800, 'is_popular' => true]],
            ],
            [
                'ref' => 'v9', 'business_name' => 'Bella Glow Makeup', 'category' => 'makeup',
                'city' => 'Lusaka', 'area' => 'Longacres, Lusaka', 'latitude' => -15.4150, 'longitude' => 28.3050,
                'price_from' => 700, 'price_unit' => 'per session', 'status' => 'approved', 'source' => 'platform',
                'rating_avg' => 4.9, 'rating_count' => 112,
                'description' => 'Bridal and event makeup artistry with a natural, long-lasting finish.',
                'cover' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Bridal Makeup Session', 'price' => 700, 'unit' => 'per session', 'is_popular' => true]],
            ],
            [
                'ref' => 'v10', 'business_name' => 'Royal Ride Car Hire', 'category' => 'cars',
                'city' => 'Lusaka', 'area' => 'Kamwala, Lusaka', 'latitude' => -15.4250, 'longitude' => 28.2950,
                'price_from' => 2500, 'price_unit' => 'per day', 'status' => 'approved', 'source' => 'platform',
                'rating_avg' => 4.5, 'rating_count' => 47,
                'description' => 'Decorated luxury car hire for weddings and special occasions.',
                'cover' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1502877338535-766e1452684a?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Wedding Car + Decoration', 'price' => 2500, 'unit' => 'per day', 'is_popular' => true]],
            ],
            [
                'ref' => 'v11', 'business_name' => 'Copperbelt Tents & Chairs', 'category' => 'tents',
                'city' => 'Ndola', 'area' => 'Ndola Central', 'latitude' => -12.9587, 'longitude' => 28.6366,
                'price_from' => 1800, 'price_unit' => 'per set', 'status' => 'approved', 'source' => 'google_places',
                'rating_avg' => 4.4, 'rating_count' => 29,
                'description' => 'Tent, chair and table hire for outdoor events across the Copperbelt.',
                'cover' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1519225421980-715cb0215aed?q=80&w=500&auto=format&fit=crop'],
                'packages' => [],
                'no_user' => true,
            ],
            [
                'ref' => 'v12', 'business_name' => 'Livingstone Riverside Hall', 'category' => 'venues',
                'city' => 'Livingstone', 'area' => 'Livingstone', 'latitude' => -17.8419, 'longitude' => 25.8543,
                'price_from' => 9500, 'price_unit' => 'per event', 'status' => 'approved', 'source' => 'platform',
                'rating_avg' => 4.6, 'rating_count' => 63,
                'description' => 'Riverside event hall in Livingstone with scenic views for weddings and functions.',
                'cover' => 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop',
                'gallery' => ['https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=500&auto=format&fit=crop'],
                'packages' => [['name' => 'Riverside Hall Hire', 'price' => 9500, 'is_popular' => true]],
            ],
            // Pending approvals (for admin demo)
            [
                'ref' => 'v13', 'business_name' => 'Bantu Beats Entertainment', 'category' => 'mcs-djs',
                'city' => 'Kitwe', 'area' => 'Kitwe', 'latitude' => -12.8024, 'longitude' => 28.2132,
                'price_from' => 2900, 'price_unit' => 'per event', 'status' => 'pending', 'source' => 'platform',
                'rating_avg' => 0, 'rating_count' => 0,
                'description' => 'MC and DJ services for parties and corporate events in the Copperbelt.',
                'cover' => 'https://images.unsplash.com/photo-1571266028243-e4c8f8b2f3a6?q=80&w=1600&auto=format&fit=crop',
                'gallery' => [],
                'packages' => [],
            ],
            [
                'ref' => 'v14', 'business_name' => 'Glow Up Studio', 'category' => 'makeup',
                'city' => 'Livingstone', 'area' => 'Livingstone', 'latitude' => -17.8419, 'longitude' => 25.8543,
                'price_from' => 650, 'price_unit' => 'per session', 'status' => 'pending', 'source' => 'platform',
                'rating_avg' => 0, 'rating_count' => 0,
                'description' => 'Makeup and beauty studio serving Livingstone brides and event guests.',
                'cover' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=1600&auto=format&fit=crop',
                'gallery' => [],
                'packages' => [],
            ],
        ];

        $vendorProfiles = [];

        foreach ($vendorDefs as $def) {
            $vendorUser = null;
            if (empty($def['no_user'])) {
                $slug = Str::slug($def['business_name']);
                $vendorUser = User::updateOrCreate(['email' => $def['email'] ?? "$slug@example.test"], [
                    'name' => $def['business_name'],
                    'password' => $password,
                    'role' => 'vendor',
                    'phone' => '+260971234567',
                    'whatsapp' => '+260971234567',
                ]);
            }

            $vendor = VendorProfile::updateOrCreate(
                ['slug' => Str::slug($def['business_name'])],
                [
                    'user_id' => $vendorUser?->id,
                    'category_id' => $catId($def['category']),
                    'business_name' => $def['business_name'],
                    'description' => $def['description'],
                    'phone' => '+260971234567',
                    'whatsapp' => '+260971234567',
                    'email' => $def['email'] ?? null,
                    'city' => $def['city'],
                    'area' => $def['area'],
                    'address' => $def['address'] ?? null,
                    'latitude' => $def['latitude'],
                    'longitude' => $def['longitude'],
                    'logo_path' => $def['logo'] ?? null,
                    'cover_path' => $def['cover'],
                    'facebook_url' => $def['facebook_url'] ?? null,
                    'instagram_url' => $def['instagram_url'] ?? null,
                    'price_from' => $def['price_from'],
                    'price_unit' => $def['price_unit'],
                    'status' => $def['status'],
                    'source' => $def['source'],
                    'is_featured' => $def['is_featured'] ?? false,
                    'featured_until' => $def['featured_until'] ?? null,
                    'rating_avg' => $def['rating_avg'],
                    'rating_count' => $def['rating_count'],
                    'response_time_minutes' => rand(20, 180),
                ]
            );

            $vendorProfiles[$def['ref']] = $vendor;

            foreach ($def['gallery'] as $i => $url) {
                PortfolioMedia::updateOrCreate(
                    ['vendor_profile_id' => $vendor->id, 'url' => $url],
                    ['type' => 'image', 'sort_order' => $i]
                );
            }

            foreach ($def['packages'] as $pkg) {
                Package::updateOrCreate(
                    ['vendor_profile_id' => $vendor->id, 'name' => $pkg['name']],
                    [
                        'price' => $pkg['price'],
                        'unit' => $pkg['unit'] ?? $def['price_unit'],
                        'guest_capacity' => $pkg['guest_capacity'] ?? null,
                        'is_popular' => $pkg['is_popular'] ?? false,
                        'is_active' => true,
                    ]
                );
            }
        }

        // --- Reviews (a few real rows for the reviews endpoint) ---
        Review::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Chileshe Banda']->id],
            ['rating' => 5, 'comment' => 'Beautiful venue and the staff were extremely helpful throughout our wedding planning. Highly recommend!', 'status' => 'published']
        );
        Review::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Natasha Mumba']->id],
            ['rating' => 4, 'comment' => 'Great garden space for our corporate function. Parking was a bit tight when we had over 300 guests.', 'status' => 'published']
        );
        Review::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v5']->id, 'user_id' => $customers['Given Zulu']->id],
            ['rating' => 5, 'comment' => 'Golden Aisle Decor made our reception look like something out of a magazine.', 'status' => 'published']
        );
        Review::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v2']->id, 'user_id' => $customers['Mwansa Phiri']->id],
            ['rating' => 1, 'comment' => 'Spam test review flagged for moderation.', 'status' => 'flagged']
        );

        // --- Enquiries ---
        Enquiry::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Chileshe Banda']->id],
            ['event_date' => '2026-12-14', 'guest_count' => 300, 'message' => 'Interested in Signature Wedding package for 300 guests.', 'status' => 'new']
        );
        $enquiry2 = Enquiry::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Natasha Mumba']->id],
            ['event_date' => '2027-01-02', 'guest_count' => 150, 'message' => 'Corporate anniversary — need quote for 150 pax.', 'status' => 'responded']
        );
        $enquiry3 = Enquiry::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Mwansa Phiri']->id],
            ['event_date' => '2026-11-20', 'guest_count' => 80, 'message' => 'Availability check for a birthday party.', 'status' => 'booked']
        );

        // --- Bookings ---
        Booking::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Mwansa Phiri']->id, 'event_date' => '2026-11-20'],
            ['package_id' => Package::where('vendor_profile_id', $vendorProfiles['v1']->id)->where('name', 'Garden Basic')->value('id'),
                'enquiry_id' => $enquiry3->id, 'customer_name' => 'Mwansa Phiri', 'amount' => 8500, 'status' => 'confirmed']
        );
        Booking::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v1']->id, 'user_id' => $customers['Bwalya Chilufya']->id, 'event_date' => '2026-10-05'],
            ['package_id' => Package::where('vendor_profile_id', $vendorProfiles['v1']->id)->where('name', 'Signature Wedding')->value('id'),
                'customer_name' => 'Bwalya Chilufya', 'amount' => 16000, 'status' => 'confirmed']
        );
        Booking::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v5']->id, 'user_id' => $customers['Given Zulu']->id, 'event_date' => '2026-09-18'],
            ['customer_name' => 'Given Zulu', 'amount' => 6000, 'status' => 'pending']
        );

        // --- Favourites ---
        Favourite::updateOrCreate(['user_id' => $customers['Chileshe Banda']->id, 'vendor_profile_id' => $vendorProfiles['v1']->id]);
        Favourite::updateOrCreate(['user_id' => $customers['Chileshe Banda']->id, 'vendor_profile_id' => $vendorProfiles['v5']->id]);

        // --- Business claims ---
        BusinessClaim::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v4']->id, 'user_id' => $customers['Given Zulu']->id],
            ['evidence' => 'Business registration document + utility bill attached.', 'status' => 'pending']
        );
        BusinessClaim::updateOrCreate(
            ['vendor_profile_id' => $vendorProfiles['v11']->id, 'user_id' => $customers['Bwalya Chilufya']->id],
            ['evidence' => 'NRC + utility bill attached.', 'status' => 'pending']
        );

        // --- Advertisements ---
        Advertisement::updateOrCreate(
            ['title' => 'Silverleaf Gardens — Homepage Banner'],
            [
                'vendor_profile_id' => $vendorProfiles['v1']->id, 'placement' => 'homepage_hero',
                'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20),
                'clicks' => 2140, 'impressions' => 48200, 'status' => 'running',
            ]
        );
        Advertisement::updateOrCreate(
            ['title' => 'Bliss Weddings — Category Spotlight'],
            [
                'vendor_profile_id' => $vendorProfiles['v6']->id, 'placement' => 'category_spotlight',
                'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(35),
                'clicks' => 860, 'impressions' => 12400, 'status' => 'scheduled',
            ]
        );

        // --- Sample event for "save vendor to event" ---
        $event = Event::updateOrCreate(
            ['user_id' => $customers['Chileshe Banda']->id, 'name' => "My Sister's Wedding"],
            ['event_date' => '2026-12-14']
        );
        $event->vendorProfiles()->syncWithoutDetaching([$vendorProfiles['v1']->id, $vendorProfiles['v5']->id]);
    }
}
