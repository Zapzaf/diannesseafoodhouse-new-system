<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $this->resolveBranchId($request);

        $feedback = Feedback::query()
            ->with('branch')
            ->when($branchId, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('branch_id', $request->user()->branch_id))
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('improvements', 'like', "%{$search}%");
                });
            })
            ->latest('date')
            ->latest()
            ->paginate($this->perPage($request, 15))
            ->withQueryString();

        return view('feedback.index', [
            'feedback' => $feedback,
            'ratingFields' => Feedback::RATING_FIELDS,
        ]);
    }

    public function create(Request $request): View
    {
        return view('feedback.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedBranchId' => $this->resolveBranchId($request),
            'ratingFields' => Feedback::RATING_FIELDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'food_taste_rating' => ['required', 'integer', 'between:1,5'],
            'overall_experience' => ['required', 'integer', 'between:1,5'],
            'service_satisfaction' => ['required', 'integer', 'between:1,5'],
            'speed_of_service' => ['required', 'integer', 'between:1,5'],
            'cleanliness' => ['required', 'integer', 'between:1,5'],
            'friendliness' => ['required', 'integer', 'between:1,5'],
            'improvements' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateBranchAccess($request, (int) $validated['branch_id']);

        Feedback::create($validated);

        return redirect()->route('feedback.index')->with('success', 'Feedback recorded successfully.');
    }

    public function show(Request $request, Feedback $feedback): View
    {
        $this->validateBranchAccess($request, (int) $feedback->branch_id);

        $feedback->load('branch');

        return view('feedback.show', [
            'feedback' => $feedback,
            'ratingFields' => Feedback::RATING_FIELDS,
        ]);
    }

    public function destroy(Request $request, Feedback $feedback): RedirectResponse
    {
        $this->validateBranchAccess($request, (int) $feedback->branch_id);

        $feedback->delete();

        return redirect()->route('feedback.index')->with('success', 'Feedback deleted successfully.');
    }

    private function resolveBranchId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    private function validateBranchAccess(Request $request, int $branchId): void
    {
        if (! $request->user()->isAdmin() && (int) $request->user()->branch_id !== $branchId) {
            abort(403, 'This feedback is outside your branch.');
        }

        $activeBranchId = $this->resolveBranchId($request);

        if ($request->user()->isAdmin() && $activeBranchId && $activeBranchId !== $branchId && $request->isMethod('post')) {
            throw ValidationException::withMessages([
                'branch_id' => 'Please use the active branch for this feedback.',
            ]);
        }
    }
}
