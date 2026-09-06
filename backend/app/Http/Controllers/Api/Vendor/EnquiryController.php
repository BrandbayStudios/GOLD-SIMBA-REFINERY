<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $query = $vendor->enquiries()->with('user')->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->paginate(15);
    }

    public function updateStatus(Request $request, int $enquiry)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $data = $request->validate([
            'status' => ['required', 'in:new,responded,booked,closed'],
        ]);

        $item = $vendor->enquiries()->findOrFail($enquiry);
        $item->update($data);

        return response()->json($item);
    }
}
