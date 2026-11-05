<?php

namespace Modules\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouterRequest extends FormRequest
{
    private const MAC_ADDRESS_REGEX = '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/';

    /**
     * Credential sub-fields the edit form submits. Left blank (per the
     * "Dejar vacío para no cambiar" hint in routers/edit.blade.php), each one
     * must keep the router's currently stored value instead of being wiped.
     */
    private const CREDENTIAL_FIELDS = ['username', 'password', 'api_port'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) auth()->user()?->canEditInModule('routers');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $routerId = $this->route('router')?->id;

        return [
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:255', Rule::unique('routers', 'code')->ignore($routerId)],
            'brand' => ['required', Rule::in(StoreRouterRequest::BRANDS)],
            'model' => 'nullable|string|max:255',
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('routers', 'serial_number')->ignore($routerId)],
            'ip_address' => ['required', 'ip', Rule::unique('routers', 'ip_address')->ignore($routerId)],
            'mac_address' => ['nullable', 'string', 'regex:' . self::MAC_ADDRESS_REGEX, Rule::unique('routers', 'mac_address')->ignore($routerId)],
            'zone' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'node_id' => 'nullable|exists:nodes,id',
            'max_clients' => 'required|integer|min:1',
            'status' => ['nullable', Rule::in(StoreRouterRequest::STATUSES)],
            'notes' => 'nullable|string',
            'credentials' => 'nullable|array',
            'credentials.username' => 'nullable|string|max:255',
            'credentials.password' => 'nullable|string|max:255',
            'credentials.api_port' => 'nullable|integer|min:1|max:65535',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'nombre',
            'code' => 'código',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_number' => 'número de serie',
            'ip_address' => 'dirección IP',
            'mac_address' => 'dirección MAC',
            'zone' => 'zona',
            'location' => 'ubicación',
            'node_id' => 'nodo',
            'max_clients' => 'capacidad máxima de clientes',
            'status' => 'estado',
            'notes' => 'notas',
            'credentials.username' => 'usuario',
            'credentials.password' => 'contraseña',
            'credentials.api_port' => 'puerto API',
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
            'ip_address.ip' => 'La dirección IP debe ser una dirección IP válida',
            'ip_address.unique' => 'Ya existe otro router registrado con esta dirección IP',
            'mac_address.regex' => 'La dirección MAC debe tener el formato 00:0C:29:12:34:56',
            'mac_address.unique' => 'Ya existe otro router registrado con esta dirección MAC',
            'brand.in' => 'La marca seleccionada no es válida',
            'code.unique' => 'Ya existe otro router con este código',
            'serial_number.unique' => 'Ya existe otro router con este número de serie',
        ];
    }

    /**
     * Get the validated data, keeping any blank credential sub-field as the
     * router's currently stored value instead of wiping it. This matches the
     * edit form's "leave blank to keep" hint for username/password/api_port.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        $validated['credentials'] = $this->mergeWithExistingCredentials($validated['credentials'] ?? []);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function mergeWithExistingCredentials(array $submitted): array
    {
        $router = $this->route('router');
        $existing = $router?->credentials ?? [];

        foreach (self::CREDENTIAL_FIELDS as $field) {
            $value = $submitted[$field] ?? null;

            if ($value === null || $value === '') {
                if (array_key_exists($field, $existing)) {
                    $submitted[$field] = $existing[$field];
                } else {
                    unset($submitted[$field]);
                }
            }
        }

        return $submitted;
    }
}
