<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Payments\GatewaySettings;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use RefreshDatabase;

    private function atResponse(int $code = 101, string $status = 'Success'): array
    {
        return ['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [['statusCode' => $code, 'number' => '+260972015689', 'status' => $status, 'cost' => 'ZMW 0.3000', 'messageId' => 'ATXid_1']]]];
    }

    public function test_without_settings_sms_is_only_logged(): void
    {
        Http::fake();
        app(SmsService::class)->send('0972015689', 'Hello');
        $this->assertSame('logged', SmsLog::first()->status);
        Http::assertNothingSent();
    }

    public function test_admin_enables_africas_talking_and_sends_a_test_sms(): void
    {
        Http::fake([
            'api.africastalking.com/version1/messaging' => Http::response($this->atResponse(), 201),
            'api.africastalking.com/version1/user*' => Http::response(['UserData' => ['balance' => 'ZMW 120.0000']]),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/sms', ['at_enabled' => 1, 'at_username' => 'handynow', 'at_api_key' => 'atsk_live_key', 'at_sender_id' => 'HANDYNOW'])
            ->assertSessionHasNoErrors();
        $this->assertNotSame('atsk_live_key', Setting::find('pg_at_api_key')->value, 'API key is stored encrypted');

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Balance: ZMW 120.0000')->assertSee('Live')->assertDontSee('atsk_live_key');

        $this->actingAs($admin)->post('/admin/settings/sms-test', ['test_phone' => '0972015689'])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Test SMS sent'));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.africastalking.com/version1/messaging'
            && $r->hasHeader('apiKey', 'atsk_live_key') && $r['to'] === '+260972015689' && $r['from'] === 'HANDYNOW' && $r['username'] === 'handynow');
        $this->assertSame('sent', SmsLog::latest('id')->first()->status);
        $this->assertSame('africastalking', SmsLog::latest('id')->first()->driver);
    }

    public function test_sandbox_uses_sandbox_api_and_rejected_numbers_are_marked_failed(): void
    {
        Http::fake(['api.sandbox.africastalking.com/version1/messaging' => Http::response($this->atResponse(403, 'InvalidPhoneNumber'), 201)]);
        $pg = app(GatewaySettings::class);
        $pg->put('at_enabled', '1');
        $pg->put('at_username', 'sandbox');
        $pg->put('at_api_key', 'sandbox-key');

        app(SmsService::class)->send('0972015689', 'Hello');
        $log = SmsLog::first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('africastalking-sandbox', $log->driver);
        $this->assertStringContainsString('InvalidPhoneNumber', $log->response);
    }

    public function test_enabling_requires_username_and_key(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put('/admin/settings/sms', ['at_enabled' => 1])->assertSessionHasErrors('at_username');
        $this->actingAs($admin)->put('/admin/settings/sms', ['at_enabled' => 1, 'at_username' => 'sandbox'])->assertSessionHasErrors('at_api_key');
        $this->actingAs($admin)->put('/admin/settings/sms', ['at_sender_id' => 'Bad!Sender'])->assertSessionHasErrors('at_sender_id');
    }
}
