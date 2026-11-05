<?php

namespace Modules\Services\Services;

use Modules\Services\Repositories\PromotionRepository;
use Modules\Services\Repositories\PlanRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;

class PromotionService
{
    /**
     * @var PromotionRepository
     */
    protected $promotionRepository;

    /**
     * @var PlanRepository
     */
    protected $planRepository;

    /**
     * PromotionService constructor.
     *
     * @param PromotionRepository $promotionRepository
     * @param PlanRepository $planRepository
     */
    public function __construct(
        PromotionRepository $promotionRepository,
        PlanRepository $planRepository
    ) {
        $this->promotionRepository = $promotionRepository;
        $this->planRepository = $planRepository;
    }

    /**
     * Obtener todas las promociones.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function getAllPromotions($onlyActive = false)
    {
        if ($onlyActive) {
            $promotions = $this->promotionRepository->getCurrentPromotions();
        } else {
            $promotions = $this->promotionRepository->getPromotionsForIndex();
        }

        return [
            'success' => true,
            'promotions' => $promotions
        ];
    }

    /**
     * Crear una nueva promoción.
     *
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function createPromotion(array $data, $ip)
    {
        try {
            $planIds = $data['plan_ids'] ?? [];
            unset($data['plan_ids']);

            $promotion = $this->promotionRepository->create($data);

            // Asociar planes si se especificaron
            if (!empty($planIds)) {
                $promotion->plans()->attach($planIds);
            }

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'promotion_created',
                'services',
                "Promoción creada: {$promotion->name}",
                $ip,
                null,
                $promotion->toArray()
            );

            return [
                'success' => true,
                'promotion' => $promotion,
                'message' => 'Promoción creada correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear la promoción: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar una promoción existente.
     *
     * @param int $id
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function updatePromotion($id, array $data, $ip)
    {
        try {
            $promotion = $this->promotionRepository->find($id);
            $oldData = $promotion->toArray();

            $planIds = $data['plan_ids'] ?? null;
            unset($data['plan_ids']);

            $this->promotionRepository->update($id, $data);

            // Actualizar planes asociados si se especificaron
            if ($planIds !== null) {
                $promotion->plans()->sync($planIds);
            }

            $promotion = $this->promotionRepository->find($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'promotion_updated',
                'services',
                "Promoción actualizada: {$promotion->name}",
                $ip,
                $oldData,
                $promotion->toArray()
            );

            return [
                'success' => true,
                'promotion' => $promotion,
                'message' => 'Promoción actualizada correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar la promoción: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Activar o desactivar una promoción.
     *
     * @param int $id
     * @param bool $active
     * @param string $ip
     * @return array
     */
    public function togglePromotionStatus($id, $active, $ip)
    {
        try {
            $promotion = $this->promotionRepository->find($id);
            $oldStatus = $promotion->active;

            $this->promotionRepository->update($id, ['active' => $active]);

            $actionType = $active ? 'promotion_activated' : 'promotion_deactivated';
            $actionDetail = $active ? "Promoción activada: {$promotion->name}" : "Promoción desactivada: {$promotion->name}";

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

            $message = $active ? 'Promoción activada correctamente.' : 'Promoción desactivada correctamente.';

            return [
                'success' => true,
                'message' => $message
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cambiar el estado de la promoción: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una promoción.
     *
     * @param int $id
     * @param string $ip
     * @return array
     */
    public function deletePromotion($id, $ip)
    {
        try {
            $promotion = $this->promotionRepository->find($id);
            $promotionData = $promotion->toArray();

            // Desasociar todos los planes
            $promotion->plans()->detach();

            $this->promotionRepository->delete($id);

            // Registrar acción
            AuditLog::register(
                Auth::id(),
                'promotion_deleted',
                'services',
                "Promoción eliminada: {$promotion->name}",
                $ip,
                $promotionData,
                null
            );

            return [
                'success' => true,
                'message' => 'Promoción eliminada correctamente.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la promoción: ' . $e->getMessage()
            ];
        }
    }
}
