<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationTemplateRequest extends FormRequest
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
            'communication_type' => 'required|string|in:email,sms,system',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'active' => 'boolean',
            'language' => 'required|string|max:5'
        ];

        // Si no es una actualización, verificar unicidad
        if (!$this->route('id')) {
            $rules['name'] .= '|unique:notification_templates,name';
        } else {
            // Si es actualización, verificar que no exista el nombre para otro ID
            $rules['name'] .= '|unique:notification_templates,name,' . $this->route('id');
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
            'name.required' => 'El nombre de la plantilla es obligatorio',
            'name.unique' => 'Ya existe una plantilla con ese nombre',
            'communication_type.required' => 'El tipo de comunicación es obligatorio',
            'communication_type.in' => 'El tipo de comunicación debe ser email, sms o system',
            'content.required' => 'El contenido de la plantilla es obligatorio',
            'language.required' => 'El idioma de la plantilla es obligatorio'
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convertir variables a array si viene como string JSON
        if ($this->has('variables') && is_string($this->variables)) {
            $this->merge([
                'variables' => json_decode($this->variables, true) ?? []
            ]);
        }

        // Convertir active a boolean
        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->active, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
