<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PasswordResetService;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role', 'customer');
        $q = User::query()->latest();
        if (in_array($role, ['customer', 'provider', 'admin'], true)) {
            $q->where('role', $role);
        }
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('nrc_number', 'like', "%{$term}%"));
        }
        $q->withCount(['customerRequests', 'providerJobs']);
        $users = $q->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users', 'role'));
    }

    public function show(User $user)
    {
        $requests = $user->customerRequests()->with('category')->latest()->take(10)->get();

        return view('admin.users.show', compact('user', 'requests'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => mb_strtolower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:120|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        $data['phone'] = zm_phone($data['phone']);
        if (User::where('phone', $data['phone'])->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }
        User::create($data + ['role' => User::ROLE_ADMIN]);

        return redirect()->route('admin.users.index', ['role' => 'admin'])->with('success', 'Administrator account created.');
    }

    /** For someone locked out (and for when SMS codes are not switched on yet). Shown to the admin once. */
    public function resetPassword(Request $request, User $user, Notifier $notify, SmsService $sms)
    {
        abort_if($user->id === $request->user()->id, 422, 'Change your own password under Settings.');
        $temp = PasswordResetService::temporaryPassword();
        $user->forceFill(['password' => $temp, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        PasswordResetCode::where('user_id', $user->id)->delete();

        $texted = false;
        if ($request->boolean('send_sms') && $sms->isLive()) {
            $sms->send($user->phone, "Your password was reset by support. Temporary password: {$temp} – log in and choose a new one.", 'Temporary password sent');
            $texted = true;
        }
        $notify->user($user, 'Password reset by support', 'Your password was reset by HANDY NOW support. You will be asked to choose a new one when you log in.', null, 'warning');

        return back()->with('temp_password', ['name' => $user->name, 'phone' => $user->phone, 'password' => $temp, 'texted' => $texted]);
    }

    public function toggleStatus(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own account.');
        $suspend = $user->status === 'active';
        $user->update(['status' => $suspend ? 'suspended' : 'active']);
        if ($suspend) {
            $user->tokens()->delete();
            $user->providerProfile?->update(['is_online' => false]);
        }

        return back()->with('success', $user->name.' has been '.($suspend ? 'suspended' : 'reactivated').'.');
    }
}
