<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigRequest extends FormRequest
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
            'value' => 'required|string',
            'editable' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Obtener configuración actual para validar según su tipo de dato
            $configId = $this->route('id');
            $config = app('Modules\Core\Services\ConfigurationService')->findById($configId);

            if (!$config) {
                $validator->errors()->add('config', 'La configuración no existe.');
                return;
            }

            // Validar formato de JSON si el tipo de dato es json
            if ($config['data_type'] === 'json' && $this->value) {
                json_decode($this->value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add('value', 'El valor no es un JSON válido.');
                }
            }

            // Validar que el valor sea un número si el tipo es integer o float
            if ($config['data_type'] === 'integer' && !is_numeric($this->value)) {
                $validator->errors()->add('value', 'El valor debe ser un número entero.');
            }

            if ($config['data_type'] === 'float' && !is_numeric($this->value)) {
                $validator->errors()->add('value', 'El valor debe ser un número decimal.');
            }

            // Validar que el valor sea booleano si el tipo es boolean
            if ($config['data_type'] === 'boolean' && !in_array(strtolower($this->value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])) {
                $validator->errors()->add('value', 'El valor debe ser un valor booleano (true, false, 1, 0, yes, no, on, off).');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'value.required' => 'El valor es obligatorio.',
        ];
    }
}
