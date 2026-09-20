<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve a safe pagination size from the request, clamped to [1, 100].
     */
    protected function perPage(Request $request, int $default = 20): int
    {
        return max(1, min((int) $request->input('per_page', $default), 100));
    }

    /**
     * The branch context for the current user: admins follow the global
     * branch switcher (null = all branches); everyone else is locked to
     * their assigned branch.
     */
    protected function activeBranchId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->isAdmin()) {
            return $request->session()->get('selected_branch_id') ?: null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    /**
     * Block non-admins from records that belong to another branch.
     * Records without a branch (legacy rows) stay visible to everyone.
     */
    protected function authorizeBranchRecord(Request $request, ?int $recordBranchId): void
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin() && $recordBranchId !== null && (int) $user->branch_id !== (int) $recordBranchId) {
            abort(403, 'This record belongs to another branch.');
        }
    }

    /**
     * True when the request's date_from/date_to are both today — the exact
     * value the shared period-filter widget (resources/views/reports/
     * partials/period-filter.blade.php) submits when the user never touched
     * it. Used to tell "the user left the date picker alone" apart from "the
     * user deliberately chose today" isn't perfectly possible, but this is
     * the only signal available, and picking today on purpose while also
     * searching is a rare combination compared to the widget's default
     * silently swallowing a search for anything not dated today.
     */
    protected function isUntouchedDefaultDateRange(Request $request): bool
    {
        $today = now()->toDateString();

        return $request->input('date_from') === $today && $request->input('date_to') === $today;
    }
}
