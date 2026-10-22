<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * The original EventHub Zambia static vendor database, ported from the
     * front-end `const vendors = {...}` structure into relational records.
     */
    public function run(): void
    {
        foreach ($this->vendors() as $slug => $data) {
            $vendor = Vendor::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'          => $data['name'],
                    'category'      => $data['category'],
                    'location'      => $data['location'],
                    'rating'        => $data['rating'],
                    'reviews_count' => $data['reviews'],
                    'experience'    => $data['experience'],
                    'events_count'  => $data['events'],
                    'phone'         => $data['phone'],
                    'cover'         => $data['cover'],
                    'logo'          => $data['logo'],
                    'about'         => $data['about'],
                    'services'      => $data['services'],
                    'event_types'   => $data['eventTypes'],
                    'price_from'    => $data['price'],
                    'featured'      => $data['featured'],
                    'verified'      => true,
                    'status'        => 'approved',
                ]
            );

            $vendor->packages()->delete();
            foreach ($data['packages'] as $i => $package) {
                $vendor->packages()->create([
                    'name'  => $package['name'],
                    'price' => $package['price'],
                    'items' => $package['items'],
                    'sort'  => $i,
                ]);
            }

            $vendor->images()->delete();
            foreach ($data['portfolio'] as $i => $url) {
                $vendor->images()->create(['url' => $url, 'sort' => $i]);
            }

            $vendor->reviews()->delete();
            foreach ($data['customerReviews'] as $review) {
                $vendor->reviews()->create([
                    'author' => $review[0],
                    'stars'  => $review[1],
                    'rating' => substr_count($review[1], '★'), // filled-star count
                    'body'   => $review[2],
                ]);
            }
        }
    }

    private function vendors(): array
    {
        return [
            'royal-gardens' => [
                'name' => 'Royal Gardens', 'category' => 'Venue', 'location' => 'Lusaka',
                'rating' => 4.9, 'reviews' => 38, 'experience' => '9+', 'events' => '320+',
                'phone' => '260970000001', 'price' => 5000, 'featured' => true,
                'cover' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=500&q=90',
                'about' => 'Royal Gardens is a premium event venue offering elegant indoor and outdoor spaces for weddings, corporate functions, private celebrations and special occasions. Our team focuses on creating refined environments with professional event support.',
                'services' => ['Wedding Venue', 'Corporate Functions', 'Private Events', 'Outdoor Events', 'Reception Setup', 'Event Coordination'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Birthdays', 'Graduations', 'Private Celebrations'],
                'packages' => [
                    ['name' => 'Classic Package', 'price' => 'K5,000', 'items' => ['Venue hire', 'Tables & chairs', 'Basic setup', 'Parking']],
                    ['name' => 'Premium Package', 'price' => 'K8,500', 'items' => ['Premium venue', 'Full reception setup', 'Decor coordination', 'Dedicated event support']],
                    ['name' => 'Luxury Package', 'price' => 'K15,000', 'items' => ['Exclusive venue', 'Premium décor', 'Event coordination', 'VIP setup']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Chipo M.', '★★★★★', 'Beautiful venue and very professional team.'],
                    ['Brian K.', '★★★★★', 'The venue looked even better in person.'],
                    ['Martha C.', '★★★★☆', 'Great location and excellent service.'],
                ],
            ],
            'elegant-moments' => [
                'name' => 'Elegant Moments', 'category' => 'Decor', 'location' => 'Lusaka',
                'rating' => 4.8, 'reviews' => 31, 'experience' => '7+', 'events' => '210+',
                'phone' => '260970000002', 'price' => 3500, 'featured' => true,
                'cover' => 'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=500&q=90',
                'about' => 'Elegant Moments specialises in sophisticated event décor, floral styling, reception styling and customised event concepts for clients who want a polished and memorable celebration.',
                'services' => ['Wedding Décor', 'Floral Styling', 'Reception Styling', 'Backdrop Design', 'Corporate Décor', 'Custom Themes'],
                'eventTypes' => ['Weddings', 'Birthdays', 'Corporate Events', 'Baby Showers', 'Private Events'],
                'packages' => [
                    ['name' => 'Essential Décor', 'price' => 'K3,500', 'items' => ['Backdrop', 'Table setup', 'Basic centrepieces', 'Entrance décor']],
                    ['name' => 'Signature Décor', 'price' => 'K6,500', 'items' => ['Full reception styling', 'Floral arrangements', 'Premium backdrop', 'Table styling']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1531058020387-3be344556be6?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Natasha L.', '★★★★★', 'The décor was exactly what we wanted.'],
                    ['Sarah K.', '★★★★★', 'Elegant, professional and creative.'],
                    ['Mwape B.', '★★★★☆', 'Very good service and communication.'],
                ],
            ],
            'glow-beauty' => [
                'name' => 'Glow Beauty', 'category' => 'Beauty', 'location' => 'Lusaka',
                'rating' => 4.7, 'reviews' => 26, 'experience' => '6+', 'events' => '180+',
                'phone' => '260970000003', 'price' => 1200, 'featured' => false,
                'cover' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=500&q=90',
                'about' => 'Glow Beauty provides professional bridal, event and occasion beauty services with a focus on polished, long-lasting looks.',
                'services' => ['Bridal Makeup', 'Hair Styling', 'Nail Services', 'Makeup', 'Beauty Packages'],
                'eventTypes' => ['Weddings', 'Birthdays', 'Graduations', 'Photoshoots'],
                'packages' => [
                    ['name' => 'Event Glam', 'price' => 'K1,200', 'items' => ['Professional makeup', 'Hair styling', 'Touch-up kit']],
                    ['name' => 'Bridal Glam', 'price' => 'K2,500', 'items' => ['Bridal makeup', 'Hair styling', 'Trial session', 'Touch-up support']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1526045478516-99145907023c?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Faith M.', '★★★★★', 'Very professional and the makeup lasted all day.'],
                    ['Chileshe P.', '★★★★☆', 'Beautiful work and friendly service.'],
                ],
            ],
            'executive-rides' => [
                'name' => 'Executive Rides', 'category' => 'Car Hire', 'location' => 'Lusaka',
                'rating' => 4.8, 'reviews' => 29, 'experience' => '8+', 'events' => '400+',
                'phone' => '260970000004', 'price' => 2500, 'featured' => true,
                'cover' => 'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=500&q=90',
                'about' => 'Executive Rides provides premium and reliable event transportation for weddings, corporate functions, airport transfers and special occasions.',
                'services' => ['Wedding Cars', 'Luxury Cars', 'Airport Transfers', 'Corporate Transport', 'Chauffeur Service'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Airport Transfers', 'Private Events'],
                'packages' => [
                    ['name' => 'Executive', 'price' => 'K2,500', 'items' => ['Luxury vehicle', 'Professional driver', 'Event-day service']],
                    ['name' => 'VIP', 'price' => 'K4,500', 'items' => ['Premium vehicle', 'Chauffeur', 'Full-day service', 'Decorated vehicle']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1563720223185-11003d516935?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Daniel M.', '★★★★★', 'The car was clean and arrived on time.'],
                    ['Brenda K.', '★★★★★', 'Excellent wedding transportation.'],
                ],
            ],
            'golden-plate' => [
                'name' => 'Golden Plate Catering', 'category' => 'Catering', 'location' => 'Ndola',
                'rating' => 4.9, 'reviews' => 42, 'experience' => '10+', 'events' => '500+',
                'phone' => '260970000005', 'price' => 4500, 'featured' => true,
                'cover' => 'https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=500&q=90',
                'about' => 'Golden Plate Catering provides event catering for weddings, corporate functions, private celebrations and large gatherings across the Copperbelt.',
                'services' => ['Wedding Catering', 'Corporate Catering', 'Buffet', 'Cocktail Catering', 'Dessert Tables', 'Waiter Service'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Birthdays', 'Graduations', 'Private Events'],
                'packages' => [
                    ['name' => 'Classic Buffet', 'price' => 'K4,500', 'items' => ['Buffet setup', 'Two main dishes', 'Two sides', 'Waiter service']],
                    ['name' => 'Premium Banquet', 'price' => 'K7,500', 'items' => ['Premium menu', 'Full buffet setup', 'Dessert', 'Professional service team']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Mwaka C.', '★★★★★', 'Excellent food and professional service.'],
                    ['Joseph B.', '★★★★★', 'Our guests loved the food.'],
                    ['Tasha N.', '★★★★★', 'Everything was delivered as agreed.'],
                ],
            ],
            'prime-lens' => [
                'name' => 'Prime Lens Photography', 'category' => 'Photography', 'location' => 'Kitwe',
                'rating' => 4.8, 'reviews' => 34, 'experience' => '8+', 'events' => '280+',
                'phone' => '260970000006', 'price' => 2800, 'featured' => false,
                'cover' => 'https://images.unsplash.com/photo-1531058020387-3be344556be6?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1531058020387-3be344556be6?auto=format&fit=crop&w=500&q=90',
                'about' => 'Prime Lens Photography captures weddings, corporate events, birthdays and milestone celebrations with a documentary and editorial approach.',
                'services' => ['Wedding Photography', 'Event Photography', 'Video Coverage', 'Drone Coverage', 'Photo Booth', 'Album Design'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Birthdays', 'Graduations', 'Traditional Ceremonies'],
                'packages' => [
                    ['name' => 'Essential', 'price' => 'K2,800', 'items' => ['Photography', 'Edited images', 'Online gallery']],
                    ['name' => 'Complete', 'price' => 'K5,500', 'items' => ['Photography', 'Video', 'Drone coverage', 'Premium album']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1531058020387-3be344556be6?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Kelvin T.', '★★★★★', 'Amazing photos and very professional.'],
                    ['Natasha M.', '★★★★★', 'They captured every important moment.'],
                ],
            ],
            'royal-sound' => [
                'name' => 'Royal Sound Entertainment', 'category' => 'DJ', 'location' => 'Lusaka',
                'rating' => 4.9, 'reviews' => 45, 'experience' => '11+', 'events' => '600+',
                'phone' => '260970000007', 'price' => 3000, 'featured' => false,
                'cover' => 'https://images.unsplash.com/photo-1571266028243-d220c9c3b0c7?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1571266028243-d220c9c3b0c7?auto=format&fit=crop&w=500&q=90',
                'about' => 'Royal Sound Entertainment provides DJs, MCs, professional sound systems and entertainment solutions for events of all sizes.',
                'services' => ['DJ Services', 'MC Services', 'Sound Systems', 'Lighting', 'Live Entertainment'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Birthdays', 'Parties', 'Private Events'],
                'packages' => [
                    ['name' => 'DJ Package', 'price' => 'K3,000', 'items' => ['Professional DJ', 'Sound system', 'Basic lighting']],
                    ['name' => 'Premium Entertainment', 'price' => 'K6,000', 'items' => ['DJ', 'MC', 'Premium sound', 'Lighting']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1571266028243-d220c9c3b0c7?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1506157786151-b8491531f063?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Moses K.', '★★★★★', 'The music was excellent and the DJ was professional.'],
                    ['Chanda P.', '★★★★★', 'Royal Sound made our reception amazing.'],
                ],
            ],
            'grand-hall' => [
                'name' => 'Grand Hall Events', 'category' => 'Venue', 'location' => 'Livingstone',
                'rating' => 4.6, 'reviews' => 21, 'experience' => '6+', 'events' => '160+',
                'phone' => '260970000008', 'price' => 4000, 'featured' => false,
                'cover' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=500&q=90',
                'about' => 'Grand Hall Events offers spacious event facilities in Livingstone suitable for weddings, conferences, celebrations and private functions.',
                'services' => ['Event Venue', 'Conference Venue', 'Wedding Reception', 'Private Events', 'Parking'],
                'eventTypes' => ['Weddings', 'Corporate Events', 'Conferences', 'Birthdays', 'Graduations'],
                'packages' => [
                    ['name' => 'Hall Hire', 'price' => 'K4,000', 'items' => ['Venue hire', 'Tables & chairs', 'Basic setup']],
                    ['name' => 'Conference Package', 'price' => 'K6,500', 'items' => ['Hall', 'Seating setup', 'PA system', 'Event support']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1507504031003-b417219a0fde?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Brian S.', '★★★★☆', 'Spacious and convenient venue.'],
                    ['Linda C.', '★★★★★', 'Good venue for our conference.'],
                ],
            ],
            'mwezi-couture' => [
                'name' => 'Mwezi Couture Studio', 'category' => 'Fashion & Outfits', 'location' => 'Lusaka',
                'rating' => 4.9, 'reviews' => 46, 'experience' => '8+', 'events' => '185+',
                'phone' => '260970000009', 'price' => 2500, 'featured' => true,
                'cover' => 'https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=1800&q=90',
                'logo' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=500&q=90',
                'about' => 'Mwezi Couture Studio is a fashion and outfit design studio creating custom looks for weddings, traditional ceremonies, engagements, graduations, birthdays and corporate occasions. Clients can request bespoke designs, tailoring, styling and selected outfit rentals.',
                'services' => ['Custom Outfit Design', 'Wedding Dresses', 'Bridal Party Wear', 'Groom & Groomsmen Suits', 'Traditional Ceremony Wear', 'Custom Tailoring', 'Outfit Styling', 'Selected Outfit Rentals'],
                'eventTypes' => ['Weddings', 'Kitchen Parties', 'Engagements', 'Traditional Ceremonies', 'Graduations', 'Birthdays', 'Corporate Events'],
                'packages' => [
                    ['name' => 'Custom Outfit', 'price' => 'From K2,500', 'items' => ['Design consultation', 'Measurements', 'Custom pattern', 'One fitting']],
                    ['name' => 'Bridal Package', 'price' => 'From K8,500', 'items' => ['Bridal gown design', 'Bridesmaid styling', 'Fittings', 'Final alterations']],
                    ['name' => 'Groom & Groomsmen', 'price' => 'From K6,000', 'items' => ['Suit styling', 'Measurements', 'Coordinated looks', 'Final fitting']],
                ],
                'portfolio' => [
                    'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1581044777550-4cfa60707c03?auto=format&fit=crop&w=900&q=85',
                    'https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=900&q=85',
                ],
                'customerReviews' => [
                    ['Natasha M.', '★★★★★', 'The custom design and fitting process was excellent.'],
                    ['Chanda K.', '★★★★★', 'Beautiful traditional ceremony outfits and great finishing.'],
                    ['Ruth B.', '★★★★☆', 'Professional service and the final outfit looked exactly like the concept.'],
                ],
            ],
        ];
    }
}
