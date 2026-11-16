<?php

namespace Tests\Feature;

use App\Models\PasswordResetCode;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SpySmsDriver implements SmsDriver
{
    public array $sent = [];

    public function send(string $to, string $message): array
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        return ['status' => 'sent', 'response' => null];
    }

    public function name(): string
    {
        return 'spy';
    }
}

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private SpySmsDriver $sms;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new SpySmsDriver;
        $this->app->instance(SmsDriver::class, $this->sms);
        $this->user = User::factory()->create(['phone' => '260966555444', 'email' => 'forgetful@example.com', 'password' => 'OldPass123']);
    }

    private function lastCode(): string
    {
        preg_match('/code is (\d{6})/', end($this->sms->sent)['message'], $m);

        return $m[1] ?? '';
    }

    private function codeSms(): array
    {
        return array_values(array_filter($this->sms->sent, fn ($s) => str_contains($s['message'], 'reset code')));
    }

    public function test_full_reset_by_sms_code(): void
    {
        $this->get('/login')->assertSee('Forgot password?');
        $this->get('/forgot-password')->assertOk()->assertSee('Send me a code');

        $this->post('/forgot-password', ['identifier' => '0966 555 444'])->assertRedirect('/reset-password')->assertSessionHas('success');
        $this->assertCount(1, $this->codeSms());
        $this->assertSame('260966555444', $this->sms->sent[0]['to']);
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertStringNotContainsString($code, SmsLog::first()->message, 'the code is never written to the SMS log');
        $this->assertNotSame($code, PasswordResetCode::first()->code_hash, 'stored hashed');

        $this->get('/reset-password')->assertOk()->assertSee('6-digit code');
        $this->post('/reset-password', ['identifier' => '0966555444', 'code' => $code, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['identifier' => '0966555444', 'code' => $code, 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertRedirect('/login')->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass456', $this->user->fresh()->password));
        $this->assertTrue(collect($this->sms->sent)->contains(fn ($s) => str_contains($s['message'], 'password was just changed')), 'security alert sent');
        $this->post('/login', ['login' => '0966555444', 'password' => 'OldPass123'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'forgetful@example.com', 'password' => 'NewPass456'])->assertRedirect();

        // The code cannot be used a second time.
        auth()->logout();
        $this->post('/reset-password', ['identifier' => '0966555444', 'code' => $code, 'password' => 'Another789', 'password_confirmation' => 'Another789'])->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('NewPass456', $this->user->fresh()->password));
    }

    public function test_unknown_accounts_get_the_same_answer_and_no_sms(): void
    {
        $known = $this->post('/forgot-password', ['identifier' => '0966555444']);
        $unknown = $this->post('/forgot-password', ['identifier' => '0955000999']);
        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
        $this->assertSame(session('success'), 'If that account exists, we have sent a 6-digit code to its phone. It is valid for 15 minutes.');
        $this->assertCount(1, $this->codeSms(), 'only the real account is texted');
        $this->post('/reset-password', ['identifier' => '0955000999', 'code' => '123456', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])->assertSessionHasErrors('code');
    }

    public function test_email_lookup_texts_the_phone_on_the_account_and_suspended_accounts_get_nothing(): void
    {
        $this->post('/forgot-password', ['identifier' => 'Forgetful@Example.com']);
        $this->assertSame('260966555444', $this->sms->sent[0]['to']);

        $this->user->update(['status' => 'suspended']);
        $this->sms->sent = [];
        PasswordResetCode::query()->delete();
        $this->post('/forgot-password', ['identifier' => '0966555444']);
        $this->assertSame([], $this->sms->sent);
    }

    public function test_wrong_guesses_lock_the_code_and_old_codes_die_when_a_new_one_is_sent(): void
    {
        $svc = app(PasswordResetService::class);
        $svc->requestCode('0966555444');
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < PasswordResetService::MAX_ATTEMPTS; $i++) {
            $this->assertNull($svc->reset('0966555444', $wrong, 'NewPass456'));
        }
        $this->assertNull($svc->reset('0966555444', $code, 'NewPass456'), 'locked even for the right code after 5 wrong tries');

        $this->travel(61)->seconds();
        $svc->requestCode('0966555444');
        $fresh = $this->lastCode();
        $this->assertCount(2, $this->codeSms());
        if ($fresh !== $code) {
            $this->assertNull($svc->reset('0966555444', $code, 'NewPass456'), 'the earlier code stopped working');
        }
        $this->assertNotNull($svc->reset('0966555444', $fresh, 'NewPass456'));
    }

    public function test_cooldown_hourly_cap_and_expiry(): void
    {
        $svc = app(PasswordResetService::class);
        $svc->requestCode('0966555444');
        $svc->requestCode('0966555444'); // inside the 60 second cooldown
        $this->assertCount(1, $this->codeSms());
        foreach ([61, 61] as $wait) {
            $this->travel($wait)->seconds();
            $svc->requestCode('0966555444');
        }
        $this->assertCount(3, $this->codeSms());
        $this->travel(61)->seconds();
        $svc->requestCode('0966555444'); // fourth within the hour
        $this->assertCount(3, $this->codeSms(), 'at most 3 codes an hour');

        $this->travel(2)->hours();
        $svc->requestCode('0966555444');
        $code = $this->lastCode();
        $this->travel(PasswordResetService::TTL_MINUTES + 1)->minutes();
        $this->assertNull($svc->reset('0966555444', $code, 'NewPass456'), 'expired');
    }

    public function test_reset_signs_out_other_devices_and_revokes_app_tokens(): void
    {
        $this->user->createToken('phone');
        $this->assertSame(1, $this->user->tokens()->count());
        // Logged in on a browser...
        $this->post('/login', ['login' => '0966555444', 'password' => 'OldPass123'])->assertRedirect();
        $this->get('/account')->assertOk();
        // ...then the password is reset (here, from another device).
        app(PasswordResetService::class)->requestCode('0966555444');
        $this->assertNotNull(app(PasswordResetService::class)->reset('0966555444', $this->lastCode(), 'NewPass456'));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->app['auth']->forgetGuards(); // the browser's next request starts fresh
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_sms_must_be_switched_on_in_production(): void
    {
        $this->app->instance(SmsDriver::class, new LogSmsDriver);
        $this->app['env'] = 'production';
        $this->get('/forgot-password')->assertOk()->assertSee('not switched on yet')->assertDontSee('Send me a code');
        // (production enforces CSRF, so send a token like a browser would)
        $this->withSession(['_token' => 'tok'])->post('/forgot-password', ['_token' => 'tok', 'identifier' => '0966555444'])->assertSessionHasErrors('identifier');
        $this->assertSame(0, PasswordResetCode::count());
    }

    public function test_admin_issues_a_temporary_password_that_must_be_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->user->createToken('phone');

        $this->actingAs($admin)->post(route('admin.users.reset-password', $admin))->assertStatus(422);
        $this->actingAs($admin)->post(route('admin.users.reset-password', $this->user), ['send_sms' => 1])->assertSessionHas('temp_password');
        $temp = session('temp_password')['password'];
        $this->assertMatchesRegularExpression('/^(?=.*[A-Za-z])(?=.*\d).{10}$/', $temp);
        $this->assertTrue(session('temp_password')['texted']);
        $this->assertTrue(collect($this->sms->sent)->contains(fn ($s) => str_contains($s['message'], $temp)));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->actingAs($admin)->get('/admin/users?role=customer')->assertOk();

        // The user logs in with it and can do nothing else until they choose their own password.
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['login' => '0966555444', 'password' => $temp])->assertRedirect();
        $this->get('/account')->assertRedirect(route('password.change'));
        $this->get('/account/requests')->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('Choose your own password');
        $this->put(route('password.change.update'), ['current_password' => $temp, 'password' => $temp, 'password_confirmation' => $temp])->assertSessionHasErrors('password');
        $this->put(route('password.change.update'), ['current_password' => 'wrong', 'password' => 'Chosen789', 'password_confirmation' => 'Chosen789'])->assertSessionHasErrors('current_password');
        $this->put(route('password.change.update'), ['current_password' => $temp, 'password' => 'Chosen789', 'password_confirmation' => 'Chosen789'])->assertRedirect();
        $this->assertFalse($this->user->fresh()->must_change_password);
        $this->get('/account')->assertOk();

        // Ordinary users cannot use it.
        $this->actingAs(User::factory()->create())->post(route('admin.users.reset-password', $this->user))->assertForbidden();
    }

    public function test_mobile_api_reset(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => '0966555444'])->assertOk();
        $code = $this->lastCode();
        $this->postJson('/api/v1/auth/reset-password', ['identifier' => '0966555444', 'code' => '000000', 'password' => 'NewPass456'])->assertStatus(422);
        $this->postJson('/api/v1/auth/reset-password', ['identifier' => '0966555444', 'code' => $code, 'password' => 'NewPass456'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['login' => '0966555444', 'password' => 'NewPass456'])->assertOk()->assertJsonPath('user.must_change_password', false);

        $this->app->instance(SmsDriver::class, new LogSmsDriver);
        $this->app['env'] = 'production';
        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => '0966555444'])->assertStatus(503);
    }
}
