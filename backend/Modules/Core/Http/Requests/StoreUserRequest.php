<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\SecurityPolicy;

class StoreUserRequest extends FormRequest
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
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'status' => 'required|in:active,inactive,suspended',
            'requires_2fa' => 'boolean',
            'roles' => 'required|array|min:1|max:1',
            'roles.*' => ['required', Rule::exists('roles', 'id')],
            'preferences' => 'nullable|array',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'employee_status' => 'required|string|in:active,inactive,suspended',
            'hire_date' => 'required|date',
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
            // Validar política de contraseñas
            $passwordValidation = SecurityPolicy::validatePassword($this->password);
            
            if ($passwordValidation !== true) {
                foreach ($passwordValidation as $error) {
                    $validator->errors()->add('password', $error);
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
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.unique' => 'Este nombre de usuario ya está en uso.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Por favor ingrese un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'roles.required' => 'Debe seleccionar un rol interno.',
            'roles.max' => 'Solo se puede seleccionar un rol interno.',
            'roles.*.exists' => 'Uno de los roles seleccionados no existe.',
            'first_name.required' => 'El nombre del empleado es obligatorio.',
            'last_name.required' => 'El apellido del empleado es obligatorio.',
            'position.required' => 'El cargo es obligatorio.',
            'department.required' => 'El departamento es obligatorio.',
            'employee_status.required' => 'El estado laboral es obligatorio.',
            'employee_status.in' => 'El estado laboral seleccionado no es válido.',
            'hire_date.required' => 'La fecha de ingreso es obligatoria.',
            'hire_date.date' => 'La fecha de ingreso no es válida.',
        ];
    }
}
