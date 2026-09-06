<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $driver = DB::connection()->getDriverName();
        $ymExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyVendors = VendorProfile::selectRaw("{$ymExpr} as ym, count(*) as total")
            ->groupBy('ym')->orderBy('ym')->pluck('total', 'ym');

        $totalEnquiries = Enquiry::count();
        $totalBookings = Booking::count();
        $conversion = $totalEnquiries > 0 ? round(($totalBookings / $totalEnquiries) * 100, 1) : 0;

        $topCategories = Category::withCount(['vendorProfiles as bookings_count' => function ($q) {
            $q->join('bookings', 'bookings.vendor_profile_id', '=', 'vendor_profiles.id');
        }])
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get(['id', 'name']);

        return response()->json([
            'monthly_new_vendors' => $monthlyVendors,
            'enquiry_to_booking_conversion_pct' => $conversion,
            'total_enquiries' => $totalEnquiries,
            'total_bookings' => $totalBookings,
            'top_categories_by_bookings' => $topCategories,
        ]);
    }
}
