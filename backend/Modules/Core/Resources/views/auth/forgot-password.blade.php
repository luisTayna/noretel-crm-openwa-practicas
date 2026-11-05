@extends('core::layouts.master')

@section('title', 'Recuperar Contraseña')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0">Recuperar Contraseña</h4>
            </div>
            <div class="card-body">
                <p class="text-center mb-4">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
                
                <form method="POST" action="{{ route('core.auth.forgot-password') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Enviar enlace</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <div class="small">
                    <a href="{{ route('core.auth.login') }}">Volver a iniciar sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection