<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FamilyTransferRequest;
use App\Services\ChurchAccessService;

class FamilyTransferRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_families');
    }

    public function view(User $user, FamilyTransferRequest $request): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        return ChurchAccessService::canAccessChurch($user, $request->from_church_id) ||
               ChurchAccessService::canAccessChurch($user, $request->to_church_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_families');
    }

    public function sourceApprove(User $user, FamilyTransferRequest $request): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return true;
        }
        if ($user->hasRole('Priest / Vicar')) {
            return ChurchAccessService::canAccessChurch($user, $request->from_church_id);
        }
        return false;
    }

    public function dioceseApprove(User $user, FamilyTransferRequest $request): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return true;
        }
        if ($user->hasRole('Diocese Secretary') && $user->hasPermissionTo('approve_diocese_transfers')) {
            return true;
        }
        return false;
    }

    public function targetAccept(User $user, FamilyTransferRequest $request): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return true;
        }
        if ($user->hasRole(['Priest / Vicar', 'Parish Admin'])) {
            return ChurchAccessService::canAccessChurch($user, $request->to_church_id);
        }
        return false;
    }

    public function complete(User $user, FamilyTransferRequest $request): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return true;
        }
        if ($user->hasRole(['Priest / Vicar', 'Parish Admin'])) {
            return ChurchAccessService::canAccessChurch($user, $request->to_church_id);
        }
        return false;
    }
}
