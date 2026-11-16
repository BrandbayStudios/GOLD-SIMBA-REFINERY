<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Geo;
use App\Services\Payments\GatewaySettings;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PricingFeeTest extends TestCase
{
    use RefreshDatabase;

    private Category $roadside;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->roadside = Category::where('slug', 'roadside-assistance')->first();
        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create(['phone' => '260966111222']);
    }

    private function service(string $slug, array $attrs = []): Service
    {
        $s = Service::where('slug', $slug)->first();
        $s->update($attrs);

        return $s->fresh();
    }

    private function provider(): User
    {
        $u = User::factory()->provider()->create();
        ProviderProfile::create(['user_id' => $u->id, 'verification_status' => 'approved', 'is_online' => true, 'base_latitude' => -15.4067, 'base_longitude' => 28.3340,
            'service_radius_km' => 30, 'operating_areas' => ['Kabulonga']])->categories()->sync([$this->roadside->id]);

        return $u;
    }

    private function submit(Service $service, array $over = [])
    {
        return $this->actingAs($this->customer)->post('/request', array_merge([
            'category_id' => $this->roadside->id, 'service_id' => $service->id, 'contact_name' => 'Cust', 'contact_phone' => '0966111222',
            'description' => 'Need help with this job please.', 'address' => 'Arcades, Lusaka', 'latitude' => -15.40, 'longitude' => 28.30, 'priority' => 'normal',
        ], $over));
    }

    /** Request through the real form, a quote accepted by the customer: the provider is now assigned. */
    private function assignedViaQuote(Service $service, User $provider, float $amount = 200): ServiceRequest
    {
        $this->submit($service);
        $sr = ServiceRequest::latest('id')->first();
        $this->actingAs($provider)->post(route('provider.jobs.quote', $sr), ['amount' => $amount, 'callout_fee' => 0, 'eta_minutes' => 20])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post(route('customer.quotes.accept', [$sr, $sr->quotations()->first()]))->assertSessionHasNoErrors();

        return $sr->fresh();
    }

    public function test_price_ceiling_blocks_quotes_above_the_maximum_only_for_capped_services(): void
    {
        $p = $this->provider();
        $capped = $this->service('battery-jump-start', ['pricing_mode' => 'capped', 'min_price' => 150, 'max_price' => 350]);
        $this->submit($capped);
        $sr = ServiceRequest::first();

        $this->actingAs($p)->post(route('provider.jobs.quote', $sr), ['amount' => 300, 'callout_fee' => 100, 'eta_minutes' => 15])->assertSessionHasErrors('amount');
        $this->assertStringContainsString('K350', session('errors')->first('amount'));
        $this->actingAs($p)->get(route('provider.jobs.show', $sr))->assertSee('Price ceiling for this service: K350');
        $this->actingAs($p)->post(route('provider.jobs.quote', $sr), ['amount' => 250, 'callout_fee' => 100, 'eta_minutes' => 15])->assertSessionHasNoErrors();

        // Open services and requests with no specific service are never capped.
        $open = $this->service('flat-tyre-replacement', ['pricing_mode' => 'open', 'max_price' => 300]);
        $this->submit($open);
        $sr2 = ServiceRequest::latest('id')->first();
        $this->actingAs($p)->post(route('provider.jobs.quote', $sr2), ['amount' => 5000, 'eta_minutes' => 15])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post('/request', ['category_id' => $this->roadside->id, 'contact_name' => 'C', 'contact_phone' => '0966111222',
            'description' => 'Something else entirely.', 'address' => 'Lusaka', 'priority' => 'normal']);
        $this->actingAs($p)->post(route('provider.jobs.quote', ServiceRequest::latest('id')->first()), ['amount' => 9000, 'eta_minutes' => 15])->assertSessionHasNoErrors();
    }

    public function test_fixed_price_job_goes_to_the_first_provider_to_accept(): void
    {
        $a = $this->provider();
        $b = $this->provider();
        $svc = $this->service('battery-jump-start', ['pricing_mode' => 'fixed', 'fixed_price' => 250]);

        $this->submit($svc)->assertRedirect();
        $sr = ServiceRequest::first();
        $this->assertEquals(250, $sr->fixed_price);
        $this->assertSame('searching', $sr->status);
        $this->assertTrue(SmsLog::where('message', 'like', '%fixed price K250%')->exists());
        $this->assertTrue($a->notifications->contains(fn ($n) => str_contains($n->data['body'], 'Fixed price K250')));

        $this->actingAs($a)->get(route('provider.jobs.show', $sr))->assertSee('Accept this job')->assertDontSee('Send quote');
        $this->actingAs($a)->post(route('provider.jobs.quote', $sr), ['amount' => 100, 'eta_minutes' => 10])->assertSessionHasErrors('amount');

        $this->actingAs($a)->post(route('provider.jobs.accept', $sr))->assertSessionHasNoErrors();
        $sr->refresh();
        $this->assertSame($a->id, $sr->provider_id);
        $this->assertSame('accepted', $sr->status);
        $this->assertEquals(250, $sr->agreed_amount);
        $this->assertSame('none', $sr->booking_fee_status);

        $this->actingAs($b)->post(route('provider.jobs.accept', $sr))->assertSessionHasErrors('amount');
        $this->assertSame($a->id, $sr->fresh()->provider_id);
        $this->actingAs($this->customer)->get(route('customer.requests.show', $sr))->assertOk()->assertSee('Fixed price');
    }

    public function test_towing_is_priced_by_distance_and_needs_a_drop_off_point(): void
    {
        $this->provider();
        $tow = $this->service('vehicle-towing', ['pricing_mode' => 'fixed', 'fixed_price' => 500, 'price_per_km' => 15, 'included_km' => 5, 'needs_destination' => true]);

        $this->submit($tow)->assertSessionHasErrors(['dest_latitude', 'dest_address']);
        $this->submit($tow, ['latitude' => null, 'longitude' => null, 'dest_address' => 'Garage', 'dest_latitude' => -15.30, 'dest_longitude' => 28.30])->assertSessionHasErrors('latitude');

        $this->submit($tow, ['dest_address' => 'Toyota Zambia, Great East Road', 'dest_latitude' => -15.30, 'dest_longitude' => 28.30])->assertSessionHasNoErrors();
        $sr = ServiceRequest::first();
        $km = round(Geo::distanceKm(-15.40, 28.30, -15.30, 28.30) * 1.3, 1);
        $this->assertEquals($km, $sr->route_km);
        $this->assertGreaterThan(10, $km);
        $this->assertEquals(round(500 + ($km - 5) * 15), $sr->fixed_price);
        $this->assertSame('Toyota Zambia, Great East Road', $sr->dest_address);

        // A short hop inside the included distance costs the base price.
        $this->submit($tow, ['dest_address' => 'Next street', 'dest_latitude' => -15.4005, 'dest_longitude' => 28.30])->assertSessionHasNoErrors();
        $this->assertEquals(500, ServiceRequest::latest('id')->first()->fixed_price);
    }

    public function test_booking_fee_keeps_contact_details_locked_until_handy_now_is_paid(): void
    {
        Setting::put('booking_fee', 50);
        Setting::put('booking_fee_instructions', 'Send the fee to MTN MoMo 0972 015 689');
        $p = $this->provider();
        $sr = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p);

        $this->assertSame('pending', $sr->booking_fee_status);
        $this->assertEquals(50, $sr->booking_fee_amount);
        $this->assertFalse($sr->contactUnlocked());

        // Provider: no customer details, cannot move the job on.
        $this->actingAs($p)->get(route('provider.jobs.show', $sr))->assertOk()->assertSee('Waiting for the booking fee')->assertDontSee('0966111222')->assertDontSee('Call</a>', false);
        $this->actingAs($p)->post(route('provider.jobs.status', $sr), ['status' => 'accepted'])->assertSessionHasErrors('status');
        // Customer: no provider phone, no chat, a pay card instead.
        $page = $this->actingAs($this->customer)->get(route('customer.requests.show', $sr))->assertOk();
        $page->assertSee('Confirm your booking: K50.00')->assertSee('Contact details unlock when the booking fee is paid')->assertSee('Send the fee to MTN MoMo 0972 015 689')->assertDontSee($p->phone);
        $this->actingAs($this->customer)->postJson(route('messages.store', $sr), ['body' => 'hello'])->assertForbidden();
        $this->actingAs($p)->getJson(route('messages.index', $sr))->assertForbidden();

        // Cash is not allowed for a fee paid to HANDY NOW.
        $this->actingAs($this->customer)->post(route('customer.requests.fee', $sr), ['method' => 'cash'])->assertSessionHasErrors('method');
        $this->actingAs($this->customer)->post(route('customer.requests.fee', $sr), ['method' => 'mtn_momo', 'payer_phone' => '0966111222', 'transaction_reference' => 'MP999'])->assertSessionHasNoErrors();
        $fee = Payment::where('kind', 'booking_fee')->first();
        $this->assertSame('manual', $fee->gateway);
        $this->assertSame('pending', $fee->status);
        $this->assertNull($fee->provider_id, 'the provider can never confirm a fee paid to HANDY NOW');
        $this->assertTrue($this->admin->notifications->contains(fn ($n) => $n->data['title'] === 'Booking fee to confirm'));
        $this->assertFalse($sr->fresh()->contactUnlocked());
        $this->actingAs($p)->post(route('provider.payments.confirm', $fee))->assertForbidden();

        // Admin confirms: the job is unlocked for both sides and the provider is told.
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $fee))->assertSessionHasNoErrors();
        $sr->refresh();
        $this->assertSame('paid', $sr->booking_fee_status);
        $this->assertTrue($sr->contactUnlocked());
        $this->assertTrue($p->fresh()->notifications->contains(fn ($n) => $n->data['title'] === 'Booking fee paid – details unlocked'));
        $this->assertSame(0, LedgerEntry::count(), 'a booking fee is HANDY NOW revenue, not a provider balance');
        $this->actingAs($p)->get(route('provider.jobs.show', $sr))->assertSee('0966111222')->assertDontSee('Waiting for the booking fee');
        $this->actingAs($this->customer)->get(route('customer.requests.show', $sr))->assertSee($p->phone)->assertDontSee('Confirm your booking');
        $this->actingAs($p)->post(route('provider.jobs.status', $sr), ['status' => 'accepted'])->assertSessionHasNoErrors();

        // Reporting keeps fees apart from job revenue and commission.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('+ K50 booking fees');
        $this->get(route('admin.payments.index', ['kind' => 'booking_fee']))->assertOk()->assertSee('Booking fees received')->assertSee('K50.00');
        $this->assertEquals(0, Payment::where('kind', 'job')->where('status', 'confirmed')->sum('amount'));
    }

    public function test_booking_fee_can_be_paid_by_mobile_money_prompt_and_unlocks_automatically(): void
    {
        $pg = app(GatewaySettings::class);
        foreach (['mtn_enabled' => '1', 'mtn_environment' => 'sandbox', 'mtn_subscription_key' => 'k', 'mtn_api_user' => 'u', 'mtn_api_key' => 'a'] as $k => $v) {
            $pg->put($k, $v);
        }
        $status = 'PENDING';
        Http::fake([
            '*/collection/token/' => Http::response(['access_token' => 't']),
            '*/collection/v1_0/requesttopay' => Http::response(null, 202),
            '*/collection/v1_0/requesttopay/*' => function () use (&$status) {
                return Http::response(['status' => $status, 'financialTransactionId' => '555']);
            },
        ]);
        Setting::put('booking_fee', 40);
        $p = $this->provider();
        $sr = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p);

        $this->actingAs($this->customer)->get(route('customer.requests.show', $sr))->assertSee('Pay in app');
        $this->actingAs($this->customer)->post(route('customer.requests.fee', $sr), ['method' => 'mtn_momo', 'payer_phone' => '0966111222'])->assertSessionHasNoErrors();
        $fee = Payment::where('kind', 'booking_fee')->first();
        $this->assertSame('mtn_momo', $fee->gateway);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/requesttopay') && $r['amount'] === '40');

        $this->actingAs($this->customer)->getJson(route('track.payment-status', $sr->reference))->assertJsonPath('payment.status', 'pending');
        $this->assertFalse($sr->fresh()->contactUnlocked());
        $status = 'SUCCESSFUL';
        $this->actingAs($this->customer)->getJson(route('track.payment-status', $sr->reference))->assertJsonPath('payment.status', 'confirmed');
        $this->assertTrue($sr->fresh()->contactUnlocked());
        $this->assertSame('paid', $sr->fresh()->booking_fee_status);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_fee_can_be_off_waived_or_overridden_per_service(): void
    {
        $p = $this->provider();
        $sr = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p);
        $this->assertSame('none', $sr->booking_fee_status, 'no fee by default');
        $this->assertTrue($sr->contactUnlocked());

        Setting::put('booking_fee', 50);
        $this->service('battery-jump-start', ['booking_fee' => 0]);
        $this->assertSame('none', $this->assignedViaQuote(Service::where('slug', 'battery-jump-start')->first(), $p)->booking_fee_status, 'service override of 0 means no fee');
        $this->service('vehicle-towing', ['booking_fee' => 120]);
        $tow = $this->assignedViaQuote(Service::where('slug', 'vehicle-towing')->first(), $p);
        $this->assertEquals(120, $tow->booking_fee_amount);

        // Admin goodwill unlocks and fails the open payment.
        $this->actingAs($this->customer)->post(route('customer.requests.fee', $tow), ['method' => 'mtn_momo', 'payer_phone' => '0966111222', 'transaction_reference' => 'X']);
        $this->actingAs($this->admin)->post(route('admin.requests.waive-fee', $tow))->assertSessionHasNoErrors();
        $this->assertSame('waived', $tow->fresh()->booking_fee_status);
        $this->assertTrue($tow->fresh()->contactUnlocked());
        $this->assertSame('failed', Payment::where('kind', 'booking_fee')->first()->status);
        $this->actingAs($this->admin)->post(route('admin.requests.waive-fee', $tow))->assertSessionHasErrors('fee');
    }

    public function test_fee_is_refunded_when_the_provider_cancels_but_kept_when_the_customer_does(): void
    {
        Setting::put('booking_fee', 50);
        $p = $this->provider();
        $pay = function (ServiceRequest $sr) {
            $this->actingAs($this->customer)->post(route('customer.requests.fee', $sr), ['method' => 'airtel_money', 'payer_phone' => '0977111222', 'transaction_reference' => 'A1']);
            $this->actingAs($this->admin)->post(route('admin.payments.confirm', Payment::where('service_request_id', $sr->id)->where('kind', 'booking_fee')->first()));
        };

        // Provider withdraws after the customer paid: refund due.
        $one = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p);
        $pay($one);
        $this->assertSame('paid', $one->fresh()->booking_fee_status);
        $this->actingAs($p)->post(route('provider.jobs.release', $one), ['reason' => 'Truck broke down'])->assertSessionHasNoErrors();
        $this->assertSame('refund_due', $one->fresh()->booking_fee_status);
        $this->assertTrue($this->admin->fresh()->notifications->contains(fn ($n) => $n->data['title'] === 'Booking fee refund due'));
        $this->actingAs($this->admin)->get(route('admin.requests.show', $one))->assertSee('Mark as refunded');
        $this->actingAs($this->admin)->post(route('admin.requests.refund-fee', $one))->assertSessionHasNoErrors();
        $this->assertSame('refunded', $one->fresh()->booking_fee_status);
        $this->assertSame('refunded', Payment::where('service_request_id', $one->id)->first()->status);
        $this->actingAs($this->admin)->post(route('admin.requests.refund-fee', $one))->assertSessionHasErrors('fee');

        // The customer cancels after paying: the fee stays with HANDY NOW.
        $two = $this->assignedViaQuote($this->service('battery-jump-start'), $p);
        $pay($two);
        $this->actingAs($this->customer)->post(route('customer.requests.cancel', $two), ['reason' => 'Changed my mind'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $two->fresh()->booking_fee_status);

        // Cancelling before paying drops the open fee.
        $three = $this->assignedViaQuote($this->service('vehicle-towing'), $p);
        $this->actingAs($this->customer)->post(route('customer.requests.fee', $three), ['method' => 'mtn_momo', 'payer_phone' => '0966111222', 'transaction_reference' => 'Z']);
        $this->actingAs($this->customer)->post(route('customer.requests.cancel', $three), ['reason' => 'No longer needed']);
        $this->assertSame('none', $three->fresh()->booking_fee_status);
        $this->assertSame('failed', Payment::where('service_request_id', $three->id)->first()->status);
    }

    public function test_extra_costs_on_site_need_the_customers_approval_and_respect_limits(): void
    {
        $p = $this->provider();
        $sr = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p, 500);
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 50, 'reason' => 'Replace valve'])->assertSessionHasErrors('amount'); // not on site yet
        foreach (['accepted', 'en_route', 'arrived'] as $s) {
            $this->actingAs($p)->post(route('provider.jobs.status', $sr), ['status' => $s])->assertSessionHasNoErrors();
        }

        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 100, 'reason' => 'Replace burst valve'])->assertSessionHasNoErrors();
        $v = $sr->variations()->first();
        $this->assertEquals(500, $sr->fresh()->agreed_amount, 'nothing changes until the customer approves');
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 20, 'reason' => 'Another thing'])->assertSessionHasErrors('amount');
        $this->actingAs($this->customer)->get(route('customer.requests.show', $sr))->assertSee('asks to add an extra cost')->assertSee('K600.00');
        $this->actingAs($this->customer)->post(route('customer.requests.pay', $sr), ['method' => 'mtn_momo', 'payer_phone' => '0966111222'])->assertSessionHasErrors('method');

        $this->actingAs($this->customer)->post(route('customer.variations.approve', [$sr, $v]))->assertSessionHasNoErrors();
        $this->assertEquals(600, $sr->fresh()->agreed_amount);
        $this->actingAs($this->customer)->post(route('customer.variations.approve', [$sr, $v]))->assertSessionHasErrors('variation');
        $this->assertTrue($p->fresh()->notifications->contains(fn ($n) => $n->data['title'] === 'Extra cost approved'));

        // Total extras are capped at 50% of the original price (K250 here): K100 used, so K200 more is too much.
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 200, 'reason' => 'Big extra job'])->assertSessionHasErrors('amount');
        $this->assertStringContainsString('K150.00', session('errors')->first('amount'));
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 150, 'reason' => 'Last extra job'])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post(route('customer.variations.decline', [$sr, $sr->variations()->where('status', 'pending')->first()]))->assertSessionHasNoErrors();
        $this->assertEquals(600, $sr->fresh()->agreed_amount, 'a declined extra changes nothing');

        // Someone else's job, or one whose payment has started, cannot be changed.
        $this->actingAs($this->provider())->post(route('provider.jobs.variation', $sr), ['amount' => 10, 'reason' => 'Not my job'])->assertForbidden();
        $this->actingAs($this->customer)->post(route('customer.requests.pay', $sr), ['method' => 'cash'])->assertSessionHasNoErrors();
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 10, 'reason' => 'Too late now'])->assertSessionHasErrors('amount');
    }

    public function test_a_price_ceiling_also_limits_extra_costs(): void
    {
        $p = $this->provider();
        $svc = $this->service('flat-tyre-replacement', ['pricing_mode' => 'capped', 'min_price' => 100, 'max_price' => 520]);
        $sr = $this->assignedViaQuote($svc, $p, 500);
        foreach (['accepted', 'en_route', 'arrived'] as $s) {
            $this->actingAs($p)->post(route('provider.jobs.status', $sr), ['status' => $s]);
        }
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 50, 'reason' => 'Over the ceiling'])->assertSessionHasErrors('amount');
        $this->assertStringContainsString('K20.00', session('errors')->first('amount'));
        $this->actingAs($p)->post(route('provider.jobs.variation', $sr), ['amount' => 20, 'reason' => 'Right up to it'])->assertSessionHasNoErrors();
    }

    public function test_chat_hides_phone_numbers_and_emails(): void
    {
        Setting::put('booking_fee', 50);
        $p = $this->provider();
        $sr = $this->assignedViaQuote($this->service('flat-tyre-replacement'), $p);
        $this->actingAs($this->admin)->post(route('admin.requests.waive-fee', $sr));

        $res = $this->actingAs($this->customer)->postJson(route('messages.store', $sr), ['body' => 'Call me on 0977 123 456 or +260 966-111-222, mail me at a.b@gmail.com. Price is K1 500 000.'])->assertOk();
        $res->assertJsonPath('masked', true);
        $stored = $sr->messages()->first()->body;
        $this->assertStringNotContainsString('123 456', $stored);
        $this->assertStringNotContainsString('gmail', $stored);
        $this->assertStringContainsString('[number hidden]', $stored);
        $this->assertStringContainsString('K1 500 000', $stored, 'ordinary amounts are untouched');
        $this->actingAs($this->customer)->postJson(route('messages.store', $sr), ['body' => 'See you in 20 minutes'])->assertJsonPath('masked', false);
        $this->actingAs($p)->getJson(route('messages.index', $sr))->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_mobile_api_hides_contacts_and_supports_fixed_jobs_fees_and_extras(): void
    {
        Setting::put('booking_fee', 30);
        $p = $this->provider();
        $this->service('battery-jump-start', ['pricing_mode' => 'fixed', 'fixed_price' => 200]);
        $ctoken = $this->customer->createToken('app')->plainTextToken;
        $ptoken = $p->createToken('app')->plainTextToken;
        $as = function (string $token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token);
        };

        $ref = $as($ctoken)->postJson('/api/v1/requests', ['category_id' => $this->roadside->id, 'service_id' => Service::where('slug', 'battery-jump-start')->value('id'),
            'description' => 'Battery is dead, please help.', 'address' => 'Lusaka', 'latitude' => -15.40, 'longitude' => 28.30, 'priority' => 'emergency'])
            ->assertCreated()->assertJsonPath('data.pricing.fixed_price', '200.00')->json('data.reference');

        $job = $as($ptoken)->getJson('/api/v1/provider/jobs/available')->assertOk()->json('data.0');
        $this->assertSame($ref, $job['reference']);
        $this->assertNull($job['contact_phone'], 'providers never see the customer phone before the job is unlocked');
        $as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/quote", ['amount' => 100, 'eta_minutes' => 10])->assertStatus(422);
        $as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/accept")->assertOk()->assertJsonPath('data.pricing.booking_fee.status', 'pending')->assertJsonPath('data.contact_unlocked', false);

        $detail = $as($ctoken)->getJson("/api/v1/requests/{$ref}")->assertOk()->json('data');
        $this->assertNull($detail['provider']['phone']);
        $as($ctoken)->postJson("/api/v1/requests/{$ref}/fee", ['method' => 'cash'])->assertStatus(422);
        $as($ctoken)->postJson("/api/v1/requests/{$ref}/fee", ['method' => 'mtn_momo', 'payer_phone' => '0966111222', 'transaction_reference' => 'M1'])->assertCreated();
        $this->flushHeaders(); // the web admin is not the app user
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', Payment::where('kind', 'booking_fee')->first()));
        $this->assertSame('paid', ServiceRequest::first()->booking_fee_status);
        $this->app['auth']->forgetGuards();
        $this->assertNotNull($as($ctoken)->getJson("/api/v1/requests/{$ref}")->json('data.provider.phone'));
        $this->assertSame($this->customer->phone, $as($ptoken)->getJson('/api/v1/provider/jobs')->json('data.0.contact_phone'));

        foreach (['en_route', 'arrived'] as $s) { // a fixed-price job is already accepted
            $as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/status", ['status' => $s])->assertOk();
        }
        $as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/variation", ['amount' => 50, 'reason' => 'Replace terminal'])->assertCreated();
        $vid = $as($ctoken)->getJson("/api/v1/requests/{$ref}")->json('data.variations.0.id');
        $as($ctoken)->postJson("/api/v1/requests/{$ref}/variations/{$vid}", ['approve' => true])->assertOk();
        $this->assertEquals(250, ServiceRequest::first()->agreed_amount);
    }

    public function test_admin_sets_pricing_rules_per_service(): void
    {
        $this->actingAs($this->admin);
        $svc = Service::where('slug', 'vehicle-towing')->first();
        $base = ['name' => $svc->name, 'min_price' => 350, 'callout_fee' => 0, 'is_active' => 1];

        $this->put(route('admin.services.update', $svc), $base + ['pricing_mode' => 'fixed'])->assertSessionHasErrors('fixed_price');
        $this->put(route('admin.services.update', $svc), $base + ['pricing_mode' => 'capped'])->assertSessionHasErrors('max_price');
        $this->put(route('admin.services.update', $svc), $base + ['pricing_mode' => 'fixed', 'fixed_price' => 600, 'price_per_km' => 12, 'included_km' => 8, 'needs_destination' => 1, 'booking_fee' => ''])->assertSessionHasNoErrors();
        $svc->refresh();
        $this->assertSame('fixed', $svc->pricing_mode);
        $this->assertEquals(600, $svc->fixed_price);
        $this->assertTrue($svc->needs_destination);
        $this->assertNull($svc->booking_fee);
        $this->assertSame('Fixed price K600 + K12/km after 8 km', $svc->priceRange());

        // A destination only makes sense for fixed prices.
        $this->put(route('admin.services.update', $svc), $base + ['pricing_mode' => 'capped', 'max_price' => 900, 'needs_destination' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($svc->fresh()->needs_destination);
        $this->assertSame('Starting at K350', $svc->fresh()->priceRange());

        $cat = Category::where('slug', 'roadside-assistance')->first();
        $this->get(route('admin.categories.edit', $cat))->assertOk()->assertSee('Fixed price – no quotes')->assertSee('Ceiling K900');
        $this->put(route('admin.settings.update'), ['commission_rate' => 10, 'match_radius_km' => 30, 'min_payout' => 100, 'booking_fee' => 75, 'booking_fee_instructions' => 'Pay MoMo', 'variation_limit_percent' => 40,
            'support_phone' => '+260 972 015 689', 'support_whatsapp' => '260972015689', 'support_email' => 'a@b.zm'])->assertSessionHasNoErrors();
        $this->assertSame('75', Setting::get('booking_fee'));
        $this->get(route('admin.settings'))->assertOk()->assertSee('Booking fee (ZMW)');
    }

    public function test_public_pages_show_fixed_prices_the_price_promise_and_the_fee(): void
    {
        Setting::put('booking_fee', 50);
        $this->service('battery-jump-start', ['pricing_mode' => 'fixed', 'fixed_price' => 250]);
        $this->service('flat-tyre-replacement', ['pricing_mode' => 'capped', 'min_price' => 150, 'max_price' => 400]);

        $this->get('/services/roadside-assistance/battery-jump-start')->assertOk()->assertSee('Fixed price K250')->assertSee('Booking fee');
        $this->get('/services/roadside-assistance/flat-tyre-replacement')->assertOk()->assertSee('Price promise')->assertSee('Never more than K400')->assertSee('Starting at K150');
        $this->get('/services/roadside-assistance')->assertOk()->assertSee('Fixed price K250');
        $this->get('/request?category=roadside-assistance&service=battery-jump-start')->assertOk()->assertSee('Fixed price – no quotes to compare')->assertSee('booking fee of');
        $this->get('/emergency?category=roadside-assistance')->assertOk();
    }
}
