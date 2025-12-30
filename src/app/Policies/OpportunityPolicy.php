<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OpportunityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::ORGANIZATION_MANAGER);
    }

    /**
     * Determine whether the user can view all models.
     */
    public function viewAll(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->isAdmin() || $this->isManagerForOpportunity($user, $opportunity);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(Role::ORGANIZATION_MANAGER);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->isAdmin() || $this->isManagerForOpportunity($user, $opportunity);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->isAdmin() || $this->isManagerForOpportunity($user, $opportunity);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $user->isAdmin() || $this->isManagerForOpportunity($user, $opportunity);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        return $user->isAdmin() || $this->isManagerForOpportunity($user, $opportunity);
    }

    /**
     * Determine whether the user can attach an organization to the model.
     */
    public function attachOrganization(User $user, Opportunity $opportunity, Organization $organization): bool
    {
        return $user->isAdmin()
            || ($this->isManagerForOpportunity($user, $opportunity)
                && $this->isManagerForOrganization($user, $organization));
    }

    /**
     * Determine whether the user can detach an organization from the model.
     */
    public function detachOrganization(User $user, Opportunity $opportunity, Organization $organization): bool
    {
        return $user->isAdmin()
            || ($this->isManagerForOpportunity($user, $opportunity)
                && $this->isManagerForOrganization($user, $organization));
    }

    private function isManagerForOpportunity(User $user, Opportunity $opportunity): bool
    {
        if (!$user->hasRole(Role::ORGANIZATION_MANAGER)) {
            return false;
        }

        return $opportunity->organizations()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    private function isManagerForOrganization(User $user, Organization $organization): bool
    {
        if (!$user->hasRole(Role::ORGANIZATION_MANAGER)) {
            return false;
        }

        return $organization->users()
            ->where('users.id', $user->id)
            ->exists();
    }
}
