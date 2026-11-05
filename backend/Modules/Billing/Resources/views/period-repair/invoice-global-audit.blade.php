<div class="container-fluid py-4">
    <div class="alert alert-info">
        <h3 class="mb-2">Auditoría global de facturación (solo lectura)</h3>
        <p class="mb-0">Esta pantalla no crea, anula ni modifica facturas, pagos o contratos.</p>
    </div>

    @php
        $sections = [
            'Meses activos duplicados dentro del mismo contrato' => $invoiceAudit['duplicateContractMonths'],
            'Contratos activos potencialmente duplicados' => $invoiceAudit['duplicateActiveContracts'],
            'Facturas automáticas en contratos cancelados' => $invoiceAudit['automaticOnCancelled'],
            'Mismo cliente y mes en contratos distintos' => $invoiceAudit['crossContractCustomerMonths'],
            'Facturas con periodo no reconocible' => $invoiceAudit['unrecognized'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach($sections as $title => $cases)
            <div class="col-md-4">
                <div class="card h-100"><div class="card-body">
                    <div class="fs-2 fw-bold">{{ $cases->count() }}</div>
                    <div>{{ $title }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    @foreach($sections as $title => $cases)
        <div class="card mb-4">
            <div class="card-header"><strong>{{ $title }}</strong></div>
            <div class="card-body">
                @forelse($cases as $case)
                    @php($rows = $case instanceof \Illuminate\Support\Collection ? $case : collect([$case]))
                    <div class="border rounded p-3 mb-3">
                        @foreach($rows as $row)
                            @if($row instanceof \Modules\Billing\Entities\Invoice)
                                <div>
                                    <strong>{{ $row->invoice_number }}</strong> · contrato #{{ $row->contract_id }} ·
                                    {{ $row->billing_period ?: 'sin periodo' }} · {{ $row->status }} ·
                                    {{ $row->generation_type ?: 'sin tipo' }}
                                </div>
                            @else
                                <div>
                                    <strong>Contrato #{{ $row->id }}</strong> ·
                                    {{ $row->customer?->full_name ?? 'cliente sin nombre' }} · {{ $row->status }}
                                </div>
                            @endif
                        @endforeach
                    </div>
                @empty
                    <p class="text-success mb-0">No se encontraron casos.</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>