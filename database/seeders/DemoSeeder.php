<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Quotation;
use App\Models\RequestProviderMatch;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEMONSTRATION DATA. Every record created here is flagged is_demo = true, shown with a "Demo" badge,
 * and can be removed in one click from Admin → Settings. All demo accounts use the password "Password123".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('is_demo', true)->exists()) {
            $this->command?->warn('Demo data already present – skipping.');

            return;
        }
        $cats = Category::pluck('id', 'slug');

        // Lusaka neighbourhood coordinates.
        $places = [
            'Kabulonga' => [-15.4067, 28.3340], 'Woodlands' => [-15.4269, 28.3120], 'Rhodes Park' => [-15.4100, 28.3050],
            'Chelstone' => [-15.3700, 28.3900], 'Matero' => [-15.3700, 28.2600], 'Kabwata' => [-15.4350, 28.2950],
            'Chilenje' => [-15.4450, 28.3150], 'Avondale' => [-15.3850, 28.3550], 'Roma' => [-15.3750, 28.3200],
            'Ibex Hill' => [-15.4300, 28.3700], 'Emmasdale' => [-15.3900, 28.2700], 'Lusaka CBD' => [-15.4167, 28.2833],
        ];

        $providers = [
            ['Mwila Banda', 'Mwila Towing & Recovery', ['roadside-assistance'], 'Kabulonga', true, 4.8, 9, 'avatar-m'],
            ['Chanda Mulenga', null, ['electrical-services', 'additional-services'], 'Woodlands', true, 4.9, 12, null],
            ['Joseph Phiri', 'Phiri Plumbing Works', ['plumbing-services'], 'Chelstone', true, 4.7, 7, null],
            ['Grace Tembo', 'SafeGas Zambia', ['gas-services'], 'Rhodes Park', true, 4.6, 5, null],
            ['Kelvin Zulu', 'Zulu Auto Clinic', ['mechanical-services', 'roadside-assistance'], 'Emmasdale', true, 4.8, 15, null],
            ['Bwalya Kunda', null, ['carpentry', 'additional-services'], 'Matero', false, 4.5, 6, null],
            ['Lameck Sakala', 'Sakala Steel Fabricators', ['welding-metalwork'], 'Kabwata', true, 4.7, 10, null],
            ['Natasha Mwape', 'CoolTech Refrigeration', ['additional-services', 'electrical-services'], 'Avondale', true, 4.9, 8, null],
            ['Moses Lungu', null, ['plumbing-services', 'additional-services'], 'Chilenje', false, 4.4, 4, null],
        ];

        $providerUsers = [];
        foreach ($providers as $i => [$name, $biz, $catSlugs, $area, $online, $rating, $years]) {
            [$lat, $lng] = $places[$area];
            $u = User::create([
                'name' => $name, 'email' => 'provider'.($i + 1).'@demo.handynow.co.zm', 'phone' => '26097710'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'password' => 'Password123', 'role' => 'provider', 'city' => 'Lusaka', 'is_demo' => true,
            ]);
            $p = ProviderProfile::create([
                'user_id' => $u->id, 'business_name' => $biz, 'provider_type' => $biz ? 'business' : 'individual',
                'nrc_number' => sprintf('%06d/%02d/1', 100000 + $i * 7919, 10 + $i), 'business_reg_number' => $biz ? 'PACRA-'.(120000 + $i) : null,
                'bio' => "Experienced professional serving {$area} and surrounding areas for {$years} years. Fast, honest and fully equipped.",
                'years_experience' => $years, 'whatsapp' => $u->phone, 'operating_areas' => [$area, 'Lusaka'], 'service_radius_km' => 30,
                'base_latitude' => $lat, 'base_longitude' => $lng, 'is_online' => $online, 'verification_status' => 'approved',
                'verified_at' => now()->subDays(30 + $i), 'rating_avg' => $rating, 'rating_count' => 0, 'avg_response_minutes' => 15 + $i * 3,
            ]);
            $p->categories()->sync(collect($catSlugs)->map(fn ($s) => $cats[$s])->all());
            $providerUsers[] = $u;
        }

        // One provider still awaiting verification, to demonstrate the admin approval queue.
        $pending = User::create([
            'name' => 'Patrick Mumba', 'email' => 'pending@demo.handynow.co.zm', 'phone' => '260977109999', 'password' => 'Password123',
            'role' => 'provider', 'city' => 'Lusaka', 'is_demo' => true,
        ]);
        ProviderProfile::create([
            'user_id' => $pending->id, 'provider_type' => 'individual', 'nrc_number' => '334455/10/1', 'operating_areas' => ['Roma', 'Lusaka'],
            'base_latitude' => -15.3750, 'base_longitude' => 28.3200, 'verification_status' => 'pending', 'years_experience' => 3,
            'bio' => 'Electrician and solar installer.',
        ])->categories()->sync([$cats['electrical-services']]);

        $customers = collect([
            ['Thandiwe Mutale', 'customer@demo.handynow.co.zm', '260966200001'],
            ['Brian Chileshe', 'brian@demo.handynow.co.zm', '260966200002'],
            ['Ruth Ngoma', 'ruth@demo.handynow.co.zm', '260966200003'],
        ])->map(fn ($c) => User::create(['name' => $c[0], 'email' => $c[1], 'phone' => $c[2], 'password' => 'Password123', 'role' => 'customer', 'city' => 'Lusaka', 'is_demo' => true]));

        $scenarios = [
            // [customer idx, provider idx|null, category, service slug, place, priority, status, amount, paid, rating, days ago]
            [0, 0, 'roadside-assistance', 'vehicle-towing', 'Ibex Hill', 'emergency', 'completed', 1200, true, 5, 12],
            [0, 2, 'plumbing-services', 'burst-pipe-repairs', 'Kabulonga', 'emergency', 'completed', 650, true, 5, 8],
            [1, 1, 'electrical-services', 'power-failure-troubleshooting', 'Woodlands', 'urgent', 'completed', 480, true, 4, 6],
            [2, 4, 'mechanical-services', 'vehicle-diagnostics', 'Emmasdale', 'normal', 'completed', 550, true, 5, 4],
            [1, 6, 'welding-metalwork', 'gate-repairs', 'Kabwata', 'normal', 'completed', 900, false, null, 2],
            [0, 3, 'gas-services', 'gas-leak-inspection', 'Rhodes Park', 'emergency', 'en_route', 300, false, null, 0],
            [2, 7, 'additional-services', 'air-conditioning-repairs', 'Avondale', 'urgent', 'in_progress', 750, false, null, 0],
            [1, null, 'plumbing-services', 'blocked-drainage', 'Chelstone', 'urgent', 'searching', null, false, null, 0],
            [0, null, 'roadside-assistance', 'battery-jump-start', 'Lusaka CBD', 'emergency', 'searching', null, false, null, 0],
            [2, 1, 'electrical-services', 'db-board-repairs', 'Roma', 'normal', 'cancelled', null, false, null, 3],
        ];

        $flow = ['pending', 'searching', 'provider_assigned', 'accepted', 'en_route', 'arrived', 'in_progress', 'completed'];
        $comments = [5 => 'Arrived in 20 minutes and sorted everything out. Highly recommended!', 4 => 'Good work, fair price. Slightly late but kept me updated.'];

        foreach ($scenarios as [$ci, $pi, $catSlug, $svcSlug, $place, $priority, $status, $amount, $paid, $rating, $daysAgo]) {
            $customer = $customers[$ci];
            $provider = $pi !== null ? $providerUsers[$pi] : null;
            $cat = Category::where('slug', $catSlug)->first();
            $svc = $cat->services()->where('slug', $svcSlug)->first();
            [$lat, $lng] = $places[$place];
            $created = now()->subDays($daysAgo)->subHours(rand(1, 5));

            $sr = ServiceRequest::create([
                'customer_id' => $customer->id, 'provider_id' => $provider?->id, 'category_id' => $cat->id, 'service_id' => $svc?->id,
                'contact_name' => $customer->name, 'contact_phone' => $customer->phone,
                'description' => ($svc?->name ?? $cat->name).' needed urgently. '.($svc?->description ?? ''),
                'address' => 'Plot '.rand(10, 999).", {$place}, Lusaka", 'directions' => 'Near the main road, blue gate.',
                'latitude' => $lat + (rand(-50, 50) / 10000), 'longitude' => $lng + (rand(-50, 50) / 10000),
                'priority' => $priority, 'status' => $status, 'agreed_amount' => $amount, 'is_demo' => true,
                'assigned_at' => $provider ? $created->copy()->addMinutes(8) : null,
                'completed_at' => $status === 'completed' ? $created->copy()->addHours(2) : null,
                'cancelled_at' => $status === 'cancelled' ? $created->copy()->addMinutes(30) : null,
                'cancel_reason' => $status === 'cancelled' ? 'Cancelled by customer: Fixed the problem myself' : null,
            ]);
            $sr->forceFill(['created_at' => $created, 'updated_at' => $created])->save();

            // Timeline
            $stop = $status === 'cancelled' ? 1 : array_search($status, $flow, true);
            foreach (array_slice($flow, 0, $stop + 1) as $k => $st) {
                $sr->statusLogs()->create(['status' => $st, 'user_id' => $k >= 3 ? $provider?->id : null, 'note' => $k === 0 ? 'Request submitted' : null])
                    ->forceFill(['created_at' => $created->copy()->addMinutes($k * 9)])->save();
            }
            if ($status === 'cancelled') {
                $sr->statusLogs()->create(['status' => 'cancelled', 'user_id' => $customer->id, 'note' => $sr->cancel_reason]);
            }

            if ($provider) {
                RequestProviderMatch::create(['service_request_id' => $sr->id, 'provider_id' => $provider->id, 'distance_km' => rand(20, 90) / 10, 'status' => 'quoted']);
                if ($amount) {
                    Quotation::create(['service_request_id' => $sr->id, 'provider_id' => $provider->id, 'amount' => $amount - ($svc?->callout_fee ?? 0),
                        'callout_fee' => $svc?->callout_fee ?? 0, 'eta_minutes' => 25, 'message' => 'I can be there quickly with all equipment.', 'status' => 'accepted']);
                }
            }

            if ($status === 'searching') {
                // Match to eligible demo providers so they see live job alerts and can quote.
                $eligible = ProviderProfile::where('verification_status', 'approved')->whereHas('categories', fn ($q) => $q->where('categories.id', $cat->id))->get();
                foreach ($eligible as $j => $p) {
                    RequestProviderMatch::firstOrCreate(['service_request_id' => $sr->id, 'provider_id' => $p->user_id], ['distance_km' => rand(15, 120) / 10]);
                    if ($j === 0) {
                        Quotation::create(['service_request_id' => $sr->id, 'provider_id' => $p->user_id, 'amount' => ($svc?->min_price ?? 200) + 100,
                            'callout_fee' => $svc?->callout_fee ?? 0, 'eta_minutes' => 20, 'message' => 'Available now, 20 minutes away.', 'status' => 'pending']);
                    }
                }
            }

            if ($status === 'completed' && $paid) {
                $commission = round($amount * 0.10, 2);
                Payment::create([
                    'service_request_id' => $sr->id, 'customer_id' => $customer->id, 'provider_id' => $provider->id, 'amount' => $amount,
                    'method' => ['mtn_momo', 'airtel_money', 'cash', 'zamtel_kwacha'][rand(0, 3)], 'payer_phone' => $customer->phone,
                    'transaction_reference' => 'DEMO'.rand(100000, 999999), 'status' => 'confirmed', 'commission_rate' => 10,
                    'commission_amount' => $commission, 'provider_amount' => $amount - $commission, 'confirmed_by' => $provider->id,
                    'confirmed_at' => $sr->completed_at, 'is_demo' => true,
                ]);
                $sr->update(['payment_status' => 'paid', 'commission_rate' => 10, 'commission_amount' => $commission, 'provider_earnings' => $amount - $commission]);
            }
            if ($status === 'completed' && $provider) {
                $provider->providerProfile->increment('jobs_completed');
            }
            if ($rating) {
                Review::create(['service_request_id' => $sr->id, 'customer_id' => $customer->id, 'provider_id' => $provider->id,
                    'rating' => $rating, 'comment' => $comments[$rating], 'is_demo' => true]);
            }
        }

        // Ratings: combine seeded "historic" averages with real demo reviews for display.
        foreach ($providerUsers as $u) {
            $u->providerProfile->update(['rating_count' => $u->reviewsReceived()->count() + rand(8, 40)]);
        }

        Complaint::create([
            'user_id' => $customers[1]->id, 'service_request_id' => ServiceRequest::where('customer_id', $customers[1]->id)->where('status', 'completed')->value('id'),
            'subject' => 'Provider arrived later than quoted', 'description' => 'The provider quoted 25 minutes but arrived after an hour. Work was fine.',
            'status' => 'open', 'is_demo' => true,
        ]);

        $this->command?->info('Demo data created. Demo logins (password "Password123"): customer@demo.handynow.co.zm, provider1@demo.handynow.co.zm');
    }
}
