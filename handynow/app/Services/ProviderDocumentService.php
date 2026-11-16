<?php

namespace App\Services;

use App\Models\ProviderDocument;
use App\Models\ProviderProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Stores provider identity and licence files on the private disk (never publicly reachable). */
class ProviderDocumentService
{
    public const FILE_RULE = 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120';

    public function store(ProviderProfile $profile, string $type, UploadedFile $file, array $meta = []): ProviderDocument
    {
        $categoryId = $type === 'licence' ? ($meta['category_id'] ?? null) : null;

        $existing = null;
        if (in_array($type, ProviderDocument::SINGLE, true)) {
            $existing = $profile->documents()->where('type', $type)->first();
        } elseif ($type === 'licence' && $categoryId) {
            $existing = $profile->documents()->where('type', 'licence')->where('category_id', $categoryId)->first();
        }

        $attrs = [
            'title' => $meta['title'] ?? null,
            'number' => $meta['number'] ?? null,
            'expires_at' => $meta['expires_at'] ?? null,
            'path' => $file->store('provider-documents/'.$profile->id, 'local'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 150),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];

        if ($existing) {
            Storage::disk('local')->delete($existing->path);
            $existing->update($attrs);

            return $existing;
        }

        return $profile->documents()->create(['type' => $type, 'category_id' => $categoryId] + $attrs);
    }

    public function delete(ProviderDocument $document): void
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();
    }
}
