<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SecurityPolicyRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:100',
            'policy_type' => 'required|string|in:password,login,account_lockout,session,api,file_upload',
            'configuration' => 'required|array',
            'active' => 'boolean'
        ];

        // Reglas específicas según el tipo de política
        switch ($this->policy_type) {
            case 'password':
                $rules['configuration.min_length'] = 'required|integer|min:6|max:30';
                $rules['configuration.require_numbers'] = 'boolean';
                $rules['configuration.require_uppercase'] = 'boolean';
                $rules['configuration.require_lowercase'] = 'boolean';
                $rules['configuration.require_special'] = 'boolean';
                $rules['configuration.password_history'] = 'nullable|integer|min:0|max:24';
                $rules['configuration.expire_days'] = 'nullable|integer|min:0|max:365';
                break;

            case 'login':
                $rules['configuration.max_attempts'] = 'required|integer|min:1|max:10';
                $rules['configuration.lockout_minutes'] = 'required|integer|min:1|max:1440';
                $rules['configuration.require_2fa'] = 'boolean';
                $rules['configuration.allow_remember_me'] = 'boolean';
                break;

            case 'account_lockout':
                $rules['configuration.auto_lockout_days'] = 'nullable|integer|min:0|max:365';
                $rules['configuration.inactive_warning_days'] = 'nullable|integer|min:0|max:30';
                $rules['configuration.send_warning_email'] = 'boolean';
                break;

            case 'session':
                $rules['configuration.timeout_minutes'] = 'required|integer|min:1|max:1440';
                $rules['configuration.absolute_timeout_hours'] = 'nullable|integer|min:1|max:168';
                $rules['configuration.refresh_on_activity'] = 'boolean';
                $rules['configuration.single_session'] = 'boolean';
                break;

            case 'api':
                $rules['configuration.token_expiry_days'] = 'required|integer|min:1|max:365';
                $rules['configuration.rate_limit_per_minute'] = 'required|integer|min:1|max:1000';
                $rules['configuration.require_https'] = 'boolean';
                break;

            case 'file_upload':
                $rules['configuration.max_size_kb'] = 'required|integer|min:1|max:102400';
                $rules['configuration.allowed_extensions'] = 'required|array';
                $rules['configuration.scan_for_malware'] = 'boolean';
                break;
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'El nombre de la política es obligatorio',
            'policy_type.required' => 'El tipo de política es obligatorio',
            'policy_type.in' => 'El tipo de política seleccionado no es válido',
            'configuration.required' => 'La configuración de la política es obligatoria',
            'configuration.array' => 'La configuración debe ser un conjunto de valores',

            // Password policy
            'configuration.min_length.required' => 'La longitud mínima es obligatoria',
            'configuration.min_length.integer' => 'La longitud mínima debe ser un número entero',
            'configuration.min_length.min' => 'La longitud mínima debe ser al menos 6 caracteres',

            // Login policy
            'configuration.max_attempts.required' => 'El número máximo de intentos es obligatorio',
            'configuration.lockout_minutes.required' => 'El tiempo de bloqueo es obligatorio',

            // Session policy
            'configuration.timeout_minutes.required' => 'El tiempo de inactividad es obligatorio',

            // API policy
            'configuration.token_expiry_days.required' => 'El tiempo de expiración del token es obligatorio',
            'configuration.rate_limit_per_minute.required' => 'El límite de peticiones por minuto es obligatorio',

            // File upload policy
            'configuration.max_size_kb.required' => 'El tamaño máximo de archivo es obligatorio',
            'configuration.allowed_extensions.required' => 'Las extensiones permitidas son obligatorias'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convertir configuration a array si viene como string JSON
        if ($this->has('configuration') && is_string($this->configuration)) {
            $this->merge([
                'configuration' => json_decode($this->configuration, true) ?? []
            ]);
        }

        // Convertir active a boolean
        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->active, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Procesar los valores booleanos en la configuración
        if ($this->has('configuration') && is_array($this->configuration)) {
            $config = $this->configuration;

            $booleanFields = [
                'require_numbers', 'require_uppercase', 'require_lowercase', 'require_special',
                'require_2fa', 'allow_remember_me', 'send_warning_email',
                'refresh_on_activity', 'single_session', 'require_https', 'scan_for_malware'
            ];

            foreach ($booleanFields as $field) {
                if (isset($config[$field])) {
                    $config[$field] = filter_var($config[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Convertir allowed_extensions a array si es string
            if (isset($config['allowed_extensions']) && is_string($config['allowed_extensions'])) {
                $extensions = explode(',', $config['allowed_extensions']);
                $config['allowed_extensions'] = array_map('trim', $extensions);
            }

            $this->merge(['configuration' => $config]);
        }
    }
}
