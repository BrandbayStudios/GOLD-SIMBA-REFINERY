<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'customer')->withCount('enquiries');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        return $query->latest()->paginate((int) $request->input('per_page', 15));
    }

    public function destroy(User $customer)
    {
        abort_unless($customer->role === 'customer', 404);
        $customer->delete();

        return response()->json(['message' => 'Customer removed.']);
    }
}
