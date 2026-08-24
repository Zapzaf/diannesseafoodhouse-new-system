<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Shared by AccountController (self-service "My Account") and UserController
 * (admin creating/editing another user) — both forms post the same two
 * fields for a profile picture: a cropped base64 JPEG from the Cropper.js
 * modal (profile_photo_cropped), or the raw uploaded file as a fallback if
 * the crop step never ran (profile_photo). Logged at each step since photo
 * uploads have been reported as silently not saving, which usually means
 * one of these branches wasn't the one actually hit.
 */
trait HandlesProfilePhotoUpload
{
    /**
     * @return array<string, string> The payload additions, or empty if no photo was submitted.
     */
    private function resolveProfilePhotoUpload(array $validated, ?User $forUser, string $context): array
    {
        $hasCropped = ! empty($validated['profile_photo_cropped']);
        $hasRawFile = request()->hasFile('profile_photo');

        Log::info('profile-photo: incoming request', [
            'context' => $context,
            'user_id' => $forUser?->id,
            'has_cropped_field' => $hasCropped,
            'cropped_field_length' => $hasCropped ? strlen($validated['profile_photo_cropped']) : 0,
            'has_raw_file' => $hasRawFile,
            'raw_file_name' => $hasRawFile ? request()->file('profile_photo')->getClientOriginalName() : null,
            'raw_file_size' => $hasRawFile ? request()->file('profile_photo')->getSize() : null,
        ]);

        if ($hasCropped && str_starts_with($validated['profile_photo_cropped'], 'data:image/')) {
            $base64 = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $validated['profile_photo_cropped']);
            $binary = base64_decode((string) $base64, true);
            $imageInfo = $binary !== false ? @getimagesizefromstring($binary) : false;

            if ($imageInfo === false || ! in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                Log::warning('profile-photo: cropped payload failed image validation', [
                    'context' => $context,
                    'user_id' => $forUser?->id,
                    'base64_decoded' => $binary !== false,
                ]);

                throw ValidationException::withMessages([
                    'profile_photo_cropped' => 'The cropped photo must be a valid JPEG or PNG image.',
                ]);
            }

            $path = 'profiles/'.($forUser?->id ?? 'new').'-'.now()->timestamp.'.jpg';
            Storage::disk('public')->put($path, $binary);

            Log::info('profile-photo: saved from cropped canvas', [
                'context' => $context,
                'user_id' => $forUser?->id,
                'path' => $path,
                'bytes' => strlen($binary),
            ]);

            return ['profile_photo_path' => $path];
        }

        if ($hasRawFile) {
            $path = request()->file('profile_photo')->store('profiles', 'public');

            Log::info('profile-photo: saved from raw file upload (crop step was skipped or unavailable)', [
                'context' => $context,
                'user_id' => $forUser?->id,
                'path' => $path,
            ]);

            return ['profile_photo_path' => $path];
        }

        Log::info('profile-photo: no photo submitted with this request — leaving existing photo unchanged', [
            'context' => $context,
            'user_id' => $forUser?->id,
        ]);

        return [];
    }
}
