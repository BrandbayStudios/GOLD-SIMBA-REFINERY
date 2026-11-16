<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    private const GENERIC = 'If that account exists, we have sent a 6-digit code to its phone. It is valid for 15 minutes.';

    public function showForgot(PasswordResetService $svc)
    {
        return view('auth.forgot-password', ['smsAvailable' => $svc->smsAvailable()]);
    }

    /** Always answers the same way, so nobody can use it to find out who has an account. */
    public function sendCode(Request $request, PasswordResetService $svc)
    {
        $data = $request->validate(['identifier' => 'required|string|max:120']);
        if (! $svc->smsAvailable()) {
            return back()->withErrors(['identifier' => 'Password reset by SMS is not available yet. Please contact support.']);
        }
        $svc->requestCode($data['identifier'], $request->ip());

        return redirect()->route('password.reset')->withInput(['identifier' => $data['identifier']])->with('success', self::GENERIC);
    }

    public function showReset(Request $request, PasswordResetService $svc)
    {
        return view('auth.reset-password', ['smsAvailable' => $svc->smsAvailable(), 'identifier' => old('identifier', $request->query('identifier'))]);
    }

    public function reset(Request $request, PasswordResetService $svc)
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:120',
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['code.digits' => 'Enter the 6-digit code from the SMS.']);

        if (! $svc->reset($data['identifier'], $data['code'], $data['password'])) {
            return back()->withInput($request->only('identifier'))->withErrors(['code' => 'That code is incorrect or has expired. You can request a new one.']);
        }

        return redirect()->route('login')->with('success', 'Your password has been changed. Log in with your new password.');
    }

    /** Forced after an admin issues a temporary password, and open to anyone who wants to change it. */
    public function showChange(Request $request)
    {
        return view('auth.change-password', ['forced' => $request->user()->must_change_password]);
    }

    public function change(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], ['password.different' => 'Choose a password that is different from the temporary one.']);
        $request->user()->changePassword($data['password']);
        Auth::logoutOtherDevices($data['password']);

        return redirect($request->user()->dashboardRoute())->with('success', 'Your password has been changed.');
    }
}
