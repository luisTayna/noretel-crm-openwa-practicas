@extends('core::layouts.master')

@section('title', 'Revisar pagos Yape')
@section('page-title', 'Revisar pagos Yape')

@section('content')

<style>
    body {
        background: radial-gradient(at 0% 0%, rgba(224, 231, 255, 0.6) 0, transparent 50%), 
                    radial-gradient(at 50% 0%, rgba(209, 250, 229, 0.6) 0, transparent 50%), 
                    radial-gradient(at 100% 0%, rgba(254, 226, 226, 0.6) 0, transparent 50%), 
                    #f8fafc !important;
        position: relative;
    }
    .ambient-blob {
        filter: blur(120px) !important;
        opacity: 0.15 !important;
        position: absolute !important;
        pointer-events: none !important;
        border-radius: 50% !important;
        z-index: 0 !important;
    }
    .blob-purple {
        background: #8b5cf6 !important;
    }
    .blob-teal {
        background: #14b8a6 !important;
    }
    .customer-header-card {
        background: rgba(255, 255, 255, 0.45) !important;
        backdrop-filter: blur(20px) !important;
        -webkit-backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05) !important;
        border-radius: 20px !important;
    }
    .yape-intent-card {
        background: rgba(255, 255, 255, 0.45) !important;
        backdrop-filter: blur(20px) !important;
        -webkit-backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.4) !important;
        border-left: 5px solid !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05) !important;
        border-radius: 16px !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .yape-intent-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 36px 0 rgba(31, 38, 135, 0.08) !important;
        background: rgba(255, 255, 255, 0.6) !important;
    }
    .state-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.78rem;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .state-badge-validated {
        background-color: rgba(16, 185, 129, 0.08) !important;
        color: #065f46 !important;
        border-color: rgba(16, 185, 129, 0.15) !important;
    }
    .state-badge-waiting_receiver {
        background-color: rgba(37, 99, 235, 0.08) !important;
        color: #1d4ed8 !important;
        border-color: rgba(37, 99, 235, 0.15) !important;
    }
    .state-badge-waiting_client {
        background-color: rgba(245, 158, 11, 0.08) !important;
        color: #b45309 !important;
        border-color: rgba(245, 158, 11, 0.15) !important;
    }
    .state-badge-observed {
        background-color: rgba(239, 68, 68, 0.08) !important;
        color: #b91c1c !important;
        border-color: rgba(239, 68, 68, 0.15) !important;
    }
    .state-badge-cancelled {
        background-color: rgba(107, 114, 128, 0.08) !important;
        color: #374151 !important;
        border-color: rgba(107, 114, 128, 0.15) !important;
    }
    .evidence-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.4);
        border: 1px solid rgba(226, 232, 240, 0.8);
        transition: all 0.2s ease;
    }
    .evidence-pill.has-evidence {
        background: rgba(16, 185, 129, 0.08) !important;
        color: #059669 !important;
        border-color: rgba(16, 185, 129, 0.2) !important;
    }
    .evidence-pill.no-evidence {
        background: rgba(245, 158, 11, 0.08) !important;
        color: #d97706 !important;
        border-color: rgba(245, 158, 11, 0.2) !important;
    }
    .btn-yape-primary {
        background: linear-gradient(135deg, #4f46e5, #7c3aed) !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 14px rgba(124, 58, 237, 0.2) !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-yape-primary:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(124, 58, 237, 0.3) !important;
        filter: brightness(1.1) !important;
        color: #ffffff !important;
    }
    .btn-yape-secondary {
        background: transparent !important;
        color: #4f46e5 !important;
        border: 2px solid rgba(79, 70, 229, 0.3) !important;
        border-radius: 12px !important;
        box-shadow: none !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-yape-secondary:hover {
        transform: translateY(-2px) !important;
        border-color: #4f46e5 !important;
        background: rgba(79, 70, 229, 0.05) !important;
        color: #4f46e5 !important;
    }
</style>

<div class="position-relative overflow-hidden" style="min-height: 600px;">
    <!-- Ambient Background Blobs -->
    <div class="ambient-blob blob-purple" style="width: 300px; height: 300px; top: 5%; left: -5%;"></div>
    <div class="ambient-blob blob-teal" style="width: 350px; height: 350px; bottom: 15%; right: -10%;"></div>

    <div class="position-relative" style="z-index: 1;">
        <div class="card customer-header-card mb-4 shadow-sm">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="mb-1 fw-bold text-dark">{{ $customer->full_name }}</h4>
                    <div class="text-muted small">DNI: {{ $customer->identity_document ?: '-' }} · {{ $intents->count() }} pago(s) pendiente(s)</div>
                </div>
                <a class="btn btn-yape-secondary px-3 py-2" href="{{ route('billing.yape.review-queue') }}">
                    <i class="fas fa-arrow-left me-1"></i> Volver a clientes
                </a>
            </div>
        </div>

        @foreach($intents as $intent)
        @php
            $statusKey = $intent->status;
            $borderColor = '#f59e0b'; // default yellow for waiting_client_evidence
            $badgeClass = 'state-badge-waiting_client';
            
            if ($statusKey === \Modules\Billing\Entities\YapePaymentIntent::STATUS_VALIDATED) {
                $borderColor = '#10b981'; // green
                $badgeClass = 'state-badge-validated';
            } elseif ($statusKey === \Modules\Billing\Entities\YapePaymentIntent::STATUS_WAITING_RECEIVER) {
                $borderColor = '#3b82f6'; // blue
                $badgeClass = 'state-badge-waiting_receiver';
            } elseif ($statusKey === \Modules\Billing\Entities\YapePaymentIntent::STATUS_OBSERVED) {
                $borderColor = '#ef4444'; // red
                $badgeClass = 'state-badge-observed';
            } elseif ($statusKey === \Modules\Billing\Entities\YapePaymentIntent::STATUS_CANCELLED) {
                $borderColor = '#6b7280'; // grey
                $badgeClass = 'state-badge-cancelled';
            }
        @endphp
        <div class="card yape-intent-card mb-3" style="border-left-color: {{ $borderColor }} !important;">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="mb-3 d-flex align-items-center gap-2">
                        <span class="h4 mb-0 fw-bold text-dark">S/ {{ number_format((float) $intent->expected_amount, 2) }}</span>
                        <span class="state-badge {{ $badgeClass }}">{{ $intent->status_label }}</span>
                    </div>
                    <div class="small text-muted mb-2">Recibo: {{ $intent->invoice?->invoice_number ?: '-' }} · Operación: {{ $intent->operation_code ?: 'Pendiente de leer' }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="evidence-pill {{ $intent->client_evidence_id ? 'has-evidence' : 'no-evidence' }}">
                            <i class="fab fa-whatsapp text-success me-1"></i> WhatsApp {{ $intent->client_evidence_id ? '✓' : '⏳' }}
                        </span>
                        <span class="evidence-pill {{ $intent->receiver_event_id ? 'has-evidence' : 'no-evidence' }}">
                            <i class="fas fa-mobile-alt text-primary me-1"></i> Receptor {{ $intent->receiver_event_id ? '✓' : '⏳' }}
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a class="btn btn-yape-primary px-4 py-2" href="{{ route('billing.yape.show', $intent) }}">
                        <i class="fas fa-eye me-1"></i> Revisar pago
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
