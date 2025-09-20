<?php

namespace App\Http\Controllers\Api\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait HasPermissionHelpers
{
    /**
     * Get the authenticated user with proper type casting
     *
     * @return User|null
     */
    protected function getAuthenticatedUser(): ?User
    {
        $user = Auth::user();
        return $user instanceof User ? $user : null;
    }

    /**
     * Check if current user has permission
     *
     * @param string $permission
     * @return bool
     */
    protected function userCan(string $permission): bool
    {
        $user = $this->getAuthenticatedUser();
        return $user && $user->hasPermission($permission);
    }

    /**
     * Check if current user has any of the permissions
     *
     * @param array $permissions
     * @return bool
     */
    protected function userCanAny(array $permissions): bool
    {
        $user = $this->getAuthenticatedUser();
        return $user && $user->hasAnyPermission($permissions);
    }

    /**
     * Check if current user has all permissions
     *
     * @param array $permissions
     * @return bool
     */
    protected function userCanAll(array $permissions): bool
    {
        $user = $this->getAuthenticatedUser();
        return $user && $user->hasAllPermissions($permissions);
    }

    /**
     * Get current user's permissions
     *
     * @return array
     */
    protected function getUserPermissions(): array
    {
        $user = $this->getAuthenticatedUser();
        return $user ? $user->getAllPermissions() : [];
    }

    /**
     * Check permission and return error response if denied
     *
     * @param string $permission
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function checkPermissionOrFail(string $permission)
    {
        if (!$this->userCan($permission)) {
            return $this->errorResponse(
                'Forbidden - Insufficient permissions',
                [
                    'required_permission' => $permission,
                    'user_permissions' => $this->getUserPermissions()
                ],
                403
            );
        }
        
        return null;
    }
}