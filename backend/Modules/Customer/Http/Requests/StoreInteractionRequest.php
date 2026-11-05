<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInteractionRequest extends FormRequest
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
            'customer_id' => 'required|integer|exists:customers,id',
            'interaction_type' => 'required|string|max:100',
            'date' => 'nullable|date',
            'channel' => 'required|string|max:100',
            'description' => 'required|string',
            'result' => 'nullable|string',
            'follow_up_required' => 'boolean',
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
            'customer_id.required' => 'El cliente es obligatorio.',
            'customer_id.exists' => 'El cliente seleccionado no existe.',
            'interaction_type.required' => 'El tipo de interacción es obligatorio.',
            'channel.required' => 'El canal de comunicación es obligatorio.',
            'description.required' => 'La descripción es obligatoria.',
        ];
    }
}