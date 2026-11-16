<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Ledger;
use App\Services\RequestWorkflow;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    private User $admin;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->cat = Category::first();
        $this->admin = User::factory()->admin()->create();
        $this->provider = $this->makeProvider();
    }

    private function makeProvider(array $profile = ['payout_method' => 'mtn_momo', 'payout_phone' => '260965111222', 'payout_account_name' => 'Pat Provider']): User
    {
        $u = User::factory()->provider()->create();
        ProviderProfile::create(['user_id' => $u->id, 'verification_status' => 'approved'] + $profile)->categories()->sync([$this->cat->id]);

        return $u;
    }

    /** A completed job whose payment is confirmed. $gateway 'manual' = customer paid the provider; 'mtn_momo' = customer paid through the app. */
    private function paidJob(float $amount, string $gateway = 'manual', ?User $provider = null): Payment
    {
        $provider ??= $this->provider;
        $sr = ServiceRequest::create(['category_id' => $this->cat->id, 'provider_id' => $provider->id, 'contact_name' => 'C', 'contact_phone' => '0966111222',
            'description' => 'Test job description', 'address' => 'Lusaka', 'status' => 'completed', 'agreed_amount' => $amount]);
        $payment = Payment::create(['service_request_id' => $sr->id, 'provider_id' => $provider->id, 'amount' => $amount, 'method' => 'mtn_momo', 'gateway' => $gateway, 'status' => 'pending']);
        app(RequestWorkflow::class)->confirmPayment($payment, $this->admin);

        return $payment->fresh();
    }

    public function test_payments_are_booked_in_the_right_direction_exactly_once(): void
    {
        $ledger = app(Ledger::class);
        $direct = $this->paidJob(1000, 'manual');   // customer paid the provider -> provider owes 10% commission
        $this->assertEquals(-100, $ledger->balance($this->provider));
        $this->assertSame('commission_due', LedgerEntry::first()->type);

        $app = $this->paidJob(500, 'mtn_momo');     // customer paid HANDY NOW -> we owe the provider 450
        $this->assertEquals(350, $ledger->balance($this->provider));
        $this->assertSame('earning', LedgerEntry::latest('id')->first()->type);

        $ledger->recordPayment($direct);
        $ledger->recordPayment($app);
        app(RequestWorkflow::class)->confirmPayment($app, $this->admin);
        $this->assertSame(2, LedgerEntry::count(), 'never booked twice');
        $this->assertEquals(350, $ledger->balance($this->provider));
    }

    public function test_payout_lifecycle_cannot_overpay_or_double_pay(): void
    {
        $this->paidJob(500, 'mtn_momo'); // 450 owed
        $this->actingAs($this->admin);

        $this->post(route('admin.payouts.create', $this->provider), ['amount' => 460])->assertSessionHasErrors('amount');
        $this->post(route('admin.payouts.create', $this->provider), ['amount' => 300])->assertSessionHasNoErrors();
        $payout = Payout::first();
        $this->assertSame('pending', $payout->status);
        $this->assertSame('MTN Mobile Money – 260965111222', $payout->destination);
        $this->assertEquals(150, app(Ledger::class)->balance($this->provider), 'reserved straight away');
        $this->post(route('admin.payouts.create', $this->provider), ['amount' => 200])->assertSessionHasErrors('amount');

        $this->post(route('admin.payouts.paid', $payout), [])->assertSessionHasErrors('transaction_reference');
        $this->post(route('admin.payouts.paid', $payout), ['transaction_reference' => 'MP260101.1234'])->assertSessionHasNoErrors();
        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame($this->admin->id, $payout->paid_by);
        $this->assertTrue(SmsLog::where('to', $this->provider->phone)->where('message', 'like', '%K300.00%')->exists());
        $this->assertTrue($this->provider->notifications->contains(fn ($n) => $n->data['title'] === 'Payout sent'));
        $this->post(route('admin.payouts.paid', $payout), ['transaction_reference' => 'again'])->assertSessionHasErrors('payout');
        $this->post(route('admin.payouts.cancel', $payout), ['reason' => 'oops'])->assertSessionHasErrors('payout');

        // A cancelled payout goes back on the balance.
        $this->post(route('admin.payouts.create', $this->provider), ['amount' => 150])->assertSessionHasNoErrors();
        $second = Payout::latest('id')->first();
        $this->assertEquals(0, app(Ledger::class)->balance($this->provider));
        $this->post(route('admin.payouts.cancel', $second), ['reason' => 'Wrong number'])->assertSessionHasNoErrors();
        $this->assertEquals(150, app(Ledger::class)->balance($this->provider));
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_commission_owed_by_a_provider_can_be_collected_but_not_over_collected(): void
    {
        $this->paidJob(1000, 'manual'); // owes 100
        $this->actingAs($this->admin);
        $this->post(route('admin.payouts.create', $this->provider), ['amount' => 10])->assertSessionHasErrors('amount');
        $this->post(route('admin.payouts.commission', $this->provider), ['amount' => 150])->assertSessionHasErrors('amount');
        $this->post(route('admin.payouts.commission', $this->provider), ['amount' => 60, 'method' => 'Cash', 'reference' => 'R1'])->assertSessionHasNoErrors();
        $this->assertEquals(-40, app(Ledger::class)->balance($this->provider));
        $this->post(route('admin.payouts.commission', $this->provider), ['amount' => 40])->assertSessionHasNoErrors();
        $this->assertEquals(0, app(Ledger::class)->balance($this->provider));
        $this->post(route('admin.payouts.commission', $this->provider), ['amount' => 1])->assertSessionHasErrors('amount');
    }

    public function test_direct_jobs_offset_in_app_earnings_in_one_balance(): void
    {
        $this->paidJob(1000, 'manual');  // -100
        $this->paidJob(1000, 'mtn_momo'); // +900
        $this->assertEquals(800, app(Ledger::class)->balance($this->provider));
        $this->actingAs($this->admin)->get(route('admin.payouts.provider', $this->provider))->assertOk()->assertSee('K800.00')->assertSee('HANDY NOW owes');
    }

    public function test_adjustments_and_providers_without_payment_details(): void
    {
        $bare = $this->makeProvider([]);
        $this->actingAs($this->admin);
        $this->post(route('admin.payouts.adjust', $bare), ['amount' => 0, 'reason' => 'x'])->assertSessionHasErrors('amount');
        $this->post(route('admin.payouts.adjust', $bare), ['amount' => 200, 'reason' => 'Dispute refund in provider favour'])->assertSessionHasNoErrors();
        $this->assertEquals(200, app(Ledger::class)->balance($bare));
        $this->post(route('admin.payouts.create', $bare), ['amount' => 100])->assertSessionHasErrors('amount'); // no payout method yet
        $this->post(route('admin.payouts.adjust', $bare), ['amount' => -50, 'reason' => 'Correction'])->assertSessionHasNoErrors();
        $this->assertEquals(150, app(Ledger::class)->balance($bare));
    }

    public function test_pages_dashboard_alert_provider_view_and_permissions(): void
    {
        $this->paidJob(1000, 'mtn_momo'); // 900 owed, over the K100 threshold
        $this->actingAs($this->admin)->post(route('admin.payouts.create', $this->provider), ['amount' => 500]);

        $this->actingAs($this->admin)->get(route('admin.payouts.index'))->assertOk()->assertSee('Payouts to send')->assertSee($this->provider->name);
        $this->get(route('admin.payouts.index', ['show' => 'due']))->assertOk();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('due to 1 provider');
        $this->get(route('admin.payouts.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->provider)->get(route('provider.earnings'))->assertOk()->assertSee('Your balance with HANDY NOW')->assertSee('K400.00')->assertSee('Being sent');
        $other = $this->makeProvider();
        $this->actingAs($other)->get(route('provider.earnings'))->assertOk()->assertDontSee('Being sent');

        foreach ([$this->provider, User::factory()->create()] as $nonAdmin) {
            $this->actingAs($nonAdmin)->get(route('admin.payouts.index'))->assertForbidden();
            $this->actingAs($nonAdmin)->post(route('admin.payouts.create', $this->provider), ['amount' => 1])->assertForbidden();
            $this->actingAs($nonAdmin)->post(route('admin.payouts.paid', Payout::first()), ['transaction_reference' => 'x'])->assertForbidden();
        }
    }
}
