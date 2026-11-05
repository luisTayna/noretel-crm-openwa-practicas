<?php

namespace Modules\Services\Services;

use Modules\Services\Repositories\AdditionalServiceRepository;
use Modules\Services\Repositories\ServiceRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;

class AdditionalServiceService
{
    /**
     * @var AdditionalServiceRepository
     */
    protected $additionalServiceRepository;

    /**
     * @var ServiceRepository
     */
    protected $serviceRepository;

    /**
     * AdditionalServiceService constructor.
     *
     * @param AdditionalServiceRepository $additionalServiceRepository
     * @param ServiceRepository $serviceRepository
     */
    public function __construct(
        AdditionalServiceRepository $additionalServiceRepository,
        ServiceRepository $serviceRepository
    ) {
        $this->additionalServiceRepository = $additionalServiceRepository;
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Obtener todos los servicios adicionales.
     *
     * @return array
     */
    public function getAllAdditionalServices()
    {
        $services = $this->additionalServiceRepository->all();

        return [
            'success' => true,
            'additionalServices' => $services
        ];
    }

    /**
     * Obtener servicios adicionales por servicio.
     *
     * @param int $serviceId
     * @return array
     */
    public function getByService($serviceId)
    {
        try {
            // Verificar que el servicio existe
            $service = $this->serviceRepository->find($serviceId);

            $additionalServices = $this->additionalServiceRepository->getByService($serviceId);

            return [
                'success' => true,
                'service' => $service,
                'additionalServices' => $additionalServices
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener los servicios adicionales: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crear un nuevo servicio adicional.
     *
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function createAdditionalService(array $data, $ip)
    {
        try {
            // Verificar que el servicio existe
            $this->serviceRepository->find($data['service_id']);

            $additionalService = $this->additionalServiceRepository->create($data);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'additional_service_created',
                'services',
                "Servicio adicional creado: {$additionalService->name}",
                $ip,
                null,
                $additionalService->toArray()
            );

            return [
                'success' => true,
                'additionalService' => $additionalService,
                'message' => 'Servicio adicional creado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear el servicio adicional: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un servicio adicional existente.
     *
     * @param int $id
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function updateAdditionalService($id, array $data, $ip)
    {
        try {
            $additionalService = $this->additionalServiceRepository->find($id);
            $oldData = $additionalService->toArray();

            $this->additionalServiceRepository->update($id, $data);
            $additionalService = $this->additionalServiceRepository->find($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'additional_service_updated',
                'services',
                "Servicio adicional actualizado: {$additionalService->name}",
                $ip,
                $oldData,
                $additionalService->toArray()
            );

            return [
                'success' => true,
                'additionalService' => $additionalService,
                'message' => 'Servicio adicional actualizado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el servicio adicional: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un servicio adicional.
     *
     * @param int $id
     * @param string $ip
     * @return array
     */
    public function deleteAdditionalService($id, $ip)
    {
        try {
            $additionalService = $this->additionalServiceRepository->find($id);

            // Verificar si tiene servicios contratados asociados
            if ($additionalService->contractedServices()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'No se puede eliminar el servicio adicional porque está en uso en contratos activos.'
                ];
            }

            $serviceData = $additionalService->toArray();
            $this->additionalServiceRepository->delete($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'additional_service_deleted',
                'services',
                "Servicio adicional eliminado: {$additionalService->name}",
                $ip,
                $serviceData,
                null
            );

            return [
                'success' => true,
                'message' => 'Servicio adicional eliminado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el servicio adicional: ' . $e->getMessage()
            ];
        }
    }
}
