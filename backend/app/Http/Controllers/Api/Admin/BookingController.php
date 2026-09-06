<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;

class BookingController extends Controller
{
    public function index()
    {
        return Booking::with(['vendorProfile', 'user'])->latest()->paginate(20);
    }
}
