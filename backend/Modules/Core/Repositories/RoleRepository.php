<?php

namespace Modules\Core\Repositories;

use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function getActiveRoles()
    {
        return $this->model->orderBy('name')->get();
    }

    public function getRoleWithPermissions($id)
    {
        return $this->model->with(['permissions', 'users'])->findOrFail($id);
    }

    public function findByName($name)
    {
        return $this->model->where('name', $name)->first();
    }

    public function syncPermissions($roleId, array $permissions)
    {
        $role = $this->find($roleId);
        $permissionNames = collect($permissions)
            ->map(function ($permission) {
                if (is_string($permission)) {
                    return $permission;
                }

                if (isset($permission['name'])) {
                    return $permission['name'];
                }

                if (isset($permission['module'], $permission['action'])) {
                    return $permission['module'] . '.' . $permission['action'];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();

        $role->syncPermissions($permissionNames);

        return $role->fresh('permissions');
    }

    public function assignToUser($roleId, $userId)
    {
        $role = $this->find($roleId);
        $user = \Modules\Core\Entities\User::findOrFail($userId);
        $user->assignRole($role);
    }

    public function removeFromUser($roleId, $userId)
    {
        $role = $this->find($roleId);
        $user = \Modules\Core\Entities\User::findOrFail($userId);
        $user->removeRole($role);
    }
}
