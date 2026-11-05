<?php

namespace Modules\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouterRequest extends FormRequest
{
    /**
     * Brands the router inventory accepts. This mirrors the routers.brand
     * enum column and the options rendered by the create/edit forms; it is
     * intentionally wider than the brands RouterService can operate against
     * (mikrotik, huawei, cisco), since a router can be catalogued here before
     * (or without) live API/SSH integration.
     */
    public const BRANDS = ['Huawei', 'MikroTik', 'Cisco', 'TP-Link', 'Ubiquiti'];

    public const STATUSES = ['active', 'inactive', 'maintenance', 'error', 'offline'];

    private const MAC_ADDRESS_REGEX = '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) auth()->user()?->canCreateInModule('routers');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:routers,code',
            'brand' => ['required', Rule::in(self::BRANDS)],
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:routers,serial_number',
            'ip_address' => 'required|ip|unique:routers,ip_address',
            'mac_address' => ['nullable', 'string', 'regex:' . self::MAC_ADDRESS_REGEX, 'unique:routers,mac_address'],
            'zone' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'node_id' => 'nullable|exists:nodes,id',
            'max_clients' => 'required|integer|min:1',
            'status' => ['nullable', Rule::in(self::STATUSES)],
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
            'ip_address.unique' => 'Ya existe un router registrado con esta dirección IP',
            'mac_address.regex' => 'La dirección MAC debe tener el formato 00:0C:29:12:34:56',
            'mac_address.unique' => 'Ya existe un router registrado con esta dirección MAC',
            'brand.in' => 'La marca seleccionada no es válida',
            'code.unique' => 'Ya existe un router con este código',
            'serial_number.unique' => 'Ya existe un router con este número de serie',
        ];
    }
}
