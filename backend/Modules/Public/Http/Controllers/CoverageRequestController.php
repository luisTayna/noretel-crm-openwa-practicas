<?php

namespace Modules\Public\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Entities\User;
use Modules\Core\Services\NotificationService;
use Modules\Public\Emails\CoverageRequestConfirmationMail;
use Modules\Public\Entities\CoverageRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;

class CoverageRequestController extends Controller
{
    /**
     * Number of pending coverage requests in a zone (1km radius) that
     * triggers a one-time alert notification to admin/technician users.
     */
    private const COVERAGE_ALERT_THRESHOLD = 15;

    /**
     * Crear solicitud de cobertura
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'coordinates' => 'required|array',
            'coordinates.lat' => 'required|numeric|between:-90,90',
            'coordinates.lng' => 'required|numeric|between:-180,180',
            'comments' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos incompletos o inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verificar si ya existe una solicitud reciente con el mismo email
        $existingRequest = CoverageRequest::where('email', $request->email)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(30))
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes una solicitud activa. Te notificaremos cuando haya novedades.',
            ], 409);
        }

        // Contar solicitudes en la zona
        $requestsInZone = CoverageRequest::countInRadius(
            $request->coordinates['lat'],
            $request->coordinates['lng'],
            1 // 1 km de radio
        );

        // Crear la solicitud
        $coverageRequest = CoverageRequest::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'latitude' => $request->coordinates['lat'],
            'longitude' => $request->coordinates['lng'],
            'comments' => $request->comments,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Incrementar contador
        $newCount = $requestsInZone + 1;

        // Notificar al equipo técnico solo la vez que se alcanza el umbral,
        // no en cada solicitud adicional una vez superado.
        if ($newCount === self::COVERAGE_ALERT_THRESHOLD) {
            $this->notifyTechnicalTeam($coverageRequest, $newCount);
        }

        // Enviar email de confirmación al solicitante
        $this->sendConfirmationEmail($coverageRequest);

        return response()->json([
            'success' => true,
            'message' => '¡Solicitud registrada exitosamente!',
            'data' => [
                'id' => $coverageRequest->id,
                'name' => $coverageRequest->name,
                'email' => $coverageRequest->email,
            ],
            'requests_in_zone' => $newCount,
            'threshold' => self::COVERAGE_ALERT_THRESHOLD,
            'percentage' => min(100, ($newCount / self::COVERAGE_ALERT_THRESHOLD) * 100),
        ], 201);
    }

    /**
     * Notificar al equipo técnico que una zona alcanzó el umbral de
     * solicitudes de cobertura pendientes. Nunca debe romper la creación
     * de la solicitud si la notificación falla.
     */
    protected function notifyTechnicalTeam($request, $count)
    {
        try {
            $recipients = User::role(['admin', 'technician'])
                ->pluck('email')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($recipients)) {
                $content = sprintf(
                    'Se alcanzaron %d solicitudes de cobertura pendientes cerca de "%s". Revisa si es viable ampliar cobertura en la zona.',
                    $count,
                    $request->address
                );

                app(NotificationService::class)->notifySystemUsers(
                    $recipients,
                    'coverage_threshold_reached',
                    $content,
                    [
                        'coverage_request_id' => $request->id,
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                        'count' => $count,
                    ]
                );
            }

            Log::info("Umbral de cobertura alcanzado: {$count} solicitudes pendientes en la zona.", [
                'coverage_request_id' => $request->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,
                'notified_recipients' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Error notificando al equipo técnico sobre umbral de cobertura: ' . $e->getMessage());
        }
    }

    /**
     * Enviar email de confirmación al solicitante. Nunca debe romper la
     * creación de la solicitud si el envío falla.
     */
    protected function sendConfirmationEmail($request)
    {
        if (empty($request->email)) {
            return;
        }

        try {
            Mail::to($request->email)->send(new CoverageRequestConfirmationMail($request));

            Log::info("Email de confirmación enviado a: {$request->email}");
        } catch (\Exception $e) {
            Log::warning("No se pudo enviar el email de confirmación a: {$request->email}. Error: " . $e->getMessage());
        }
    }

    /**
     * Listar solicitudes (solo para admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CoverageRequest::query();

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($requests);
    }

    /**
     * Ver detalle de solicitud
     */
    public function show($id): JsonResponse
    {
        $request = CoverageRequest::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $request,
        ]);
    }
}
