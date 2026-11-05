<?php

namespace Modules\Network\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Network\Entities\ServiceRequest;
use Modules\Network\Jobs\ProcessRouterReboot;
use Modules\Customer\Entities\Customer;

class ServiceRequestApiController extends Controller
{
    /**
     * Crear nueva solicitud de servicio desde el frontend
     */
    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|in:router_reboot,connection_issue,slow_speed,no_internet,other',
            'description' => 'required|string|max:1000',
        ]);

        try {
            // Obtener cliente autenticado
            $user = $request->user();
            $customer = Customer::where('user_id', $user->id)->firstOrFail();

            // Obtener contrato activo
            $contract = $customer->contracts()
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes un contrato activo'
                ], 400);
            }

            // Obtener router asociado al contrato
            $router = $contract->installation?->router ?? null;

            // Crear solicitud de servicio
            $serviceRequest = ServiceRequest::create([
                'customer_id' => $customer->id,
                'router_id' => $router?->id,
                'contract_id' => $contract->id,
                'type' => $request->type,
                'description' => $request->description,
                'customer_notes' => $request->notes,
                'priority' => $this->determinePriority($request->type),
                'status' => 'pending',
                'is_automated' => $this->canBeAutomated($request->type),
            ]);

            // Si es automatizable, disparar job
            if ($serviceRequest->canBeAutomated() && $router) {
                ProcessRouterReboot::dispatch($serviceRequest)->delay(now()->addSeconds(5));

                $message = 'Tu solicitud está siendo procesada automáticamente. Recibirás una notificación en 2-3 minutos.';
            } else {
                $message = 'Tu solicitud ha sido registrada. Un técnico se pondrá en contacto contigo pronto.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'request_id' => $serviceRequest->id,
                    'ticket_number' => $serviceRequest->ticket_number,
                    'status' => $serviceRequest->status,
                    'is_automated' => $serviceRequest->is_automated,
                    'estimated_time' => $serviceRequest->is_automated ? '2-3 minutos' : '30-60 minutos',
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al crear solicitud de servicio', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar tu solicitud. Intenta nuevamente.'
            ], 500);
        }
    }

    /**
     * Obtener mis solicitudes
     */
    public function myRequests(Request $request)
    {
        try {
            $user = $request->user();
            $customer = Customer::where('user_id', $user->id)->firstOrFail();

            $serviceRequests = ServiceRequest::where('customer_id', $customer->id)
                ->with(['router', 'assignedTechnician'])
                ->latest()
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $serviceRequests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener solicitudes'
            ], 500);
        }
    }

    /**
     * Ver detalles de una solicitud por número de ticket
     */
    public function show(Request $request, $ticketNumber)
    {
        try {
            $user = $request->user();
            $customer = Customer::where('user_id', $user->id)->firstOrFail();

            $serviceRequest = ServiceRequest::where('ticket_number', $ticketNumber)
                ->where('customer_id', $customer->id)
                ->with(['router', 'assignedTechnician', 'routerLogs'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'ticket_number' => $serviceRequest->ticket_number,
                    'type' => $serviceRequest->type_label,
                    'status' => $serviceRequest->status_label,
                    'priority' => $serviceRequest->priority_label,
                    'description' => $serviceRequest->description,
                    'resolution_notes' => $serviceRequest->resolution_notes,
                    'created_at' => $serviceRequest->created_at->format('d/m/Y H:i'),
                    'completed_at' => $serviceRequest->completed_at?->format('d/m/Y H:i'),
                    'resolution_time' => $serviceRequest->resolution_time ? "{$serviceRequest->resolution_time} minutos" : null,
                    'technician' => $serviceRequest->assignedTechnician ? [
                        'name' => $serviceRequest->assignedTechnician->name,
                        'phone' => $serviceRequest->assignedTechnician->phone,
                    ] : null,
                    'router' => $serviceRequest->router ? [
                        'name' => $serviceRequest->router->name,
                        'status' => $serviceRequest->router->status_label,
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Solicitud no encontrada'
            ], 404);
        }
    }

    /**
     * Obtener solicitudes pendientes (para admin)
     */
    public function pending(Request $request)
    {
        $serviceRequests = ServiceRequest::pending()
            ->with(['customer', 'router'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $serviceRequests
        ]);
    }

    /**
     * Listar todas las solicitudes (para admin)
     */
    public function index(Request $request)
    {
        $query = ServiceRequest::with(['customer', 'router', 'assignedTechnician']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $serviceRequests = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $serviceRequests
        ]);
    }

    /**
     * Asignar técnico a solicitud
     */
    public function assign(Request $request, ServiceRequest $serviceRequest)
    {
        $request->validate([
            'technician_id' => 'required|exists:users,id',
        ]);

        try {
            $technician = \Modules\Core\Entities\User::findOrFail($request->technician_id);
            $serviceRequest->assignTo($technician);

            return response()->json([
                'success' => true,
                'message' => 'Técnico asignado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al asignar técnico'
            ], 500);
        }
    }

    /**
     * Determinar prioridad según tipo de solicitud
     */
    protected function determinePriority($type)
    {
        return match($type) {
            'no_internet' => 'high',
            'connection_issue' => 'high',
            'slow_speed' => 'medium',
            'router_reboot' => 'medium',
            default => 'low'
        };
    }

    /**
     * Verificar si puede ser automatizado
     */
    protected function canBeAutomated($type)
    {
        return in_array($type, ['router_reboot']);
    }
}
