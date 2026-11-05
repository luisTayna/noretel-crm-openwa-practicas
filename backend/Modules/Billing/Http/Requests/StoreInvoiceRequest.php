<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'invoice_number' => 'required|string|max:255|unique:invoices',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'billing_name' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string|max:255',
            'billing_document' => 'nullable|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'billing_period' => 'nullable|string|max:255',
            'generation_type' => 'nullable|string|in:manual,automatic,recurring',

            // Items de factura
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.item_type' => 'nullable|string|max:255',
            'items.*.service_id' => 'nullable|integer',
            'items.*.period_start' => 'nullable|string',
            'items.*.period_end' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Calcular impuestos y totales para cada ítem
        if ($this->has('items')) {
            $items = $this->get('items');

            foreach ($items as $key => $item) {
                $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 0;
                $unitPrice = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
                $taxRate = isset($item['tax_rate']) ? floatval($item['tax_rate']) : 0;
                $discount = isset($item['discount']) ? floatval($item['discount']) : 0;

                // Calcular subtotal (cantidad * precio unitario)
                $subtotal = $quantity * $unitPrice;

                // Aplicar descuento si existe
                $afterDiscount = $subtotal - $discount;

                // Calcular impuesto
                $taxAmount = ($afterDiscount * $taxRate) / 100;

                // Calcular monto total del ítem
                $amount = $afterDiscount + $taxAmount;

                // Actualizar ítem con valores calculados
                $items[$key]['tax_amount'] = $taxAmount;
                $items[$key]['amount'] = $amount;
            }

            $this->merge(['items' => $items]);

            // Calcular totales de la factura
            $amount = 0;
            $taxes = 0;
            $total_amount = 0;

            foreach ($items as $item) {
                $amount += ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $taxes += $item['tax_amount'];
                $total_amount += $item['amount'];
            }

            $this->merge([
                'amount' => $amount,
                'taxes' => $taxes,
                'total_amount' => $total_amount
            ]);
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'contract_id.required' => 'El contrato es obligatorio',
            'invoice_number.required' => 'El número de factura es obligatorio',
            'invoice_number.unique' => 'El número de factura ya existe',
            'issue_date.required' => 'La fecha de emisión es obligatoria',
            'due_date.required' => 'La fecha de vencimiento es obligatoria',
            'due_date.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la fecha de emisión',
            'items.required' => 'Debe agregar al menos un ítem a la factura',
            'items.min' => 'Debe agregar al menos un ítem a la factura',
            'items.*.description.required' => 'La descripción del ítem es obligatoria',
            'items.*.quantity.required' => 'La cantidad del ítem es obligatoria',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo',
            'items.*.tax_rate.required' => 'La tasa de impuesto es obligatoria',
            'items.*.tax_rate.min' => 'La tasa de impuesto no puede ser negativa',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->canCreateInModule('invoices');
    }
}
