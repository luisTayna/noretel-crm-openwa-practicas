@extends('core::layouts.master')

@section('title', 'Editar Política de Seguridad')

@section('page-title', 'Editar Política de Seguridad')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Editar Política: {{ $policy->name }}</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('core.security.update', $policy->id) }}" method="POST" id="policyForm">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $policy->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="policy_type" class="form-label">Tipo de Política <span class="text-danger">*</span></label>
                    <select class="form-select @error('policy_type') is-invalid @enderror" id="policy_type" name="policy_type" required>
                        <option value="">Seleccione un tipo</option>
                        @foreach($policyTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('policy_type', $policy->policy_type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('policy_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Configuración de Política de Contraseñas -->
            <div id="password-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de Contraseñas</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_min_length" class="form-label">Longitud mínima <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.min_length') is-invalid @enderror" id="config_min_length" name="configuration[min_length]" value="{{ old('configuration.min_length', $policy->configuration['min_length'] ?? 8) }}" min="6" max="30">
                        @error('configuration.min_length')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="config_password_history" class="form-label">Historial de contraseñas</label>
                        <input type="number" class="form-control @error('configuration.password_history') is-invalid @enderror" id="config_password_history" name="configuration[password_history]" value="{{ old('configuration.password_history', $policy->configuration['password_history'] ?? 3) }}" min="0" max="24">
                        <div class="form-text">Número de contraseñas anteriores que no se pueden reutilizar (0 para deshabilitar)</div>
                        @error('configuration.password_history')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_expire_days" class="form-label">Caducidad (días)</label>
                        <input type="number" class="form-control @error('configuration.expire_days') is-invalid @enderror" id="config_expire_days" name="configuration[expire_days]" value="{{ old('configuration.expire_days', $policy->configuration['expire_days'] ?? 90) }}" min="0" max="365">
                        <div class="form-text">Días antes de que expire la contraseña (0 para nunca)</div>
                        @error('configuration.expire_days')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="config_require_numbers" name="configuration[require_numbers]" value="1" {{ old('configuration.require_numbers', $policy->configuration['require_numbers'] ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="config_require_numbers">
                                Requerir números
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="config_require_uppercase" name="configuration[require_uppercase]" value="1" {{ old('configuration.require_uppercase', $policy->configuration['require_uppercase'] ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="config_require_uppercase">
                                Requerir mayúsculas
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="config_require_lowercase" name="configuration[require_lowercase]" value="1" {{ old('configuration.require_lowercase', $policy->configuration['require_lowercase'] ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="config_require_lowercase">
                                Requerir minúsculas
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="config_require_special" name="configuration[require_special]" value="1" {{ old('configuration.require_special', $policy->configuration['require_special'] ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="config_require_special">
                                Requerir caracteres especiales
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración de Política de Inicio de Sesión -->
            <div id="login-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de Inicio de Sesión</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_max_attempts" class="form-label">Máximo de intentos fallidos <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.max_attempts') is-invalid @enderror" id="config_max_attempts" name="configuration[max_attempts]" value="{{ old('configuration.max_attempts', $policy->configuration['max_attempts'] ?? 5) }}" min="1" max="10">
                        @error('configuration.max_attempts')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="config_lockout_minutes" class="form-label">Tiempo de bloqueo (minutos) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.lockout_minutes') is-invalid @enderror" id="config_lockout_minutes" name="configuration[lockout_minutes]" value="{{ old('configuration.lockout_minutes', $policy->configuration['lockout_minutes'] ?? 15) }}" min="1" max="1440">
                        @error('configuration.lockout_minutes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_require_2fa" name="configuration[require_2fa]" value="1" {{ old('configuration.require_2fa', $policy->configuration['require_2fa'] ?? 0) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_require_2fa">
                            Requerir autenticación de dos factores
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_allow_remember_me" name="configuration[allow_remember_me]" value="1" {{ old('configuration.allow_remember_me', $policy->configuration['allow_remember_me'] ?? 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_allow_remember_me">
                            Permitir "Recordarme"
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de Política de Bloqueo de Cuenta -->
            <div id="account_lockout-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de Bloqueo de Cuenta</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_auto_lockout_days" class="form-label">Bloqueo automático por inactividad (días)</label>
                        <input type="number" class="form-control @error('configuration.auto_lockout_days') is-invalid @enderror" id="config_auto_lockout_days" name="configuration[auto_lockout_days]" value="{{ old('configuration.auto_lockout_days', $policy->configuration['auto_lockout_days'] ?? 90) }}" min="0" max="365">
                        <div class="form-text">Días antes de bloquear una cuenta inactiva (0 para deshabilitar)</div>
                        @error('configuration.auto_lockout_days')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="config_inactive_warning_days" class="form-label">Aviso de inactividad (días)</label>
                        <input type="number" class="form-control @error('configuration.inactive_warning_days') is-invalid @enderror" id="config_inactive_warning_days" name="configuration[inactive_warning_days]" value="{{ old('configuration.inactive_warning_days', $policy->configuration['inactive_warning_days'] ?? 7) }}" min="0" max="30">
                        <div class="form-text">Días antes de enviar aviso de inactividad (0 para deshabilitar)</div>
                        @error('configuration.inactive_warning_days')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_send_warning_email" name="configuration[send_warning_email]" value="1" {{ old('configuration.send_warning_email', $policy->configuration['send_warning_email'] ?? 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_send_warning_email">
                            Enviar email de advertencia antes del bloqueo
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de Política de Sesión -->
            <div id="session-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de Sesión</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_timeout_minutes" class="form-label">Tiempo de inactividad (minutos) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.timeout_minutes') is-invalid @enderror" id="config_timeout_minutes" name="configuration[timeout_minutes]" value="{{ old('configuration.timeout_minutes', $policy->configuration['timeout_minutes'] ?? 30) }}" min="1" max="1440">
                        @error('configuration.timeout_minutes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="config_absolute_timeout_hours" class="form-label">Tiempo máximo de sesión (horas)</label>
                        <input type="number" class="form-control @error('configuration.absolute_timeout_hours') is-invalid @enderror" id="config_absolute_timeout_hours" name="configuration[absolute_timeout_hours]" value="{{ old('configuration.absolute_timeout_hours', $policy->configuration['absolute_timeout_hours'] ?? 8) }}" min="1" max="168">
                        <div class="form-text">Horas antes de forzar cierre de sesión independientemente de la actividad</div>
                        @error('configuration.absolute_timeout_hours')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_refresh_on_activity" name="configuration[refresh_on_activity]" value="1" {{ old('configuration.refresh_on_activity', $policy->configuration['refresh_on_activity'] ?? 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_refresh_on_activity">
                            Refrescar tiempo de inactividad con actividad del usuario
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_single_session" name="configuration[single_session]" value="1" {{ old('configuration.single_session', $policy->configuration['single_session'] ?? 0) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_single_session">
                            Permitir una sola sesión activa por usuario
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de Política de API -->
            <div id="api-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de API</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_token_expiry_days" class="form-label">Expiración de tokens (días) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.token_expiry_days') is-invalid @enderror" id="config_token_expiry_days" name="configuration[token_expiry_days]" value="{{ old('configuration.token_expiry_days', $policy->configuration['token_expiry_days'] ?? 30) }}" min="1" max="365">
                        @error('configuration.token_expiry_days')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="config_rate_limit_per_minute" class="form-label">Límite de peticiones por minuto <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.rate_limit_per_minute') is-invalid @enderror" id="config_rate_limit_per_minute" name="configuration[rate_limit_per_minute]" value="{{ old('configuration.rate_limit_per_minute', $policy->configuration['rate_limit_per_minute'] ?? 60) }}" min="1" max="1000">
                        @error('configuration.rate_limit_per_minute')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_require_https" name="configuration[require_https]" value="1" {{ old('configuration.require_https', $policy->configuration['require_https'] ?? 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_require_https">
                            Requerir HTTPS para peticiones de API
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configuración de Política de Carga de Archivos -->
            <div id="file_upload-config" class="policy-config" style="display: none;">
                <h6 class="mb-3">Configuración de Política de Carga de Archivos</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="config_max_size_kb" class="form-label">Tamaño máximo (KB) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('configuration.max_size_kb') is-invalid @enderror" id="config_max_size_kb" name="configuration[max_size_kb]" value="{{ old('configuration.max_size_kb', $policy->configuration['max_size_kb'] ?? 10240) }}" min="1" max="102400">
                        @error('configuration.max_size_kb')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label for="config_allowed_extensions" class="form-label">Extensiones permitidas <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('configuration.allowed_extensions') is-invalid @enderror" id="config_allowed_extensions" name="configuration[allowed_extensions]" value="{{ old('configuration.allowed_extensions', is_array($policy->configuration['allowed_extensions'] ?? null) ? implode(',', $policy->configuration['allowed_extensions']) : ($policy->configuration['allowed_extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx,zip')) }}">
                    <div class="form-text">Lista de extensiones separadas por comas</div>
                    @error('configuration.allowed_extensions')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="config_scan_for_malware" name="configuration[scan_for_malware]" value="1" {{ old('configuration.scan_for_malware', $policy->configuration['scan_for_malware'] ?? 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="config_scan_for_malware">
                            Escanear archivos en busca de malware
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="active" name="active" value="1" {{ old('active', $policy->active) ? 'checked' : '' }}>
                <label class="form-check-label" for="active">Activar política</label>
                <div class="form-text">Si está activa, reemplazará a cualquier política activa del mismo tipo</div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('core.security.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Política</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const policyTypeField = document.getElementById('policy_type');
        const configSections = document.querySelectorAll('.policy-config');

        // Función para mostrar la configuración según el tipo de política
        function toggleConfigSections() {
            const selectedType = policyTypeField.value;

            // Ocultar todas las secciones
            configSections.forEach(section => {
                section.style.display = 'none';
            });

            // Mostrar la sección correspondiente al tipo seleccionado
            if (selectedType) {
                const selectedSection = document.getElementById(selectedType + '-config');
                if (selectedSection) {
                    selectedSection.style.display = 'block';
                }
            }
        }

        // Ejecutar al cargar la página
        toggleConfigSections();

        // Ejecutar al cambiar el tipo de política
        policyTypeField.addEventListener('change', toggleConfigSections);
    });
</script>
@endpush
