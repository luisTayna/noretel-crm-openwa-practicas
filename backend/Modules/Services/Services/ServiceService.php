<?php

namespace Modules\Services\Services;

use Modules\Services\Repositories\ServiceRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;

class ServiceService
{
    /**
     * @var ServiceRepository
     */
    protected $serviceRepository;

    /**
     * ServiceService constructor.
     *
     * @param ServiceRepository $serviceRepository
     */
    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Obtener todos los servicios.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function getAllServices($onlyActive = false)
    {
        if ($onlyActive) {
            $services = $this->serviceRepository->getActiveServices();
        } else {
            $services = $this->serviceRepository->getServicesForIndex();
        }

        return [
            'success' => true,
            'services' => $services
        ];
    }

    /**
     * Obtener servicios con sus planes asociados.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function getServicesWithPlans($onlyActive = true)
    {
        $services = $this->serviceRepository->getServicesWithPlans($onlyActive);

        return [
            'success' => true,
            'services' => $services
        ];
    }

    /**
     * Crear un nuevo servicio.
     *
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function createService(array $data, $ip)
    {
        try {
            $service = $this->serviceRepository->create($data);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'service_created',
                'services',
                "Servicio creado: {$service->name}",
                $ip,
                null,
                $service->toArray()
            );

            return [
                'success' => true,
                'service' => $service,
                'message' => 'Servicio creado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear el servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un servicio existente.
     *
     * @param int $id
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function updateService($id, array $data, $ip)
    {
        try {
            $service = $this->serviceRepository->find($id);
            $oldData = $service->toArray();

            $this->serviceRepository->update($id, $data);
            $service = $this->serviceRepository->find($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'service_updated',
                'services',
                "Servicio actualizado: {$service->name}",
                $ip,
                $oldData,
                $service->toArray()
            );

            return [
                'success' => true,
                'service' => $service,
                'message' => 'Servicio actualizado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Activar o desactivar un servicio.
     *
     * @param int $id
     * @param bool $active
     * @param string $ip
     * @return array
     */
    public function toggleServiceStatus($id, $active, $ip)
    {
        try {
            $service = $this->serviceRepository->find($id);
            $oldStatus = $service->active;

            $this->serviceRepository->update($id, ['active' => $active]);

            $actionType = $active ? 'service_activated' : 'service_deactivated';
            $actionDetail = $active ? "Servicio activado: {$service->name}" : "Servicio desactivado: {$service->name}";

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                $actionType,
                'services',
                $actionDetail,
                $ip,
                ['active' => $oldStatus],
                ['active' => $active]
            );

            $message = $active ? 'Servicio activado correctamente.' : 'Servicio desactivado correctamente.';

            return [
                'success' => true,
                'message' => $message
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar el estado del servicio: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un servicio.
     *
     * @param int $id
     * @param string $ip
     * @return array
     */
    public function deleteService($id, $ip)
    {
        try {
            $service = $this->serviceRepository->find($id);

            // Verificar si tiene planes o servicios adicionales asociados
            if ($service->plans()->count() > 0 || $service->additionalServices()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'No se puede eliminar el servicio porque tiene planes o servicios adicionales asociados.'
                ];
            }

            $serviceData = $service->toArray();
            $this->serviceRepository->delete($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'service_deleted',
                'services',
                "Servicio eliminado: {$service->name}",
                $ip,
                $serviceData,
                null
            );

            return [
                'success' => true,
                'message' => 'Servicio eliminado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el servicio: ' . $e->getMessage()
            ];
        }
    }
}
