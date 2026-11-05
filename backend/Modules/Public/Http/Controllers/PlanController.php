<?php

namespace Modules\Public\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Services\Repositories\PlanRepository;
use Modules\Services\Repositories\PromotionRepository;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Promotion;

class PlanController extends Controller
{
    /**
     * @var PlanRepository
     */
    protected $planRepository;

    /**
     * @var PromotionRepository
     */
    protected $promotionRepository;

    /**
     * PlanController constructor.
     */
    public function __construct(
        PlanRepository $planRepository,
        PromotionRepository $promotionRepository
    ) {
        $this->planRepository = $planRepository;
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Obtener todos los planes activos para el frontend
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Obtener solo planes activos con sus relaciones
            $plans = $this->planRepository->query()
                ->where('active', true)
                ->with(['service', 'promotions' => function ($query) {
                    $query->currentlyActive();
                }])
                //->orderBy('order', 'asc')
                ->orderBy('price', 'asc')
                ->get();

            // Formatear para el frontend Vue.js
            $formattedPlans = $plans->map(function ($plan) {
                return $this->formatPlanForFrontend($plan);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedPlans,
                'total' => $formattedPlans->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener planes públicos', [
                'action' => 'public_plan.index',
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes',
                'error' => 'No se pudieron obtener los planes. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }

    /**
     * Obtener un plan específico por ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $plan = $this->planRepository->query()
                ->where('id', $id)
                ->where('active', true)
                ->with(['service', 'promotions' => function ($query) {
                    $query->currentlyActive();
                }])
                ->firstOrFail();

            $formattedPlan = $this->formatPlanForFrontend($plan);

            return response()->json([
                'success' => true,
                'data' => $formattedPlan,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener plan público', [
                'action' => 'public_plan.show',
                'plan_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Plan no encontrado',
                'error' => 'Plan no encontrado.',
            ], 404);
        }
    }

    /**
     * Obtener planes por tipo de servicio
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getByServiceType(Request $request): JsonResponse
    {
        try {
            $serviceType = $request->input('service_type'); // 'internet', 'television', 'telefonia'

            $plans = $this->planRepository->query()
                ->where('active', true)
                ->whereHas('service', function ($query) use ($serviceType) {
                    $query->where('type', $serviceType)
                        ->where('active', true);
                })
                ->with(['service', 'promotions' => function ($query) {
                    $query->currentlyActive();
                }])
                //->orderBy('order', 'asc')
                ->orderBy('price', 'asc')
                ->get();

            $formattedPlans = $plans->map(function ($plan) {
                return $this->formatPlanForFrontend($plan);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedPlans,
                'total' => $formattedPlans->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener planes por tipo de servicio', [
                'action' => 'public_plan.getByServiceType',
                'service_type' => $serviceType ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes por tipo de servicio',
                'error' => 'No se pudieron obtener los planes por tipo de servicio. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }

    /**
     * Formatear plan para el frontend Vue.js
     *
     * @param $plan
     * @return array
     */
    private function formatPlanForFrontend($plan): array
    {
        // Calcular precio con descuento si hay promociones activas
        $originalPrice = (float) $plan->price;
        $discountedPrice = $originalPrice;
        $activePromotion = null;
        $discountPercentage = 0;

        if ($plan->promotions && $plan->promotions->count() > 0) {
            $promotion = $plan->promotions->first();
            $activePromotion = [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'discount_type' => $promotion->discount_type,
                'discount_value' => (float) $promotion->discount_value,
                'start_date' => $promotion->start_date,
                'end_date' => $promotion->end_date,
            ];

            if ($promotion->discount_type === 'percentage') {
                $discountPercentage = (float) $promotion->discount_value;
                $discountedPrice = $originalPrice * (1 - ($discountPercentage / 100));
            } else {
                $discountedPrice = max(0, $originalPrice - (float) $promotion->discount_value);
                $discountPercentage = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
            }
        }

        // Parsear características del plan
        $features = json_decode($plan->features, true) ?? [];
        $technicalDetails = json_decode($plan->technical_specifications, true) ?? [];

        // Determinar el rango de velocidad para filtros
        $speed = (float) ($plan->download_speed ?? 0);
        $speedRange = $this->getSpeedRange($speed);

        // Determinar el rango de pago para filtros
        $paymentRange = $this->getPaymentRange($discountedPrice);

        // Formato compatible con CardService y CardServiceTwo de Vue.js
        $formattedPlan = [
            'id' => $plan->id,
            'type' => $this->determinCardType($plan),
            'title' => $plan->name,
            'description' => $plan->description,

            // Precios
            'price' => round($discountedPrice, 2),
            'originalPrice' => round($originalPrice, 2),
            'discountedPrice' => round($discountedPrice, 2),
            'currency' => 'S/',
            'period' => $plan->billing_cycle ?? 'mensual',
            'hasDiscount' => $discountedPrice < $originalPrice,
            'discountPercentage' => round($discountPercentage, 0),

            // Velocidades
            'speed' => $speed,
            'speedUnit' => 'Mbps',
            'downloadSpeed' => (float) ($plan->download_speed ?? 0),
            'uploadSpeed' => (float) ($plan->upload_speed ?? 0),
            'speedDescription' => $this->getSpeedDescription($speed),
            'speedRange' => $speedRange,

            // Rangos para filtros
            'paymentRange' => $paymentRange,

            // Características
            'features' => $features,
            'technicalDetails' => $technicalDetails,
            'benefits' => $this->extractBenefits($plan),

            // Información del servicio
            'service' => [
                'id' => $plan->service->id ?? null,
                'name' => $plan->service->name ?? 'Servicio',
                'type' => $plan->service->type ?? 'internet',
                'description' => $plan->service->description ?? '',
            ],

            // Promoción activa
            'promotion' => $activePromotion,

            // Metadatos
            'isPopular' => $plan->is_featured ?? false,
            'popularity' => $plan->popularity_score ?? 0,
            'buttonText' => 'Contratar',
            'technicalTitle' => 'Especificaciones Técnicas',
            'order' => $plan->order ?? 999,

            // Información adicional
            'contract_duration' => $plan->contract_duration ?? 12,
            'installation_cost' => (float) ($plan->installation_cost ?? 0),
            'equipment_cost' => (float) ($plan->equipment_cost ?? 0),
        ];

        return $formattedPlan;
    }

    /**
     * Determinar el tipo de card para el frontend
     */
    private function determinCardType($plan): string
    {
        // Si tiene promoción activa, usar CardServiceTwo (más destacado)
        if ($plan->promotions && $plan->promotions->count() > 0) {
            return 'serviceTwo';
        }

        // Si es un plan destacado
        if ($plan->is_featured) {
            return 'serviceTwo';
        }

        // Por defecto usar CardService
        return 'service';
    }

    /**
     * Obtener descripción de velocidad
     */
    private function getSpeedDescription($speed): string
    {
        if ($speed < 50) {
            return 'Navegación básica';
        } elseif ($speed < 100) {
            return 'Ideal para streaming';
        } elseif ($speed < 300) {
            return 'Para toda la familia';
        } elseif ($speed < 600) {
            return 'Ultra rápido';
        } else {
            return 'Velocidad extrema';
        }
    }

    /**
     * Obtener rango de velocidad para filtros
     */
    private function getSpeedRange($speed): string
    {
        if ($speed < 50) {
            return '0-50';
        } elseif ($speed < 100) {
            return '50-100';
        } elseif ($speed < 200) {
            return '100-200';
        } elseif ($speed < 500) {
            return '200-500';
        } else {
            return '500+';
        }
    }

    /**
     * Obtener rango de pago para filtros
     */
    private function getPaymentRange($price): string
    {
        if ($price < 50) {
            return '0-50';
        } elseif ($price < 100) {
            return '50-100';
        } elseif ($price < 150) {
            return '100-150';
        } elseif ($price < 200) {
            return '150-200';
        } else {
            return '200+';
        }
    }

    /**
     * Extraer beneficios del plan
     */
    private function extractBenefits($plan): array
    {
        $benefits = [];

        // Beneficios basados en velocidad
        if ($plan->download_speed >= 100) {
            $benefits[] = 'Streaming 4K sin interrupciones';
        }

        if ($plan->download_speed >= 300) {
            $benefits[] = 'Gaming online de alta velocidad';
        }

        // Beneficios adicionales
        if ($plan->installation_cost == 0) {
            $benefits[] = 'Instalación gratuita';
        }

        if ($plan->equipment_cost == 0) {
            $benefits[] = 'Router WiFi incluido';
        }

        // Agregar características personalizadas del plan
        $features = json_decode($plan->features, true) ?? [];
        foreach ($features as $feature) {
            if (is_string($feature)) {
                $benefits[] = $feature;
            } elseif (is_array($feature) && isset($feature['name'])) {
                $benefits[] = $feature['name'];
            }
        }

        return $benefits;
    }

    /**
     * Obtener planes destacados para la página principal
     *
     * @return JsonResponse
     */
    public function getFeatured(): JsonResponse
    {
        try {
            // Obtener planes destacados y completar con planes activos si faltan tarjetas públicas
            $featuredPlans = $this->planRepository->query()
                ->where('active', true)
                ->where('is_featured', true)
                ->with(['service', 'promotions' => function ($query) {
                    $query->currentlyActive();
                }])
                ->orderBy('featured_order', 'asc')
                ->orderBy('price', 'asc')
                ->limit(6)
                ->get();

            $plans = $featuredPlans;

            if ($featuredPlans->count() < 6) {
                $fallbackPlans = $this->planRepository->query()
                    ->where('active', true)
                    ->whereNotIn('id', $featuredPlans->pluck('id')->all())
                    ->with(['service', 'promotions' => function ($query) {
                        $query->currentlyActive();
                    }])
                    ->orderBy('price', 'asc')
                    ->limit(6 - $featuredPlans->count())
                    ->get();

                $plans = $featuredPlans->concat($fallbackPlans)->values();
            }

            // Formatear para el frontend Vue.js
            $formattedPlans = $plans->map(function ($plan) {
                return $this->formatPlanForFrontend($plan);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedPlans,
                'total' => $formattedPlans->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener planes destacados', [
                'action' => 'public_plan.getFeatured',
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes destacados',
                'error' => 'No se pudieron obtener los planes destacados. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }
}
