<?php

namespace Modules\Network\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Network\Entities\ServiceRequest;
use Modules\Network\Services\RouterService;
use Modules\Core\Entities\Notification;
use Illuminate\Support\Facades\Log;

class ProcessRouterReboot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $serviceRequest;

    /**
     * Número de intentos
     */
    public $tries = 3;

    /**
     * Timeout en segundos
     */
    public $timeout = 120;

    /**
     * Constructor
     */
    public function __construct(ServiceRequest $serviceRequest)
    {
        $this->serviceRequest = $serviceRequest;
    }

    /**
     * Ejecutar el job
     */
    public function handle(RouterService $routerService)
    {
        try {
            // Marcar como iniciado
            $this->serviceRequest->markAsStarted();

            // Verificar que tengamos un router
            if (!$this->serviceRequest->router) {
                throw new \Exception('No se encontró router asociado a la solicitud');
            }

            $router = $this->serviceRequest->router;

            Log::info("Procesando reinicio de router", [
                'service_request_id' => $this->serviceRequest->id,
                'router_id' => $router->id,
                'router_name' => $router->name
            ]);

            // Ejecutar reinicio
            $result = $routerService->reboot(
                $router, 
                $this->serviceRequest->customer_id,
                $this->serviceRequest
            );

            if ($result['success']) {
                // Marcar como completado
                $this->serviceRequest->markAsCompleted(
                    'Router reiniciado exitosamente. El servicio se restablecerá en 2-3 minutos.'
                );

                // Crear notificación para el cliente
                Notification::create([
                    'user_id' => $this->serviceRequest->customer->user_id ?? null,
                    'type' => 'service_completed',
                    'title' => 'Servicio Completado',
                    'message' => "Tu solicitud de reinicio ha sido procesada exitosamente. Ticket: {$this->serviceRequest->ticket_number}",
                    'data' => [
                        'service_request_id' => $this->serviceRequest->id,
                        'ticket_number' => $this->serviceRequest->ticket_number,
                        'router_name' => $router->name,
                    ],
                    'read' => false,
                ]);

                Log::info("Reinicio de router completado exitosamente", [
                    'service_request_id' => $this->serviceRequest->id,
                    'execution_time' => $result['execution_time'] ?? 0
                ]);

            } else {
                // Marcar como fallido
                $this->serviceRequest->markAsFailed(
                    'Error al reiniciar el router: ' . ($result['error'] ?? 'Error desconocido')
                );

                // Notificar al cliente del fallo
                Notification::create([
                    'user_id' => $this->serviceRequest->customer->user_id ?? null,
                    'type' => 'service_failed',
                    'title' => 'Error en Servicio',
                    'message' => "Hubo un problema al procesar tu solicitud. Un técnico será asignado pronto. Ticket: {$this->serviceRequest->ticket_number}",
                    'data' => [
                        'service_request_id' => $this->serviceRequest->id,
                        'ticket_number' => $this->serviceRequest->ticket_number,
                    ],
                    'read' => false,
                ]);

                // Asignar automáticamente a un técnico si falla
                $this->assignToTechnician();

                Log::error("Fallo al reiniciar router", [
                    'service_request_id' => $this->serviceRequest->id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            // Marcar como fallido
            $this->serviceRequest->markAsFailed(
                'Excepción al procesar solicitud: ' . $e->getMessage()
            );

            // Asignar a técnico
            $this->assignToTechnician();

            Log::error("Excepción al procesar reinicio de router", [
                'service_request_id' => $this->serviceRequest->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar la excepción para que el job se reintente
            throw $e;
        }
    }

    /**
     * Asignar solicitud a un técnico disponible
     */
    protected function assignToTechnician()
    {
        // Buscar técnico disponible (esto se puede mejorar con lógica de asignación inteligente)
        $technician = \Modules\Core\Entities\User::role('tecnico')
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if ($technician) {
            $this->serviceRequest->update([
                'assigned_to' => $technician->id,
                'assigned_at' => now(),
                'requires_visit' => true,
            ]);

            // Notificar al técnico
            Notification::create([
                'user_id' => $technician->id,
                'type' => 'service_assigned',
                'title' => 'Nueva Solicitud Asignada',
                'message' => "Se te ha asignado una nueva solicitud de servicio. Ticket: {$this->serviceRequest->ticket_number}",
                'data' => [
                    'service_request_id' => $this->serviceRequest->id,
                    'ticket_number' => $this->serviceRequest->ticket_number,
                    'type' => $this->serviceRequest->type,
                ],
                'read' => false,
            ]);

            Log::info("Solicitud asignada a técnico", [
                'service_request_id' => $this->serviceRequest->id,
                'technician_id' => $technician->id
            ]);
        }
    }

    /**
     * Manejar fallo del job
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Job ProcessRouterReboot falló definitivamente", [
            'service_request_id' => $this->serviceRequest->id,
            'exception' => $exception->getMessage()
        ]);

        // Marcar solicitud como fallida si no lo está ya
        if ($this->serviceRequest->status !== 'failed') {
            $this->serviceRequest->markAsFailed(
                'El sistema no pudo procesar la solicitud después de múltiples intentos. Se ha asignado a un técnico.'
            );
        }

        // Asignar a técnico si no está asignado
        if (!$this->serviceRequest->assigned_to) {
            $this->assignToTechnician();
        }
    }
}
