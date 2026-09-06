<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        return $vendor->bookings()->with('package')->latest()->paginate(15);
    }

    public function updateStatus(Request $request, int $booking)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,cancelled'],
        ]);

        $item = $vendor->bookings()->findOrFail($booking);
        $item->update($data);

        return response()->json($item);
    }
}
