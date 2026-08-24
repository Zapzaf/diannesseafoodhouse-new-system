<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesProfilePhotoUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    use HandlesProfilePhotoUpload;

    public function index()
    {
        return view('account.index');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            // ~4 MB of image data becomes ~5.6 MB of base64; cap the payload accordingly.
            'profile_photo_cropped' => ['nullable', 'string', 'max:6000000'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $payload += $this->resolveProfilePhotoUpload($validated, $user, 'account.update');

        $user->update($payload);

        Log::info('profile-photo: user record saved', [
            'context' => 'account.update',
            'user_id' => $user->id,
            'profile_photo_path' => $user->fresh()->profile_photo_path,
        ]);

        return back()->with('success', 'Account updated successfully.');
    }

    public function showProfilePhoto(User $user)
    {
        if (! $user->profile_photo_path || ! Storage::disk('public')->exists($user->profile_photo_path)) {
            abort(404);
        }

        // Every view of this URL passes a ?v=<updated_at timestamp> cache-buster
        // (see layouts.app, account.index, users.edit), so it's safe to let the
        // browser cache this response indefinitely — a new photo upload bumps
        // updated_at and therefore the URL, rather than reusing this one.
        return response()->file(storage_path('app/public/' . $user->profile_photo_path), [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
