<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]);
        $demo = [
            'users' => User::where('is_demo', true)->count(),
            'requests' => ServiceRequest::where('is_demo', true)->count(),
        ];

        return view('admin.settings', compact('settings', 'demo'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:50',
            'match_radius_km' => 'required|integer|min:1|max:500',
            'support_phone' => 'required|string|max:30',
            'support_whatsapp' => 'required|string|max:20',
            'support_email' => 'required|email|max:120',
        ]);
        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }

        return back()->with('success', 'Settings saved.');
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
