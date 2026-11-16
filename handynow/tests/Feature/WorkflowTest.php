<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Category $roadside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->roadside = Category::where('slug', 'roadside-assistance')->first();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function provider(array $profile = [], ?Category $cat = null): User
    {
        $u = User::factory()->provider()->create();
        $p = ProviderProfile::create(array_merge([
            'user_id' => $u->id, 'verification_status' => 'approved', 'is_online' => true,
            'base_latitude' => -15.4067, 'base_longitude' => 28.3340, 'service_radius_km' => 30, 'operating_areas' => ['Kabulonga'],
        ], $profile));
        $p->categories()->sync([($cat ?? $this->roadside)->id]);

        return $u;
    }

    private function submitGuestRequest(array $over = [])
    {
        return $this->post('/request', array_merge([
            'category_id' => $this->roadside->id,
            'service_id' => $this->roadside->services()->where('slug', 'battery-jump-start')->value('id'),
            'contact_name' => 'Mutinta Hamoonga',
            'contact_phone' => '0966 123 456',
            'description' => 'Car battery is completely dead outside the mall.',
            'address' => 'Arcades Shopping Mall, Great East Road, Lusaka',
            'latitude' => -15.4000, 'longitude' => 28.3200,
            'priority' => 'emergency',
            'photos' => [UploadedFile::fake()->image('car.jpg')],
        ], $over));
    }

    public function test_full_emergency_flow_from_guest_request_to_paid_and_rated_job(): void
    {
        $near = $this->provider();
        $far = $this->provider(['base_latitude' => -12.8, 'base_longitude' => 28.2]);   // Ndola – out of range
        $offline = $this->provider(['is_online' => false]);
        $admin = User::factory()->admin()->create();

        $res = $this->submitGuestRequest();
        $sr = ServiceRequest::first();
        $res->assertRedirect($sr->trackingUrl());

        // Matching: only the nearby, online, approved provider is alerted.
        $this->assertSame(ServiceRequest::STATUS_SEARCHING, $sr->status);
        $this->assertSame([$near->id], $sr->matches()->pluck('provider_id')->all());
        $this->assertCount(1, $near->notifications);
        $this->assertCount(0, $far->notifications);
        $this->assertCount(0, $offline->notifications);
        $this->assertCount(1, $admin->notifications, 'admins are alerted about emergencies');
        $this->assertTrue(SmsLog::where('to', '260966123456')->exists(), 'customer received an SMS with the tracking link');
        Storage::disk('public')->assertExists($sr->photos[0]);

        // Provider sends a quotation.
        $this->actingAs($near)->get(route('provider.jobs.show', $sr))->assertOk()->assertSee('Send a quotation');
        $this->actingAs($near)->post(route('provider.jobs.quote', $sr), ['amount' => 200, 'callout_fee' => 100, 'eta_minutes' => 20])->assertSessionHasNoErrors();
        $quote = $sr->quotations()->first();
        $this->assertEquals(300, $quote->total());

        // A stranger cannot see or act on the guest request; the tracking link holder can.
        auth()->logout();
        $this->flushSession();
        $this->get(route('track.show', $sr->reference))->assertForbidden();
        $this->post(route('track.quotes.accept', [$sr->reference, $quote]))->assertForbidden();
        $this->get($sr->trackingUrl())->assertOk()->assertSee('K300.00');
        $this->post(route('track.quotes.accept', [$sr->reference, $quote]))->assertRedirect();
        $sr->refresh();
        $this->assertSame(ServiceRequest::STATUS_ASSIGNED, $sr->status);
        $this->assertSame($near->id, $sr->provider_id);
        $this->assertEquals(300, $sr->agreed_amount);

        // Provider moves through the job; invalid jumps are refused.
        $this->actingAs($near)->post(route('provider.jobs.status', $sr), ['status' => 'completed'])->assertSessionHasErrors('status');
        foreach (['accepted', 'en_route', 'arrived', 'in_progress', 'completed'] as $s) {
            $this->actingAs($near)->post(route('provider.jobs.status', $sr), ['status' => $s])->assertSessionHasNoErrors();
        }
        $this->actingAs($near)->post(route('provider.location'), ['lat' => -15.401, 'lng' => 28.321])->assertOk();
        $sr->refresh();
        $this->assertSame('completed', $sr->status);
        $this->assertSame(1, $near->providerProfile->fresh()->jobs_completed);

        // Customer records mobile money payment; provider confirms; commission is calculated.
        Setting::put('commission_rate', 12.5);
        auth()->logout();
        $this->post(route('track.pay', $sr->reference), ['method' => 'mtn_momo', 'payer_phone' => '0966123456', 'transaction_reference' => 'MP123'])->assertSessionHasNoErrors();
        $payment = Payment::first();
        $this->assertSame('pending', $payment->status);
        $this->actingAs($near)->post(route('provider.payments.confirm', $payment))->assertSessionHasNoErrors();
        $payment->refresh();
        $sr->refresh();
        $this->assertSame('confirmed', $payment->status);
        $this->assertEquals(37.5, $payment->commission_amount);
        $this->assertEquals(262.5, $payment->provider_amount);
        $this->assertSame('paid', $sr->payment_status);
        $this->assertEquals(37.5, $sr->commission_amount);

        // Rating updates the provider's average.
        auth()->logout();
        $this->post(route('track.review', $sr->reference), ['rating' => 4, 'comment' => 'Quick and friendly'])->assertSessionHasNoErrors();
        $this->assertEquals(4.0, $near->providerProfile->fresh()->rating_avg);
        $this->post(route('track.review', $sr->reference), ['rating' => 5])->assertSessionHasErrors('rating');

        $this->assertGreaterThanOrEqual(8, $sr->statusLogs()->count());
    }

    public function test_request_without_gps_matches_by_operating_area_and_unmatched_stays_pending(): void
    {
        $p = $this->provider(['base_latitude' => null, 'base_longitude' => null, 'operating_areas' => ['Chelstone']]);
        $this->submitGuestRequest(['latitude' => null, 'longitude' => null, 'address' => 'House 5, Chelstone, Lusaka']);
        $this->assertSame([$p->id], ServiceRequest::latest('id')->first()->matches()->pluck('provider_id')->all());

        $this->submitGuestRequest(['latitude' => null, 'longitude' => null, 'address' => 'Livingstone town centre']);
        $this->assertSame('pending', ServiceRequest::latest('id')->first()->status);
    }

    public function test_provider_coming_online_receives_open_requests(): void
    {
        $p = $this->provider(['is_online' => false]);
        $this->submitGuestRequest();
        $sr = ServiceRequest::first();
        $this->assertSame('pending', $sr->status);

        $this->actingAs($p)->post(route('provider.availability'), ['online' => 1])->assertSessionHasNoErrors();
        $this->assertSame('searching', $sr->fresh()->status);
        $this->assertTrue($sr->matches()->where('provider_id', $p->id)->exists());
    }

    public function test_registered_customer_flow_cancel_and_complaint(): void
    {
        $this->provider();
        $customer = User::factory()->create();
        $this->actingAs($customer)->submitGuestRequest(['contact_name' => '', 'contact_phone' => ''])->assertRedirect();
        $sr = ServiceRequest::first();
        $this->assertSame($customer->id, $sr->customer_id);
        $this->assertSame($customer->phone, $sr->contact_phone);

        $this->actingAs($customer)->get(route('customer.requests.show', $sr))->assertOk();
        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()->assertSee($sr->reference);
        $this->actingAs($customer)->post(route('customer.requests.cancel', $sr), ['reason' => 'Sorted it myself'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $sr->fresh()->status);

        $this->actingAs($customer)->post(route('customer.complaints.store'), ['service_request_id' => $sr->id, 'subject' => 'Test', 'description' => 'Something happened during the job.'])->assertRedirect();
        $this->assertDatabaseHas('complaints', ['user_id' => $customer->id, 'service_request_id' => $sr->id]);

        // Another customer cannot see this request.
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('customer.requests.show', $sr))->assertNotFound();
    }

    public function test_client_account_needs_name_phone_nrc_and_a_pinned_address(): void
    {
        $base = ['name' => 'Mutale K', 'phone' => '0966000111', 'nrc_number' => '654321/10/1', 'address' => 'Plot 4, Roma', 'latitude' => -15.38, 'longitude' => 28.32,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => 1];
        $this->post('/register', array_diff_key($base, ['latitude' => 1, 'longitude' => 1]))->assertSessionHasErrors('latitude');
        $this->post('/register', array_merge($base, ['nrc_number' => '1234']))->assertSessionHasErrors('nrc_number');
        $this->post('/register', array_diff_key($base, ['nrc_number' => 1]))->assertSessionHasErrors('nrc_number');
        $this->post('/register', $base)->assertSessionHasNoErrors()->assertRedirect(route('customer.dashboard'));
        $u = User::where('phone', '260966000111')->first();
        $this->assertNull($u->email);
        $this->assertSame('654321/10/1', $u->nrc_number);
        $this->assertTrue($u->hasPinnedAddress());

        // Same NRC cannot open a second account; email stays optional but unique when given.
        auth()->logout();
        $this->post('/register', array_merge($base, ['phone' => '0966000222']))->assertSessionHasErrors('nrc_number');

        // The saved address is offered (not forced) on the request form.
        $this->actingAs($u)->get('/emergency')->assertOk()->assertSee('Use my saved address');
        $this->actingAs($u)->put(route('customer.profile.update'), array_merge($base, ['name' => 'Mutale Kabwe']))->assertSessionHasNoErrors();
        $this->assertSame('Mutale Kabwe', $u->fresh()->name);
    }

    public function test_role_based_access_control(): void
    {
        $customer = User::factory()->create();
        $provider = $this->provider();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/provider')->assertForbidden();
        $this->actingAs($provider)->get('/admin')->assertForbidden();
        $this->actingAs($provider)->get('/account')->assertForbidden();
        auth()->logout();
        $this->get('/admin')->assertRedirect('/login');

        // A provider who was not matched cannot open or quote on a job.
        $this->submitGuestRequest();
        $sr = ServiceRequest::first();
        $outsider = $this->provider([], Category::where('slug', 'plumbing-services')->first());
        $this->actingAs($outsider)->get(route('provider.jobs.show', $sr))->assertNotFound();
        $this->actingAs($outsider)->post(route('provider.jobs.quote', $sr), ['amount' => 10, 'eta_minutes' => 10])->assertNotFound();
    }

    public function test_registration_login_and_suspension(): void
    {
        $this->post('/register', [
            'name' => 'Chipo Banda', 'email' => 'Chipo@Example.com', 'phone' => '0977 000 111', 'password' => 'secret123',
            'password_confirmation' => 'secret123', 'terms' => 1, 'nrc_number' => '123456781', 'address' => 'Plot 9, Kabulonga', 'latitude' => -15.40, 'longitude' => 28.33,
        ])->assertRedirect(route('customer.dashboard'));
        $u = User::where('email', 'chipo@example.com')->first();
        $this->assertSame('260977000111', $u->phone);
        $this->assertNotSame('secret123', $u->password);

        auth()->logout();
        $this->post('/login', ['login' => '0977000111', 'password' => 'secret123'])->assertRedirect(route('customer.dashboard'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.users.status', $u))->assertRedirect();
        $this->assertSame('suspended', $u->fresh()->status);
        $this->actingAs($u->fresh())->get('/account')->assertRedirect('/login');
        auth()->logout();
        $this->post('/login', ['login' => 'chipo@example.com', 'password' => 'secret123'])->assertSessionHasErrors('login');
    }

    public function test_provider_registration_and_admin_verification(): void
    {
        $admin = User::factory()->admin()->create();
        $jpg = fn ($n) => UploadedFile::fake()->image($n.'.jpg');
        $this->post('/become-a-provider', [
            'name' => 'Peter Mwanza', 'phone' => '0955111222', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'provider_type' => 'individual', 'nrc_number' => '123456/78/1', 'nrc_front' => $jpg('f'), 'nrc_back' => $jpg('b'), 'selfie' => $jpg('s'),
            'categories' => [$this->roadside->id], 'licences' => [$this->roadside->id => ['file' => UploadedFile::fake()->create('dl.pdf', 100, 'application/pdf'), 'number' => 'DL-1', 'expiry' => now()->addYear()->format('Y-m-d')]],
            'city' => 'Lusaka', 'operating_areas' => 'Kabulonga, Woodlands', 'service_radius_km' => 20,
            'payout_method' => 'mtn_momo', 'payout_phone' => '0965111222', 'payout_account_name' => 'Peter Mwanza', 'terms' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('provider.dashboard'));
        $u = User::where('phone', '260955111222')->first();
        $profile = $u->providerProfile;
        $this->assertNull($u->email);
        $this->assertSame('123456/78/1', $u->nrc_number);
        $this->assertSame('pending', $profile->verification_status);
        $this->assertSame(['Kabulonga', 'Woodlands'], $profile->operating_areas);
        $this->assertSame(['nrc_front', 'nrc_back', 'selfie', 'licence'], $profile->documents()->pluck('type')->all());
        foreach ($profile->documents as $d) {
            Storage::disk('local')->assertExists($d->path);
        }
        $this->assertSame('MTN Mobile Money – 260965111222', $profile->payoutLabel());
        $this->assertCount(1, $admin->notifications);

        // Cannot go online until verified.
        $this->actingAs($u)->post(route('provider.availability'), ['online' => 1])->assertSessionHasErrors('availability');

        // Admin cannot approve until every document is checked.
        $this->actingAs($admin)->post(route('admin.providers.verify', $u), ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->actingAs($admin)->get(route('admin.documents.view', $profile->documents->first()))->assertOk();
        $this->actingAs($admin)->get(route('admin.providers.show', $u))->assertOk()->assertSee('Approval checklist');

        // One document is rejected with a reason: the provider is told and can replace it.
        $selfie = $profile->documents->firstWhere('type', 'selfie');
        $this->actingAs($admin)->post(route('admin.documents.review', $selfie), ['decision' => 'rejected'])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('admin.documents.review', $selfie), ['decision' => 'rejected', 'note' => 'Photo is blurry'])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->notifications->contains(fn ($n) => str_contains($n->data['body'], 'Photo is blurry')));
        $this->actingAs($u->fresh())->post(route('provider.documents.store'), ['type' => 'selfie', 'file' => $jpg('s2')])->assertSessionHasNoErrors();
        $this->assertSame('pending', $selfie->fresh()->status);
        $this->assertNull($selfie->fresh()->review_note);
        $this->assertSame(1, $profile->documents()->where('type', 'selfie')->count(), 'replacement does not add a second selfie');

        foreach ($profile->documents()->get() as $d) {
            $this->actingAs($admin)->post(route('admin.documents.review', $d), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->post(route('admin.providers.verify', $u), ['decision' => 'rejected'])->assertSessionHasErrors('notes');
        $this->actingAs($admin)->post(route('admin.providers.verify', $u), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $profile->fresh()->verification_status);
        $this->actingAs($u->fresh())->post(route('provider.availability'), ['online' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($profile->fresh()->is_online);

        // Documents are private: another provider cannot open them.
        $other = $this->provider();
        $this->actingAs($other)->get(route('provider.documents.view', $selfie))->assertNotFound();
        $this->actingAs($u->fresh())->get(route('provider.documents.view', $selfie))->assertOk();
    }

    public function test_provider_registration_is_refused_without_required_documents_or_licence(): void
    {
        $jpg = fn ($n) => UploadedFile::fake()->image($n.'.jpg');
        $base = [
            'name' => 'No Docs', 'phone' => '0955333444', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'provider_type' => 'individual',
            'nrc_number' => '123456/78/1', 'nrc_front' => $jpg('f'), 'nrc_back' => $jpg('b'), 'selfie' => $jpg('s'), 'categories' => [$this->roadside->id],
            'city' => 'Lusaka', 'operating_areas' => 'Kabulonga', 'service_radius_km' => 20,
            'payout_method' => 'bank_transfer', 'bank_name' => 'Zanaco', 'bank_account_number' => '0123456789', 'payout_account_name' => 'No Docs', 'terms' => 1,
        ];
        // Roadside needs a licence.
        $this->post('/become-a-provider', $base)->assertSessionHasErrors("licences.{$this->roadside->id}.file");
        // Missing selfie.
        $this->post('/become-a-provider', array_diff_key($base, ['selfie' => 1]))->assertSessionHasErrors('selfie');
        // Mobile number on the wrong network.
        $this->post('/become-a-provider', array_merge($base, ['payout_method' => 'airtel_money', 'payout_phone' => '0965111222']))->assertSessionHasErrors('payout_phone');
        // Bad NRC.
        $this->post('/become-a-provider', array_merge($base, ['nrc_number' => '12345']))->assertSessionHasErrors('nrc_number');
        $this->assertSame(0, User::where('role', 'provider')->count());

        // Services without a licence rule need none.
        $carpentry = Category::where('slug', 'carpentry')->first();
        $this->post('/become-a-provider', array_merge($base, ['categories' => [$carpentry->id]]))->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('role', 'provider')->count());
    }

    public function test_provider_changing_payout_details_alerts_admins_and_expired_licence_blocks_matching(): void
    {
        $admin = User::factory()->admin()->create();
        $p = $this->provider(['payout_method' => 'mtn_momo', 'payout_phone' => '260965111222', 'payout_account_name' => 'Old Name']);
        $this->actingAs($p)->put(route('provider.payout.update'), ['payout_method' => 'bank_transfer', 'bank_name' => 'Absa Bank', 'bank_account_number' => '99887766', 'payout_account_name' => 'New Name'])->assertSessionHasNoErrors();
        $this->assertSame('Absa Bank •••• 7766', explode(' – ', $p->providerProfile->fresh()->payoutLabel())[1]);
        $this->assertTrue($admin->fresh()->notifications->contains(fn ($n) => str_contains($n->data['title'], 'payout')));

        // An approved licence that expires takes the provider out of matching.
        $profile = $p->providerProfile;
        $profile->documents()->create(['type' => 'licence', 'category_id' => $this->roadside->id, 'path' => 'x', 'status' => 'approved', 'expires_at' => now()->addDays(10)]);
        $this->assertSame(1, ProviderProfile::available()->count());
        $profile->documents()->update(['expires_at' => now()->subDay()]);
        $this->assertSame(0, ProviderProfile::available()->count());
    }

    public function test_admin_can_assign_provider_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $p = $this->provider(['is_online' => false]);
        $this->submitGuestRequest();
        $sr = ServiceRequest::first();
        $this->actingAs($admin)->post(route('admin.requests.assign', $sr), ['provider_id' => $p->id])->assertSessionHasNoErrors();
        $this->assertSame('provider_assigned', $sr->fresh()->status);
        $this->assertSame($p->id, $sr->fresh()->provider_id);
    }
}
