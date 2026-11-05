<div class="container-fluid py-3">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4 bg-primary text-white rounded">
            <h2 class="mb-2"><i class="fas fa-shield-alt me-2"></i>Reparación quirúrgica de pagos multimes</h2>
            <p class="mb-0">
                Esta pantalla está limitada a dos casos identificados. Cada acción vuelve a validar y bloquear pagos,
                facturas y contrato dentro de una transacción. No procesa los duplicados automáticos de la auditoría general.
            </p>
        </div>
    </div>

    @foreach(['success', 'warning', 'error'] as $flashType)
        @if(session($flashType))
            <div class="alert alert-{{ $flashType === 'error' ? 'danger' : $flashType }}">{{ session($flashType) }}</div>
        @endif
    @endforeach

    @foreach($surgicalCases as $case)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h4 class="mb-1">{{ $case['label'] }}</h4>
                    <div class="text-muted">{{ $case['summary'] }}</div>
                </div>
                <span class="badge {{ $case['status'] === 'repaired' ? 'bg-success' : ($case['status'] === 'blocked' ? 'bg-danger' : 'bg-warning text-dark') }}">
                    {{ $case['status_label'] }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><strong>Pago:</strong> #{{ $case['target_payment_id'] }} · S/ {{ number_format($case['amount'], 2) }}</div>
                    <div class="col-md-3"><strong>Origen:</strong> {{ $case['source_invoice'] }} · {{ $case['source_status'] ?? 'No encontrado' }}</div>
                    <div class="col-md-3"><strong>Destino:</strong> {{ $case['destination_invoice'] ?? 'Se creará agosto 2026' }}</div>
                    <div class="col-md-3"><strong>Pago actual:</strong> {{ $case['payment_status'] ?? 'No encontrado' }}</div>
                </div>

                @if($case['status'] === 'pending_review')
                    <form method="POST" action="{{ route('billing.payments.store') }}"
                          onsubmit="return confirm('Esta acción reparará únicamente {{ $case['label'] }}. ¿Confirmas que revisaste los datos?');">
                        @csrf
                        <input type="hidden" name="surgical_repair_case" value="{{ $case['key'] }}">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-tools me-1"></i> Ejecutar solo esta reparación
                        </button>
                    </form>
                @elseif($case['status'] === 'repaired')
                    <div class="text-success"><i class="fas fa-check-circle me-1"></i>La operación ya está reparada. Una repetición se omite.</div>
                @else
                    <div class="text-danger"><i class="fas fa-ban me-1"></i>No se habilita la ejecución mientras el caso esté bloqueado.</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
