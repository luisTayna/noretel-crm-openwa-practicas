<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\SecurityPolicy;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Events\SecurityPolicyChanged;
use Modules\Core\Http\Requests\SecurityPolicyRequest;
use Illuminate\Support\Facades\Auth;

class SecurityPolicyController extends Controller
{
    /**
     * Display a listing of security policies.
     *
     * @return Renderable
     */
    public function index()
    {
        $policies = SecurityPolicy::orderBy('policy_type')
            ->orderBy('update_date', 'desc')
            ->paginate(15);

        return view('core::security.index', [
            'policies' => $policies
        ]);
    }

    /**
     * Show the form for creating a new security policy.
     *
     * @return Renderable
     */
    public function create()
    {
        $policyTypes = [
            'password' => 'Política de contraseñas',
            'login' => 'Política de inicio de sesión',
            'account_lockout' => 'Política de bloqueo de cuenta',
            'session' => 'Política de sesión',
            'api' => 'Política de API',
            'file_upload' => 'Política de carga de archivos'
        ];

        return view('core::security.create', [
            'policyTypes' => $policyTypes
        ]);
    }

    /**
     * Store a newly created security policy.
     *
     * @param SecurityPolicyRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(SecurityPolicyRequest $request)
    {
        $data = $request->validated();

        $policy = SecurityPolicy::updatePolicy(
            $data['policy_type'],
            $data['configuration'],
            $data['name'],
            $data['active'] ?? false
        );

        // Registrar auditoría
        AuditLog::register(
            Auth::id(),
            'policy_created',
            'security',
            "Política de seguridad creada: {$policy->name}",
            $request->ip(),
            null,
            $policy->toArray()
        );

        // Disparar evento
        event(new SecurityPolicyChanged($policy, Auth::user()));

        return redirect()->route('core.security.index')
            ->with('success', 'Política de seguridad creada correctamente');
    }

    /**
     * Display the specified security policy.
     *
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $policy = SecurityPolicy::findOrFail($id);

        // Obtener versiones anteriores de la misma política
        $previousVersions = SecurityPolicy::where('policy_type', $policy->policy_type)
            ->where('id', '!=', $policy->id)
            ->orderBy('update_date', 'desc')
            ->get();

        return view('core::security.show', [
            'policy' => $policy,
            'previousVersions' => $previousVersions
        ]);
    }

    /**
     * Show the form for editing the specified security policy.
     *
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $policy = SecurityPolicy::findOrFail($id);

        $policyTypes = [
            'password' => 'Política de contraseñas',
            'login' => 'Política de inicio de sesión',
            'account_lockout' => 'Política de bloqueo de cuenta',
            'session' => 'Política de sesión',
            'api' => 'Política de API',
            'file_upload' => 'Política de carga de archivos'
        ];

        return view('core::security.edit', [
            'policy' => $policy,
            'policyTypes' => $policyTypes
        ]);
    }

    /**
     * Update the specified security policy.
     *
     * @param SecurityPolicyRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(SecurityPolicyRequest $request, $id)
    {
        $oldPolicy = SecurityPolicy::findOrFail($id);
        $data = $request->validated();

        // La actualización de política siempre crea una nueva versión
        $policy = SecurityPolicy::updatePolicy(
            $data['policy_type'],
            $data['configuration'],
            $data['name'],
            $data['active'] ?? false
        );

        // Registrar auditoría
        AuditLog::register(
            Auth::id(),
            'policy_updated',
            'security',
            "Política de seguridad actualizada: {$policy->name}",
            $request->ip(),
            $oldPolicy->toArray(),
            $policy->toArray()
        );

        // Disparar evento
        event(new SecurityPolicyChanged($policy, Auth::user()));

        return redirect()->route('core.security.index')
            ->with('success', 'Política de seguridad actualizada correctamente');
    }

    /**
     * Activate the specified security policy.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate($id)
    {
        $policy = SecurityPolicy::findOrFail($id);

        // Desactivar las políticas activas del mismo tipo
        SecurityPolicy::where('policy_type', $policy->policy_type)
            ->where('active', true)
            ->update(['active' => false]);

        // Activar la política actual
        $policy->update(['active' => true]);

        // Registrar auditoría
        AuditLog::register(
            Auth::id(),
            'policy_activated',
            'security',
            "Política de seguridad activada: {$policy->name}",
            request()->ip(),
            ['active' => false],
            ['active' => true]
        );

        // Disparar evento
        event(new SecurityPolicyChanged($policy, Auth::user()));

        return redirect()->route('core.security.index')
            ->with('success', 'Política de seguridad activada correctamente');
    }

    /**
     * Deactivate the specified security policy.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivate($id)
    {
        $policy = SecurityPolicy::findOrFail($id);

        // Desactivar la política
        $policy->update(['active' => false]);

        // Registrar auditoría
        AuditLog::register(
            Auth::id(),
            'policy_deactivated',
            'security',
            "Política de seguridad desactivada: {$policy->name}",
            request()->ip(),
            ['active' => true],
            ['active' => false]
        );

        // Disparar evento
        event(new SecurityPolicyChanged($policy, Auth::user()));

        return redirect()->route('core.security.index')
            ->with('success', 'Política de seguridad desactivada correctamente');
    }

    /**
     * Test a password against the current password policy.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $result = SecurityPolicy::validatePassword($request->password);

        if ($result === true) {
            return response()->json([
                'valid' => true,
                'message' => 'La contraseña cumple con la política de seguridad'
            ]);
        }

        return response()->json([
            'valid' => false,
            'errors' => $result
        ]);
    }
}
