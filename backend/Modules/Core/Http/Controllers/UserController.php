<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Repositories\UserRepository;
use Modules\Core\Repositories\RoleRepository;
use Modules\Core\Services\PermissionService;
use Modules\Core\Http\Requests\StoreUserRequest;
use Modules\Core\Http\Requests\UpdateUserRequest;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Entities\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var RoleRepository
     */
    protected $roleRepository;

    /**
     * @var PermissionService
     */
    protected $permissionService;

    /**
     * UserController constructor.
     *
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param PermissionService $permissionService
     */
    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        PermissionService $permissionService
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $role = $request->get('role');
        $perPage = $request->get('per_page', 10);

        // Cargar usuarios con sus roles, excluyendo al rol 'customer'
        $query = $this->userRepository->query()->with(['employee', 'roles'])
                      ->whereDoesntHave('roles', function($q) {
                          $q->where('name', 'customer');
                      });

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($role) {
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->paginate($perPage);
        
        $roles = $this->assignableRolesFor(Auth::user());

        return view('core::users.index', compact('users', 'search', 'status', 'roles', 'role'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $roles = $this->assignableRolesFor(Auth::user());
        $roleLabels = $this->roleLabels();

        return view('core::users.create', compact('roles', 'roleLabels'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreUserRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $roleNames = $this->validatedAssignableRoleNames($request->input('roles', []), Auth::user());

        $user = DB::transaction(function () use ($data, $roleNames) {
            $employee = Employee::create($this->employeeData($data));

            $user = $this->userRepository->create($this->userData($data, $employee->id));
            $user->syncRoles($roleNames);

            return $user->fresh(['employee', 'roles']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Registrar acción
        AuditLog::register(
            Auth::id(),
            'user_created',
            'users',
            "Usuario creado: {$user->username}",
            $request->ip(),
            null,
            $user->toArray()
        );

        return redirect()->route('core.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $user = $this->userRepository->query()->with(['employee', 'roles.permissions'])->findOrFail($id);

        // Obtener logs de auditoría del usuario
        $logs = AuditLog::where('user_id', $id)
            ->orderBy('action_date', 'desc')
            ->limit(50)
            ->get();

        return view('core::users.show', compact('user', 'logs'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $user = $this->userRepository->query()->with(['employee', 'roles'])->findOrFail($id);
        $this->authorizeManageTargetUser($user, Auth::user());
        $roles = $this->assignableRolesFor(Auth::user());
        $userRoles = $user->roles->pluck('id')->toArray();

        $roleLabels = $this->roleLabels();

        return view('core::users.edit', compact('user', 'roles', 'userRoles', 'roleLabels'));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateUserRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = $this->userRepository->find($id);
        $this->authorizeManageTargetUser($user, Auth::user());
        $data = $request->validated();
        $roleNames = $this->validatedAssignableRoleNames($request->input('roles', []), Auth::user());

        // // Si se proporciona una nueva contraseña, hashearla
        // if (!empty($data['password'])) {
        //     $data['password'] = Hash::make($data['password']);
        // } else {
        //     unset($data['password']);
        // }

        // Guardar datos anteriores para auditoría
        $oldData = $user->toArray();

        DB::transaction(function () use ($user, $id, $data, $roleNames) {
            $employee = $user->employee;

            if ($employee) {
                $employee->update($this->employeeData($data));
            } else {
                $employee = Employee::create($this->employeeData($data));
            }

            $this->userRepository->update($id, $this->userData($data, $employee->id, false));

            $user->refresh();
            $user->syncRoles($roleNames);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Registrar acción
        AuditLog::register(
            Auth::id(),
            'user_updated',
            'users',
            "Usuario actualizado: {$user->username}",
            $request->ip(),
            $oldData,
            $user->fresh()->toArray()
        );

        return redirect()->route('core.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $this->userRepository->find($id);

        // No permitir eliminar el propio usuario
        if ($id == Auth::id()) {
            return redirect()->route('core.users.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        // Guardar datos para auditoría
        $userData = $user->toArray();

        // Eliminar usuario
        $this->userRepository->delete($id);

        // Registrar acción
        AuditLog::register(
            Auth::id(),
            'user_deleted',
            'users',
            "Usuario eliminado: {$user->username}",
            $request->ip(),
            $userData,
            null
        );

        return redirect()->route('core.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Activate a user
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function activate($id, Request $request)
    {
        $user = $this->userRepository->find($id);

        // Actualizar estado
        $this->userRepository->update($id, ['status' => 'active']);

        // Registrar acción
        AuditLog::register(
            Auth::id(),
            'user_activated',
            'users',
            "Usuario activado: {$user->username}",
            $request->ip(),
            ['status' => $user->status],
            ['status' => 'active']
        );

        return redirect()->route('core.users.index')
            ->with('success', 'Usuario activado correctamente.');
    }

    /**
     * Deactivate a user
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deactivate($id, Request $request)
    {
        $user = $this->userRepository->find($id);

        // No permitir desactivar el propio usuario
        if ($id == Auth::id()) {
            return redirect()->route('core.users.index')
                ->with('error', 'No puedes desactivar tu propio usuario.');
        }

        // Actualizar estado
        $this->userRepository->update($id, ['status' => 'inactive']);

        // Registrar acción
        AuditLog::register(
            Auth::id(),
            'user_deactivated',
            'users',
            "Usuario desactivado: {$user->username}",
            $request->ip(),
            ['status' => $user->status],
            ['status' => 'inactive']
        );

        return redirect()->route('core.users.index')
            ->with('success', 'Usuario desactivado correctamente.');
    }

    /**
     * Get user permissions
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermissions(Request $request)
    {
        $permissions = $this->permissionService->getUserPermissions(Auth::id());

        return response()->json([
            'success' => true,
            'permissions' => $permissions
        ]);
    }

    /**
     * Internal staff roles that can be assigned from the user form.
     *
     * Read from the database instead of a fixed list: the list used to be
     * hardcoded, so every new role existed in the database but never reached the
     * form, and there was no way to assign it from the panel.
     *
     * Only 'customer' is excluded, because it belongs to portal accounts and is
     * not internal staff.
     */
    private function internalRoleNames(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', ['customer'])
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function assignableRoleNamesFor($actor): array
    {
        if ($actor?->hasRole('admin')) {
            return $this->internalRoleNames();
        }

        return [];
    }

    private function authorizeManageTargetUser($target, $actor): void
    {
        if ($actor?->hasRole('admin')) {
            return;
        }

        abort(403, 'No tienes permiso para administrar este usuario.');
    }

    private function assignableRolesFor($actor)
    {
        return Role::whereIn('name', $this->assignableRoleNamesFor($actor))
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * Spanish labels for the role picker. Role names are technical identifiers,
     * and the people using this form are not the ones who chose them.
     */
    private function roleLabels(): array
    {
        return [
            'admin' => 'Administrador',
            'sales' => 'Ventas',
            'technician' => 'Técnico',
            'collections' => 'Cobranzas',
        ];
    }

    private function validatedAssignableRoleNames(array $roleIds, $actor): array
    {
        $roles = Role::whereIn('id', $roleIds)->pluck('name', 'id');
        $allowed = $this->assignableRoleNamesFor($actor);

        if ($roles->isEmpty() || $roles->diff($allowed)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'No tienes permiso para asignar uno de los roles seleccionados.',
            ]);
        }

        return $roles->values()->all();
    }

    private function employeeData(array $data): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'position' => $data['position'],
            'department' => $data['department'],
            'status' => $data['employee_status'],
            'hire_date' => $data['hire_date'],
        ];
    }

    private function userData(array $data, int $employeeId, bool $includePassword = true): array
    {
        $userData = [
            'employee_id' => $employeeId,
            'username' => $data['username'],
            'email' => $data['email'],
            'status' => $data['status'],
            'requires_2fa' => (bool) ($data['requires_2fa'] ?? false),
            'preferences' => $data['preferences'] ?? null,
        ];

        if ($includePassword || !empty($data['password'])) {
            $userData['password'] = $data['password'];
        }

        return $userData;
    }
}
