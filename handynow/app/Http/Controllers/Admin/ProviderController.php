<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $q = User::where('role', 'provider')->with(['providerProfile.categories'])->latest();
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $q->whereHas('providerProfile', fn ($p) => $p->where('verification_status', $status));
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('providerProfile', fn ($p) => $p->where('business_name', 'like', "%{$term}%")->orWhere('nrc_number', 'like', "%{$term}%")));
        }
        $providers = $q->paginate(20)->withQueryString();

        return view('admin.providers.index', compact('providers', 'status'));
    }

    public function show(User $user)
    {
        abort_unless($user->isProvider(), 404);
        $user->load(['providerProfile.categories']);
        $jobs = $user->providerJobs()->with('category')->latest()->take(10)->get();
        $reviews = $user->reviewsReceived()->with('customer')->latest()->take(10)->get();

        return view('admin.providers.show', ['provider' => $user, 'profile' => $user->providerProfile, 'jobs' => $jobs, 'reviews' => $reviews]);
    }

    public function verify(Request $request, User $user, Notifier $notify)
    {
        abort_unless($user->isProvider(), 404);
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'notes' => 'nullable|required_if:decision,rejected|string|max:1000',
        ], ['notes.required_if' => 'Please explain why the application was rejected so the provider can fix it.']);

        $user->providerProfile->update([
            'verification_status' => $data['decision'],
            'verification_notes' => $data['notes'] ?? null,
            'verified_at' => $data['decision'] === 'approved' ? now() : null,
            'verified_by' => $request->user()->id,
            'is_online' => $data['decision'] === 'approved' ? $user->providerProfile->is_online : false,
        ]);

        $data['decision'] === 'approved'
            ? $notify->user($user, 'You are verified!', 'Your HANDY NOW provider account is approved. Go online to start receiving jobs.', route('provider.dashboard'), 'success', true)
            : $notify->user($user, 'Verification unsuccessful', 'Your application needs attention: '.$data['notes'], route('provider.profile'), 'warning', true);

        return back()->with('success', 'Provider '.($data['decision'] === 'approved' ? 'approved' : 'rejected').'.');
    }

    public function document(User $user, string $type)
    {
        abort_unless(in_array($type, ['nrc_document', 'business_document'], true), 404);
        $path = $user->providerProfile?->{$type};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
