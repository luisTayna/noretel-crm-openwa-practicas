@extends('core::layouts.master')

@section('title', 'Recibos de clientes')
@section('page-title', 'Recibos de clientes')

@section('content')
@php
    $statusMeta = [
        'paid' => ['label' => 'Pagada', 'class' => 'success', 'icon' => 'check-circle'],
        'partial' => ['label' => 'Pago parcial', 'class' => 'info', 'icon' => 'adjust'],
        'pending' => ['label' => 'Pendiente', 'class' => 'warning', 'icon' => 'clock'],
        'overdue' => ['label' => 'Vencida', 'class' => 'danger', 'icon' => 'exclamation-triangle'],
        'draft' => ['label' => 'Borrador', 'class' => 'secondary', 'icon' => 'file'],
        'cancelled' => ['label' => 'Cancelada', 'class' => 'secondary', 'icon' => 'ban'],
        'void' => ['label' => 'Anulada', 'class' => 'dark', 'icon' => 'ban'],
    ];

    $periodLabel = function ($invoice) {
        $period = trim((string) $invoice->billing_period);

        if ($period !== '') {
            if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $period, $matches, PREG_SET_ORDER)) {
                try {
                    $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
                    $date = \Carbon\Carbon::create((int) $targetMatch[3], (int) $targetMatch[2], 1);
                    return ucfirst($date->locale('es')->translatedFormat('F Y'));
                } catch (\Throwable) {
                    return $period;
                }
            }

            return ucfirst($period);
        }

        return optional($invoice->issue_date)->locale('es')->translatedFormat('F Y') ?: 'Sin periodo';
    };

    $phoneForInvoice = function ($invoice) {
        $contract = $invoice->contract;
        $customer = $contract?->customer;

        return old(
            'phone',
            $contract?->whatsapp_phone ?: ($customer?->phone ?: $customer?->whatsapp_phone)
        );
    };
@endphp

<style>
    .receipt-workbench {
        display: grid;
        gap: 1.25rem;
    }

    .receipt-hero {
        background: linear-gradient(135deg, #0f3057 0%, #0ea5e9 100%);
        color: #fff;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 18px 40px rgba(15, 48, 87, 0.18);
    }

    .receipt-hero .muted {
        color: rgba(255, 255, 255, 0.75);
    }

    .receipt-card {
        border: 1px solid #e8eef8;
        border-radius: 20px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .receipt-filter {
        background: #f8fbff;
        border-bottom: 1px solid #e8eef8;
        padding: 1rem;
    }

    .receipt-row {
        display: grid;
        grid-template-columns: minmax(260px, 1.4fr) minmax(180px, .8fr) minmax(150px, .7fr) minmax(130px, .6fr) minmax(250px, .9fr);
        gap: 1rem;
        align-items: center;
        padding: 1rem 1.1rem;
        border-bottom: 1px solid #edf2f7;
    }

    .receipt-row:last-child {
        border-bottom: 0;
    }

    .receipt-row:hover {
        background: #f9fbff;
    }

    .receipt-client {
        display: flex;
        gap: .85rem;
        align-items: center;
        min-width: 0;
    }

    .receipt-avatar {
        width: 44px;
        height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #eaf3ff;
        color: #0d6efd;
        flex: 0 0 auto;
    }

    .receipt-actions {
        display: flex;
        gap: .45rem;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .receipt-actions .btn {
        border-radius: 12px;
        font-weight: 700;
    }

    .receipt-mini-label {
        font-size: .72rem;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7280;
        font-weight: 800;
    }

    .receipt-empty {
        padding: 3rem 1rem;
        text-align: center;
    }

    @media (max-width: 1200px) {
        .receipt-row {
            grid-template-columns: 1fr;
            align-items: stretch;
        }

        .receipt-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="receipt-workbench">
    <section class="receipt-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="receipt-mini-label text-white-50 mb-2">Bandeja operativa</div>
                <h3 class="mb-2 fw-bold">Recibos y constancias de clientes</h3>
                <p class="mb-0 muted">
                    Busca un cliente y abre su recibo, imprime la constancia térmica o reenvíala por WhatsApp sin entrar a pantallas técnicas.
                </p>
            </div>
            <div class="d-flex gap-2 align-items-start flex-wrap">
                @can('create-invoices')
                <form action="{{ route('billing.invoices.generate-scheduled') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning text-dark fw-bold"
                            onclick="return confirm('¿Generar recibos mensuales masivos para todos los contratos activos? Esto puede tardar unos minutos.')">
                        <i class="fas fa-bolt me-1"></i> Generar este mes
                    </button>
                </form>
                <a href="{{ route('billing.invoices.create') }}" class="btn btn-light fw-bold text-primary">
                    <i class="fas fa-plus me-1"></i> Nuevo recibo
                </a>
                @endcan
            </div>
        </div>
    </section>

    <section class="card receipt-card">
        <div class="receipt-filter">
            <form action="{{ route('billing.invoices.index') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="search" class="form-label fw-bold">Buscar cliente o recibo</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control" id="search" name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Nombre, DNI, teléfono o Nro. recibo">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <label for="status" class="form-label fw-bold">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            @foreach($statuses as $key => $value)
                                <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                    {{ $value }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label for="from" class="form-label fw-bold">Desde</label>
                        <input type="date" class="form-control" id="from" name="from" value="{{ request('from') }}">
                    </div>
                    <div class="col-lg-2">
                        <label for="to" class="form-label fw-bold">Hasta</label>
                        <input type="date" class="form-control" id="to" name="to" value="{{ request('to') }}">
                    </div>
                    <div class="col-lg-2">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1 fw-bold">
                                Filtrar
                            </button>
                            <a href="{{ route('billing.invoices.index') }}" class="btn btn-light border fw-bold">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            @forelse($invoices as $invoice)
                @php
                    $effectiveStatus = $invoice->status === 'pending' && $invoice->isOverdue() ? 'overdue' : $invoice->status;
                    $meta = $statusMeta[$effectiveStatus] ?? ['label' => ucfirst($invoice->status), 'class' => 'dark', 'icon' => 'circle'];
                    $latestPayment = $invoice->payments->first();
                    $customer = $invoice->contract?->customer;
                    $phone = $phoneForInvoice($invoice);
                @endphp

                <article class="receipt-row">
                    <div class="receipt-client">
                        <span class="receipt-avatar">
                            <i class="fas fa-user"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate">{{ $customer?->full_name ?: $invoice->billing_name ?: 'Cliente no registrado' }}</div>
                            <div class="text-muted small">
                                {{ $customer?->identity_document ?: $invoice->billing_document ?: 'Sin documento' }}
                                @if($customer?->phone)
                                    · {{ $customer->phone }}
                                @endif
                            </div>
                            <div class="small mt-1">
                                <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="fw-bold text-decoration-none">
                                    {{ $invoice->invoice_number }}
                                </a>
                                <span class="text-muted">· {{ optional($invoice->contract?->plan)->name ?: 'Servicio' }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="receipt-mini-label">Periodo</div>
                        <div class="fw-bold">{{ $periodLabel($invoice) }}</div>
                        <div class="text-muted small">Emitido: {{ $invoice->issue_date->format('d/m/Y') }}</div>
                    </div>

                    <div>
                        <div class="receipt-mini-label">Vencimiento</div>
                        <div class="fw-bold">{{ $invoice->due_date->format('d/m/Y') }}</div>
                        @if($effectiveStatus === 'overdue')
                            <div class="text-danger small fw-bold">{{ $invoice->daysOverdue() }} días vencida</div>
                        @endif
                    </div>

                    <div>
                        <div class="receipt-mini-label">Monto</div>
                        <div class="fw-bold fs-5">S/ {{ number_format((float) $invoice->total_amount, 2) }}</div>
                        <span class="badge bg-{{ $meta['class'] }}">
                            <i class="fas fa-{{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                        </span>
                    </div>

                    <div class="receipt-actions">
                        <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i> Ver
                        </a>

                        @if($latestPayment)
                            <a href="{{ route('billing.payments.print', $latestPayment->id) }}" target="_blank" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-receipt me-1"></i> Ticket
                            </a>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#sendReceiptModal-{{ $invoice->id }}">
                                <i class="fab fa-whatsapp me-1"></i> Reenviar
                            </button>
                        @elseif(in_array($invoice->status, ['pending', 'partial']))
                            <a href="{{ route('billing.payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-money-bill-wave me-1"></i> Cobrar
                            </a>
                        @else
                            <span class="text-muted small">Sin pago completado</span>
                        @endif

                        @if($invoice->status == 'draft')
                            <a href="{{ route('billing.invoices.edit', $invoice->id) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                        @endif
                    </div>
                </article>


            @empty
                <div class="receipt-empty">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="fw-bold">No se encontraron recibos</h5>
                    <p class="text-muted mb-3">Prueba buscando por nombre, DNI, teléfono o número de recibo.</p>
                    @can('create-invoices')
                        <a href="{{ route('billing.invoices.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Crear recibo
                        </a>
                    @endcan
                </div>
            @endforelse
        </div>

        @if($invoices->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                <div class="d-flex justify-content-center">
                    {{ $invoices->appends(request()->all())->links() }}
                </div>
            </div>
        @endif
    </section>
</div>

@foreach($invoices as $invoice)
    @php
        $latestPayment = $invoice->payments->first();
        $customer = $invoice->contract?->customer;
        $phone = $phoneForInvoice($invoice);
    @endphp
    @if($latestPayment)
        <div class="modal fade" id="sendReceiptModal-{{ $invoice->id }}" tabindex="-1" aria-labelledby="sendReceiptModalLabel-{{ $invoice->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
                    <form action="{{ route('billing.payments.send-whatsapp', $latestPayment->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 bg-light" style="border-top-left-radius: 18px; border-top-right-radius: 18px;">
                            <h5 class="modal-title fw-bold text-success" id="sendReceiptModalLabel-{{ $invoice->id }}">
                                <i class="fab fa-whatsapp me-2"></i> Reenviar constancia térmica
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info border-0">
                                Se reenviará el ticket térmico del pago <strong>#{{ $latestPayment->id }}</strong>
                                asociado al recibo <strong>{{ $invoice->invoice_number }}</strong>.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Cliente</label>
                                <div class="form-control bg-light">{{ $customer?->full_name ?: $invoice->billing_name }}</div>
                            </div>
                            <div class="mb-3">
                                <label for="phone-{{ $invoice->id }}" class="form-label fw-bold">WhatsApp destino</label>
                                <input type="text" class="form-control" id="phone-{{ $invoice->id }}" name="phone"
                                       value="{{ $phone }}" required pattern="[0-9]{9,20}" placeholder="Ej: 000000000">
                                <div class="form-text">Usa solo números. Si no colocas código país, OpenWA asumirá Perú según la configuración actual.</div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success fw-bold">
                                <i class="fas fa-paper-plane me-1"></i> Enviar ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mover los modales al body para evitar problemas de stacking context / backdrop
        const modals = document.querySelectorAll('[id^="sendReceiptModal-"]');
        modals.forEach(modal => {
            document.body.appendChild(modal);
        });
    });
</script>
@endpush
@endsection
