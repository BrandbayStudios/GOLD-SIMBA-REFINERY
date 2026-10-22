<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isVendor() && $user->vendor) {
            return $this->vendor($request);
        }

        return $this->customer($request);
    }

    private function customer(Request $request)
    {
        $user = $request->user();

        return view('dashboard.customer', [
            'user'   => $user,
            'quotes' => $user->quoteRequests()->with('vendor')->latest()->get(),
            'plans'  => $user->eventPlans()->latest()->get(),
        ]);
    }

    private function vendor(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor->loadCount('reviews');

        return view('dashboard.vendor', [
            'user'    => $user,
            'vendor'  => $vendor,
            'quotes'  => $vendor->quoteRequests()->with('user')->latest()->get(),
            'reviews' => $vendor->reviews()->latest()->get(),
        ]);
    }
}
