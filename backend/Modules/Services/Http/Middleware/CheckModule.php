<?php

namespace Modules\Services\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckModule
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $module
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $module)
    {
        if (!Auth::check()) {
            return redirect()->route('core.auth.login');
        }

        // Verificar si el usuario puede acceder a este módulo
        if (!Auth::user()->canViewModule($module)) {
            abort(403, 'No tiene permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
