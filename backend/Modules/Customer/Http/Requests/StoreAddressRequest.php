<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'address_type' => 'required|string|in:main,billing,installation',
            'location_name' => 'nullable|string|max:120',
            'street' => 'required|string|max:255',
            'number' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:10',
            'apartment' => 'nullable|string|max:10',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'reference' => 'required|string|max:255',
            'coordinates' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
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
            'address_type.required' => 'El tipo de dirección es obligatorio.',
            'address_type.in' => 'El tipo de dirección debe ser principal, facturación o instalación.',
            'street.required' => 'La calle es obligatoria.',
            'city.required' => 'La ciudad es obligatoria.',
            'state.required' => 'La provincia/estado es obligatoria.',
            'reference.required' => 'La referencia de la dirección es obligatoria.',
        ];
    }
}
