<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['vendorProfile', 'user']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(15);
    }

    public function updateStatus(Request $request, Review $review)
    {
        $data = $request->validate(['status' => ['required', 'in:published,flagged']]);
        $review->update($data);
        $review->vendorProfile->recalculateRating();

        return response()->json($review);
    }

    public function destroy(Review $review)
    {
        $vendor = $review->vendorProfile;
        $review->delete();
        $vendor->recalculateRating();

        return response()->json(['message' => 'Review removed.']);
    }
}
