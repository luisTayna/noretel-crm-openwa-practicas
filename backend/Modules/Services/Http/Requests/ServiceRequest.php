<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Services\Entities\Service;

class ServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // La autorización se maneja con middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'service_type' => ['required', 'string', 'max:50', Rule::in(Service::activeServiceTypeKeys())],
            'technology' => ['required', 'string', 'max:50', Rule::in(Service::activeTechnologyKeys())],
            'active' => 'boolean'
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
            'name.required' => 'El nombre del servicio es obligatorio.',
            'name.max' => 'El nombre del servicio no puede tener más de 100 caracteres.',
            'service_type.required' => 'El tipo de servicio es obligatorio.',
            'service_type.in' => 'Por ahora solo está habilitado el tipo de servicio Internet.',
            'technology.required' => 'La tecnología es obligatoria.',
            'technology.in' => 'Por ahora solo está habilitada la tecnología Fibra Óptica + WiFi.'
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
            'technology' => Service::normalizeTechnology($this->input('technology')),
            'active' => $this->boolean('active'),
        ]);
    }
}
