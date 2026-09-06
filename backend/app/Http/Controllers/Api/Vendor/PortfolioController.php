<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioMediaResource;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        return PortfolioMediaResource::collection($vendor->portfolioMedia);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'type' => ['nullable', 'in:image,video'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $vendor->portfolioMedia()->create([
            'url' => $data['url'],
            'type' => $data['type'] ?? 'image',
            'caption' => $data['caption'] ?? null,
            'sort_order' => $vendor->portfolioMedia()->max('sort_order') + 1,
        ]);

        return new PortfolioMediaResource($media);
    }

    public function destroy(Request $request, int $media)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $item = $vendor->portfolioMedia()->findOrFail($media);
        $item->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
