<?php

/**
 * IDE Helper for custom methods on User model
 * This file helps IDEs recognize custom methods on the User model
 * 
 * @see https://github.com/barryvdh/laravel-ide-helper
 */

namespace Illuminate\Support\Facades {
    /**
     * @method static \App\Models\User|null user()
     */
    class Auth extends \Illuminate\Support\Facades\Auth {}
}

namespace App\Models {
    /**
     * @method bool hasPermission(string $permissionCode)
     * @method bool hasAnyPermission(array $permissions)
     * @method bool hasAllPermissions(array $permissions)
     * @method array getAllPermissions()
     * @method \Illuminate\Database\Eloquent\Collection getPermissions()
     */
    class User extends \App\Models\BaseAuthenticatable {}
}