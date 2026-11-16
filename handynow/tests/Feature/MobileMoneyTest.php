<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\GatewaySettings;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileMoneyTest extends TestCase
{
    use RefreshDatabase;

    private ServiceRequest $sr;

    private User $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $cat = Category::first();
        $this->provider = User::factory()->provider()->create();
        ProviderProfile::create(['user_id' => $this->provider->id, 'verification_status' => 'approved'])->categories()->sync([$cat->id]);
        $this->sr = ServiceRequest::create([
            'category_id' => $cat->id, 'provider_id' => $this->provider->id, 'contact_name' => 'Guest', 'contact_phone' => '0966111222',
            'description' => 'Test job description', 'address' => 'Lusaka', 'status' => 'completed', 'agreed_amount' => 500,
        ]);
    }

    private function enableMtn(): void
    {
        $pg = app(GatewaySettings::class);
        foreach (['mtn_enabled' => '1', 'mtn_environment' => 'sandbox', 'mtn_subscription_key' => 'sub-key', 'mtn_api_user' => 'user-uuid', 'mtn_api_key' => 'api-key'] as $k => $v) {
            $pg->put($k, $v);
        }
    }

    private function enableAirtel(): void
    {
        $pg = app(GatewaySettings::class);
        foreach (['airtel_enabled' => '1', 'airtel_environment' => 'sandbox', 'airtel_client_id' => 'cid', 'airtel_client_secret' => 'secret'] as $k => $v) {
            $pg->put($k, $v);
        }
    }

    private function pay(string $method, string $phone)
    {
        return $this->get($this->sr->trackingUrl())->assertOk()
            && $this->post(route('track.pay', $this->sr->reference), ['method' => $method, 'payer_phone' => $phone]);
    }

    public function test_mtn_prompt_is_confirmed_only_after_the_operator_reports_success(): void
    {
        $this->enableMtn();
        $status = 'PENDING';
        Http::fake([
            '*/collection/token/' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/collection/v1_0/requesttopay' => Http::response(null, 202),
            '*/collection/v1_0/requesttopay/*' => function () use (&$status) {
                return Http::response(['status' => $status, 'financialTransactionId' => '987654321']);
            },
        ]);

        $this->get($this->sr->trackingUrl())->assertOk()->assertSee('Pay in app');
        $this->post(route('track.pay', $this->sr->reference), ['method' => 'mtn_momo', 'payer_phone' => '0966111222'])->assertSessionHasNoErrors();
        $payment = Payment::first();
        $this->assertSame('mtn_momo', $payment->gateway);
        $this->assertNotNull($payment->gateway_reference);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/requesttopay') && $r['payer']['partyId'] === '260966111222'
            && $r['currency'] === 'EUR' && $r->hasHeader('X-Reference-Id', $payment->gateway_reference));

        // A callback while MTN still says PENDING changes nothing, even if it claims success.
        $this->postJson('/webhooks/payments/mtn_momo', ['externalId' => (string) $payment->id, 'status' => 'SUCCESSFUL'])->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);

        // Once MTN reports SUCCESSFUL, the callback settles the payment with commission.
        $status = 'SUCCESSFUL';
        $this->postJson('/webhooks/payments/mtn_momo', ['externalId' => (string) $payment->id])->assertOk();
        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);
        $this->assertSame('987654321', $payment->transaction_reference);
        $this->assertEquals(50, $payment->commission_amount);
        $this->assertSame('paid', $this->sr->fresh()->payment_status);
        $this->assertCount(1, $this->provider->fresh()->notifications);
    }

    public function test_airtel_prompt_failure_lets_the_customer_try_again(): void
    {
        $this->enableAirtel();
        Http::fake([
            '*/auth/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/merchant/v2/payments/' => Http::response(['status' => ['success' => true, 'message' => 'Success']]),
            '*/standard/v1/payments/*' => Http::response(['data' => ['transaction' => ['status' => 'TF', 'message' => 'Insufficient funds']]]),
        ]);

        $this->get($this->sr->trackingUrl());
        $this->post(route('track.pay', $this->sr->reference), ['method' => 'airtel_money', 'payer_phone' => '0977123456'])->assertSessionHasNoErrors();
        $payment = Payment::first();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/merchant/v2/payments/') && $r['subscriber']['msisdn'] === '977123456' && $r->hasHeader('X-Country', 'ZM'));

        $this->getJson(route('track.payment-status', $this->sr->reference))->assertOk()->assertJsonPath('payment.status', 'failed');
        $this->assertSame('Insufficient funds', $payment->fresh()->failure_reason);
        $this->assertSame('unpaid', $this->sr->fresh()->payment_status);
        $this->get($this->sr->trackingUrl())->assertSee('go through (Insufficient funds)');
    }

    public function test_wrong_network_number_and_operator_errors_are_reported(): void
    {
        $this->enableMtn();
        Http::fake([
            '*/collection/token/' => Http::response(['access_token' => 'tok']),
            '*/collection/v1_0/requesttopay' => Http::response(['message' => 'Payer not found'], 500),
        ]);
        $this->get($this->sr->trackingUrl());
        $this->post(route('track.pay', $this->sr->reference), ['method' => 'mtn_momo', 'payer_phone' => '0977123456'])
            ->assertSessionHasErrors('payer_phone');
        $this->post(route('track.pay', $this->sr->reference), ['method' => 'mtn_momo', 'payer_phone' => '0966111222'])
            ->assertSessionHasErrors('method');
        $this->assertSame('failed', Payment::first()->status);
        $this->assertSame('completed', $this->sr->fresh()->status);
    }

    public function test_without_keys_payments_stay_manual_and_admin_can_save_keys_encrypted(): void
    {
        $this->get($this->sr->trackingUrl())->assertDontSee('Pay in app');
        $this->post(route('track.pay', $this->sr->reference), ['method' => 'mtn_momo', 'payer_phone' => '0966111222'])->assertSessionHasNoErrors();
        $this->assertSame('manual', Payment::first()->gateway);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/settings/gateways', [
            'mtn_enabled' => 1, 'mtn_environment' => 'production', 'mtn_api_user' => 'u-1', 'mtn_subscription_key' => 'plain-sub', 'mtn_api_key' => 'plain-key',
            'airtel_environment' => 'sandbox',
        ])->assertSessionHasNoErrors();
        $stored = Setting::find('pg_mtn_api_key')->value;
        $this->assertNotSame('plain-key', $stored);
        $this->assertSame('plain-key', app(GatewaySettings::class)->get('mtn_api_key'));
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Live in app')->assertDontSee('plain-key');

        // Blank secret fields keep the saved key.
        $this->actingAs($admin)->put('/admin/settings/gateways', ['mtn_enabled' => 1, 'mtn_environment' => 'production', 'airtel_environment' => 'sandbox'])->assertSessionHasNoErrors();
        $this->assertSame('plain-key', app(GatewaySettings::class)->get('mtn_api_key'));
    }
}
