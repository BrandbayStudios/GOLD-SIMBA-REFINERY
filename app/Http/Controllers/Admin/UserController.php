<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }
        $q->withCount(['customerRequests', 'providerJobs']);
        $users = $q->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users', 'role'));
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
