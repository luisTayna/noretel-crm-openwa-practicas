<style>
    .repair-wrap { display: grid; gap: 1.25rem; }
    .repair-hero { padding: 1.5rem; border-radius: 22px; color: #fff; background: linear-gradient(135deg, #0f3057, #0ea5e9); }
    .repair-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
    .repair-stat { padding: 1rem; border-radius: 16px; background: rgba(255,255,255,.14); }
    .repair-card { background: #fff; border: 1px solid #e7edf6; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 28px rgba(15,23,42,.06); }
    .repair-card-header { padding: 1rem 1.25rem; border-bottom: 1px solid #e7edf6; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
    .repair-group { padding: 1.1rem 1.25rem; border-bottom: 1px solid #edf2f7; }
    .repair-group:last-child { border-bottom: 0; }
    .repair-invoices { display: grid; gap: .65rem; margin-top: .8rem; }
    .repair-invoice { display: grid; grid-template-columns: 1.1fr .8fr .8fr .7fr .7fr auto; gap: .75rem; padding: .75rem; border-radius: 12px; background: #f8fafc; align-items: center; }
    .repair-payment-details { grid-column: 1 / -1; padding-top: .65rem; border-top: 1px dashed #d7dfeb; }
    .repair-payment-row { display: flex; flex-wrap: wrap; gap: .45rem 1rem; font-size: .82rem; color: #475467; }
    .repair-payment-row strong { color: #1d2939; }
    .badge-safe { color: #067647; background: #d1fadf; }
    .badge-manual { color: #b42318; background: #fee4e2; }
    .repair-badge { display: inline-block; padding: .3rem .65rem; border-radius: 999px; font-size: .78rem; font-weight: 700; }
    @media (max-width: 900px) {
        .repair-stats { grid-template-columns: 1fr; }
        .repair-invoice { grid-template-columns: 1fr 1fr; }
    }
</style>

<div class="repair-wrap">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="repair-hero">
        <h2 class="mb-2"><i class="fas fa-shield-alt me-2"></i>Auditoría global de períodos duplicados</h2>
        <p class="mb-0">Revisa todos los clientes. Un caso seguro conserva el recibo pagado por adelantado y anula solamente el duplicado automático sin pagos.</p>
        <div class="repair-stats">
            <div class="repair-stat"><strong class="d-block fs-3">{{ $groups->count() }}</strong><span>Períodos duplicados</span></div>
            <div class="repair-stat"><strong class="d-block fs-3">{{ $safeGroups->count() }}</strong><span>Casos seguros</span></div>
            <div class="repair-stat"><strong class="d-block fs-3">{{ $manualGroups->count() }}</strong><span>Revisión manual</span></div>
        </div>
    </section>

    <section class="repair-card">
        <div class="repair-card-header">
            <div>
                <h4 class="mb-1">Casos encontrados</h4>
                <small class="text-muted">La pantalla no crea ni mueve pagos.</small>
            </div>
            <span class="text-muted">Los casos seguros se corrigen individualmente con la acción existente de anulación.</span>
        </div>

        @forelse($groups as $group)
            <article class="repair-group">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h5 class="mb-1">{{ optional($group['customer'])->full_name ?: 'Cliente no disponible' }}</h5>
                        <div class="text-muted">
                            Contrato #{{ $group['contract_id'] }} · {{ ucfirst($group['period_label']) }}
                            @if(optional($group['customer'])->identity_document)
                                · DNI/RUC {{ $group['customer']->identity_document }}
                            @endif
                        </div>
                    </div>
                    <span class="repair-badge {{ $group['safe'] ? 'badge-safe' : 'badge-manual' }}">
                        {{ $group['safe'] ? 'Corrección segura' : 'Revisión manual' }}
                    </span>
                </div>

                <div class="repair-invoices">
                    @foreach($group['invoices'] as $detail)
                        @php($invoice = $detail['invoice'])
                        <div class="repair-invoice">
                            <div>
                                <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="fw-bold">{{ $invoice->invoice_number }}</a>
                                <small class="d-block text-muted">{{ $invoice->billing_period }}</small>
                            </div>
                            <div><small class="text-muted d-block">Estado</small>{{ ucfirst($invoice->status) }}</div>
                            <div><small class="text-muted d-block">Generación</small>{{ ucfirst($invoice->generation_type) }}</div>
                            <div><small class="text-muted d-block">Pagado</small>S/ {{ number_format($detail['paid'], 2) }}</div>
                            <div><small class="text-muted d-block">Pendiente</small>S/ {{ number_format($detail['pending'], 2) }}</div>
                            <div>
                                @if($group['safe'] && $detail['safe_duplicate'])
                                    <form method="POST" action="{{ route('billing.invoices.void', $invoice->id) }}" onsubmit="return confirm('Se anulará {{ $invoice->invoice_number }} porque es un duplicado automático sin pagos y existe otro recibo cubierto para el mismo mes. ¿Continuar?');">
                                        @csrf
                                        <input type="hidden" name="reason" value="Duplicado automático de un período cubierto mediante pago adelantado.">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-ban me-1"></i>Anular duplicado
                                        </button>
                                    </form>
                                @elseif(! $group['safe']
                                    && $invoice->payments->where('status', 'completed')->isEmpty()
                                    && $invoice->creditNotes->where('status', 'applied')->isEmpty()
                                    && ! in_array($invoice->status, ['paid', 'void', 'cancelled'], true))
                                    <form method="POST" action="{{ route('billing.invoices.void', $invoice->id) }}" onsubmit="return confirm('CONFIRMACIÓN MANUAL: se anulará {{ $invoice->invoice_number }}. Esta acción solo es correcta si verificaste que es el recibo duplicado y que no contiene dinero del cliente. ¿Continuar?');">
                                        @csrf
                                        <input type="hidden" name="reason" value="Duplicado confirmado mediante revisión manual de períodos y pagos.">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-ban me-1"></i>Anular factura
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @if($invoice->payments->isNotEmpty() || $invoice->creditNotes->isNotEmpty())
                                <div class="repair-payment-details">
                                    @forelse($invoice->payments as $payment)
                                        <div class="repair-payment-row mb-1">
                                            <span><strong>Pago #:</strong> {{ $payment->id }}</span>
                                            <span><strong>Estado:</strong> {{ $payment->status }}</span>
                                            <span><strong>Fecha:</strong> {{ optional($payment->payment_date)->format('d/m/Y') ?: $payment->payment_date }}</span>
                                            <span><strong>Monto:</strong> S/ {{ number_format((float) $payment->amount, 2) }}</span>
                                            <span><strong>Método:</strong> {{ $payment->payment_method ?: 'Sin método' }}</span>
                                            <span><strong>Referencia:</strong> {{ $payment->reference ?: 'Sin referencia' }}</span>
                                            <span><strong>Grupo:</strong> {{ data_get($payment->payment_details, 'payment_group_id') ?: 'Sin grupo' }}</span>
                                            @if(! $group['safe'] && $payment->status === 'completed')
                                                <form method="POST" action="{{ route('billing.payments.void', $payment->id) }}" onsubmit="return confirm('CONFIRMACIÓN MANUAL: se anulará el pago #{{ $payment->id }} por S/ {{ number_format((float) $payment->amount, 2) }}. Hazlo solamente si comprobaste que ese pago también está duplicado. Si fue dinero real adicional del cliente, debe convertirse en saldo a favor y NO anularse. ¿Continuar?');">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Pago duplicado confirmado durante la reparación manual de períodos.">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-undo me-1"></i>Anular pago duplicado
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @empty
                                    @endforelse
                                    @foreach($invoice->creditNotes as $creditNote)
                                        <div class="repair-payment-row mb-1">
                                            <span><strong>Nota de crédito #:</strong> {{ $creditNote->id }}</span>
                                            <span><strong>Estado:</strong> {{ $creditNote->status }}</span>
                                            <span><strong>Monto:</strong> S/ {{ number_format((float) $creditNote->amount, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <small class="d-block mt-2 {{ $group['safe'] ? 'text-success' : 'text-danger' }}">{{ $group['reason'] }}</small>
            </article>
        @empty
            <div class="p-5 text-center">
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h4>No existen períodos duplicados activos</h4>
                <p class="text-muted mb-0">No se encontraron clientes con más de un recibo activo para el mismo contrato y mes.</p>
            </div>
        @endforelse
    </section>
</div>
