@extends('core::layouts.master')

@section('title', 'Completar Instalación')
@section('page-title', 'Completar Instalación')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-check-circle"></i> Completar instalación #{{ $installation->id }}</h5>
        <a href="{{ route('contract.installations.show', $installation->id) }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            Cliente:
            <strong>{{ optional(optional($installation->contract)->customer)->full_name ?? optional(optional($installation->onboardingOrder)->customer)->full_name ?? 'No registrado' }}</strong>
            @if($installation->contract)
                · Contrato #{{ $installation->contract->id }}
            @elseif($installation->onboardingOrder)
                · Orden de instalación {{ $installation->onboardingOrder->order_number }}
            @endif
        </div>

        <form action="{{ route('contract.installations.complete', $installation->id) }}" method="POST" enctype="multipart/form-data" id="completeInstallationForm">
            @csrf

            <div class="mb-3">
                <label for="completed_date" class="form-label">Fecha de completado <span class="text-danger">*</span></label>
                <input type="datetime-local" name="completed_date" id="completed_date" class="form-control @error('completed_date') is-invalid @enderror" value="{{ old('completed_date', now()->format('Y-m-d\TH:i')) }}" required>
                @error('completed_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="notes" class="form-label">Notas técnicas</label>
                <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $installation->notes) }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            @php($photosRequired = config('contract.installation.require_photos', false))
            <div class="mb-3">
                <label class="form-label">
                    Foto de evidencia
                    @if($photosRequired)
                        <span class="text-danger">*</span>
                    @else
                        <span class="text-muted">(opcional)</span>
                    @endif
                </label>
                <input type="file" name="photos[]" class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror" accept="image/*" {{ $photosRequired ? 'required' : '' }}>
                <input type="text" name="photo_descriptions[]" class="form-control mt-2 @error('photo_descriptions.*') is-invalid @enderror" value="{{ old('photo_descriptions.0', 'Evidencia de instalación') }}" placeholder="Descripción de la evidencia (opcional)" {{ $photosRequired ? 'required' : '' }}>
                <div class="form-text">Puedes subir una foto si deseas dejar evidencia visual de la instalación.</div>
                @error('photos') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @error('photos.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @error('photo_descriptions.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            @php($signatureRequired = config('contract.installation.require_customer_signature', false))
            <div class="mb-3">
                <label class="form-label">
                    Firma del cliente
                    @if($signatureRequired)
                        <span class="text-danger">*</span>
                    @else
                        <span class="text-muted">(opcional)</span>
                    @endif
                </label>
                <div class="border rounded bg-white">
                    <canvas id="signaturePad" height="180" style="width: 100%; touch-action: none;"></canvas>
                </div>
                <input type="hidden" name="customer_signature" id="customer_signature" value="{{ old('customer_signature') }}">
                <div class="form-text">La firma puede registrarse si el cliente está presente y la empresa lo requiere.</div>
                @error('customer_signature') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="clearSignature">Limpiar firma</button>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Marcar como completada
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('signaturePad');
    const input = document.getElementById('customer_signature');

    if (!canvas || !input) {
        return;
    }

    const ctx = canvas.getContext('2d');
    let drawing = false;

    function resizeCanvas() {
        const data = canvas.toDataURL();
        canvas.width = canvas.offsetWidth;
        canvas.height = 180;
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        if (input.value || data.length > 1000) {
            const image = new Image();
            image.onload = () => ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
            image.src = input.value || data;
        }
    }

    function point(event) {
        const rect = canvas.getBoundingClientRect();
        const source = event.touches ? event.touches[0] : event;
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    }

    function start(event) {
        drawing = true;
        const p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        event.preventDefault();
    }

    function move(event) {
        if (!drawing) return;
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        input.value = canvas.toDataURL('image/png');
        event.preventDefault();
    }

    function stop() {
        drawing = false;
        input.value = canvas.toDataURL('image/png');
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start);
    canvas.addEventListener('touchmove', move);
    canvas.addEventListener('touchend', stop);

    document.getElementById('clearSignature').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        input.value = '';
    });
});
</script>
@endpush
