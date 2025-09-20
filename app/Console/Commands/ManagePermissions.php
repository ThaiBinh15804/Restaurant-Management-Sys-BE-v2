<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Console\Command;

class ManagePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:manage 
                            {action : Action to perform (list-roles, list-permissions, assign-role, check-permission)}
                            {--user= : User ID or email}
                            {--role= : Role ID or name}
                            {--permission= : Permission code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage RBAC roles and permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'list-roles':
                $this->listRoles();
                break;
            case 'list-permissions':
                $this->listPermissions();
                break;
            case 'assign-role':
                $this->assignRole();
                break;
            case 'check-permission':
                $this->checkPermission();
                break;
            default:
                $this->error('Invalid action. Available actions: list-roles, list-permissions, assign-role, check-permission');
        }

        return 0;
    }

    /**
     * List all roles
     */
    private function listRoles()
    {
        $roles = Role::with('permissions')->get();

        $this->info('Available Roles:');
        $this->line('');

        foreach ($roles as $role) {
            $this->line("ID: {$role->id}");
            $this->line("Name: {$role->name}");
            $this->line("Description: {$role->description}");
            $this->line("Active: " . ($role->is_active ? 'Yes' : 'No'));
            $this->line("Permissions: " . $role->permissions->count());
            $this->line('---');
        }
    }

    /**
     * List all permissions
     */
    private function listPermissions()
    {
        $permissions = Permission::orderBy('code')->get();

        $this->info('Available Permissions:');
        $this->line('');

        $groups = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->code);
            $group = $parts[0] ?? 'other';
            $groups[$group][] = $permission;
        }

        foreach ($groups as $group => $groupPermissions) {
            $this->line(strtoupper($group) . ':');
            foreach ($groupPermissions as $permission) {
                $status = $permission->is_active ? '✓' : '✗';
                $this->line("  {$status} {$permission->code} - {$permission->name}");
            }
            $this->line('');
        }
    }

    /**
     * Assign role to user
     */
    private function assignRole()
    {
        $userInput = $this->option('user');
        $roleInput = $this->option('role');

        if (!$userInput || !$roleInput) {
            $this->error('Both --user and --role options are required');
            return;
        }

        // Find user
        $user = User::where('id', $userInput)
            ->orWhere('email', $userInput)
            ->first();

        if (!$user) {
            $this->error("User not found: {$userInput}");
            return;
        }

        // Find role
        $role = Role::where('id', $roleInput)
            ->orWhere('name', $roleInput)
            ->first();

        if (!$role) {
            $this->error("Role not found: {$roleInput}");
            return;
        }

        // Assign role
        $user->role_id = $role->id;
        $user->save();

        $this->info("Successfully assigned role '{$role->name}' to user '{$user->name}' ({$user->email})");
    }

    /**
     * Check if user has permission
     */
    private function checkPermission()
    {
        $userInput = $this->option('user');
        $permission = $this->option('permission');

        if (!$userInput || !$permission) {
            $this->error('Both --user and --permission options are required');
            return;
        }

        // Find user
        $user = User::with('role.permissions')->where('id', $userInput)
            ->orWhere('email', $userInput)
            ->first();

        if (!$user) {
            $this->error("User not found: {$userInput}");
            return;
        }

        $hasPermission = $user->hasPermission($permission);

        if ($hasPermission) {
            $this->info("✓ User '{$user->name}' HAS permission '{$permission}'");
        } else {
            $this->error("✗ User '{$user->name}' does NOT have permission '{$permission}'");
        }

        // Show user's current permissions
        $this->line('');
        $this->line("User's current permissions:");
        $permissions = $user->getAllPermissions();
        foreach ($permissions as $perm) {
            $this->line("  - {$perm}");
        }
    }
}