<?php

namespace App\Http\Controllers;

use App\Models\ChangelogUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    /**
     * "What's New": every authenticated user can browse it, regardless of
     * role. Admins additionally see drafts (is_published = false) so they
     * can review an update before it goes live for everyone else.
     */
    public function index(Request $request): View
    {
        $type = $request->input('type');

        $updates = ChangelogUpdate::query()
            ->with('creator')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->published())
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 12))
            ->withQueryString();

        return view('changelog.index', [
            'updates' => $updates,
            'type' => $type,
        ]);
    }

    public function create(): View
    {
        return view('changelog.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUpdate($request);

        $imagePath = null;
        try {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('changelog', 'public');
            }

            ChangelogUpdate::create([
                ...$validated,
                'image' => $imagePath,
                'created_by' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        return redirect()->route('changelog.index')->with('success', 'Update published successfully.');
    }

    public function edit(ChangelogUpdate $changelog): View
    {
        return view('changelog.edit', ['update' => $changelog]);
    }

    public function update(Request $request, ChangelogUpdate $changelog): RedirectResponse
    {
        $validated = $this->validateUpdate($request);

        $newImagePath = null;
        $oldImagePath = $changelog->image;

        try {
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')->store('changelog', 'public');
                $validated['image'] = $newImagePath;
            }

            if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
                $validated['image'] = null;
            }

            $changelog->update($validated);
        } catch (\Throwable $e) {
            if ($newImagePath && Storage::disk('public')->exists($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $e;
        }

        // Only clean up the old file once the new state is safely saved, and
        // only if it actually changed (new upload, or explicit removal).
        if ($oldImagePath && $oldImagePath !== $changelog->image && Storage::disk('public')->exists($oldImagePath)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('changelog.index')->with('success', 'Update saved successfully.');
    }

    public function destroy(ChangelogUpdate $changelog): RedirectResponse
    {
        if ($changelog->image && Storage::disk('public')->exists($changelog->image)) {
            Storage::disk('public')->delete($changelog->image);
        }

        $changelog->delete();

        return redirect()->route('changelog.index')->with('success', 'Update deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdate(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(array_keys(ChangelogUpdate::TYPES))],
            'released_at' => ['required', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        unset($validated['image']);
        // Checkbox: absent from the request means unchecked, not "keep default" —
        // default to published only when the field wasn't sent at all (e.g. a
        // non-form caller), not merely unchecked in the UI.
        $validated['is_published'] = $request->has('is_published') ? $request->boolean('is_published') : true;

        return $validated;
    }
}
