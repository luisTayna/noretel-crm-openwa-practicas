<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\AssignRoleRequest;
use Modules\Core\Http\Requests\StoreRoleRequest;
use Modules\Core\Http\Requests\SyncPermissionsRequest;
use Modules\Core\Http\Requests\UpdateRoleRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Renderable
    {
        $search = $request->get('search');
        $perPage = $request->get('per_page', 10);

        $query = Role::with(['permissions', 'users'])->orderBy('name');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->paginate($perPage);

        return view('core::roles.index', compact('roles', 'search'));
    }

    public function create(): Renderable
    {
        $modules = $this->getAvailableModules();
        $this->ensurePermissionsExist($modules);

        return view('core::roles.create', compact('modules'));
    }

    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();
        $modules = $this->getAvailableModules();
        $this->ensurePermissionsExist($modules);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        $permissionNames = $this->normalizePermissionNames($data['permissions'] ?? []);
        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(
            Auth::id(),
            'role_created',
            'roles',
            "Rol creado: {$role->name}",
            $request->ip(),
            null,
            ['role' => $role->toArray(), 'permissions' => $permissionNames]
        );

        return redirect()->route('core.roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function show($id): Renderable
    {
        $role = Role::with(['permissions', 'users'])->findOrFail($id);
        $users = $role->users()->paginate(10);
        $permissionsByModule = $this->groupPermissionsByModule($role->permissions);
        $availableUsers = User::whereDoesntHave('roles', function ($query) use ($role) {
            $query->where('roles.id', $role->id);
        })->orderBy('username')->get();

        return view('core::roles.show', compact('role', 'users', 'permissionsByModule', 'availableUsers'));
    }

    public function edit($id): Renderable
    {
        $role = Role::with('permissions')->findOrFail($id);
        $modules = $this->getAvailableModules();
        $this->ensurePermissionsExist($modules);
        $currentPermissions = $role->permissions->pluck('name')->flip()->map(fn () => true)->toArray();

        return view('core::roles.edit', compact('role', 'modules', 'currentPermissions'));
    }

    public function update(UpdateRoleRequest $request, $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $data = $request->validated();
        $oldData = $role->toArray();

        $role->update([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        $permissionNames = $this->normalizePermissionNames($data['permissions'] ?? []);
        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(
            Auth::id(),
            'role_updated',
            'roles',
            "Rol actualizado: {$role->name}",
            $request->ip(),
            $oldData,
            $role->fresh('permissions')->toArray()
        );

        return redirect()->route('core.roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy($id, Request $request)
    {
        $role = Role::with('users')->findOrFail($id);

        if ($role->users()->count() > 0) {
            return redirect()->route('core.roles.index')
                ->with('error', 'No se puede eliminar un rol que está asignado a usuarios.');
        }

        $roleData = $role->toArray();
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(
            Auth::id(),
            'role_deleted',
            'roles',
            "Rol eliminado: {$roleData['name']}",
            $request->ip(),
            $roleData,
            null
        );

        return redirect()->route('core.roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }

    public function syncPermissions(SyncPermissionsRequest $request, $id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $data = $request->validated();
        $oldPermissions = $role->permissions->pluck('name')->values()->all();
        $permissionNames = $this->normalizePermissionNames($data['permissions'] ?? []);

        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(
            Auth::id(),
            'permissions_synced',
            'roles',
            "Permisos sincronizados para el rol: {$role->name}",
            $request->ip(),
            ['permissions' => $oldPermissions],
            ['permissions' => $permissionNames]
        );

        return redirect()->route('core.roles.show', $id)
            ->with('success', 'Permisos actualizados correctamente.');
    }

    public function assignToUser(AssignRoleRequest $request)
    {
        $data = $request->validated();
        $user = User::findOrFail($data['user_id']);
        $role = Role::findOrFail($data['role_id']);

        if ($user->hasRole($role->name)) {
            return back()->with('error', "El usuario ya tiene el rol {$role->name}.");
        }

        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(Auth::id(), 'role_assigned', 'permissions', "Rol {$role->name} asignado al usuario {$user->username}", $request->ip(), null, ['role_id' => $role->id, 'user_id' => $user->id]);

        return back()->with('success', "Rol {$role->name} asignado correctamente.");
    }

    public function removeFromUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        if (!$user->hasRole($role->name)) {
            return back()->with('error', "El usuario no tiene el rol {$role->name}.");
        }

        $user->removeRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLog::register(Auth::id(), 'role_removed', 'permissions', "Rol {$role->name} removido del usuario {$user->username}", $request->ip(), ['role_id' => $role->id, 'user_id' => $user->id], null);

        return back()->with('success', "Rol {$role->name} removido correctamente.");
    }

    private function normalizePermissionNames(array $permissions): array
    {
        return collect($permissions)
            ->map(fn ($permission) => str_replace('|', '.', $permission))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function groupPermissionsByModule($permissions)
    {
        return $permissions
            ->sortBy('name')
            ->groupBy(fn ($permission) => explode('.', $permission->name)[0] ?? 'general');
    }

    private function ensurePermissionsExist(array $modules): void
    {
        foreach ($modules as $moduleKey => $module) {
            foreach (array_keys($module['actions']) as $actionKey) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$actionKey}",
                    'guard_name' => 'web',
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function getAvailableModules(): array
    {
        return [
            'operations' => ['name' => 'Operaciones', 'actions' => ['view' => 'Ver operaciones', 'create' => 'Crear operaciones', 'edit' => 'Editar operaciones', 'manage' => 'Gestionar operaciones']],
            'onboarding_orders' => ['name' => 'Órdenes de alta', 'actions' => ['view' => 'Ver órdenes', 'create' => 'Crear órdenes', 'edit' => 'Editar órdenes', 'delete' => 'Eliminar órdenes', 'manage' => 'Gestionar órdenes']],
            'customers' => ['name' => 'Clientes', 'actions' => ['view' => 'Ver clientes', 'create' => 'Crear clientes', 'edit' => 'Editar clientes', 'delete' => 'Eliminar clientes', 'manage' => 'Gestionar clientes']],
            'contracts' => ['name' => 'Contratos', 'actions' => ['view' => 'Ver contratos', 'create' => 'Crear contratos', 'edit' => 'Editar contratos', 'delete' => 'Eliminar contratos', 'manage' => 'Gestionar contratos']],
            'installations' => ['name' => 'Instalaciones', 'actions' => ['view' => 'Ver instalaciones', 'create' => 'Crear instalaciones', 'edit' => 'Editar instalaciones', 'delete' => 'Eliminar instalaciones', 'manage' => 'Gestionar instalaciones']],
            'routes' => ['name' => 'Rutas', 'actions' => ['view' => 'Ver rutas', 'create' => 'Crear rutas', 'edit' => 'Editar rutas', 'delete' => 'Eliminar rutas', 'manage' => 'Gestionar rutas']],
            'routers' => ['name' => 'Routers / OLT', 'actions' => ['view' => 'Ver routers/OLT', 'create' => 'Crear routers/OLT', 'edit' => 'Editar routers/OLT', 'delete' => 'Eliminar routers/OLT', 'manage' => 'Gestionar routers/OLT']],
            'service_requests' => ['name' => 'Solicitudes técnicas', 'actions' => ['view' => 'Ver solicitudes', 'create' => 'Crear solicitudes', 'edit' => 'Editar solicitudes', 'delete' => 'Eliminar solicitudes', 'manage' => 'Gestionar solicitudes']],
            'slas' => ['name' => 'SLA', 'actions' => ['view' => 'Ver SLA', 'create' => 'Crear SLA', 'edit' => 'Editar SLA', 'delete' => 'Eliminar SLA', 'manage' => 'Gestionar SLA']],
            'invoices' => ['name' => 'Facturas', 'actions' => ['view' => 'Ver facturas', 'create' => 'Crear facturas', 'edit' => 'Editar facturas', 'delete' => 'Eliminar facturas', 'void' => 'Anular facturas', 'manage' => 'Gestionar facturas']],
            'payments' => ['name' => 'Pagos', 'actions' => ['view' => 'Ver pagos', 'create' => 'Crear pagos', 'edit' => 'Editar pagos', 'delete' => 'Eliminar pagos', 'void' => 'Anular pagos', 'manage' => 'Gestionar pagos']],
            'credit_notes' => ['name' => 'Notas de crédito', 'actions' => ['view' => 'Ver notas', 'create' => 'Crear notas', 'edit' => 'Editar notas', 'delete' => 'Eliminar notas', 'manage' => 'Gestionar notas']],
            'services' => ['name' => 'Servicios', 'actions' => ['view' => 'Ver servicios', 'create' => 'Crear servicios', 'edit' => 'Editar servicios', 'delete' => 'Eliminar servicios', 'manage' => 'Gestionar servicios']],
            'plans' => ['name' => 'Planes', 'actions' => ['view' => 'Ver planes', 'create' => 'Crear planes', 'edit' => 'Editar planes', 'delete' => 'Eliminar planes', 'manage' => 'Gestionar planes']],
            'promotions' => ['name' => 'Promociones', 'actions' => ['view' => 'Ver promociones', 'create' => 'Crear promociones', 'edit' => 'Editar promociones', 'delete' => 'Eliminar promociones', 'manage' => 'Gestionar promociones']],
            'additional-services' => ['name' => 'Servicios adicionales', 'actions' => ['view' => 'Ver servicios adicionales', 'create' => 'Crear servicios adicionales', 'edit' => 'Editar servicios adicionales', 'delete' => 'Eliminar servicios adicionales', 'manage' => 'Gestionar servicios adicionales']],
            'users' => ['name' => 'Usuarios', 'actions' => ['view' => 'Ver usuarios', 'create' => 'Crear usuarios', 'edit' => 'Editar usuarios', 'delete' => 'Eliminar usuarios', 'manage' => 'Gestionar usuarios']],
            'roles' => ['name' => 'Roles y permisos', 'actions' => ['view' => 'Ver roles', 'create' => 'Crear roles', 'edit' => 'Editar roles', 'delete' => 'Eliminar roles', 'manage' => 'Gestionar roles']],
            'configuration' => ['name' => 'Configuración', 'actions' => ['view' => 'Ver configuración', 'edit' => 'Editar configuración', 'delete' => 'Eliminar configuración', 'manage' => 'Gestionar configuración']],
            'notifications' => ['name' => 'Notificaciones', 'actions' => ['view' => 'Ver notificaciones', 'create' => 'Crear notificaciones', 'edit' => 'Editar notificaciones', 'delete' => 'Eliminar notificaciones', 'manage' => 'Gestionar notificaciones']],
            'workflows' => ['name' => 'Workflows', 'actions' => ['view' => 'Ver workflows', 'create' => 'Crear workflows', 'edit' => 'Editar workflows', 'delete' => 'Eliminar workflows', 'execute' => 'Ejecutar workflows', 'manage' => 'Gestionar workflows']],
            'security' => ['name' => 'Seguridad', 'actions' => ['view' => 'Ver seguridad', 'create' => 'Crear seguridad', 'edit' => 'Editar seguridad', 'delete' => 'Eliminar seguridad', 'manage' => 'Gestionar seguridad']],
            'audit' => ['name' => 'Auditoría', 'actions' => ['view' => 'Ver auditoría', 'export' => 'Exportar auditoría', 'manage' => 'Gestionar auditoría']],
        ];
    }
}
