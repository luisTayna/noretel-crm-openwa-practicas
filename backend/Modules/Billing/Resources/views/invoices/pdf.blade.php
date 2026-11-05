<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }} - NorETEL</title>
    <style>
        @page {
            margin: 34px 40px;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .header {
            border-bottom: 3px solid #1d4ed8;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .brand {
            color: #1d4ed8;
            font-size: 27px;
            font-weight: bold;
            letter-spacing: .5px;
        }

        .muted {
            color: #6b7280;
        }

        .document-title {
            color: #111827;
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            text-align: right;
            text-transform: uppercase;
        }

        .document-code {
            color: #1d4ed8;
            font-size: 13px;
            font-weight: bold;
            margin-top: 4px;
            text-align: right;
        }

        .status {
            border: 1px solid #93c5fd;
            border-radius: 12px;
            color: #1d4ed8;
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            text-transform: uppercase;
        }

        .section {
            border: 1px solid #d1d5db;
            margin-bottom: 14px;
            padding: 12px 14px;
        }

        .section-title {
            color: #1d4ed8;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: .4px;
            margin: 0 0 9px;
            text-transform: uppercase;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .info td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .label {
            color: #4b5563;
            font-weight: bold;
            width: 24%;
        }

        .items th {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            font-size: 11px;
            padding: 7px;
            text-align: left;
            text-transform: uppercase;
        }

        .items td {
            border: 1px solid #d1d5db;
            padding: 7px;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals {
            margin-left: auto;
            margin-top: 12px;
            width: 42%;
        }

        .totals td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 8px;
        }

        .grand-total td {
            border-bottom: 2px solid #1d4ed8;
            color: #1d4ed8;
            font-size: 14px;
            font-weight: bold;
        }

        .note {
            background: #f9fafb;
            border-left: 4px solid #1d4ed8;
            margin-top: 12px;
            padding: 10px 12px;
        }

        .signature {
            margin-top: 34px;
            width: 100%;
        }

        .signature td {
            padding-top: 30px;
            text-align: center;
            width: 50%;
        }

        .signature-line {
            border-top: 1px solid #111827;
            display: inline-block;
            padding-top: 6px;
            width: 78%;
        }

        .footer {
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 10px;
            margin-top: 22px;
            padding-top: 10px;
            text-align: center;
        }
    </style>
</head>

<body>
    @php
    $contract = $invoice->contract;
    $customer = optional($contract)->customer;
    $plan = optional($contract)->plan;
    $addresses = optional($customer)->addresses ?: collect();
    $primaryAddress = $addresses->firstWhere('is_primary', true) ?: $addresses->first();
    $statusLabels = [
    'draft' => 'Borrador',
    'sent' => 'Enviado',
    'pending' => $invoice->isOverdue() ? 'Vencido' : 'Pendiente',
    'partial' => 'Pago parcial',
    'paid' => 'Pagado',
    'cancelled' => 'Cancelado',
    'void' => 'Anulado',
    ];
    @endphp

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">NorETEL</div>
                    <div class="muted">Proveedor de servicios de internet</div>
                    <div class="muted">Cusco, Perú</div>
                </td>
                <td>
                    <h1 class="document-title">Recibo de servicio pendiente</h1>
                    <div class="document-code">{{ $invoice->invoice_number }}</div>
                    <!-- <div class="document-code"><span class="status">{{ $statusLabels[$invoice->status] ?? $invoice->status }}</span></div> -->
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">1. Datos del cliente y servicio</h2>
        <table class="info">
            <tr>
                <td class="label">Cliente</td>
                <td>{{ $invoice->billing_name ?: optional($customer)->full_name ?: 'No registrado' }}</td>
                <td class="label">Documento</td>
                <td>{{ $invoice->billing_document ?: optional($customer)->identity_document ?: 'No registrado' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td>{{ $invoice->billing_address ?: optional($primaryAddress)->full_address ?: 'No registrada' }}</td>
                <td class="label">Correo</td>
                <td>{{ $invoice->billing_email ?: optional($customer)->email ?: 'No registrado' }}</td>
            </tr>
            <tr>
                <td class="label">Contrato</td>
                <td>#{{ optional($contract)->id ?: 'No registrado' }}</td>
                <td class="label">Plan</td>
                <td>{{ optional($plan)->name ?: 'No registrado' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">2. Información del recibo</h2>
        <table class="info">
            <tr>
                <td class="label">Fecha de emisión</td>
                <td>{{ optional($invoice->issue_date)->format('d/m/Y') ?: 'No registrada' }}</td>
                <td class="label">Fecha de vencimiento</td>
                <td>{{ optional($invoice->due_date)->format('d/m/Y') ?: 'No registrada' }}</td>
            </tr>
            <tr>
                <td class="label">Período</td>
                <td>{{ $invoice->billing_period ?: 'No especificado' }}</td>
                <td class="label">Referencia de pago</td>
                <td>{{ $invoice->payment_reference ?: 'No registrada' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">3. Detalle de conceptos</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-right">P. Unitario</th>
                    <th class="text-right">Impuesto</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->description }}
                        @if($item->period_start || $item->period_end)
                        <br><span class="muted">
                            Período:
                            {{ $item->period_start ? \Carbon\Carbon::parse($item->period_start)->format('d/m/Y') : '-' }}
                            a
                            {{ $item->period_end ? \Carbon\Carbon::parse($item->period_end)->format('d/m/Y') : '-' }}
                        </span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">S/ {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">S/ {{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="text-right">S/ {{ number_format((float) $item->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td>{{ $invoice->services_detail['description'] ?? 'Servicio contratado' }}</td>
                    <td class="text-center">1</td>
                    <td class="text-right">S/ {{ number_format((float) $invoice->amount, 2) }}</td>
                    <td class="text-right">S/ {{ number_format((float) $invoice->taxes, 2) }}</td>
                    <td class="text-right">S/ {{ number_format((float) $invoice->total_amount, 2) }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="text-right">S/ {{ number_format((float) $invoice->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Impuestos</td>
                <td class="text-right">S/ {{ number_format((float) $invoice->taxes, 2) }}</td>
            </tr>
            <tr>
                <td>Pagado</td>
                <td class="text-right">S/ {{ number_format((float) $invoice->paid_amount, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td>Total</td>
                <td class="text-right">S/ {{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Saldo pendiente</td>
                <td class="text-right">S/ {{ number_format((float) $invoice->pending_amount, 2) }}</td>
            </tr>
        </table>

        @if($invoice->notes)
        <div class="note">
            <strong>Notas:</strong> {{ $invoice->notes }}
        </div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">4. Información para pago</h2>
        <p>
            Este recibo representa el servicio registrado en el sistema NorETEL CRM. No confirma pago hasta que exista una constancia de pago asociada.
            Para evitar restricciones del servicio, regularice el saldo pendiente antes de la fecha de vencimiento.
        </p>
    </div>

    <table class="signature">
        <tr>
            <td><span class="signature-line">Recibí conforme</span></td>
            <td><span class="signature-line">Área de administración NorETEL</span></td>
        </tr>
    </table>

    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} desde el sistema NorETEL CRM.
        WhatsApp no adjunta archivos automáticamente; descargue este PDF y adjúntelo manualmente si corresponde.
    </div>
</body>

</html>
