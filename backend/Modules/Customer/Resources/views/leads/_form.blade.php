@csrf

<div class="row mb-3">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $lead->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="contact" class="form-label">Contacto</label>
        <input type="text" class="form-control @error('contact') is-invalid @enderror" id="contact" name="contact" value="{{ old('contact', $lead->contact ?? '') }}" placeholder="Teléfono, correo o referencia">
        @error('contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Si no completa contacto, indique teléfono o correo.</small>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4 mb-3">
        <label for="phone" class="form-label">Teléfono</label>
        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $lead->phone ?? '') }}" placeholder="51999999999">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="email" class="form-label">Correo</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $lead->email ?? '') }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="potential_value" class="form-label">Valor potencial</label>
        <input type="number" step="0.01" min="0" class="form-control @error('potential_value') is-invalid @enderror" id="potential_value" name="potential_value" value="{{ old('potential_value', $lead->potential_value ?? '') }}">
        @error('potential_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4 mb-3">
        <label for="source" class="form-label">Fuente</label>
        <select name="source" id="source" class="form-select @error('source') is-invalid @enderror">
            <option value="">Seleccione una fuente</option>
            @foreach($leadSources as $value => $label)
                <option value="{{ $value }}" {{ old('source', $lead->source ?? '') == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="status" class="form-label">Estado</label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
            @foreach($leadStatuses as $value => $label)
                <option value="{{ $value }}" {{ old('status', $lead->status ?? 'new') == $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="interest" class="form-label">Interés</label>
        <input type="text" class="form-control @error('interest') is-invalid @enderror" id="interest" name="interest" value="{{ old('interest', $lead->interest ?? '') }}" placeholder="plans, coverage, whatsapp...">
        @error('interest')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4 mb-3">
        <label for="capture_date" class="form-label">Fecha de captura</label>
        <input type="date" class="form-control @error('capture_date') is-invalid @enderror" id="capture_date" name="capture_date" value="{{ old('capture_date', isset($lead) && $lead->capture_date ? $lead->capture_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
        @error('capture_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-12 mb-3">
        <label for="message" class="form-label">Mensaje</label>
        <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="4">{{ old('message', $lead->message ?? '') }}</textarea>
        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
