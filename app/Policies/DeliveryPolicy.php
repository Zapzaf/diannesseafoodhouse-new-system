<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Delivery $delivery): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $user->branch_id === (int) $delivery->destination_branch_id
            || (int) $user->branch_id === (int) $delivery->source_branch_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model — only while it's
     * still pending review, and only the person who encoded it (Aileen,
     * say) or someone who could approve it. Once Jessica (or whoever)
     * approves or rejects it, the record is locked; this is what actually
     * enforces that — previously update() only checked the reviewer's
     * role/branch and never the delivery's own status, and delegated
     * entirely to approve() with no way for the original encoder to fix
     * their own mistake before handing the receipts over for review.
     */
    public function update(User $user, Delivery $delivery): bool
    {
        if (! $delivery->isPending()) {
            return false;
        }

        return (int) $user->id === (int) $delivery->created_by || $this->approve($user, $delivery);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Delivery $delivery): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Delivery $delivery): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Delivery $delivery): bool
    {
        return false;
    }

    public function approve(User $user, Delivery $delivery): bool
    {
        if ($user->isAdmin() || $user->can_approve_deliveries) {
            return true;
        }

        return $user->isBranchManager() && (int) $user->branch_id === (int) $delivery->destination_branch_id;
    }
}
