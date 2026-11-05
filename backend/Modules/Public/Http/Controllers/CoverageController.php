<?php

namespace Modules\Public\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Public\Entities\CoverageZone;
use Illuminate\Support\Facades\Validator;

class CoverageController extends Controller
{
    /**
     * Verificar cobertura por coordenadas
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de ubicación inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        // Buscar zona de cobertura que contenga este punto
        $zones = CoverageZone::active()->get();

        $coverageZone = null;
        foreach ($zones as $zone) {
            if ($zone->containsPoint($latitude, $longitude)) {
                $coverageZone = $zone;
                break;
            }
        }

        if ($coverageZone) {
            return response()->json([
                'success' => true,
                'has_coverage' => true,
                'message' => "¡Excelente! Tenemos cobertura en {$coverageZone->name}",
                'quality' => $coverageZone->quality,
                'zone' => [
                    'id' => $coverageZone->id,
                    'name' => $coverageZone->name,
                    'district' => $coverageZone->district,
                    'province' => $coverageZone->province,
                    'quality' => $coverageZone->quality,
                ],
                'available_plans' => $coverageZone->plans()->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'speed' => "{$plan->download_speed} Mbps",
                        'price' => $plan->price,
                    ];
                }),
            ]);
        }

        return response()->json([
            'success' => true,
            'has_coverage' => false,
            'message' => 'Actualmente no tenemos cobertura en tu ubicación',
        ]);
    }

    /**
     * Verificar cobertura por dirección
     */
    public function checkByAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string',
            'province' => 'required|string',
            'district' => 'required|string',
            'street' => 'nullable|string',
            'number' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de dirección incompletos',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Buscar por distrito
        $zone = CoverageZone::active()
            ->where('district', $request->district)
            ->where('province', $request->province)
            ->first();

        if ($zone) {
            return response()->json([
                'success' => true,
                'has_coverage' => true,
                'message' => "¡Tenemos cobertura en {$request->district}!",
                'quality' => $zone->quality,
                'zone' => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'district' => $zone->district,
                    'province' => $zone->province,
                    'quality' => $zone->quality,
                ],
                'available_plans' => $zone->plans()->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'speed' => "{$plan->download_speed} Mbps",
                        'price' => $plan->price,
                    ];
                }),
            ]);
        }

        return response()->json([
            'success' => true,
            'has_coverage' => false,
            'message' => "Aún no tenemos cobertura en {$request->district}",
        ]);
    }

    /**
     * Obtener estadísticas de zona
     */
    public function getZoneStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $requestCount = \Modules\Public\Entities\CoverageRequest::countInRadius(
            $request->latitude,
            $request->longitude,
            1 // 1 km de radio
        );

        return response()->json([
            'success' => true,
            'request_count' => $requestCount,
            'threshold' => 15,
            'percentage' => min(100, ($requestCount / 15) * 100),
        ]);
    }
}
