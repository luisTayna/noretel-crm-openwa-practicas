<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Pago</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #000000;
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            line-height: 1.35;
        }

        .ticket {
            width: 72mm;
            max-width: 72mm;
            margin: 0 auto;
            padding: 4mm 3mm;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .brand {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .small {
            font-size: 10px;
        }

        .separator {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .row {
            width: 100%;
            clear: both;
        }

        .row::after {
            content: "";
            display: block;
            clear: both;
        }

        .left {
            float: left;
            width: 54%;
        }

        .right {
            float: right;
            width: 46%;
            text-align: right;
        }

        .item {
            margin-bottom: 6px;
        }

        .item-description {
            font-weight: bold;
        }

        .item-meta {
            font-size: 10px;
        }

        .item-amount {
            text-align: right;
            font-weight: bold;
        }

        .total-row {
            font-size: 14px;
            font-weight: bold;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 10px;
        }
        .no-print { display: block; font-family: Arial, sans-serif; margin: 10px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Imprimir</button>
    <button onclick="window.close()">Cerrar</button>
</div>
@php
    $receipt = $receipt ?? app(\Modules\Billing\Services\PaymentReceiptService::class)->build($payment, $intent ?? null);
    $receiptItems = collect(data_get($receipt, 'receiptItems', data_get($receipt, 'lines', [])));
    $totalPaid = (float) data_get($receipt, 'totalPaid', data_get($receipt, 'total', $receiptItems->sum(fn ($item) => (float) data_get($item, 'amount', 0))));
    $totalReceived = (float) data_get($receipt, 'totalReceived', $totalPaid);
    $appliedToDebt = (float) data_get($receipt, 'appliedToDebt', $receiptItems->sum(fn ($item) => (float) data_get($item, 'amount', 0)));
    $creditAmount = (float) data_get($receipt, 'creditAmount', 0);
    $paidAt = data_get($receipt, 'paid_at');
    if (is_string($paidAt)) {
        $paidAt = \Carbon\Carbon::parse($paidAt);
    }
    $issuedAt = data_get($receipt, 'issued_at') ?: now();
    if (is_string($issuedAt)) {
        $issuedAt = \Carbon\Carbon::parse($issuedAt);
    }
    $paymentDate = optional($paidAt)->format('d/m/Y') ?: now()->format('d/m/Y');
    $paymentTime = optional($paidAt)->format('h:i A') ?: (optional($issuedAt)->format('h:i A') ?: now()->format('h:i A'));
    $customerName = data_get($receipt, 'customer_name', '-');
    $customerDocument = data_get($receipt, 'customer_document', '-');
    $contractNumber = data_get($receipt, 'contract_label', '-');
    $serviceAddress = data_get($receipt, 'address');
    $paymentMethod = data_get($receipt, 'payment_method', 'Yape');
    $operationCode = data_get($receipt, 'operation_code', '-');
    $securityCode = data_get($receipt, 'security_code', '-');
    $isPartialPayment = (bool) data_get($receipt, 'isPartialPayment', false);
    $paymentStatusLabel = data_get($receipt, 'paymentStatusLabel', $isPartialPayment ? 'PAGO PARCIAL' : 'PAGADO');
    $invoiceTotal = (float) data_get($receipt, 'invoiceTotal', 0);
    $remainingBalance = (float) data_get($receipt, 'remainingBalance', 0);
    $partialPeriod = (string) $receiptItems->pluck('period')->filter()->first();
    $partialPeriodLabel = $partialPeriod !== '' ? mb_strtoupper($partialPeriod) : 'LA MENSUALIDAD';
    $customNote = data_get($receipt, 'custom_note');
@endphp
<div class="ticket">
    <div class="center brand">NORETEL</div>
    <div class="center title">RECIBO DE PAGO</div>
    <div class="center small">Internet Service Provider</div>
    <div class="center small">Cusco, Perú</div>

    <div class="separator"></div>

    <div class="row">
        <span class="left">COMPROBANTE:</span>
        <span class="right">{{ data_get($receipt, 'receipt_number', '#'.($payment->id ?? $intent->id ?? '-')) }}</span>
    </div>
    <div class="row">
        <span class="left">FECHA:</span>
        <span class="right">{{ $paymentDate }}</span>
    </div>
    <div class="row">
        <span class="left">HORA:</span>
        <span class="right">{{ $paymentTime }}</span>
    </div>

    <div class="separator"></div>

    <div class="section-title">CLIENTE</div>
    <div>{{ $customerName ?: '-' }}</div>
    <div>DNI/RUC: {{ $customerDocument ?: '-' }}</div>
    <div>Contrato: {{ $contractNumber ?: '-' }}</div>
    @if(!empty($serviceAddress) && $serviceAddress !== '-')
        <div>{{ $serviceAddress }}</div>
    @endif

    <div class="separator"></div>

    <div class="section-title">PAGO</div>
    <div class="row">
        <span class="left">Método:</span>
        <span class="right">{{ $paymentMethod ?: 'Yape' }}</span>
    </div>
    <div class="row">
        <span class="left">Operación:</span>
        <span class="right">{{ $operationCode ?: '-' }}</span>
    </div>
    <div class="row">
        <span class="left">Cód. seguridad:</span>
        <span class="right">{{ $securityCode ?: '-' }}</span>
    </div>
    <div class="row">
        <span class="left">Estado:</span>
        <span class="right">{{ $paymentStatusLabel }}</span>
    </div>

    <div class="separator"></div>

    <div class="section-title">DETALLE</div>

    @forelse($receiptItems as $item)
        <div class="item">
            <div class="item-description">{{ data_get($item, 'description', 'Concepto') }}</div>

            @if(!empty(data_get($item, 'period')))
                <div class="item-meta">{{ data_get($item, 'period') }}</div>
            @endif

            <div class="item-amount">
                S/ {{ number_format((float) data_get($item, 'amount', 0), 2) }}
            </div>
        </div>
    @empty
        <div class="item">
            <div class="item-description">Pago aplicado</div>
            <div class="item-amount">S/ {{ number_format($totalPaid, 2) }}</div>
        </div>
    @endforelse

    <div class="separator"></div>

    @if($isPartialPayment)
        <div class="row">
            <span class="left">IMPORTE {{ $partialPeriodLabel }}</span>
            <span class="right">S/ {{ number_format($invoiceTotal, 2) }}</span>
        </div>
        <div class="row">
            <span class="left">ABONO RECIBIDO</span>
            <span class="right">S/ {{ number_format($totalReceived, 2) }}</span>
        </div>
        <div class="row total-row">
            <span class="left">RESTA POR PAGAR</span>
            <span class="right">S/ {{ number_format($remainingBalance, 2) }}</span>
        </div>
    @else
        <div class="row">
            <span class="left">APLICADO A DEUDA</span>
            <span class="right">S/ {{ number_format($appliedToDebt, 2) }}</span>
        </div>
    @endif
    @if($creditAmount > 0)
        <div class="row">
            <span class="left">SALDO A FAVOR</span>
            <span class="right">S/ {{ number_format($creditAmount, 2) }}</span>
        </div>
    @endif
    @unless($isPartialPayment)
        <div class="row total-row">
            <span class="left">TOTAL RECIBIDO</span>
            <span class="right">S/ {{ number_format($totalReceived, 2) }}</span>
        </div>
    @endunless

    @if(!empty($customNote))
        <div class="separator"></div>
        <div class="center small" style="font-weight: bold; padding: 2px 0;">
            {{ $customNote }}
        </div>
    @endif

    <div class="separator"></div>

    <div class="footer">
        @if($isPartialPayment)
            <div>Este comprobante acredita un abono de S/ {{ number_format($totalReceived, 2) }}.</div>
            <div>Aún quedan S/ {{ number_format($remainingBalance, 2) }} por pagar{{ $partialPeriod !== '' ? ' de '.$partialPeriod : '' }}.</div>
        @endif
        @if(mb_strtolower($paymentMethod) === 'yape')
            <div>Pago validado por Yape</div>
        @elseif(mb_strtolower($paymentMethod) === 'efectivo')
            <div>Pago realizado en Efectivo</div>
        @else
            <div>Pago registrado vía {{ $paymentMethod }}</div>
        @endif
        <div>Gracias por confiar en NoreTEL</div>
    </div>
</div>
<script>if (window.location.search.includes('auto_print=1')) { window.onload = function () { setTimeout(function () { window.print(); }, 300); }; }</script>
</body>
</html>
