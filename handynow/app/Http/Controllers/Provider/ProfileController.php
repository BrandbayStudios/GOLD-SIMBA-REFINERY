<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Concerns\ValidatesNrc;
use App\Http\Controllers\Concerns\ValidatesPayout;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProviderDocument;
use App\Models\User;
use App\Services\Notifier;
use App\Services\ProviderDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    use ValidatesNrc, ValidatesPayout;

    public function edit(Request $request)
    {
        $user = $request->user();
        $profile = $user->providerProfile()->with(['categories', 'documents.category'])->first();

        return view('provider.profile', [
            'user' => $user, 'profile' => $profile, 'categories' => Category::active()->get(),
            'docTypes' => ProviderDocument::TYPES, 'missing' => $profile->missingRequirements(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))) ?: null]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'business_name' => 'nullable|string|max:150',
            'city' => 'required|string|max:80',
            'bio' => 'nullable|string|max:1000',
            'years_experience' => 'nullable|integer|min:0|max:60',
            'categories' => 'required|array|min:1',
            'categories.*' => 'integer|exists:categories,id',
            'operating_areas' => 'required|string|max:500',
            'service_radius_km' => 'required|integer|min:1|max:200',
            'base_latitude' => 'nullable|numeric|between:-90,90',
            'base_longitude' => 'nullable|numeric|between:-180,180',
            'avatar' => 'nullable|image|max:2048',
        ]);
        $phone = zm_phone($data['phone']);
        if (User::where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['phone' => 'This phone number is used by another account.']);
        }
        $userData = ['name' => $data['name'], 'email' => $data['email'], 'phone' => $phone, 'city' => $data['city']];
        if ($request->hasFile('avatar')) {
            $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        $user->update($userData);
        $profile = $user->providerProfile;
        $profile->update([
            'business_name' => $data['business_name'] ?? null,
            'whatsapp' => ! empty($data['whatsapp']) ? zm_phone($data['whatsapp']) : $phone,
            'bio' => $data['bio'] ?? null,
            'years_experience' => $data['years_experience'] ?? 0,
            'operating_areas' => array_values(array_filter(array_map('trim', explode(',', $data['operating_areas'])))),
            'service_radius_km' => $data['service_radius_km'],
            'base_latitude' => $data['base_latitude'] ?? $profile->base_latitude,
            'base_longitude' => $data['base_longitude'] ?? $profile->base_longitude,
        ]);
        $profile->categories()->sync($data['categories']);

        return back()->with('success', 'Profile updated. If you added a service that needs a licence, upload it under Documents below.');
    }

    /** NRC number and PACRA number (the files are uploaded separately). */
    public function identity(Request $request, Notifier $notify)
    {
        $data = $request->validate(['nrc_number' => $this->nrcRule(), 'business_reg_number' => 'nullable|string|max:50']);
        $user = $request->user();
        $nrc = $this->uniqueNrc($data['nrc_number'], $user->id);
        $changed = $user->nrc_number !== $nrc;
        $user->update(['nrc_number' => $nrc]);
        $user->providerProfile->update(['nrc_number' => $nrc, 'business_reg_number' => $data['business_reg_number'] ?? null]);
        if ($changed) {
            $notify->admins('Provider changed NRC number', "{$user->name} updated their NRC number. Please re-check their documents.", route('admin.providers.show', $user), 'verification');
        }

        return back()->with('success', 'Identity details saved.');
    }

    /** Upload or replace a document. Replacing puts it back in the review queue. */
    public function storeDocument(Request $request, Notifier $notify, ProviderDocumentService $docs)
    {
        $profile = $request->user()->providerProfile;
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ProviderDocument::TYPES))],
            'file' => 'required|'.ProviderDocumentService::FILE_RULE,
            'category_id' => ['nullable', 'required_if:type,licence', Rule::in($profile->categories()->pluck('categories.id')->all())],
            'title' => 'nullable|string|max:120',
            'number' => 'nullable|string|max:60',
            'expires_at' => 'nullable|date|after:today',
        ], [
            'category_id.required_if' => 'Choose which service this licence is for.',
            'category_id.in' => 'Add that service to your profile first.',
            'expires_at.after' => 'That document has already expired. Upload a valid one.',
            'file.required' => 'Choose a photo or PDF to upload.',
        ]);
        if ($data['type'] === 'business_cert' && $profile->provider_type !== 'business') {
            return back()->withErrors(['type' => 'Business documents are only for registered businesses.']);
        }
        $doc = $docs->store($profile, $data['type'], $request->file('file'), [
            'category_id' => $data['category_id'] ?? null, 'title' => $data['title'] ?? null,
            'number' => $data['number'] ?? null, 'expires_at' => $data['expires_at'] ?? null,
        ]);
        $notify->admins('Provider uploaded a document', "{$request->user()->name}: {$doc->label()} is waiting for review.", route('admin.providers.show', $request->user()), 'verification');

        return back()->with('success', $doc->label().' uploaded. Our team will check it shortly.');
    }

    public function destroyDocument(Request $request, ProviderDocument $document, ProviderDocumentService $docs)
    {
        abort_unless($document->provider_profile_id === $request->user()->providerProfile->id, 404);
        if (in_array($document->type, ProviderDocument::SINGLE, true) || ($document->type === 'licence' && $document->status === 'approved')) {
            return back()->withErrors(['document' => 'This document is required. Upload a new one to replace it instead.']);
        }
        $docs->delete($document);

        return back()->with('success', 'Document removed.');
    }

    public function viewDocument(Request $request, ProviderDocument $document)
    {
        abort_unless($document->provider_profile_id === $request->user()->providerProfile->id, 404);

        return $this->streamDocument($document);
    }

    public function payout(Request $request, Notifier $notify)
    {
        $data = $request->validate($this->payoutRules(), $this->payoutMessages());
        $profile = $request->user()->providerProfile;
        $attrs = $this->payoutAttributes($data);
        $changed = $profile->hasPayoutDetails() && $this->payoutChanged($profile, $attrs);
        $profile->update($attrs);
        if ($changed) {
            $notify->admins('Provider changed payout details', "{$request->user()->name} changed how they get paid to {$profile->payoutLabel()}. Verify with them if unexpected.", route('admin.providers.show', $request->user()), 'verification');
        }

        return back()->with('success', 'Payment method saved.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $request->user()->changePassword($data['password']);
        Auth::logoutOtherDevices($data['password']);

        return back()->with('success', 'Password changed.');
    }

    /** Private files are streamed through the app, never linked directly. */
    public static function streamDocument(ProviderDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
