<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->canCreateInModule('customers');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_type' => 'required|string|in:individual,business',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'identity_document' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'identity_document'),
                function ($attribute, $value, $fail) {
                    $type = $this->customerTypeForIdentityValidation();
                    if ($type === 'individual' && !preg_match('/^\d{8}$/', $value)) {
                        $fail('El DNI debe tener exactamente 8 dígitos.');
                    }
                    if ($type === 'business' && !preg_match('/^\d{11}$/', $value)) {
                        $fail('El RUC debe tener exactamente 11 dígitos.');
                    }
                },
            ],
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'credit_score' => 'nullable|integer|min:0|max:1000',
            'contact_preferences' => 'nullable|string|max:255',
            'segment' => 'nullable|string|max:50',
            'active' => 'boolean',

            // Addresses validation
            'addresses' => 'required|array|min:1',
            'addresses.*.address_type' => 'required|string|in:main,billing,installation',
            'addresses.*.location_name' => 'nullable|string|max:120',
            'addresses.*.street' => 'required|string|max:255',
            'addresses.*.number' => 'nullable|string|max:20',
            'addresses.*.floor' => 'nullable|string|max:10',
            'addresses.*.apartment' => 'nullable|string|max:10',
            'addresses.*.city' => 'required|string|max:100',
            'addresses.*.state' => 'required|string|max:100',
            'addresses.*.postal_code' => 'nullable|string|max:20',
            'addresses.*.reference' => 'required|string|max:255',
            // 'addresses.*.country' => 'required|string|max:100',
            'addresses.*.coordinates' => 'nullable|string|max:100',
            'addresses.*.is_primary' => 'boolean',

            // Emergency contacts validation
            'emergency_contacts' => 'nullable|array',
            'emergency_contacts.*.name' => 'required|string|max:255',
            'emergency_contacts.*.relationship' => 'required|string|max:100',
            'emergency_contacts.*.phone' => 'required|string|max:20',
            'emergency_contacts.*.email' => 'nullable|email|max:255',
        ];
    }

    protected function customerTypeForIdentityValidation(): ?string
    {
        return $this->input('customer_type');
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'customer_type.required' => 'El tipo de cliente es obligatorio.',
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'identity_document.required' => 'El documento de identidad es obligatorio.',
            'identity_document.unique' => 'Ya existe un cliente con este DNI/RUC. Abra su ficha y use "Agregar servicio"; no cree otro cliente.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado para otro cliente.',

            'addresses.required' => 'Se requiere al menos una dirección.',
            'addresses.min' => 'Se requiere al menos una dirección.',
            'addresses.*.address_type.required' => 'El tipo de dirección es obligatorio.',
            'addresses.*.street.required' => 'La calle es obligatoria.',
            'addresses.*.city.required' => 'La ciudad es obligatoria.',
            'addresses.*.state.required' => 'La provincia/estado es obligatoria.',
            'addresses.*.reference.required' => 'La referencia de la dirección es obligatoria.',
            'addresses.*.country.required' => 'El país es obligatorio.',

            'emergency_contacts.*.name.required' => 'El nombre del contacto de emergencia es obligatorio.',
            'emergency_contacts.*.relationship.required' => 'La relación con el contacto de emergencia es obligatoria.',
            'emergency_contacts.*.phone.required' => 'El teléfono del contacto de emergencia es obligatorio.',
            'emergency_contacts.*.email.email' => 'El email del contacto de emergencia debe tener un formato válido.',
        ];
    }
}
