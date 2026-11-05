<?php

namespace Modules\Core\Services;

use Modules\Core\Repositories\UserRepository;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Repositories\SystemConfigurationRepository;
use Modules\Core\Entities\SecurityPolicy;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AuthService
{
    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var SystemConfigurationRepository
     */
    protected $configRepository;

    /**
     * AuthService constructor.
     *
     * @param UserRepository $userRepository
     * @param SystemConfigurationRepository $configRepository
     */
    public function __construct(
        UserRepository $userRepository,
        SystemConfigurationRepository $configRepository
    ) {
        $this->userRepository = $userRepository;
        $this->configRepository = $configRepository;
    }

    /**
     * Authenticate a user
     *
     * @param string $username
     * @param string $password
     * @param string $ip
     * @return array
     */
    public function login($username, $password, $ip)
    {
        // Verificar max intentos de login
        $maxLoginAttempts = $this->configRepository->getValue('security', 'max_login_attempts', 5);
        $lockoutTime = $this->configRepository->getValue('security', 'lockout_time', 15); // minutos

        // Buscar usuario por nombre de usuario o email
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            $user = $this->userRepository->findByEmail($username);
        }

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ];
        }

        // Verificar si la cuenta está activa
        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'La cuenta no está activa.'
            ];
        }

        // Verificar intentos fallidos
        $failedAttempts = AuditLog::where('user_id', $user->id)
            ->where('action_type', 'login_failed')
            ->where('action_date', '>=', Carbon::now()->subMinutes($lockoutTime))
            ->count();

        if ($failedAttempts >= $maxLoginAttempts) {
            return [
                'success' => false,
                'message' => "Demasiados intentos fallidos. Intente nuevamente después de {$lockoutTime} minutos."
            ];
        }
        // Verificar contraseña
        if (!Hash::check($password, $user->password)) {
            // Registrar intento fallido
            AuditLog::register(
                $user->id,
                'login_failed',
                'auth',
                'Intento de inicio de sesión fallido',
                $ip
            );

            return [
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ];
        }

        // Generar inicio de sesión
        Auth::login($user);

        // Actualizar última fecha de acceso
        $user->last_access = Carbon::now();
        $user->save();

        // Registrar inicio de sesión exitoso
        AuditLog::register(
            $user->id,
            'login_successful',
            'auth',
            'Inicio de sesión exitoso',
            $ip
        );

        return [
            'success' => true,
            'user' => $user,
            'requires_2fa' => $user->requires_2fa
        ];
    }

    /**
     * Logout the user
     *
     * @param string $ip
     * @return bool
     */
    public function logout($ip)
    {
        $user = Auth::user();

        if ($user) {
            // Registrar cierre de sesión
            AuditLog::register(
                $user->id,
                'logout',
                'auth',
                'Cierre de sesión',
                $ip
            );
        }

        Auth::logout();

        return true;
    }

    /**
     * Register a new user
     *
     * @param array $data
     * @param string $ip
     * @return array
     */
    public function register(array $data, $ip)
    {
        // Validar política de contraseñas
        $passwordValidation = SecurityPolicy::validatePassword($data['password']);

        if ($passwordValidation !== true) {
            return [
                'success' => false,
                'message' => 'La contraseña no cumple con la política de seguridad.',
                'errors' => $passwordValidation
            ];
        }

        // Crear usuario
        $user = $this->userRepository->create($data);

        // Registrar creación de usuario
        AuditLog::register(
            $user->id,
            'user_registered',
            'auth',
            'Usuario registrado',
            $ip
        );

        return [
            'success' => true,
            'user' => $user
        ];
    }

    /**
     * Request password reset
     *
     * @param string $email
     * @param string $ip
     * @return array
     */
    public function requestPasswordReset($email, $ip)
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ];
        }

        // Generar token de restablecimiento
        $token = \Str::random(60);

        // Guardar token en la base de datos
        \DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()
        ]);

        // Registrar solicitud de restablecimiento
        AuditLog::register(
            $user->id,
            'password_reset_request',
            'auth',
            'Solicitud de restablecimiento de contraseña',
            $ip
        );

        // Aquí se enviaría el correo con el enlace de restablecimiento
        // Este código normalmente invocaría el servicio de notificaciones

        return [
            'success' => true,
            'message' => 'Se ha enviado un correo con instrucciones para restablecer la contraseña.'
        ];
    }

    /**
     * Reset password
     *
     * @param string $token
     * @param string $email
     * @param string $password
     * @param string $ip
     * @return array
     */
    public function resetPassword($token, $email, $password, $ip)
    {
        // Validar política de contraseñas
        $passwordValidation = SecurityPolicy::validatePassword($password);

        if ($passwordValidation !== true) {
            return [
                'success' => false,
                'message' => 'La contraseña no cumple con la política de seguridad.',
                'errors' => $passwordValidation
            ];
        }

        // Buscar el token
        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            return [
                'success' => false,
                'message' => 'Token inválido o expirado.'
            ];
        }

        // Verificar token
        if (!Hash::check($token, $resetRecord->token)) {
            return [
                'success' => false,
                'message' => 'Token inválido.'
            ];
        }

        // Verificar expiración
        $expirationTime = $this->configRepository->getValue('security', 'password_reset_expiration', 60); // minutos

        if (Carbon::parse($resetRecord->created_at)->addMinutes($expirationTime)->isPast()) {
            return [
                'success' => false,
                'message' => 'El token ha expirado.'
            ];
        }

        // Buscar usuario
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ];
        }

        // Actualizar contraseña
        $this->userRepository->update($user->id, [
            'password' => $password
        ]);

        // Eliminar token
        \DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        // Registrar cambio de contraseña
        AuditLog::register(
            $user->id,
            'password_reset',
            'auth',
            'Contraseña restablecida',
            $ip
        );

        return [
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.'
        ];
    }

    /**
     * Change password
     *
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @param string $ip
     * @return array
     */
    public function changePassword($userId, $currentPassword, $newPassword, $ip)
    {
        $user = $this->userRepository->find($userId);

        // Verificar contraseña actual
        if (!Hash::check($currentPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.'
            ];
        }

        // Validar política de contraseñas
        $passwordValidation = SecurityPolicy::validatePassword($newPassword);

        if ($passwordValidation !== true) {
            return [
                'success' => false,
                'message' => 'La contraseña no cumple con la política de seguridad.',
                'errors' => $passwordValidation
            ];
        }

        // Actualizar contraseña
        $this->userRepository->update($userId, [
            'password' => $newPassword
        ]);

        // Registrar cambio de contraseña
        AuditLog::register(
            $userId,
            'password_changed',
            'auth',
            'Contraseña cambiada por el usuario',
            $ip
        );

        return [
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.'
        ];
    }
}

