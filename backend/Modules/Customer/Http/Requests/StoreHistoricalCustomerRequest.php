<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoricalCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->canCreateInModule('customers');
    }

    public function rules()
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'customer_type' => 'required_without:customer_id|nullable|string|in:individual,business',
            'first_name' => 'required_without:customer_id|nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'identity_document' => [
                Rule::excludeIf($this->filled('customer_id')),
                'nullable',
                'string',
                'max:50',
            ],
            'email' => [
                Rule::excludeIf($this->filled('customer_id')),
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email'),
            ],
            'phone' => 'nullable|string|max:20',
            'segment' => 'nullable|string|max:50',
            'street' => 'required_without:customer_id|nullable|string|max:255',
            'city' => 'required_without:customer_id|nullable|string|max:100',
            'state' => 'required_without:customer_id|nullable|string|max:100',
            'coordinates' => 'nullable|string|max:100',
            'plan_id' => 'required|exists:plans,id',
            'monthly_price' => 'required|numeric|min:0',
            'installed_at' => 'required|date|before_or_equal:today',
            'debt_start_date' => 'nullable|date|after_or_equal:installed_at|before_or_equal:today',
            'debt_end_month' => 'nullable|date_format:Y-m',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->filled('debt_start_date')) {
                return;
            }

            $start = \Carbon\Carbon::parse($this->input('debt_start_date'))->startOfDay();

            if ($this->filled('debt_end_month')) {
                $end = \Carbon\Carbon::createFromFormat('Y-m', $this->input('debt_end_month'))->endOfMonth();

                if ($end->lt($start)) {
                    $validator->errors()->add('debt_end_month', 'El mes final de deuda no puede ser anterior a la fecha inicial de deuda.');
                }
            }
        });
    }

    public function attributes()
    {
        return [
            'customer_id' => 'cliente existente',
            'customer_type' => 'tipo de cliente',
            'first_name' => 'nombres',
            'last_name' => 'apellidos',
            'identity_document' => 'documento',
            'street' => 'dirección',
            'city' => 'zona/ciudad',
            'state' => 'departamento/provincia',
            'plan_id' => 'plan',
            'monthly_price' => 'precio mensual',
            'installed_at' => 'fecha real de instalación',
            'debt_start_date' => 'cobrar deuda desde',
            'debt_end_month' => 'cobrar deuda hasta',
        ];
    }

    public function messages()
    {
        return [
            'installed_at.before_or_equal' => 'La fecha real de instalación no puede ser futura.',
            'email.unique' => 'Este correo ya pertenece a otro cliente. Seleccione el cliente existente.',
        ];
    }
}
