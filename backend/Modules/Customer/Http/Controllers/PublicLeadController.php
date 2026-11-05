<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\Services\CommercialLeadService;

class PublicLeadController extends Controller
{
    public function __construct(
        protected CommercialLeadService $commercialLeadService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:40', 'regex:/^(\+?51)?9\d{8}$/'],
            'email' => 'nullable|email|max:255',
            'message' => 'nullable|string|max:1000',
            'interest' => 'nullable|string|max:80',
            'source' => 'nullable|string|max:100',
            'page' => 'nullable|string|max:255',
            'utm' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos incompletos o inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->commercialLeadService->createFromPublicRequest(
            $validator->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => 'Lead registrado correctamente.',
            'data' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'source' => $lead->source,
                'interest' => $lead->interest,
            ],
        ], 201);
    }

    public function whatsappClick(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:1000',
            'page' => 'nullable|string|max:255',
            'utm' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos incompletos o inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = $this->commercialLeadService->trackWhatsAppClick(
            $validator->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => 'Click de WhatsApp registrado correctamente.',
            'data' => [
                'id' => $lead->id,
                'source' => $lead->source,
                'interest' => $lead->interest,
            ],
        ], 201);
    }
}
