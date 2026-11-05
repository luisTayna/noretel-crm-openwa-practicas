<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Maximum failed login attempts allowed per minute for a given
     * email + IP combination before further attempts are blocked.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Lockout window, in seconds, used to decay the login attempt counter.
     */
    private const LOGIN_DECAY_SECONDS = 60;

    /**
     * Build the rate limiter key for a login attempt, combining the
     * lowercased identifier (email or login) with the requester IP so
     * that unrelated accounts/IPs are never affected by one lockout.
     */
    private function loginThrottleKey(string $identifier, Request $request): string
    {
        return Str::lower($identifier) . '|' . $request->ip();
    }

    /**
     * Record a blocked login attempt in the audit log without ever
     * logging the submitted password.
     */
    private function recordThrottledLogin(string $identifier, Request $request): void
    {
        if (!class_exists('Modules\Core\Entities\AuditLog')) {
            return;
        }

        $user = User::where('email', Str::lower($identifier))->first();

        \Modules\Core\Entities\AuditLog::register(
            $user->id ?? null,
            'auth_login_throttled',
            'auth',
            'Intento de inicio de sesión bloqueado por exceso de intentos fallidos',
            $request->ip()
        );
    }
    // ==================== MÉTODOS WEB (para admin Laravel) ====================

    /**
     * Mostrar formulario de login (para admin Laravel)
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('admin')) {
                return redirect()->route('core.dashboard');
            } elseif ($user->hasRole('technician')) {
                return redirect()->route('operations.dashboard');
            } else {
                return redirect()->route('customer.customers.index');
            }
        }

        return view('core::auth.login');
    }

    /**
     * Procesar login web (para admin Laravel)
     */
    public function loginWeb(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = $this->loginThrottleKey($credentials['email'], $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->recordThrottledLogin($credentials['email'], $request);

            return back()->withErrors([
                'email' => "Demasiados intentos. Intente nuevamente en {$seconds} segundos.",
            ])->withInput($request->only('email'));
        }

        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $user = Auth::user();

            if (!$user->hasAnyRole(['admin', 'sales', 'technician'])) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'No tienes permisos para acceder al panel administrativo.',
                ]);
            }

            if ($user->hasRole('admin')) {
                return redirect()->route('core.dashboard');
            } elseif ($user->hasRole('technician')) {
                return redirect()->route('operations.dashboard');
            } else {
                return redirect()->route('customer.customers.index');
            }
        }

        RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->withInput($request->only('email'));
    }

    /**
     * Cerrar sesión web (para admin Laravel)
     */
    public function logoutWeb(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('core.auth.login')->with('success', 'Sesión cerrada correctamente');
    }

    // ==================== MÉTODOS API (para Vue.js) ====================

    /**
     * Registrar nuevo usuario (API para Vue.js)
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'identity_document' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'customer_type' => 'required|in:individual,company',
            'acquisition_channel' => 'nullable|string',
            'utm_source' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de registro inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generar username único
            $baseUsername = Str::slug($request->first_name . '.' . $request->last_name, '.');
            $username = $baseUsername;
            $counter = 1;

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            // Crear usuario
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $user->assignRole('customer');

            // Crear cliente
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_type' => $request->customer_type,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'identity_document' => $request->identity_document,
                'email' => $request->email,
                'phone' => $request->phone,
                'customer_status' => 'prospect',
                'registration_date' => now(),
                'active' => true,
                'acquisition_channel' => $request->acquisition_channel ?? 'web',
                'utm_source' => $request->utm_source,
                'utm_campaign' => $request->utm_campaign,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '¡Cuenta creada exitosamente!',
                'data' => [
                    'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ],
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'identity_document' => $customer->identity_document,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'customer_status' => $customer->customer_status,
                        'months_as_customer' => $customer->months_as_customer ?? 0,
                    ],
                    'token' => $token,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en registro', [
                'action' => 'auth.register',
                'email' => $request->email,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear cuenta. Por favor, intenta de nuevo.',
                'error' => null,
            ], 500);
        }
    }

    /**
     * Login API (para Vue.js)
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required_without:email|string',
            'email' => 'required_without:login|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de inicio de sesión inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $login = trim((string) ($request->input('login') ?: $request->input('email')));
        $throttleKey = $this->loginThrottleKey($login, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->recordThrottledLogin($login, $request);

            return response()->json([
                'success' => false,
                'message' => "Demasiados intentos. Intente nuevamente en {$seconds} segundos.",
            ], 429);
        }

        $document = preg_replace('/\D+/', '', $login);

        $user = User::where('email', $login)->first();

        if (!$user && $document !== '') {
            $customerByDocument = Customer::where('identity_document', $document)->first();
            $user = $customerByDocument?->user;
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está inactiva. Contacta a soporte.',
            ], 403);
        }

        $customer = Customer::where('user_id', $user->id)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta no tiene un cliente asociado.',
            ], 403);
        }

        if (!$user->hasRole('customer')) {
            $user->assignRole('customer');
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->update(['last_access' => now()]);

        return response()->json([
            'success' => true,
            'message' => '¡Bienvenido!',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'status' => $user->status,
                    'roles' => $user->getRoleNames(),
                ],
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'identity_document' => $customer->identity_document,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'customer_status' => $customer->customer_status,
                    'registration_date' => $customer->registration_date,
                    'months_as_customer' => $customer->months_as_customer ?? 0,
                ] : null,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout API (para Vue.js)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Obtener usuario actual (API para Vue.js)
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = Customer::where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'status' => $user->status,
                    'roles' => $user->getRoleNames(),
                ],
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'identity_document' => $customer->identity_document,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'customer_status' => $customer->customer_status,
                    'registration_date' => $customer->registration_date,
                    'months_as_customer' => $customer->months_as_customer ?? 0,
                ] : null,
            ],
        ]);
    }

    public function changePasswordApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de contraseña inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }


    /**
     * Mostrar formulario de cambio de contraseña
     */
    public function showChangePasswordForm()
    {
        return view('core::auth.change-password');
    }

/**
     * Procesar cambio de contraseña
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual es incorrecta.',
            ]);
        }

        // Validar política de contraseñas (si existe)
        if (class_exists('Modules\Core\Entities\SecurityPolicy')) {
            $passwordValidation = \Modules\Core\Entities\SecurityPolicy::validatePassword($request->password);

            if ($passwordValidation !== true) {
                return back()->withErrors([
                    'password' => $passwordValidation,
                ]);
            }
        }

        // Actualizar contraseña
        $user->password = Hash::make($request->password);
        $user->save();

        // Registrar en auditoría
        if (class_exists('Modules\Core\Entities\AuditLog')) {
            \Modules\Core\Entities\AuditLog::register(
                $user->id,
                'password_changed',
                'auth',
                'Contraseña cambiada por el usuario',
                $request->ip()
            );
        }

        return redirect()->route('core.dashboard')
            ->with('success', 'Contraseña actualizada correctamente');
    }

    /**
     * Mostrar perfil de usuario
     */
    public function showProfile()
    {
        $user = Auth::user();
        return view('core::auth.profile', compact('user'));
    }

    /**
     * Actualizar perfil de usuario
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->username = $request->username;
        $user->email = $request->email;

        if ($request->has('preferences')) {
            $user->preferences = $request->preferences;
        }

        $user->save();

        // Registrar en auditoría
        if (class_exists('Modules\Core\Entities\AuditLog')) {
            \Modules\Core\Entities\AuditLog::register(
                $user->id,
                'profile_updated',
                'auth',
                'Perfil actualizado por el usuario',
                $request->ip()
            );
        }

        return back()->with('success', 'Perfil actualizado correctamente');
    }

    /**
     * Mostrar formulario de recuperación de contraseña
     */
    public function showForgotPasswordForm()
    {
        return view('core::auth.forgot-password');
    }

    /**
     * Enviar enlace de recuperación de contraseña
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Generar token
        $token = Str::random(60);

        // Guardar token en base de datos
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Aquí deberías enviar un email con el enlace
        // Por ahora, solo retornamos éxito

        return back()->with('success', 'Se ha enviado un enlace de recuperación a tu correo electrónico');
    }

/**
 * Mostrar formulario de reseteo de contraseña
 */
    public function showResetPasswordForm($token)
    {
        return view('core::auth.reset-password', compact('token'));
    }

    /**
     * Resetear contraseña
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Verificar token
        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return back()->withErrors(['email' => 'Token de recuperación inválido']);
        }

        // Actualizar contraseña
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Eliminar token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Registrar en auditoría
        if (class_exists('Modules\Core\Entities\AuditLog')) {
            \Modules\Core\Entities\AuditLog::register(
                $user->id,
                'password_reset',
                'auth',
                'Contraseña restablecida vía recuperación',
                $request->ip()
            );
        }

        return redirect()->route('core.auth.login')
            ->with('success', 'Contraseña restablecida correctamente');
    }
}

