<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessClaim;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function index(Request $request)
    {
        $query = BusinessClaim::with(['vendorProfile', 'user']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->latest()->paginate(15);
    }

    public function updateStatus(Request $request, BusinessClaim $claim)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected']]);
        $claim->update($data);

        if ($data['status'] === 'approved') {
            $claim->vendorProfile->update([
                'user_id' => $claim->user_id,
                'source' => 'platform',
            ]);
        }

        return response()->json($claim->fresh(['vendorProfile', 'user']));
    }
}
