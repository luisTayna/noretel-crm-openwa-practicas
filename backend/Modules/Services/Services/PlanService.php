<?php

namespace Modules\Services\Services;

use Modules\Services\Repositories\PlanRepository;
use Modules\Services\Repositories\ServiceRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;

class PlanService
{
    /**
     * @var PlanRepository
     */
    protected $planRepository;

    /**
     * @var ServiceRepository
     */
    protected $serviceRepository;

    /**
     * PlanService constructor.
     *
     * @param PlanRepository $planRepository
     * @param ServiceRepository $serviceRepository
     */
    public function __construct(
        PlanRepository $planRepository,
        ServiceRepository $serviceRepository
    ) {
        $this->planRepository = $planRepository;
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Obtener todos los planes.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function getAllPlans($onlyActive = false)
    {
        if ($onlyActive) {
            $plans = $this->planRepository->getActivePlans();
        } else {
            $plans = $this->planRepository->getPlansForIndex();
        }

        return [
            'success' => true,
            'plans' => $plans
        ];
    }

    /**
     * Obtener planes por servicio.
     *
     * @param int $serviceId
     * @param bool $onlyActive
     * @return array
     */
    public function getPlansByService($serviceId, $onlyActive = true)
    {
        try {
            // Verificar que el servicio existe
            $service = $this->serviceRepository->find($serviceId);

            $plans = $this->planRepository->getPlansByService($serviceId, $onlyActive);

            return [
                'success' => true,
                'service' => $service,
                'plans' => $plans
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener los planes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener planes con sus promociones.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function getPlansWithPromotions($onlyActive = true)
    {
        $plans = $this->planRepository->getPlansWithPromotions($onlyActive);

        return [
            'success' => true,
            'plans' => $plans
        ];
    }

    /**
     * Crear un nuevo plan.
     *
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function createPlan(array $data, $ip)
    {
        try {
            // Verificar que el servicio existe
            $this->serviceRepository->find($data['service_id']);

            $plan = $this->planRepository->create($data);

            // Asignar promociones si se especificaron
            if (isset($data['promotion_ids']) && is_array($data['promotion_ids'])) {
                $this->planRepository->syncPromotions($plan->id, $data['promotion_ids']);
            }

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'plan_created',
                'services',
                "Plan creado: {$plan->name}",
                $ip,
                null,
                $plan->toArray()
            );

            return [
                'success' => true,
                'plan' => $plan,
                'message' => 'Plan creado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear el plan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un plan existente.
     *
     * @param int $id
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function updatePlan($id, array $data, $ip)
    {
        try {
            $plan = $this->planRepository->find($id);
            $oldData = $plan->toArray();

            $this->planRepository->update($id, $data);

            // Actualizar promociones si se especificaron
            if (isset($data['promotion_ids']) && is_array($data['promotion_ids'])) {
                $this->planRepository->syncPromotions($id, $data['promotion_ids']);
            }

            $plan = $this->planRepository->find($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'plan_updated',
                'services',
                "Plan actualizado: {$plan->name}",
                $ip,
                $oldData,
                $plan->toArray()
            );

            return [
                'success' => true,
                'plan' => $plan,
                'message' => 'Plan actualizado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar el plan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Activar o desactivar un plan.
     *
     * @param int $id
     * @param bool $active
     * @param string $ip
     * @return array
     */
    public function togglePlanStatus($id, $active, $ip)
    {
        try {
            $plan = $this->planRepository->find($id);
            $oldStatus = $plan->active;

            $this->planRepository->update($id, ['active' => $active]);

            $actionType = $active ? 'plan_activated' : 'plan_deactivated';
            $actionDetail = $active ? "Plan activado: {$plan->name}" : "Plan desactivado: {$plan->name}";

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

            $message = $active ? 'Plan activado correctamente.' : 'Plan desactivado correctamente.';

            return [
                'success' => true,
                'message' => $message
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar el estado del plan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un plan.
     *
     * @param int $id
     * @param string $ip
     * @return array
     */
    public function deletePlan($id, $ip)
    {
        try {
            $plan = $this->planRepository->find($id);

            // Verificar si tiene contratos asociados
            if ($plan->contracts()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'No se puede eliminar el plan porque tiene contratos asociados.'
                ];
            }

            $planData = $plan->toArray();
            $this->planRepository->delete($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'plan_deleted',
                'services',
                "Plan eliminado: {$plan->name}",
                $ip,
                $planData,
                null
            );

            return [
                'success' => true,
                'message' => 'Plan eliminado correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el plan: ' . $e->getMessage()
            ];
        }
    }
}
