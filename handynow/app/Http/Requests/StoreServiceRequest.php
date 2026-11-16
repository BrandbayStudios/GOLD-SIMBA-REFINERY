<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return ! $u || $u->isCustomer() || $u->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($u = $this->user()) {
            $this->merge([
                'contact_name' => $this->input('contact_name') ?: $u->name,
                'contact_phone' => $this->input('contact_phone') ?: $u->phone,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|integer|exists:categories,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'contact_name' => 'required|string|max:100',
            'contact_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'description' => 'required|string|min:10|max:2000',
            'address' => 'required|string|max:255',
            'directions' => 'nullable|string|max:500',
            'dest_address' => 'nullable|string|max:255',
            'dest_latitude' => 'nullable|numeric|between:-90,90',
            'dest_longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'priority' => 'required|in:normal,urgent,emergency',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($this->filled('service_id') && $this->filled('category_id')) {
                $service = Service::where('id', $this->input('service_id'))->where('category_id', $this->input('category_id'))->first();
                if (! $service) {
                    $v->errors()->add('service_id', 'Please choose a problem from the selected category.');
                } elseif ($service->needs_destination) {
                    // Priced by distance, so both ends of the trip must be pinned.
                    if (! $this->filled('latitude') || ! $this->filled('longitude')) {
                        $v->errors()->add('latitude', 'Pin your location on the map so we can price the trip.');
                    }
                    if (! $this->filled('dest_latitude') || ! $this->filled('dest_longitude')) {
                        $v->errors()->add('dest_latitude', 'Pin the drop-off point on the map.');
                    }
                    if (! $this->filled('dest_address')) {
                        $v->errors()->add('dest_address', 'Enter the drop-off address.');
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a valid phone number, e.g. 0971 234 567.',
            'description.min' => 'Please describe the problem in a little more detail (at least 10 characters).',
            'category_id.required' => 'Please choose the type of service you need.',
        ];
    }
}
