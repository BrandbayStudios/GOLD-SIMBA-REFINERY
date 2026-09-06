<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;

class EnquiryController extends Controller
{
    public function index()
    {
        return Enquiry::with(['vendorProfile', 'user'])->latest()->paginate(20);
    }
}
