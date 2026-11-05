<?php

namespace Modules\Network\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Network\Entities\AutomationRule;
use Modules\Network\Entities\Router;
use Modules\Network\Entities\Node;

class AutomationRuleController extends Controller
{
    /**
     * Mostrar lista de reglas de automatización
     */
    public function index(Request $request)
    {
        $query = AutomationRule::with('creator');

        // Filtros
        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $rules = $query->latest()->paginate(20);

        return view('network::automation.index', compact('rules'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $routers = Router::active()->get();
        $nodes = Node::active()->get();

        $triggerTypes = [
            'service_request' => 'Solicitud de Servicio',
            'schedule' => 'Programado',
            'threshold' => 'Umbral',
            'event' => 'Evento',
            'manual' => 'Manual'
        ];

        $actionTypes = [
            'router_reboot' => 'Reiniciar Router',
            'bandwidth_adjust' => 'Ajustar Ancho de Banda',
            'send_notification' => 'Enviar Notificación',
            'create_ticket' => 'Crear Ticket',
            'suspend_service' => 'Suspender Servicio',
            'activate_service' => 'Activar Servicio',
            'run_script' => 'Ejecutar Script',
            'multiple_actions' => 'Múltiples Acciones'
        ];

        return view('network::automation.create', compact(
            'routers',
            'nodes',
            'triggerTypes',
            'actionTypes'
        ));
    }

    /**
     * Guardar nueva regla
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:service_request,schedule,threshold,event,manual',
            'action_type' => 'required|in:router_reboot,bandwidth_adjust,send_notification,create_ticket,suspend_service,activate_service,run_script,multiple_actions',
            'scope' => 'required|in:all_routers,specific_routers,zone,node',
        ]);

        try {
            $data = $request->all();
            $data['created_by'] = auth()->id();

            $rule = AutomationRule::create($data);

            return redirect()
                ->route('network.automation.show', $rule)
                ->with('success', 'Regla de automatización creada exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al crear regla de automatización', [
                'action' => 'automation_rule.store',
                'data' => $data,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo crear la regla de automatización. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Mostrar detalles de la regla
     */
    public function show(AutomationRule $automation)
    {
        $automation->load(['creator', 'routerLogs']);

        return view('network::automation.show', compact('automation'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(AutomationRule $automation)
    {
        $routers = Router::active()->get();
        $nodes = Node::active()->get();

        $triggerTypes = [
            'service_request' => 'Solicitud de Servicio',
            'schedule' => 'Programado',
            'threshold' => 'Umbral',
            'event' => 'Evento',
            'manual' => 'Manual'
        ];

        $actionTypes = [
            'router_reboot' => 'Reiniciar Router',
            'bandwidth_adjust' => 'Ajustar Ancho de Banda',
            'send_notification' => 'Enviar Notificación',
            'create_ticket' => 'Crear Ticket',
            'suspend_service' => 'Suspender Servicio',
            'activate_service' => 'Activar Servicio',
            'run_script' => 'Ejecutar Script',
            'multiple_actions' => 'Múltiples Acciones'
        ];

        return view('network::automation.edit', compact(
            'automation',
            'routers',
            'nodes',
            'triggerTypes',
            'actionTypes'
        ));
    }

    /**
     * Actualizar regla
     */
    public function update(Request $request, AutomationRule $automation)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:service_request,schedule,threshold,event,manual',
            'action_type' => 'required|in:router_reboot,bandwidth_adjust,send_notification,create_ticket,suspend_service,activate_service,run_script,multiple_actions',
            'scope' => 'required|in:all_routers,specific_routers,zone,node',
        ]);

        try {
            $automation->update($request->all());

            return redirect()
                ->route('network.automation.show', $automation)
                ->with('success', 'Regla actualizada exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al actualizar regla de automatización', [
                'action' => 'automation_rule.update',
                'automation_rule_id' => $automation->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo actualizar la regla de automatización. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Eliminar regla
     */
    public function destroy(AutomationRule $automation)
    {
        try {
            $automation->delete();

            return redirect()
                ->route('network.automation.index')
                ->with('success', 'Regla eliminada exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al eliminar regla de automatización', [
                'action' => 'automation_rule.destroy',
                'automation_rule_id' => $automation->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo eliminar la regla de automatización. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Activar/desactivar regla
     */
    public function toggle(AutomationRule $rule)
    {
        try {
            if ($rule->is_active) {
                $rule->deactivate();
                $message = 'Regla desactivada';
            } else {
                $rule->activate();
                $message = 'Regla activada';
            }

            return redirect()
                ->back()
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Error al cambiar estado de regla de automatización', [
                'action' => 'automation_rule.toggle',
                'automation_rule_id' => $rule->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo cambiar el estado de la regla. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Ejecutar regla manualmente
     */
    public function execute(AutomationRule $rule)
    {
        try {
            // Aquí implementarías la lógica para ejecutar la regla manualmente
            // Por ahora solo incrementamos el contador

            $rule->incrementExecutionCount(true);

            return redirect()
                ->back()
                ->with('success', 'Regla ejecutada manualmente');

        } catch (\Exception $e) {
            \Log::error('Error al ejecutar regla de automatización', [
                'action' => 'automation_rule.execute',
                'automation_rule_id' => $rule->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo ejecutar la regla de automatización. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Ver historial de ejecuciones
     */
    public function history(AutomationRule $rule)
    {
        $logs = $rule->routerLogs()
            ->with(['router', 'user'])
            ->latest()
            ->paginate(50);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $logs
            ]);
        }

        return view('network::automation.history', compact('rule', 'logs'));
    }
}
