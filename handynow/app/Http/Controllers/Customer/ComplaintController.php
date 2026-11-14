<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ServiceRequest;
use App\Services\Notifier;
use Illuminate\Http\Request;

/** Complaints can be filed by customers and providers alike (route is in the customer group; providers use the shared view). */
class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $complaints = Complaint::where('user_id', $request->user()->id)->with('serviceRequest')->latest()->paginate(15);

        return view('customer.complaints.index', compact('complaints'));
    }

    public function create(Request $request)
    {
        $requests = $request->user()->customerRequests()->latest()->take(30)->get(['id', 'reference', 'category_id', 'created_at']);
        $selected = $request->query('request');

        return view('customer.complaints.create', compact('requests', 'selected'));
    }

    public function store(Request $request, Notifier $notify)
    {
        $data = $request->validate([
            'service_request_id' => 'nullable|integer',
            'subject' => 'required|string|max:150',
            'description' => 'required|string|min:10|max:3000',
        ]);
        $sr = null;
        if (! empty($data['service_request_id'])) {
            $sr = ServiceRequest::where('id', $data['service_request_id'])->where('customer_id', $request->user()->id)->firstOrFail();
        }
        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'service_request_id' => $sr?->id,
            'against_user_id' => $sr?->provider_id,
            'subject' => $data['subject'],
            'description' => $data['description'],
        ]);
        $notify->admins('New complaint '.$complaint->reference, $complaint->subject, route('admin.complaints.show', $complaint), 'complaint');

        return redirect()->route('customer.complaints.index')->with('success', "Complaint {$complaint->reference} submitted. Our team will respond within 24 hours.");
    }
}
