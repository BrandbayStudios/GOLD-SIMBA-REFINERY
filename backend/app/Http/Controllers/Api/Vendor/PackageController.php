<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        return PackageResource::collection($vendor->packages);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $data = $this->validated($request);
        $package = $vendor->packages()->create($data);

        return new PackageResource($package);
    }

    public function update(Request $request, int $package)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $item = $vendor->packages()->findOrFail($package);
        $item->update($this->validated($request, true));

        return new PackageResource($item);
    }

    public function destroy(Request $request, int $package)
    {
        $vendor = $request->user()->vendorProfile;
        abort_unless($vendor, 404);

        $vendor->packages()->findOrFail($package)->delete();

        return response()->json(['message' => 'Package removed.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = fn (string $r) => $partial ? str_replace('required', 'sometimes', $r) : $r;

        return $request->validate([
            'name' => [$rule('required'), 'string', 'max:255'],
            'price' => [$rule('required'), 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:60'],
            'guest_capacity' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_popular' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
