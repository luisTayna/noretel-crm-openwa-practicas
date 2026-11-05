<?php

namespace Modules\Contract\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteInstallationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->canEditInModule('installations');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $requireSignature = config('contract.installation.require_customer_signature', true);
        $requirePhotos = config('contract.installation.require_photos', true);
        
        $rules = [
            'completed_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ];
        
        if ($requireSignature) {
            $rules['customer_signature'] = 'required|string';
        } else {
            $rules['customer_signature'] = 'nullable|string';
        }
        
        if ($requirePhotos) {
            $rules['photos'] = 'required|array|min:1';
            $rules['photos.*'] = 'required|image|max:10240';
            $rules['photo_descriptions'] = 'required|array|min:1';
            $rules['photo_descriptions.*'] = 'required|string|max:255';
        } else {
            $rules['photos'] = 'nullable|array';
            $rules['photos.*'] = 'nullable|image|max:10240';
            $rules['photo_descriptions'] = 'nullable|array';
            $rules['photo_descriptions.*'] = 'nullable|string|max:255';
        }
        
        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'completed_date' => 'fecha de completado',
            'notes' => 'notas',
            'customer_signature' => 'firma del cliente',
            'photos' => 'fotos',
            'photos.*' => 'foto',
            'photo_descriptions' => 'descripciones de fotos',
            'photo_descriptions.*' => 'descripción de foto',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'completed_date.required' => 'La fecha de completado es obligatoria',
            'customer_signature.required' => 'La firma del cliente es obligatoria',
            'photos.required' => 'Debe subir al menos una foto',
            'photos.min' => 'Debe subir al menos una foto',
            'photos.*.image' => 'El archivo debe ser una imagen',
            'photos.*.max' => 'La imagen no debe superar los 10MB',
            'photo_descriptions.required' => 'Debe proporcionar descripciones para las fotos',
            'photo_descriptions.min' => 'Debe proporcionar descripciones para las fotos',
            'photo_descriptions.*.required' => 'La descripción de la foto es obligatoria',
        ];
    }
}