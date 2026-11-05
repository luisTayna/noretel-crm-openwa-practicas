<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class CheckPermission
{
    public function handle(Request $request, Closure $next, $module, $permission)
    {
        if (!Auth::check()) {
            return redirect()->route('core.auth.login');
        }

        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $permissionName = str_contains($permission, '.') ? $permission : "{$module}.{$permission}";
        $managePermissionName = "{$module}.manage";

        if (!$this->userHasExistingPermission($user, $managePermissionName) && !$this->userHasExistingPermission($user, $permissionName)) {
            abort(403, 'No tiene permiso para acceder a este recurso.');
        }

        return $next($request);
    }

    private function userHasExistingPermission($user, string $permissionName): bool
    {
        if (!Permission::where('name', $permissionName)->where('guard_name', $user->guard_name ?? 'web')->exists()) {
            return false;
        }

        return $user->hasPermissionTo($permissionName);
    }
}
