<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesProfilePhotoUpload;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use HandlesProfilePhotoUpload;

    public function index()
    {
        return view('users.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = User::with('branch');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'name', 'email', 'role', 'branch_id', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $users = $query->orderBy($sort, $direction)->paginate($this->perPage($request, 10));

        return response()->json($users);
    }

    public function create()
    {
        return view('users.create', [
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'branch_manager', 'regular_user', 'staff'])],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_approve_deliveries' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            // ~4 MB of image data becomes ~5.6 MB of base64; cap the payload accordingly.
            'profile_photo_cropped' => ['nullable', 'string', 'max:6000000'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] === 'staff' ? 'regular_user' : $validated['role'],
            'branch_id' => $validated['role'] === 'admin' ? null : ($validated['branch_id'] ?? null),
            'phone' => $validated['phone'] ?? null,
        ];

        if ($request->user()?->isAdmin()) {
            $payload['can_approve_deliveries'] = (bool) ($validated['can_approve_deliveries'] ?? false);
        }

        $payload += $this->resolveProfilePhotoUpload($validated, null, 'users.store');

        $user = User::create($payload);

        Log::info('profile-photo: user record saved', [
            'context' => 'users.store',
            'user_id' => $user->id,
            'profile_photo_path' => $user->profile_photo_path,
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'branch_manager', 'regular_user', 'staff'])],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'can_approve_deliveries' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
            // ~4 MB of image data becomes ~5.6 MB of base64; cap the payload accordingly.
            'profile_photo_cropped' => ['nullable', 'string', 'max:6000000'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'] === 'staff' ? 'regular_user' : $validated['role'],
            'branch_id' => $validated['role'] === 'admin' ? null : ($validated['branch_id'] ?? null),
            'phone' => $validated['phone'] ?? null,
        ];

        if ($request->user()?->isAdmin()) {
            $payload['can_approve_deliveries'] = (bool) ($validated['can_approve_deliveries'] ?? false);
        }

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $payload += $this->resolveProfilePhotoUpload($validated, $user, 'users.update');

        $user->update($payload);

        Log::info('profile-photo: user record saved', [
            'context' => 'users.update',
            'user_id' => $user->id,
            'profile_photo_path' => $user->fresh()->profile_photo_path,
        ]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
