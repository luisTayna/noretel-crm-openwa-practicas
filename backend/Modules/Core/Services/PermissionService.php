<?php

namespace Modules\Core\Services;

use Modules\Core\Repositories\RoleRepository;
use Modules\Core\Repositories\UserRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    protected RoleRepository $roleRepository;
    protected UserRepository $userRepository;

    public function __construct(
        RoleRepository $roleRepository,
        UserRepository $userRepository
    ) {
        $this->roleRepository = $roleRepository;
        $this->userRepository = $userRepository;
    }

    public function hasPermission($userId, $permission, $module, $context = [])
    {
        $permissionName = $this->permissionName($module, $permission);
        $managePermissionName = $this->permissionName($module, 'manage');
        $cacheKey = "user.{$userId}.spatie-permission.{$permissionName}";

        return Cache::remember($cacheKey, 60, function () use ($userId, $permissionName, $managePermissionName) {
            $user = $this->userRepository->find($userId);

            if ($user->hasRole('admin')) {
                return true;
            }

            return $this->userHasExistingPermission($user, $managePermissionName)
                || $this->userHasExistingPermission($user, $permissionName);
        });
    }

    public function canViewModule($userId, $module, $context = [])
    {
        return $this->hasPermission($userId, 'view', $module, $context);
    }

    public function canCreateInModule($userId, $module, $context = [])
    {
        return $this->hasPermission($userId, 'create', $module, $context);
    }

    public function canEditInModule($userId, $module, $context = [])
    {
        return $this->hasPermission($userId, 'edit', $module, $context);
    }

    public function canDeleteInModule($userId, $module, $context = [])
    {
        return $this->hasPermission($userId, 'delete', $module, $context);
    }

    public function getUserPermissions($userId)
    {
        $cacheKey = "user.{$userId}.spatie-permissions";

        return Cache::remember($cacheKey, 60, function () use ($userId) {
            $user = $this->userRepository->find($userId);

            return $user->getAllPermissions()
                ->mapWithKeys(function ($permission) {
                    [$module, $action] = array_pad(explode('.', $permission->name, 2), 2, null);

                    return [$permission->name => [
                        'name' => $permission->name,
                        'module' => $module,
                        'action' => $action,
                    ]];
                })
                ->toArray();
        });
    }

    public function clearUserPermissionsCache($userId)
    {
        Cache::forget("user.{$userId}.spatie-permissions");

        foreach (Permission::pluck('name') as $permissionName) {
            Cache::forget("user.{$userId}.spatie-permission.{$permissionName}");
        }
    }

    public function createRole(array $roleData, array $permissions, $createdBy, $ip)
    {
        $role = $this->roleRepository->create([
            'name' => $roleData['name'],
            'guard_name' => $roleData['guard_name'] ?? 'web',
        ]);

        $role->syncPermissions($permissions);

        AuditLog::register(
            $createdBy,
            'role_created',
            'permissions',
            "Rol creado: {$role->name}",
            $ip,
            null,
            ['role_id' => $role->id, 'role_name' => $role->name, 'permissions' => $permissions]
        );

        return ['success' => true, 'role' => $role];
    }

    public function updateRole($roleId, array $roleData, array $permissions, $updatedBy, $ip)
    {
        $role = $this->roleRepository->getRoleWithPermissions($roleId);
        $oldData = $role->toArray();

        $role->update([
            'name' => $roleData['name'],
            'guard_name' => $roleData['guard_name'] ?? $role->guard_name,
        ]);
        $role->syncPermissions($permissions);

        AuditLog::register(
            $updatedBy,
            'role_updated',
            'permissions',
            "Rol actualizado: {$role->name}",
            $ip,
            $oldData,
            $role->fresh('permissions')->toArray()
        );

        foreach ($role->users as $user) {
            $this->clearUserPermissionsCache($user->id);
        }

        return ['success' => true, 'role' => $role->fresh('permissions')];
    }

    public function assignRoleToUser($userId, $roleId, $assignedBy, $ip)
    {
        $user = $this->userRepository->find($userId);
        $role = $this->roleRepository->find($roleId);

        if ($user->hasRole($role->name)) {
            return ['success' => false, 'message' => "El usuario ya tiene el rol {$role->name}."];
        }

        $user->assignRole($role);

        AuditLog::register(
            $assignedBy,
            'role_assigned',
            'permissions',
            "Rol {$role->name} asignado al usuario {$user->username}",
            $ip,
            null,
            ['role_id' => $roleId, 'user_id' => $userId]
        );

        $this->clearUserPermissionsCache($userId);

        return ['success' => true, 'message' => "Rol {$role->name} asignado correctamente."];
    }

    public function removeRoleFromUser($userId, $roleId, $removedBy, $ip)
    {
        $user = $this->userRepository->find($userId);
        $role = $this->roleRepository->find($roleId);

        if (!$user->hasRole($role->name)) {
            return ['success' => false, 'message' => "El usuario no tiene el rol {$role->name}."];
        }

        $user->removeRole($role);

        AuditLog::register(
            $removedBy,
            'role_removed',
            'permissions',
            "Rol {$role->name} removido del usuario {$user->username}",
            $ip,
            ['role_id' => $roleId, 'user_id' => $userId],
            null
        );

        $this->clearUserPermissionsCache($userId);

        return ['success' => true, 'message' => "Rol {$role->name} removido correctamente."];
    }

    private function permissionName(string $module, string $permission): string
    {
        if (str_contains($permission, '.')) {
            return $permission;
        }

        return "{$module}.{$permission}";
    }

    private function userHasExistingPermission($user, string $permissionName): bool
    {
        if (!Permission::where('name', $permissionName)->where('guard_name', $user->guard_name ?? 'web')->exists()) {
            return false;
        }

        return $user->hasPermissionTo($permissionName);
    }
}
