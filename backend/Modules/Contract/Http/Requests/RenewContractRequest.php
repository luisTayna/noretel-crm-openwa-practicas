<?php

namespace Modules\Contract\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->canEditInModule('contracts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'final_price' => 'required|numeric|min:0',
            'plan_id' => 'nullable|exists:plans,id',
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
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
            'final_price' => 'precio final',
            'plan_id' => 'plan',
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
            'start_date.required' => 'La fecha de inicio es obligatoria',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'final_price.required' => 'El precio final es obligatorio',
            'final_price.numeric' => 'El precio final debe ser un número',
            'final_price.min' => 'El precio final debe ser mayor o igual a 0',
        ];
    }
}