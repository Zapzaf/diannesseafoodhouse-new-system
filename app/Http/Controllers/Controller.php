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
}
