@extends('core::layouts.master')

@section('title', 'Editar Plantilla de Notificación')

@section('page-title', 'Editar Plantilla de Notificación')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Editar Plantilla: {{ $template->name }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('core.notifications.templates.update', $template->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $template->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Nombre único que identifica esta plantilla (ej: welcome_email, password_reset)</div>
            </div>

            <div class="mb-3">
                <label for="communication_type" class="form-label">Tipo de Comunicación <span class="text-danger">*</span></label>
                <select class="form-select @error('communication_type') is-invalid @enderror" id="communication_type" name="communication_type" required>
                    <option value="">Seleccione un tipo</option>
                    @foreach($communicationTypes as $type)
                        <option value="{{ $type }}" {{ old('communication_type', $template->communication_type) == $type ? 'selected' : '' }}>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
                @error('communication_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3" id="subject-field">
                <label for="subject" class="form-label">Asunto</label>
                <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject', $template->subject) }}">
                @error('subject')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Asunto del email o título de la notificación (requerido para emails)</div>
            </div>

            <div class="mb-3">
                <label for="content" class="form-label">Contenido <span class="text-danger">*</span></label>
                <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="10" required>{{ old('content', $template->content) }}</textarea>
                @error('content')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Utilice variables de sustitución como {nombre}, {empresa}, {fecha}, etc. para personalizar el contenido.
                </div>
            </div>

            <div class="mb-3">
                <label for="variables" class="form-label">Variables Disponibles</label>
                <input type="text" class="form-control @error('variables') is-invalid @enderror" id="variables" name="variables" value="{{ old('variables', is_array($template->variables) ? implode(',', $template->variables) : $template->variables) }}">
                @error('variables')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Lista de variables separadas por comas que pueden ser utilizadas en esta plantilla</div>
            </div>

            <div class="mb-3">
                <label for="language" class="form-label">Idioma <span class="text-danger">*</span></label>
                <select class="form-select @error('language') is-invalid @enderror" id="language" name="language" required>
                    <option value="">Seleccione un idioma</option>
                    @foreach($languages as $lang)
                        <option value="{{ $lang }}" {{ old('language', $template->language) == $lang ? 'selected' : '' }}>
                            {{ $lang == 'es' ? 'Español' : 'Inglés' }}
                        </option>
                    @endforeach
                </select>
                @error('language')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="active" name="active" value="1" {{ old('active', $template->active) ? 'checked' : '' }}>
                <label class="form-check-label" for="active">Plantilla Activa</label>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('core.notifications.templates') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Plantilla</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const communicationTypeField = document.getElementById('communication_type');
        const subjectField = document.getElementById('subject-field');

        // Función para mostrar/ocultar el campo de asunto según el tipo de comunicación
        function toggleSubjectField() {
            if (communicationTypeField.value === 'email') {
                subjectField.style.display = 'block';
                document.getElementById('subject').setAttribute('required', 'required');
            } else {
                subjectField.style.display = 'none';
                document.getElementById('subject').removeAttribute('required');
            }
        }

        // Ejecutar al cargar la página
        toggleSubjectField();

        // Ejecutar al cambiar el tipo de comunicación
        communicationTypeField.addEventListener('change', toggleSubjectField);
    });
</script>
@endpush
