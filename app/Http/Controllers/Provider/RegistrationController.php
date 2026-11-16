<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Concerns\ValidatesNrc;
use App\Http\Controllers\Concerns\ValidatesPayout;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Notifier;
use App\Services\ProviderDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    use ValidatesNrc, ValidatesPayout;

    public function create()
    {
        return view('provider.register', ['categories' => Category::active()->get()]);
    }

    public function store(Request $request, Notifier $notify, ProviderDocumentService $docs)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))) ?: null]);
        $file = ProviderDocumentService::FILE_RULE;
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|max:120|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            'provider_type' => 'required|in:individual,business',
            'nrc_number' => $this->nrcRule(),
            'nrc_front' => 'required|'.$file,
            'nrc_back' => 'required|'.$file,
            'selfie' => 'required|'.$file,
            'business_name' => 'nullable|required_if:provider_type,business|string|max:150',
            'business_reg_number' => 'nullable|required_if:provider_type,business|string|max:50',
            'business_cert' => 'nullable|required_if:provider_type,business|'.$file,

            'categories' => 'required|array|min:1',
            'categories.*' => 'integer|exists:categories,id',
            'licences' => 'nullable|array',
            'licences.*.file' => 'nullable|'.$file,
            'licences.*.number' => 'nullable|string|max:60',
            'licences.*.expiry' => 'nullable|date|after:today',
            'extra_docs' => 'nullable|array|max:5',
            'extra_docs.*.title' => 'nullable|string|max:120',
            'extra_docs.*.file' => 'nullable|'.$file,

            'city' => 'required|string|max:80',
            'operating_areas' => 'required|string|max:500',
            'service_radius_km' => 'required|integer|min:1|max:200',
            'years_experience' => 'nullable|integer|min:0|max:60',
            'bio' => 'nullable|string|max:1000',
            'base_latitude' => 'nullable|numeric|between:-90,90',
            'base_longitude' => 'nullable|numeric|between:-180,180',
            'terms' => 'accepted',
        ] + $this->payoutRules(), [
            'nrc_front.required' => 'Upload a clear photo of the front of your NRC.',
            'nrc_back.required' => 'Upload a clear photo of the back of your NRC.',
            'selfie.required' => 'Upload a selfie of you holding your NRC.',
            'business_name.required_if' => 'Business name is required for registered businesses.',
            'business_reg_number.required_if' => 'PACRA registration number is required for registered businesses.',
            'business_cert.required_if' => 'Upload your business registration certificate.',
            'licences.*.expiry.after' => 'That licence has already expired. Upload a valid one.',
            'terms.accepted' => 'Please confirm and accept the terms.',
        ] + $this->payoutMessages());

        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already registered.']);
        }
        $nrc = $this->uniqueNrc($data['nrc_number']);
        $payout = $this->payoutAttributes($data);

        // Every ticked service that needs a licence must come with one.
        $errors = [];
        foreach (Category::whereIn('id', $data['categories'])->where('license_required', true)->get() as $cat) {
            if (! $request->hasFile("licences.{$cat->id}.file")) {
                $errors["licences.{$cat->id}.file"] = "Upload your licence or certificate for {$cat->name}.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $user = DB::transaction(function () use ($request, $data, $phone, $nrc, $payout, $docs) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $phone,
                'nrc_number' => $nrc,
                'city' => $data['city'],
                'password' => $data['password'],
                'role' => User::ROLE_PROVIDER,
            ]);
            $profile = ProviderProfile::create([
                'user_id' => $user->id,
                'provider_type' => $data['provider_type'],
                'business_name' => $data['business_name'] ?? null,
                'nrc_number' => $nrc,
                'business_reg_number' => $data['business_reg_number'] ?? null,
                'whatsapp' => ! empty($data['whatsapp']) ? zm_phone($data['whatsapp']) : $phone,
                'operating_areas' => array_values(array_filter(array_map('trim', explode(',', $data['operating_areas'])))),
                'service_radius_km' => $data['service_radius_km'],
                'years_experience' => $data['years_experience'] ?? 0,
                'bio' => $data['bio'] ?? null,
                'base_latitude' => $data['base_latitude'] ?? null,
                'base_longitude' => $data['base_longitude'] ?? null,
                'verification_status' => 'pending',
            ] + $payout);
            $profile->categories()->sync($data['categories']);

            $docs->store($profile, 'nrc_front', $request->file('nrc_front'));
            $docs->store($profile, 'nrc_back', $request->file('nrc_back'));
            $docs->store($profile, 'selfie', $request->file('selfie'));
            if ($data['provider_type'] === 'business') {
                $docs->store($profile, 'business_cert', $request->file('business_cert'), ['number' => $data['business_reg_number'] ?? null]);
            }
            foreach ($data['categories'] as $catId) {
                if ($file = $request->file("licences.{$catId}.file")) {
                    $meta = $request->input("licences.{$catId}", []);
                    $docs->store($profile, 'licence', $file, ['category_id' => $catId, 'number' => $meta['number'] ?? null, 'expires_at' => $meta['expiry'] ?? null]);
                }
            }
            foreach ($request->input('extra_docs', []) as $i => $extra) {
                if ($file = $request->file("extra_docs.$i.file")) {
                    $docs->store($profile, 'certificate', $file, ['title' => $extra['title'] ?? 'Certificate']);
                }
            }

            return $user;
        });

        $notify->admins('Provider awaiting verification', "{$user->name} registered as a service provider and uploaded documents.", route('admin.providers.show', $user), 'verification');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('provider.dashboard')->with('success', 'Application received! Our team will check your documents, usually within 24 hours. We will SMS you.');
    }
}
