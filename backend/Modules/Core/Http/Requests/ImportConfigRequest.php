<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportConfigRequest extends FormRequest
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
            'json_file' => 'required|file|mimes:json|max:2048',
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
            if ($this->hasFile('json_file')) {
                $json = $this->file('json_file')->get();

                // Validar que el JSON sea válido
                json_decode($json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add('json_file', 'El archivo no contiene un JSON válido: ' . json_last_error_msg());
                }

                // Validar estructura del JSON
                $data = json_decode($json, true);
                if (!is_array($data) || empty($data)) {
                    $validator->errors()->add('json_file', 'El archivo JSON debe contener un objeto con módulos y configuraciones.');
                    return;
                }

                // Validar que todas las configuraciones tengan la estructura correcta
                foreach ($data as $module => $configs) {
                    if (!is_array($configs)) {
                        $validator->errors()->add('json_file', "El módulo '{$module}' debe contener un objeto de configuraciones.");
                        continue;
                    }

                    foreach ($configs as $paramName => $paramData) {
                        if (!is_array($paramData) || !isset($paramData['value'])) {
                            $validator->errors()->add('json_file', "La configuración '{$module}.{$paramName}' tiene un formato incorrecto.");
                        }
                    }
                }
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
            'json_file.required' => 'Debe seleccionar un archivo JSON.',
            'json_file.file' => 'Debe ser un archivo válido.',
            'json_file.mimes' => 'El archivo debe ser de tipo JSON.',
            'json_file.max' => 'El archivo no debe superar los 2MB.',
        ];
    }
}
