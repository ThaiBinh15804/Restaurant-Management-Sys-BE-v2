<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RBACCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac {action : Action to perform}
                            {--dry-run : Show what would be synced without making changes (for sync action)}
                            {--with-roles : Also sync roles and role permissions (for sync action)}
                            {--user= : User ID or email (for assign-role, check-permission actions)}
                            {--role= : Role ID or name (for assign-role action)}
                            {--permission= : Permission code (for check-permission action)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive RBAC management tool - sync permissions and manage roles';

    /**
     * Available actions
     */
    private const ACTIONS = [
        'sync' => 'Synchronize permissions from config to database',
        'list-roles' => 'List all roles and their permissions',
        'list-permissions' => 'List all permissions grouped by module',
        'assign-role' => 'Assign a role to a user',
        'check-permission' => 'Check if user has specific permission',
        'help' => 'Show detailed help for all actions'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        // Show help if action is help or invalid
        if ($action === 'help' || !array_key_exists($action, self::ACTIONS)) {
            $this->showHelp();
            return $action === 'help' ? 0 : 1;
        }

        switch ($action) {
            case 'sync':
                return $this->syncPermissions();
            case 'list-roles':
                return $this->listRoles();
            case 'list-permissions':
                return $this->listPermissions();
            case 'assign-role':
                return $this->assignRole();
            case 'check-permission':
                return $this->checkPermission();
        }

        return 0;
    }

    /**
     * Show detailed help
     */
    private function showHelp()
    {
        $this->info('🛡️  RBAC Management Tool');
        $this->line('');
        $this->line('Available actions:');
        
        foreach (self::ACTIONS as $action => $description) {
            $this->line("  <fg=yellow>{$action}</fg=yellow> - {$description}");
        }

        $this->line('');
        $this->info('Examples:');
        $this->line('  <fg=green>php artisan rbac sync --dry-run</fg=green>                     # Preview permission sync');
        $this->line('  <fg=green>php artisan rbac sync --with-roles</fg=green>                 # Sync permissions and roles');
        $this->line('  <fg=green>php artisan rbac list-roles</fg=green>                        # List all roles');
        $this->line('  <fg=green>php artisan rbac list-permissions</fg=green>                  # List all permissions');
        $this->line('  <fg=green>php artisan rbac assign-role --user=admin@example.com --role=admin</fg=green>');
        $this->line('  <fg=green>php artisan rbac check-permission --user=1 --permission=users.create</fg=green>');
        
        $this->line('');
        $this->comment('For more details about RBAC system, see: RBAC_GUIDE.md');
    }

    /**
     * Synchronize permissions from config to database
     */
    private function syncPermissions(): int
    {
        $isDryRun = $this->option('dry-run');
        $withRoles = $this->option('with-roles');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $this->info('🔄 Starting permissions synchronization...');
        
        if (!$withRoles) {
            $this->comment('ℹ️  Only syncing permissions (use --with-roles to sync roles too)');
        }
        
        $this->line('');

        try {
            DB::transaction(function () use ($isDryRun, $withRoles) {
                $this->doSyncPermissions($isDryRun);
                
                if ($withRoles) {
                    $this->doCreateRolesIfNotExist($isDryRun);
                    $this->doCreateRolePermissionsIfNotExist($isDryRun);
                } else {
                    $this->info('⏭️  Skipping roles and role permissions sync');
                    $this->comment('   Use --with-roles flag to sync roles (only creates missing ones)');
                }
            });

            if ($isDryRun) {
                $this->line('');
                $this->info('🔍 DRY RUN COMPLETED - No changes were made');
                $this->comment('Run without --dry-run to apply changes');
            } else {
                $this->line('');
                $this->info('✅ Permissions synchronization completed successfully');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error during synchronization: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync permissions from config file to database
     */
    private function doSyncPermissions(bool $isDryRun): void
    {
        $this->info('📋 Syncing permissions...');
        
        $permissionConfig = config('permissions.modules', []);
        $synced = 0;
        $created = 0;
        $updated = 0;
        
        foreach ($permissionConfig as $moduleKey => $moduleData) {
            $permissions = $moduleData['permissions'] ?? [];
            
            foreach ($permissions as $code => $permissionData) {
                $permission = Permission::where('code', $code)->first();
                
                if ($permission) {
                    if (!$isDryRun) {
                        $permission->update([
                            'name' => $permissionData['name'],
                            'description' => $permissionData['description'],
                            'is_active' => true,
                        ]);
                    }
                    $updated++;
                    $this->line("  📝 Updated: {$code}");
                } else {
                    if (!$isDryRun) {
                        Permission::create([
                            'code' => $code,
                            'name' => $permissionData['name'],
                            'description' => $permissionData['description'],
                            'is_active' => true,
                        ]);
                    }
                    $created++;
                    $this->line("  ➕ Created: {$code}");
                }
                $synced++;
            }
        }

        // Handle unused permissions
        if (config('permissions.sync.auto_disable_unused', false)) {
            $configPermissionCodes = $this->getAllPermissionCodesFromConfig();
            $unusedPermissions = Permission::whereNotIn('code', $configPermissionCodes)
                ->where('is_active', true);
            
            $unusedCount = $unusedPermissions->count();
            if ($unusedCount > 0) {
                $this->line("  ⚠️  Found {$unusedCount} unused permissions");
                foreach ($unusedPermissions->get() as $permission) {
                    $this->line("    🔒 Disabling: {$permission->code}");
                }
                
                if (!$isDryRun) {
                    $unusedPermissions->update(['is_active' => false]);
                }
            }
        }

        $this->info("📋 Permissions: {$synced} total ({$created} created, {$updated} updated)");
    }

    /**
     * Create roles if they don't exist (no overwrite)
     */
    private function doCreateRolesIfNotExist(bool $isDryRun): void
    {
        $this->info('👥 Creating missing roles...');
        
        $roleConfig = config('permissions.roles', []);
        $created = 0;
        $existing = 0;
        
        foreach ($roleConfig as $roleKey => $roleData) {
            $role = Role::where('name', $roleData['name'])->first();
            
            if (!$role) {
                if (!$isDryRun) {
                    Role::create([
                        'name' => $roleData['name'],
                        'description' => $roleData['description'],
                        'is_active' => true,
                    ]);
                }
                $created++;
                $this->line("  ➕ Would create: {$roleData['name']}");
            } else {
                $existing++;
                $this->line("  ✓ Already exists: {$roleData['name']}");
            }
        }

        $this->info("👥 Roles: {$created} would be created, {$existing} already exist");
        if ($created === 0) {
            $this->comment('   All default roles already exist in database');
        }
    }

    /**
     * Create role permissions if they don't exist (no overwrite)
     */
    private function doCreateRolePermissionsIfNotExist(bool $isDryRun): void
    {
        $this->info('🔗 Creating missing role permissions...');
        
        $roleConfig = config('permissions.roles', []);
        $totalCreated = 0;
        $totalExisting = 0;
        
        foreach ($roleConfig as $roleKey => $roleData) {
            $role = Role::where('name', $roleData['name'])->first();
            
            if (!$role) {
                $this->comment("  ⚠️  Role not found: {$roleData['name']} - skipping");
                continue;
            }

            $permissionCodes = $roleData['permissions'];
            
            // Handle wildcard permissions (all permissions)
            if ($permissionCodes === '*') {
                $permissionCodes = $this->getAllPermissionCodesFromConfig();
            }

            $created = 0;
            $existing = 0;

            foreach ($permissionCodes as $permissionCode) {
                $permission = Permission::where('code', $permissionCode)->first();
                
                if (!$permission) {
                    $this->error("    ❌ Permission not found: {$permissionCode}");
                    continue;
                }

                $rolePermission = RolePermission::where('role_id', $role->id)
                    ->where('permission_id', $permission->id)
                    ->first();

                if (!$rolePermission) {
                    if (!$isDryRun) {
                        RolePermission::create([
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                        ]);
                    }
                    $created++;
                } else {
                    $existing++;
                }
            }

            if ($created > 0) {
                $this->line("  ➕ {$roleData['name']}: {$created} new permissions would be assigned");
            }
            if ($existing > 0) {
                $this->line("  ✓ {$roleData['name']}: {$existing} permissions already assigned");
            }

            $totalCreated += $created;
            $totalExisting += $existing;
        }

        $this->info("🔗 Role Permissions: {$totalCreated} would be created, {$totalExisting} already exist");
        if ($totalCreated === 0) {
            $this->comment('   All default role permissions already exist in database');
        }
    }

    /**
     * Get all permission codes from config
     */
    private function getAllPermissionCodesFromConfig(): array
    {
        $permissionConfig = config('permissions.modules', []);
        $allCodes = [];
        
        foreach ($permissionConfig as $moduleData) {
            $permissions = $moduleData['permissions'] ?? [];
            $allCodes = array_merge($allCodes, array_keys($permissions));
        }
        
        return $allCodes;
    }

    /**
     * List all roles
     */
    private function listRoles(): int
    {
        $roles = Role::with('permissions')->get();

        $this->info('🛡️  Available Roles:');
        $this->line('');

        if ($roles->isEmpty()) {
            $this->comment('No roles found in database');
            return 0;
        }

        foreach ($roles as $role) {
            $status = $role->is_active ? '✅' : '❌';
            $this->line("{$status} <fg=yellow>ID:</fg=yellow> {$role->id}");
            $this->line("   <fg=yellow>Name:</fg=yellow> {$role->name}");
            $this->line("   <fg=yellow>Description:</fg=yellow> {$role->description}");
            $this->line("   <fg=yellow>Active:</fg=yellow> " . ($role->is_active ? 'Yes' : 'No'));
            $this->line("   <fg=yellow>Permissions:</fg=yellow> " . $role->permissions->count());
            
            // Show first few permissions
            if ($role->permissions->count() > 0) {
                $this->line("   <fg=yellow>Sample Permissions:</fg=yellow>");
                foreach ($role->permissions->take(3) as $permission) {
                    $this->line("     • {$permission->code}");
                }
                if ($role->permissions->count() > 3) {
                    $this->line("     • ... and " . ($role->permissions->count() - 3) . " more");
                }
            }
            
            $this->line('');
        }

        return 0;
    }

    /**
     * List all permissions
     */
    private function listPermissions(): int
    {
        $permissions = Permission::orderBy('code')->get();

        $this->info('📋 Available Permissions:');
        $this->line('');

        if ($permissions->isEmpty()) {
            $this->comment('No permissions found in database');
            $this->comment('Run "php artisan rbac sync" to load permissions from config');
            return 0;
        }

        $groups = [];
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->code);
            $group = $parts[0] ?? 'other';
            $groups[$group][] = $permission;
        }

        foreach ($groups as $group => $groupPermissions) {
            $this->line("<fg=cyan>" . strtoupper($group) . ":</fg=cyan>");
            foreach ($groupPermissions as $permission) {
                $status = $permission->is_active ? '✅' : '❌';
                $this->line("  {$status} <fg=yellow>{$permission->code}</fg=yellow> - {$permission->name}");
            }
            $this->line('');
        }

        $this->info("Total: " . $permissions->count() . " permissions");
        return 0;
    }

    /**
     * Assign role to user
     */
    private function assignRole(): int
    {
        $userInput = $this->option('user');
        $roleInput = $this->option('role');

        if (!$userInput || !$roleInput) {
            $this->error('❌ Both --user and --role options are required');
            $this->line('Example: php artisan rbac assign-role --user=admin@example.com --role=admin');
            return 1;
        }

        // Find user
        $user = User::where('id', $userInput)
            ->orWhere('email', $userInput)
            ->first();

        if (!$user) {
            $this->error("❌ User not found: {$userInput}");
            return 1;
        }

        // Find role
        $role = Role::where('id', $roleInput)
            ->orWhere('name', $roleInput)
            ->first();

        if (!$role) {
            $this->error("❌ Role not found: {$roleInput}");
            $this->comment('Available roles:');
            $availableRoles = Role::where('is_active', true)->get(['id', 'name']);
            foreach ($availableRoles as $availableRole) {
                $this->line("  • {$availableRole->id}: {$availableRole->name}");
            }
            return 1;
        }

        if (!$role->is_active) {
            $this->error("❌ Role '{$role->name}' is not active");
            return 1;
        }

        // Check if user already has this role
        if ($user->role_id === $role->id) {
            $this->comment("ℹ️  User '{$user->name}' already has role '{$role->name}'");
            return 0;
        }

        // Show current role if any
        if ($user->role_id) {
            $currentRole = Role::find($user->role_id);
            $this->line("Current role: " . ($currentRole ? $currentRole->name : 'Unknown'));
        }

        // Assign role
        $user->role_id = $role->id;
        $user->save();

        $this->info("✅ Successfully assigned role '{$role->name}' to user '{$user->name}' ({$user->email})");
        return 0;
    }

    /**
     * Check if user has permission
     */
    private function checkPermission(): int
    {
        $userInput = $this->option('user');
        $permission = $this->option('permission');

        if (!$userInput || !$permission) {
            $this->error('❌ Both --user and --permission options are required');
            $this->line('Example: php artisan rbac check-permission --user=1 --permission=users.create');
            return 1;
        }

        // Find user
        $user = User::with('role.permissions')->where('id', $userInput)
            ->orWhere('email', $userInput)
            ->first();

        if (!$user) {
            $this->error("❌ User not found: {$userInput}");
            return 1;
        }

        $this->line("👤 <fg=yellow>User:</fg=yellow> {$user->name} ({$user->email})");
        
        if ($user->role) {
            $this->line("🛡️  <fg=yellow>Role:</fg=yellow> {$user->role->name}");
        } else {
            $this->line("🛡️  <fg=yellow>Role:</fg=yellow> None assigned");
        }

        $hasPermission = $user->hasPermission($permission);

        $this->line('');
        if ($hasPermission) {
            $this->info("✅ User HAS permission '{$permission}'");
        } else {
            $this->error("❌ User does NOT have permission '{$permission}'");
        }

        // Show user's current permissions
        $this->line('');
        $this->line("📋 <fg=yellow>User's current permissions:</fg=yellow>");
        $permissions = $user->getAllPermissions();
        
        if (empty($permissions)) {
            $this->comment('  No permissions assigned');
        } else {
            $permissionGroups = [];
            foreach ($permissions as $perm) {
                $parts = explode('.', $perm);
                $group = $parts[0] ?? 'other';
                $permissionGroups[$group][] = $perm;
            }

            foreach ($permissionGroups as $group => $groupPerms) {
                $this->line("  <fg=cyan>{$group}:</fg=cyan>");
                foreach ($groupPerms as $perm) {
                    $highlight = $perm === $permission ? '<fg=green>' : '';
                    $highlightEnd = $perm === $permission ? '</fg=green>' : '';
                    $this->line("    • {$highlight}{$perm}{$highlightEnd}");
                }
            }
            
            $this->line('');
            $this->info("Total: " . count($permissions) . " permissions");
        }

        return $hasPermission ? 0 : 1;
    }
}