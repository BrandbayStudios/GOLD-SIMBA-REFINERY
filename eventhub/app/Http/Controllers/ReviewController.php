<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body'   => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $review = $vendor->reviews()->create([
            'user_id' => $user->id,
            'author'  => $user->name,
            'rating'  => $data['rating'],
            'stars'   => str_repeat('★', $data['rating']).str_repeat('☆', 5 - $data['rating']),
            'body'    => $data['body'],
        ]);

        $vendor->registerRating($data['rating']);

        return response()->json([
            'ok'      => true,
            'message' => 'Thank you! Your review has been posted.',
            'review'  => [
                'author' => $review->author,
                'stars'  => $review->stars,
                'body'   => $review->body,
            ],
            'vendor'  => [
                'rating'  => $vendor->ratingLabel(),
                'reviews' => (string) $vendor->reviews_count,
            ],
        ]);
    }
}
