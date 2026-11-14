<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $active = $user->customerRequests()->activeJobs()->with(['category', 'provider'])->withCount(['quotations as pending_quotes_count' => fn ($q) => $q->where('status', 'pending')])->latest()->get();
        $recent = $user->customerRequests()->whereNotIn('status', ServiceRequest::ACTIVE_STATUSES)->with(['category', 'provider', 'review'])->latest()->take(5)->get();
        $stats = [
            'total' => $user->customerRequests()->count(),
            'active' => $active->count(),
            'completed' => $user->customerRequests()->where('status', 'completed')->count(),
            'spent' => Payment::where('customer_id', $user->id)->where('status', 'confirmed')->sum('amount'),
        ];
        $toRate = $user->customerRequests()->where('status', 'completed')->doesntHave('review')->whereNotNull('provider_id')->latest()->take(3)->get();

        return view('customer.dashboard', compact('active', 'recent', 'stats', 'toRate'));
    }

    public function payments(Request $request)
    {
        $payments = Payment::where('customer_id', $request->user()->id)->with('serviceRequest.category', 'provider')->latest()->paginate(15);
        $outstanding = $request->user()->customerRequests()->where('status', 'completed')->where('payment_status', 'unpaid')->get();

        return view('customer.payments', compact('payments', 'outstanding'));
    }
}
