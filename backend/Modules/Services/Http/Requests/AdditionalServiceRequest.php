<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdditionalServiceRequest extends FormRequest
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
        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')->where('active', true),
            ],
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'configurable' => 'boolean',
            'configuration_options' => 'nullable|array',
            'configuration_options.*' => 'nullable|string'
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
            'service_id.required' => 'El servicio principal es obligatorio.',
            'service_id.exists' => 'El servicio seleccionado no existe o está inactivo.',
            'name.required' => 'El nombre del servicio adicional es obligatorio.',
            'name.max' => 'El nombre del servicio adicional no puede tener más de 100 caracteres.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $configurable = $this->boolean('configurable');
        $this->merge([
            'configurable' => $configurable,
        ]);

        // Si hay opciones de configuración como string JSON, convertirlas a array
        if ($this->has('configuration_options') && is_string($this->configuration_options)) {
            $options = json_decode($this->configuration_options, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['configuration_options' => $options]);
            }
        }

        $options = $this->input('configuration_options');
        if (!$configurable) {
            $this->merge(['configuration_options' => null]);
            return;
        }

        if (is_array($options)) {
            $this->merge([
                'configuration_options' => array_values(array_filter($options, fn ($option) => filled($option))),
            ]);
        }
    }
}
