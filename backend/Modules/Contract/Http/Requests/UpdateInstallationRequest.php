<?php

namespace Modules\Contract\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallationRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->canEditInModule('installations');
    }

    public function rules()
    {
        return [
            'contract_id' => 'nullable|exists:contracts,id',
            'onboarding_order_id' => 'nullable|exists:onboarding_orders,id',
            'technician_id' => 'required|exists:employees,id',
            'route_id' => 'nullable|exists:routes,id',
            'scheduled_date' => 'required|date',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled,postponed',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function attributes()
    {
        return [
            'contract_id' => 'contrato',
            'onboarding_order_id' => 'orden de instalación',
            'technician_id' => 'técnico',
            'route_id' => 'ruta',
            'scheduled_date' => 'fecha programada',
            'status' => 'estado',
            'notes' => 'notas',
        ];
    }
}
