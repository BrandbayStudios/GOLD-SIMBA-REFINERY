<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Payments\GatewaySettings;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Sms\AfricasTalkingSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function edit(GatewaySettings $pg, PaymentGatewayManager $gateways)
    {
        $gateway = collect(GatewaySettings::FIELDS)->mapWithKeys(fn ($f) => [$f => in_array($f, GatewaySettings::SECRET_FIELDS, true) ? $pg->has($f) : $pg->get($f)]);
        $gatewayStatus = $gateways->status();
        $pendingMigrations = $this->pendingMigrations();
        $sms = collect(GatewaySettings::SMS_FIELDS)->mapWithKeys(fn ($f) => [$f => $f === 'at_api_key' ? $pg->has($f) : $pg->get($f)]);
        $smsDriver = app(SmsDriver::class);
        $smsBalance = $smsDriver instanceof AfricasTalkingSmsDriver ? $smsDriver->balance() : null;
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]);
        $demo = [
            'users' => User::where('is_demo', true)->count(),
            'requests' => ServiceRequest::where('is_demo', true)->count(),
        ];

        return view('admin.settings', compact('settings', 'demo', 'gateway', 'gatewayStatus', 'pendingMigrations', 'sms', 'smsDriver', 'smsBalance'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:50',
            'match_radius_km' => 'required|integer|min:1|max:500',
            'min_payout' => 'required|numeric|min:0|max:100000',
            'commission_instructions' => 'nullable|string|max:300',
            'support_phone' => 'required|string|max:30',
            'support_whatsapp' => 'required|string|max:20',
            'support_email' => 'required|email|max:120',
        ]);
        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }

        return back()->with('success', 'Settings saved.');
    }

    /** Saves mobile money API credentials. Secret fields left blank keep their stored value. */
    public function gateways(Request $request, GatewaySettings $pg)
    {
        $data = $request->validate([
            'mtn_environment' => 'required|in:sandbox,production',
            'mtn_target_environment' => 'nullable|string|max:40',
            'mtn_currency' => 'nullable|string|size:3',
            'mtn_api_user' => 'nullable|string|max:100',
            'mtn_subscription_key' => 'nullable|string|max:200',
            'mtn_api_key' => 'nullable|string|max:200',
            'airtel_environment' => 'required|in:sandbox,production',
            'airtel_client_id' => 'nullable|string|max:200',
            'airtel_client_secret' => 'nullable|string|max:200',
        ]);
        $data['mtn_enabled'] = $request->boolean('mtn_enabled') ? '1' : '0';
        $data['airtel_enabled'] = $request->boolean('airtel_enabled') ? '1' : '0';
        foreach ($data as $field => $value) {
            if (in_array($field, GatewaySettings::SECRET_FIELDS, true) && ($value === null || $value === '')) {
                continue;
            }
            $pg->put($field, $value === null ? null : trim((string) $value));
        }
        // New credentials must not reuse a cached access token.
        cache()->forget('mtn_momo_token_'.md5($pg->get('mtn_api_user').'https://sandbox.momodeveloper.mtn.com'));
        cache()->forget('mtn_momo_token_'.md5($pg->get('mtn_api_user').'https://proxy.momoapi.mtn.com'));
        cache()->forget('airtel_token_'.md5($pg->get('airtel_client_id').'https://openapiuat.airtel.africa'));
        cache()->forget('airtel_token_'.md5($pg->get('airtel_client_id').'https://openapi.airtel.africa'));

        return back()->with('success', 'Mobile money settings saved.');
    }

    /** Saves Africa's Talking credentials (API key stored encrypted; blank keeps the saved key). */
    public function sms(Request $request, GatewaySettings $pg)
    {
        $data = $request->validate([
            'at_username' => 'nullable|string|max:100',
            'at_api_key' => 'nullable|string|max:200',
            'at_sender_id' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]*$/'],
        ], ['at_sender_id.regex' => 'Sender ID: letters, numbers and spaces only (max 11).']);
        $data += ['at_username' => null, 'at_api_key' => null, 'at_sender_id' => null];
        if ($request->boolean('at_enabled') && ! $data['at_username']) {
            return back()->withErrors(['at_username' => 'Enter your Africa\'s Talking username (use "sandbox" for testing).']);
        }
        $pg->put('at_enabled', $request->boolean('at_enabled') ? '1' : '0');
        $pg->put('at_username', $data['at_username'] ? trim($data['at_username']) : null);
        $pg->put('at_sender_id', $data['at_sender_id'] ? trim($data['at_sender_id']) : null);
        if (! empty($data['at_api_key'])) {
            $pg->put('at_api_key', trim($data['at_api_key']));
        }
        if ($request->boolean('at_enabled') && ! $pg->has('at_api_key')) {
            return back()->withErrors(['at_api_key' => 'Enter your Africa\'s Talking API key.']);
        }

        return back()->with('success', 'SMS settings saved.');
    }

    public function smsTest(Request $request, SmsService $sms)
    {
        $data = $request->validate(['test_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/']]);
        $sms->send($data['test_phone'], 'Test message from your HANDY NOW admin panel. SMS is working.');
        $log = SmsLog::latest('id')->first();

        return back()->with('success', match ($log?->status) {
            'sent' => 'Test SMS sent to '.$data['test_phone'].' ('.$log->response.').',
            'logged' => 'SMS is not switched on – the message was only written to the SMS log.',
            default => 'Sending failed: '.($log?->response ?? 'unknown error'),
        });
    }

    /** Applies new database migrations after a deployment (for hosts without SSH). */
    public function migrate()
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');

        return back()->with('success', 'Database updates applied.');
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        $ran = $migrator->getRepository()->getRan();

        return collect($migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')]))
            ->keys()->reject(fn ($m) => in_array($m, $ran, true))->count();
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $request->user()->changePassword($data['password']);
        Auth::logoutOtherDevices($data['password']);

        return back()->with('success', 'Your password has been changed.');
    }

    /** Removes all records flagged as demonstration data, leaving live records untouched. */
    public function purgeDemo(Request $request)
    {
        $request->validate(['confirm' => 'required|in:DELETE DEMO DATA'], ['confirm.in' => 'Type DELETE DEMO DATA to confirm.']);
        DB::transaction(function () {
            Complaint::where('is_demo', true)->delete();
            Payment::where('is_demo', true)->delete();
            ServiceRequest::where('is_demo', true)->delete();
            User::where('is_demo', true)->where('id', '!=', auth()->id())->delete();
        });

        return back()->with('success', 'Demonstration data removed.');
    }

    public function smsLog()
    {
        return view('admin.sms-log', ['logs' => SmsLog::latest()->paginate(30), 'driver' => config('services.sms.driver')]);
    }
}
