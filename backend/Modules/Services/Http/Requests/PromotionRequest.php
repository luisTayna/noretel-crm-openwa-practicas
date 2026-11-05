<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $maxDiscount = $this->discount_type === 'percentage' ? 100 : null;

        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'discount' => 'required|numeric|min:0|' . ($maxDiscount ? "max:{$maxDiscount}" : ''),
            'discount_type' => 'required|in:percentage,fixed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'conditions' => 'nullable|array',
            'active' => 'boolean',
            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'exists:plans,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'name.max' => 'El nombre de la promoción no puede tener más de 100 caracteres.',
            'discount.required' => 'El descuento es obligatorio.',
            'discount.numeric' => 'El descuento debe ser un número.',
            'discount.min' => 'El descuento no puede ser negativo.',
            'discount.max' => 'El descuento en porcentaje no puede ser mayor a 100%.',
            'discount_type.required' => 'El tipo de descuento es obligatorio.',
            'discount_type.in' => 'El tipo de descuento debe ser porcentaje o monto fijo.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'plan_ids.*.exists' => 'Uno de los planes seleccionados no existe.'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'active' => $this->boolean('active'),
        ]);

        // Si hay condiciones como string JSON, convertirlas a array
        if ($this->has('conditions') && is_string($this->conditions)) {
            $conditions = json_decode($this->conditions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['conditions' => $conditions]);
            }
        }
    }
}
