<div class="container-fluid py-3">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4 bg-dark text-white rounded">
            <h2 class="mb-2"><i class="fas fa-code-branch me-2"></i>Auditoría de contratos posiblemente duplicados</h2>
            <p class="mb-0">
                Esta auditoría compara contratos activos equivalentes creados con hasta 60 segundos de diferencia.
                No pertenece a la auditoría de periodos duplicados dentro del mismo contrato y no ejecuta correcciones masivas.
            </p>
        </div>
    </div>

    @foreach(['success', 'warning', 'error'] as $flashType)
        @if(session($flashType))
            <div class="alert alert-{{ $flashType === 'error' ? 'danger' : $flashType }}">{{ session($flashType) }}</div>
        @endif
    @endforeach

    <div class="card border-primary shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Caso confirmado: Gregorio Casilla Oquinaya</h4>
                <div>Conservar contrato #253 · desactivar contrato duplicado #254</div>
            </div>
            <span class="badge bg-light text-dark">{{ strtoupper($gregorioCase['status']) }}</span>
        </div>
        <div class="card-body">
            <p>{{ $gregorioCase['message'] }}</p>
            <ul class="mb-3">
                <li>La instalación #253 se conserva como registro principal.</li>
                <li>La instalación #254 continúa completed y recibe una nota histórica; no se inventa un estado.</li>
                <li>INV-000501 se restaura a pending solamente si conserva la evidencia de anulación por duplicidad.</li>
                <li>INV-000502 e INV-001151 quedan void; INV-001150 continúa pending.</li>
                <li>Resultado esperado: un contrato activo y deuda total S/ 130.00.</li>
            </ul>

            @if($gregorioCase['status'] === 'ready')
                <form method="POST" action="{{ route('billing.payments.store') }}"
                      onsubmit="return confirm('Se reparará únicamente el caso Gregorio #122. ¿Confirmas que revisaste contratos, facturas y ausencia de movimientos?');">
                    @csrf
                    <input type="hidden" name="duplicate_contract_repair_case" value="{{ $gregorioCase['key'] }}">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-tools me-1"></i> Ejecutar solo reparación de Gregorio
                    </button>
                </form>
            @elseif(in_array($gregorioCase['status'], ['repaired', 'deleted'], true))
                <div class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>{{ $gregorioCase['message'] }}</div>
            @else
                <div class="text-danger fw-bold"><i class="fas fa-ban me-1"></i>La ejecución está bloqueada.</div>
            @endif
        </div>
    </div>

    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-1">Eliminaciones físicas confirmadas por el cuaderno de pagos</h4>
            <div>Leónidas y Delia tienen un solo servicio real. Cada caso se ejecuta individualmente.</div>
        </div>
        <div class="card-body">
            @foreach($confirmedPhysicalDeletionCases as $case)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>{{ $case['customer_name'] }}</h5>
                            <div>Conservar contrato <strong>#{{ $case['keeper_contract_id'] }}</strong> y sus recibos de junio/julio.</div>
                            <div>Eliminar físicamente contrato <strong>#{{ $case['duplicate_contract_id'] }}</strong>, su instalación y recibos paralelos.</div>
                            <div class="mt-2">{{ $case['message'] }}</div>
                        </div>
                        <span class="badge {{ $case['status'] === 'ready' ? 'bg-danger' : ($case['status'] === 'deleted' ? 'bg-success' : 'bg-secondary') }}">
                            {{ strtoupper($case['status']) }}
                        </span>
                    </div>

                    @if($case['status'] === 'ready')
                        <form method="POST" action="{{ route('billing.payments.store') }}" class="mt-3"
                              onsubmit="return confirm('ELIMINACIÓN FÍSICA IRREVERSIBLE: se borrará el contrato #{{ $case['duplicate_contract_id'] }} y sus registros duplicados. ¿Confirmas?');">
                            @csrf
                            <input type="hidden" name="duplicate_contract_repair_case" value="{{ $case['key'] }}">
                            <button type="submit" class="btn btn-danger">
                                Eliminar físicamente solo contrato #{{ $case['duplicate_contract_id'] }}
                            </button>
                        </form>
                    @elseif($case['status'] === 'deleted')
                        <div class="text-success fw-bold mt-3">Eliminación ya aplicada.</div>
                    @else
                        <div class="text-danger fw-bold mt-3">Acción bloqueada; no se modificará nada.</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between">
            <div>
                <h4 class="mb-1">Candidatos globales</h4>
                <small class="text-muted">Los demás casos son únicamente informativos y requieren revisión individual.</small>
            </div>
            <span class="badge bg-secondary align-self-center">{{ $duplicateContractCandidates->count() }} candidatos</span>
        </div>
        <div class="card-body p-0">
            @forelse($duplicateContractCandidates as $candidate)
                <div class="p-4 border-bottom">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">{{ $candidate['customer_name'] }} · cliente #{{ $candidate['customer_id'] }}</h5>
                            <div class="text-muted">DNI/RUC {{ $candidate['identity_document'] ?: 'sin registrar' }}</div>
                        </div>
                        <span class="badge {{ $candidate['risk'] === 'low' ? 'bg-success' : ($candidate['risk'] === 'medium' ? 'bg-warning text-dark' : 'bg-danger') }}">
                            Riesgo {{ $candidate['risk'] }}
                        </span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><strong>Conservar:</strong> #{{ $candidate['keeper_contract_id'] }}</div>
                        <div class="col-md-3"><strong>Posible duplicado:</strong> #{{ $candidate['duplicate_contract_id'] }}</div>
                        <div class="col-md-3"><strong>Diferencia:</strong> {{ $candidate['seconds_apart'] }} segundos</div>
                        <div class="col-md-3"><strong>Periodos coincidentes:</strong> {{ implode(', ', $candidate['overlap_periods']) ?: 'ninguno' }}</div>
                    </div>

                    <p class="mb-2"><strong>Clasificación:</strong> {{ $candidate['reason'] }}</p>
                    <div class="small text-muted mb-3">
                        Instalaciones: {{ $candidate['relations']['installations'] }} ·
                        Facturas: {{ $candidate['relations']['invoices'] }} ·
                        Pagos: {{ $candidate['relations']['payments'] }} ·
                        Notas de crédito: {{ $candidate['relations']['credit_notes'] }} ·
                        Servicios: {{ $candidate['relations']['contracted_services'] }} ·
                        Routers: {{ $candidate['relations']['router_customer'] }} ·
                        Solicitudes: {{ $candidate['relations']['service_requests'] }} ·
                        Onboarding: {{ $candidate['relations']['onboarding_orders'] }} ·
                        Yape: {{ $candidate['relations']['yape_payment_intents'] }}
                    </div>

                    @if(!$candidate['automatic_available'])
                        <span class="badge bg-light text-dark border">Sin acción automática: revisión individual obligatoria</span>
                    @endif

                    <details class="mt-3">
                        <summary class="text-primary" style="cursor:pointer">Ver instalaciones y facturas encontradas</summary>
                        <pre class="bg-light p-3 mt-2 rounded small">{{ json_encode(['installations' => $candidate['installations'], 'invoices' => $candidate['invoices'], 'services_routers_requests_and_other_relations' => $candidate['relation_details']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                </div>
            @empty
                <div class="p-4 text-muted">No se encontraron pares activos que cumplan los criterios de cercanía y equivalencia básica.</div>
            @endforelse
        </div>
    </div>
</div>
