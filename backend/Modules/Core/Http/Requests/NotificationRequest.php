<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationRequest extends FormRequest
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
            'template_name' => 'required|string|exists:notification_templates,name',
            'recipient' => 'required|string',
            'data' => 'required|array',
            'channel' => 'nullable|string|in:email,sms,system',
            'send_at' => 'nullable|date|after:now'
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
            'template_name.required' => 'Se requiere seleccionar una plantilla',
            'template_name.exists' => 'La plantilla seleccionada no existe',
            'recipient.required' => 'Se requiere el destinatario',
            'data.required' => 'Se requieren los datos para la notificación',
            'channel.in' => 'El canal debe ser email, sms o system',
            'send_at.after' => 'La fecha de envío debe ser posterior a la actual'
        ];
    }
}
