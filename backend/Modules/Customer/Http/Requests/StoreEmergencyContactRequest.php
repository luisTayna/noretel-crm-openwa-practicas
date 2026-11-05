<?php
// StoreEmergencyContactRequest.php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->canEditInModule('customers');
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
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
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
            'customer_id.required' => 'El ID del cliente es obligatorio.',
            'customer_id.exists' => 'El cliente especificado no existe.',
            'name.required' => 'El nombre del contacto es obligatorio.',
            'relationship.required' => 'La relación con el contacto es obligatoria.',
            'phone.required' => 'El teléfono del contacto es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
        ];
    }
}

// UpdateEmergencyContactRequest.php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmergencyContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->canEditInModule('customers');
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
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
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
            'name.required' => 'El nombre del contacto es obligatorio.',
            'relationship.required' => 'La relación con el contacto es obligatoria.',
            'phone.required' => 'El teléfono del contacto es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
        ];
    }
}