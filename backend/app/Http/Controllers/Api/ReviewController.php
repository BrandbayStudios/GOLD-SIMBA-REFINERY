<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(VendorProfile $vendor)
    {
        $reviews = $vendor->reviews()->where('status', 'published')->with('user')->latest()->paginate(10);

        return ReviewResource::collection($reviews);
    }

    public function store(Request $request, VendorProfile $vendor)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $vendor->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'status' => 'published']
        );

        $vendor->recalculateRating();

        return new ReviewResource($review->load('user'));
    }
}
