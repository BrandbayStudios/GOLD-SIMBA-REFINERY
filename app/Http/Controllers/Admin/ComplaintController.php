<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = Complaint::with(['user', 'serviceRequest', 'againstUser'])->latest();
        if ($status) {
            $q->where('status', $status);
        }
        $complaints = $q->paginate(20)->withQueryString();

        return view('admin.complaints.index', compact('complaints', 'status'));
    }

    public function show(Complaint $complaint)
    {
        $complaint->load(['user', 'serviceRequest.category', 'serviceRequest.provider', 'againstUser', 'handler']);

        return view('admin.complaints.show', compact('complaint'));
    }

    public function update(Request $request, Complaint $complaint, Notifier $notify)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUSES))],
            'admin_response' => 'nullable|string|max:3000',
        ]);
        $complaint->update($data + [
            'handled_by' => $request->user()->id,
            'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
        ]);
        $notify->user($complaint->user, 'Complaint '.$complaint->reference.' updated',
            'Status: '.Complaint::STATUSES[$data['status']].($data['admin_response'] ? ' – '.mb_strimwidth($data['admin_response'], 0, 140, '…') : ''),
            $complaint->user->isCustomer() ? route('customer.complaints.index') : null, 'complaint', true);

        return back()->with('success', 'Complaint updated and the customer notified.');
    }
}
