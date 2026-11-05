<?php

namespace Modules\Services\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Services\Services\PromotionService;
use Modules\Services\Services\PlanService;
use Modules\Services\Http\Requests\PromotionRequest;
use Illuminate\Support\Facades\Auth;
use Modules\Services\Repositories\PromotionRepository;

class PromotionController extends Controller
{
    /**
     * @var PromotionRepository
     */
    protected $promotionRepository;

    /**
     * @var PromotionService
     */
    protected $promotionService;

    /**
     * @var PlanService
     */
    protected $planService;

    /**
     * PromotionController constructor.
     *
     * @param PromotionService $promotionService
     * @param PlanService $planService
     */
    public function __construct(
        PromotionRepository $promotionRepository,
        PromotionService $promotionService,
        PlanService $planService)
    {
        $this->promotionService = $promotionService;
        $this->planService = $planService;
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $activeFilter = $request->query('active');

        if ($activeFilter !== null) {
            $promotions = $this->promotionRepository->query()
                ->where('active', $activeFilter == 1)
                ->withCount('plans')
                ->get();
            $onlyActive = $activeFilter == 1;
        } else {
            $result = $this->promotionService->getAllPromotions(false);
            $promotions = $result['promotions'];
            $onlyActive = false;
        }

        return view('services::promotions.index', [
            'promotions' => $promotions,
            'onlyActive' => $onlyActive
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $plans = $this->planService->getAllPlans(true)['plans'];

        return view('services::promotions.create', [
            'plans' => $plans,
            'discountTypes' => [
                'percentage' => 'Porcentaje',
                'fixed' => 'Monto fijo'
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param PromotionRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(PromotionRequest $request)
    {
        $data = $request->validated();
        $data['plan_ids'] = $request->input('plan_ids', []);

        $result = $this->promotionService->createPromotion(
            $data,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        return redirect()->route('services.promotions.index')
            ->with('success', $result['message']);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $promotion = $this->promotionRepository->find($id);
        $plans = $promotion->plans;

        return view('services::promotions.show', [
            'promotion' => $promotion,
            'plans' => $plans
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $promotion = $this->promotionRepository->find($id);
        $plans = $this->planService->getAllPlans(true)['plans'];
        $selectedPlans = $promotion->plans->pluck('id')->toArray();

        return view('services::promotions.edit', [
            'promotion' => $promotion,
            'plans' => $plans,
            'selectedPlans' => $selectedPlans,
            'discountTypes' => [
                'percentage' => 'Porcentaje',
                'fixed' => 'Monto fijo'
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param PromotionRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(PromotionRequest $request, $id)
    {
        $data = $request->validated();
        $data['plan_ids'] = $request->input('plan_ids', []);

        $result = $this->promotionService->updatePromotion(
            $id,
            $data,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        return redirect()->route('services.promotions.index')
            ->with('success', $result['message']);
    }

    /**
     * Activate the specified resource in storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate($id, Request $request)
    {
        $result = $this->promotionService->togglePromotionStatus(
            $id,
            true,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Deactivate the specified resource in storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivate($id, Request $request)
    {
        $result = $this->promotionService->togglePromotionStatus(
            $id,
            false,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id, Request $request)
    {
        $result = $this->promotionService->deletePromotion(
            $id,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->route('services.promotions.index')
            ->with('success', $result['message']);
    }

    /**
     * API: List active promotions for the public frontend.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiIndex()
    {
        try {
            $promotions = $this->promotionRepository->query()
                ->where('active', true)
                ->with(['plans' => function ($query) {
                    $query->where('active', true);
                }])
                ->withCount('plans')
                ->orderBy('end_date')
                ->get()
                ->map(function ($promotion) {
                    return $this->formatPromotionForFrontend($promotion);
                });

            return response()->json([
                'success' => true,
                'data' => $promotions,
                'total' => $promotions->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en apiIndex de PromotionController', [
                'action' => 'promotion.apiIndex',
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener promociones',
                'error' => 'No se pudieron obtener las promociones. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }

    /**
     * API: List currently active promotions for the public frontend.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiActive()
    {
        try {
            $promotions = $this->promotionRepository->query()
                ->currentlyActive()
                ->with(['plans' => function ($query) {
                    $query->where('active', true);
                }])
                ->withCount('plans')
                ->orderBy('end_date')
                ->get()
                ->map(function ($promotion) {
                    return $this->formatPromotionForFrontend($promotion);
                });

            return response()->json([
                'success' => true,
                'data' => $promotions,
                'total' => $promotions->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en apiActive de PromotionController', [
                'action' => 'promotion.apiActive',
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener promociones activas',
                'error' => 'No se pudieron obtener las promociones activas. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }

    private function formatPromotionForFrontend($promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'description' => $promotion->description ?? '',
            'discount' => (float) $promotion->discount,
            'discountType' => $promotion->discount_type,
            'startDate' => optional($promotion->start_date)->toDateTimeString(),
            'endDate' => optional($promotion->end_date)->toDateTimeString(),
            'conditions' => $promotion->conditions ?? [],
            'active' => (bool) $promotion->active,
            'currentlyActive' => $promotion->isActive(),
            'plansCount' => (int) ($promotion->plans_count ?? $promotion->plans->count()),
            'plans' => $promotion->plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => $plan->name,
                    'price' => (float) $plan->price,
                    'downloadSpeed' => (float) $plan->download_speed,
                    'uploadSpeed' => (float) $plan->upload_speed,
                ];
            })->values(),
        ];
    }
}
