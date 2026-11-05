<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create-leads');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'contact' => 'nullable|required_without_all:phone,email|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'source' => 'nullable|string|max:100',
            'capture_date' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'potential_value' => 'nullable|numeric|min:0',
            'message' => 'nullable|string|max:1000',
            'interest' => 'nullable|string|max:80',
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
            'name.required' => 'El nombre es obligatorio.',
            'contact.required_without_all' => 'Indique al menos un contacto, teléfono o correo.',
            'capture_date.date' => 'La fecha de captura debe ser una fecha válida.',
            'potential_value.numeric' => 'El valor potencial debe ser un número.',
            'potential_value.min' => 'El valor potencial no puede ser negativo.',
        ];
    }
}