<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Models\Category;
use App\Models\ServiceRequest;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function create(Request $request)
    {
        return $this->form($request, false);
    }

    public function emergency(Request $request)
    {
        return $this->form($request, true);
    }

    private function form(Request $request, bool $emergency)
    {
        $categories = Category::active()->with('activeServices:id,category_id,name,slug')->get();
        $selectedCategory = $categories->firstWhere('slug', $request->query('category'));
        $selectedService = $selectedCategory?->activeServices->firstWhere('slug', $request->query('service'));

        return view('requests.create', [
            'categories' => $categories,
            'emergency' => $emergency,
            'selectedCategory' => $selectedCategory,
            'selectedService' => $selectedService,
            'prefillAddress' => $request->query('location'),
            'prefillProblem' => $request->query('q'),
            'priorities' => ServiceRequest::PRIORITIES,
        ]);
    }

    public function store(StoreServiceRequest $request, RequestWorkflow $workflow)
    {
        $sr = $workflow->create($request->validated(), $request->user(), $request->file('photos', []));

        // Remember the tracking token in this browser so guests can manage the request without logging in.
        $request->session()->put('tracked.'.$sr->reference, $sr->tracking_token);

        if ($request->user()?->isCustomer()) {
            return redirect()->route('customer.requests.show', $sr)->with('success', "Request {$sr->reference} submitted. We're notifying nearby providers.");
        }

        return redirect($sr->trackingUrl())->with('success', "Request {$sr->reference} submitted. Save this page – we've also sent the link by SMS.");
    }
}
