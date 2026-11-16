<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Services\Notifier;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        $categories = Category::active()->withCount('services')->get();
        $featured = ProviderProfile::where('verification_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user', 'categories'])
            ->orderByDesc('is_online')->orderByDesc('rating_avg')->take(8)->get();
        $stats = [
            'providers' => ProviderProfile::where('verification_status', 'approved')->count(),
            'online' => ProviderProfile::available()->count(),
            'completed' => ServiceRequest::where('status', ServiceRequest::STATUS_COMPLETED)->count(),
            'avg_response' => (int) round(ProviderProfile::where('verification_status', 'approved')->avg('avg_response_minutes') ?: 25),
        ];

        return view('pages.home', compact('categories', 'featured', 'stats'));
    }

    public function howItWorks()
    {
        return view('pages.how-it-works');
    }

    public function faqs()
    {
        return view('pages.faqs');
    }

    public function terms()
    {
        return view('pages.terms');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function sendContact(Request $request, Notifier $notify)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:2000',
        ]);
        $notify->admins('Contact form: '.$data['name'], $data['phone'].' — '.mb_strimwidth($data['message'], 0, 300, '…'), null, 'contact');

        return back()->with('success', 'Thank you! Our support team will get back to you shortly.');
    }
}
