<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfigRequest extends FormRequest
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
            'module' => 'required|string|max:255',
            'new_module' => 'required_if:module,new_module|string|max:255',
            'parameter' => 'required|string|max:255',
            'value' => 'required|string',
            'data_type' => 'required|in:string,integer,float,boolean,json',
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
            // Validar formato de JSON si el tipo de dato es json
            if ($this->data_type === 'json' && $this->value) {
                json_decode($this->value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add('value', 'El valor no es un JSON válido.');
                }
            }

            // Validar que el valor sea un número si el tipo es integer o float
            if ($this->data_type === 'integer' && !is_numeric($this->value)) {
                $validator->errors()->add('value', 'El valor debe ser un número entero.');
            }

            if ($this->data_type === 'float' && !is_numeric($this->value)) {
                $validator->errors()->add('value', 'El valor debe ser un número decimal.');
            }

            // Validar que el valor sea booleano si el tipo es boolean
            if ($this->data_type === 'boolean' && !in_array(strtolower($this->value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])) {
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
            'module.required' => 'El módulo es obligatorio.',
            'new_module.required_if' => 'El nombre del nuevo módulo es obligatorio.',
            'parameter.required' => 'El parámetro es obligatorio.',
            'value.required' => 'El valor es obligatorio.',
            'data_type.required' => 'El tipo de dato es obligatorio.',
            'data_type.in' => 'El tipo de dato seleccionado no es válido.',
        ];
    }
}
