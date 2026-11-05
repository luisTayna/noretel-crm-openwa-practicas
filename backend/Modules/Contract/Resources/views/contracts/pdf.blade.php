<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Contrato #{{ $contract->id }} - NorETEL</title>
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
            margin-bottom: 22px;
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
            width: 28%;
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

        .badge {
            border: 1px solid #93c5fd;
            border-radius: 12px;
            color: #1d4ed8;
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            text-transform: uppercase;
        }

        .terms {
            margin: 0;
            padding-left: 18px;
        }

        .terms li {
            margin-bottom: 5px;
        }

        .signature {
            margin-top: 36px;
            width: 100%;
        }

        .signature td {
            padding-top: 34px;
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
    $customer = $contract->customer;
    $plan = $contract->plan;
    $addresses = optional($customer)->addresses ?: collect();
    $primaryAddress = $addresses->firstWhere('is_primary', true) ?: $addresses->first();
    $monthlyAmount = (float) ($contract->final_price ?: optional($plan)->price ?: 0);
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
                    <h1 class="document-title">Contrato de servicio</h1>
                    <div class="document-code">Contrato N° {{ str_pad($contract->id, 6, '0', STR_PAD_LEFT) }}</div>
                    <!-- <div class="document-code"><span class="badge">{{ $contract->status }}</span></div> -->
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">1. Datos del cliente</h2>
        <table class="info">
            <tr>
                <td class="label">Cliente</td>
                <td>{{ optional($customer)->full_name ?: 'No registrado' }}</td>
                <td class="label">Documento</td>
                <td>{{ optional($customer)->identity_document ?: 'No registrado' }}</td>
            </tr>
            <tr>
                <td class="label">Teléfono</td>
                <td>{{ optional($customer)->phone ?: 'No registrado' }}</td>
                <td class="label">Correo</td>
                <td>{{ optional($customer)->email ?: 'No registrado' }}</td>
            </tr>
            <tr>
                <td class="label">Dirección</td>
                <td colspan="3">{{ optional($primaryAddress)->full_address ?: 'No registrada' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">2. Condiciones del servicio contratado</h2>
        <table class="info">
            <tr>
                <td class="label">Plan principal</td>
                <td>{{ optional($plan)->name ?: 'Sin plan registrado' }}</td>
                <td class="label">Servicio</td>
                <td>{{ optional(optional($plan)->service)->name ?: 'Internet' }}</td>
            </tr>
            <tr>
                <td class="label">Velocidad</td>
                <td>
                    Bajada {{ optional($plan)->download_speed ?: 0 }} Mbps /
                    Subida {{ optional($plan)->upload_speed ?: 0 }} Mbps
                </td>
                <td class="label">Precio mensual</td>
                <td>S/ {{ number_format($monthlyAmount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Inicio</td>
                <td>{{ optional($contract->start_date)->format('d/m/Y') ?: 'No registrado' }}</td>
                <td class="label">Fin</td>
                <td>{{ optional($contract->end_date)->format('d/m/Y') ?: 'Indefinido' }}</td>
            </tr>
            <tr>
                <td class="label">IP asignada</td>
                <td>{{ $contract->assigned_ip ?: 'No registrada' }}</td>
                <td class="label">VLAN</td>
                <td>{{ $contract->vlan ?: 'No registrada' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">3. Servicios adicionales y SLA</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Detalle</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Plan base</td>
                    <td>{{ optional($plan)->name ?: 'Sin plan registrado' }}</td>
                    <td class="text-right">S/ {{ number_format($monthlyAmount, 2) }}</td>
                </tr>
                @forelse($contract->contractedServices as $service)
                <tr>
                    <td>Servicio adicional</td>
                    <td>{{ optional($service->additionalService)->name ?: 'Servicio adicional' }}</td>
                    <td class="text-right">S/ {{ number_format((float) $service->price, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3">No se registraron servicios adicionales.</td>
                </tr>
                @endforelse
                <tr>
                    <td>SLA</td>
                    <td colspan="2">
                        @forelse($contract->slas as $sla)
                        {{ $sla->name }}@if(!$loop->last), @endif
                        @empty
                        SLA estándar del servicio.
                        @endforelse
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">4. Términos generales</h2>
        <ol class="terms">
            <li>El cliente declara conocer y aceptar las características técnicas y económicas del servicio contratado.</li>
            <li>NorETEL brindará el servicio conforme al plan registrado, la cobertura técnica disponible y las condiciones de instalación aprobadas para el domicilio del cliente.</li>
            <li>La facturación se realizará de acuerdo con el período comercial registrado en el sistema. El pago oportuno es responsabilidad del cliente y constituye condición para la continuidad del servicio.</li>
            <li>La falta de pago, mora o deuda acumulada puede generar suspensión, restricción temporal o baja del servicio según las políticas comerciales vigentes de NorETEL.</li>
            <li>El soporte técnico se brindará sobre la infraestructura y condiciones asociadas al servicio contratado. La atención podrá requerir validaciones remotas, coordinación de visita técnica o revisión de equipos instalados.</li>
            <li>El cliente se compromete a facilitar el acceso técnico cuando sea necesario, conservar en buen estado los equipos entregados o instalados y reportar oportunamente cualquier incidencia del servicio.</li>
            <li>Los equipos, configuraciones, IP asignada, VLAN u otros parámetros técnicos son administrados por NorETEL y no deben ser modificados sin autorización expresa del área técnica.</li>
            <li>Las velocidades indicadas corresponden al plan comercial contratado y pueden estar sujetas a condiciones técnicas de red, disponibilidad, interferencias, mantenimiento, saturación o factores externos fuera del control directo de NorETEL.</li>
            <li>NorETEL podrá realizar mantenimientos preventivos o correctivos sobre su infraestructura. Cuando corresponda, se procurará informar al cliente por los canales disponibles.</li>
            <li>Cualquier cambio de plan, traslado, suspensión voluntaria, reactivación, renovación, cancelación o modificación de datos técnicos debe quedar registrado en el sistema para tener validez operativa.</li>
            <li>Los datos personales y de contacto del cliente serán utilizados para la gestión del servicio, facturación, cobranza, soporte técnico y comunicaciones relacionadas con la prestación contratada.</li>
            <li>La firma o aceptación del presente documento confirma la conformidad del cliente con los datos registrados, el plan contratado, las condiciones económicas y las obligaciones operativas descritas.</li>
        </ol>
    </div>

    <table class="signature">
        <tr>
            <td><span class="signature-line">Firma del cliente</span></td>
            <td><span class="signature-line">Representante NorETEL</span></td>
        </tr>
    </table>

    <div class="footer">
        Documento generado el {{ now()->format('d/m/Y H:i') }} desde el sistema NorETEL CRM.
        Este documento resume las condiciones comerciales registradas para el contrato indicado.
    </div>
</body>

</html>