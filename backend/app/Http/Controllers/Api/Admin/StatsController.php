<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Review;
use App\Models\User;
use App\Models\VendorProfile;

class StatsController extends Controller
{
    public function index()
    {
        return response()->json([
            'total_vendors' => VendorProfile::count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_enquiries' => Enquiry::count(),
            'total_bookings' => Booking::count(),
            'total_reviews' => Review::count(),
            'pending_approvals' => VendorProfile::where('status', 'pending')->count(),
            'vendors_by_category' => Category::withCount('vendorProfiles')
                ->orderByDesc('vendor_profiles_count')
                ->get(['id', 'name'])
                ->map(fn ($c) => ['name' => $c->name, 'count' => $c->vendor_profiles_count]),
        ]);
    }
}
