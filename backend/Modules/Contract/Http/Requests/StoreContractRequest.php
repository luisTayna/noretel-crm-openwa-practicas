<?php

namespace Modules\Contract\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->canCreateInModule('contracts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'plan_id' => 'required|exists:plans,id',
            'node_id' => 'nullable|exists:nodes,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'final_price' => 'required|numeric|min:0',
            'assigned_ip' => 'nullable|ip',
            'vlan' => 'nullable|string|max:50',
            'sla_id' => 'nullable|exists:slas,id',
            'additional_services' => 'array',
            'additional_services.*.selected' => 'boolean',
            'additional_services.*.price' => 'nullable|numeric|min:0',
            'additional_services.*.configuration' => 'nullable|array',
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
            'customer_id' => 'cliente',
            'plan_id' => 'plan',
            'node_id' => 'nodo',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'final_price' => 'precio final',
            'assigned_ip' => 'IP asignada',
            'vlan' => 'VLAN',
            'sla_id' => 'SLA',
            'additional_services' => 'servicios adicionales',
            'additional_services.*.selected' => 'seleccionado',
            'additional_services.*.price' => 'precio',
            'additional_services.*.configuration' => 'configuración',
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
            'customer_id.required' => 'Debe seleccionar un cliente',
            'plan_id.required' => 'Debe seleccionar un plan',
            'node_id.required' => 'Debe seleccionar un nodo',
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'final_price.required' => 'El precio final es obligatorio',
            'final_price.numeric' => 'El precio final debe ser un número',
            'final_price.min' => 'El precio final debe ser mayor o igual a 0',
            'assigned_ip.ip' => 'La IP asignada debe ser una dirección IP válida',
        ];
    }
}
