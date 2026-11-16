<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Smoke test: every page renders for the right role with demo data loaded. */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, DemoSeeder::class]);
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/services', '/services?q=tyre', '/services/roadside-assistance', '/services/roadside-assistance/vehicle-towing',
            '/professionals', '/professionals?online=1&category=plumbing-services', '/how-it-works', '/faqs', '/terms', '/privacy', '/contact',
            '/request', '/request?category=plumbing-services&service=toilet-repairs', '/emergency', '/track', '/login', '/register', '/become-a-provider',
            '/api-public/providers-nearby?lat=-15.41&lng=28.3'] as $url) {
            $this->get($url)->assertOk();
        }
        $provider = User::where('role', 'provider')->whereHas('providerProfile', fn ($q) => $q->where('verification_status', 'approved'))->first();
        $this->get('/professionals/'.$provider->id)->assertOk()->assertSee($provider->name);
        $this->get('/nope')->assertNotFound();
    }

    public function test_customer_pages_render(): void
    {
        $c = User::where('email', 'customer@demo.handynow.co.zm')->first();
        foreach (['/account', '/account/requests', '/account/requests?filter=active', '/account/payments', '/account/complaints', '/account/complaints/new', '/account/profile', '/notifications'] as $url) {
            $this->actingAs($c)->get($url)->assertOk();
        }
        foreach ($c->customerRequests as $sr) {
            $this->actingAs($c)->get(route('customer.requests.show', $sr))->assertOk()->assertSee($sr->reference);
        }
    }

    public function test_provider_pages_render(): void
    {
        $p = User::where('email', 'provider1@demo.handynow.co.zm')->first();
        foreach (['/provider', '/provider/jobs', '/provider/jobs?tab=active', '/provider/jobs?tab=completed', '/provider/jobs?tab=quoted', '/provider/earnings', '/provider/reviews', '/provider/profile'] as $url) {
            $this->actingAs($p)->get($url)->assertOk();
        }
        $job = ServiceRequest::whereHas('matches', fn ($m) => $m->where('provider_id', $p->id))->get();
        $this->assertNotEmpty($job);
        foreach ($job as $sr) {
            $this->actingAs($p)->get(route('provider.jobs.show', $sr))->assertOk();
        }
        $pending = User::where('email', 'pending@demo.handynow.co.zm')->first();
        $this->actingAs($pending)->get('/provider')->assertOk()->assertSee('Verification in progress');
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['/admin', '/admin/reports', '/admin/reports?from=2020-01-01&to=2030-01-01', '/admin/requests', '/admin/requests?status=active&priority=emergency',
            '/admin/payments', '/admin/providers', '/admin/providers?status=pending', '/admin/users', '/admin/users?role=provider', '/admin/users/create',
            '/admin/complaints', '/admin/categories', '/admin/categories/roadside-assistance/edit', '/admin/settings', '/admin/sms-log'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        foreach (ServiceRequest::all() as $sr) {
            $this->actingAs($admin)->get(route('admin.requests.show', $sr))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.complaints.show', Complaint::first()))->assertOk();
        $this->actingAs($admin)->get(route('admin.providers.show', User::where('role', 'provider')->first()))->assertOk();
        $this->actingAs($admin)->get('/admin/reports/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_prices_show_starting_at_and_admin_can_change_password(): void
    {
        $this->get('/services/roadside-assistance')->assertOk()->assertSee('Starting at K800')->assertDontSee('K3,500');

        $admin = User::factory()->admin()->create(['password' => 'Password123']);
        $this->actingAs($admin)->put('/admin/settings/password', ['current_password' => 'wrong', 'password' => 'Handy@2026', 'password_confirmation' => 'Handy@2026'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->put('/admin/settings/password', ['current_password' => 'Password123', 'password' => 'Handy@2026', 'password_confirmation' => 'Handy@2026'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Handy@2026', $admin->fresh()->password));
    }

    public function test_admin_sees_database_is_up_to_date_and_can_apply_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings')->assertSee('Database is up to date');
        $this->actingAs($admin)->post('/admin/settings/migrate')->assertSessionHasNoErrors();
        $this->actingAs(User::where('role', 'customer')->first())->post('/admin/settings/migrate')->assertForbidden();
    }

    public function test_admin_can_purge_demo_data_only(): void
    {
        $admin = User::factory()->admin()->create();
        $live = User::factory()->create();
        $this->actingAs($admin)->post('/admin/settings/purge-demo', ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/settings/purge-demo', ['confirm' => 'DELETE DEMO DATA'])->assertSessionHasNoErrors();
        $this->assertSame(0, User::where('is_demo', true)->count());
        $this->assertSame(0, ServiceRequest::where('is_demo', true)->count());
        $this->assertNotNull($live->fresh());
        $this->assertNotNull($admin->fresh());
    }
}
