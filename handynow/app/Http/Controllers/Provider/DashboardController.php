<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->providerProfile()->with('categories')->first();

        $newJobs = ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereHas('matches', fn ($m) => $m->where('provider_id', $user->id)->where('status', 'notified'))
            ->with(['category', 'matches' => fn ($m) => $m->where('provider_id', $user->id)])
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")->latest()->take(10)->get();
        $activeJobs = $user->providerJobs()->activeJobs()->with('category')->latest()->get();
        $pendingQuotes = $user->quotations()->where('status', 'pending')->with('serviceRequest.category')->latest()->get();
        $pendingPayments = Payment::where('provider_id', $user->id)->where('status', 'pending')->with('serviceRequest')->get();

        $stats = [
            'completed' => $user->providerJobs()->where('status', 'completed')->count(),
            'earnings_month' => ServiceRequest::where('provider_id', $user->id)->where('payment_status', 'paid')
                ->where('completed_at', '>=', now()->startOfMonth())->sum('provider_earnings'),
            'rating' => $profile?->rating_avg ?? 0,
            'rating_count' => $profile?->rating_count ?? 0,
        ];

        return view('provider.dashboard', compact('profile', 'newJobs', 'activeJobs', 'pendingQuotes', 'pendingPayments', 'stats'));
    }

    public function availability(Request $request, RequestWorkflow $wf)
    {
        $profile = $request->user()->providerProfile;
        if (! $profile->isApproved()) {
            return back()->withErrors(['availability' => 'You can go online once your account has been verified.']);
        }
        $online = $request->boolean('online');
        $profile->update(['is_online' => $online]);
        if ($online) {
            $wf->matchOpenRequestsFor($profile);
        }
        if ($request->expectsJson()) {
            return response()->json(['online' => $online]);
        }

        return back()->with('success', $online ? 'You are now ONLINE and will receive job alerts.' : 'You are now offline.');
    }

    public function location(Request $request)
    {
        $data = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);
        $request->user()->providerProfile->update([
            'current_latitude' => $data['lat'],
            'current_longitude' => $data['lng'],
            'location_updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function earnings(Request $request)
    {
        $user = $request->user();
        $payments = Payment::where('provider_id', $user->id)->with('serviceRequest.category')->latest()->paginate(20);
        $totals = Payment::where('provider_id', $user->id)->where('status', 'confirmed')
            ->selectRaw('COALESCE(SUM(amount),0) as gross, COALESCE(SUM(commission_amount),0) as commission, COALESCE(SUM(provider_amount),0) as net, COUNT(*) as jobs')
            ->first();
        $completedJobs = $user->providerJobs()->where('status', 'completed')->with('category')->latest('completed_at')->take(20)->get();

        return view('provider.earnings', compact('payments', 'totals', 'completedJobs'));
    }

    public function reviews(Request $request)
    {
        $reviews = $request->user()->reviewsReceived()->with('customer', 'serviceRequest.category')->latest()->paginate(15);
        $profile = $request->user()->providerProfile;
        $breakdown = $request->user()->reviewsReceived()->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');

        return view('provider.reviews', compact('reviews', 'profile', 'breakdown'));
    }
}
