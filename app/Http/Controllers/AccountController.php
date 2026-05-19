<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
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
            'profile_photo' => ['nullable', 'image', 'max:4096'],
            'profile_photo_cropped' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        if (! empty($validated['profile_photo_cropped']) && str_starts_with($validated['profile_photo_cropped'], 'data:image/')) {
            $base64 = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $validated['profile_photo_cropped']);
            $binary = base64_decode((string) $base64, true);
            if ($binary !== false) {
                $path = 'profiles/' . $user->id . '-' . now()->timestamp . '.jpg';
                Storage::disk('public')->put($path, $binary);
                $payload['profile_photo_path'] = $path;
            }
        } elseif ($request->hasFile('profile_photo')) {
            $payload['profile_photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }

        $user->update($payload);

        return back()->with('success', 'Account updated successfully.');
    }

    public function showProfilePhoto(User $user)
    {
        if (! $user->profile_photo_path || ! Storage::disk('public')->exists($user->profile_photo_path)) {
            abort(404);
        }

        return response()->file(storage_path('app/public/' . $user->profile_photo_path));
    }
}
