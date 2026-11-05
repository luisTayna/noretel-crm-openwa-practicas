<?php

namespace Modules\Contract\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->canCreateInModule('installations');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'contract_id' => 'required_without:onboarding_order_id|nullable|exists:contracts,id',
            'onboarding_order_id' => 'required_without:contract_id|nullable|exists:onboarding_orders,id',
            'technician_id' => 'required|exists:employees,id',
            'route_id' => 'nullable|exists:routes,id',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'equipment' => 'array',
            'equipment.*.equipment_id' => 'required|exists:equipment,id',
            'equipment.*.serial' => 'nullable|string|max:50',
            'equipment.*.mac_address' => 'nullable|string|max:20',
            'materials' => 'array',
            'materials.*.material_id' => 'required|exists:materials,id',
            'materials.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'contract_id' => 'contrato',
            'onboarding_order_id' => 'orden de instalación',
            'technician_id' => 'técnico',
            'route_id' => 'ruta',
            'scheduled_date' => 'fecha programada',
            'notes' => 'notas',
            'equipment' => 'equipos',
            'equipment.*.equipment_id' => 'equipo',
            'equipment.*.serial' => 'número de serie',
            'equipment.*.mac_address' => 'dirección MAC',
            'materials' => 'materiales',
            'materials.*.material_id' => 'material',
            'materials.*.quantity' => 'cantidad',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'contract_id.required_without' => 'Debe seleccionar un contrato o una orden de instalación',
            'onboarding_order_id.required_without' => 'Debe seleccionar una orden de instalación o un contrato',
            'technician_id.required' => 'Debe seleccionar un técnico',
            'scheduled_date.required' => 'La fecha programada es obligatoria',
            'scheduled_date.after_or_equal' => 'La fecha programada debe ser hoy o posterior',
            'equipment.*.equipment_id.required' => 'Debe seleccionar un equipo',
            'materials.*.material_id.required' => 'Debe seleccionar un material',
            'materials.*.quantity.required' => 'La cantidad es obligatoria',
            'materials.*.quantity.integer' => 'La cantidad debe ser un número entero',
            'materials.*.quantity.min' => 'La cantidad debe ser al menos 1',
        ];
    }
}
