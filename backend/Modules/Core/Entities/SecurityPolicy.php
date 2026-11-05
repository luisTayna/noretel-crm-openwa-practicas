<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\Cache;

class SecurityPolicy extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'policy_type',
        'configuration',
        'active',
        'update_date',
        'version'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'configuration' => 'array',
        'update_date' => 'datetime'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saved(function ($policy) {
            // Limpiar la caché cuando se actualiza una política
            Cache::forget("security_policy.{$policy->policy_type}");
        });

        static::deleted(function ($policy) {
            // Limpiar la caché cuando se elimina una política
            Cache::forget("security_policy.{$policy->policy_type}");
        });
    }

    /**
     * Get the active policy configuration for a specific type.
     *
     * @param string $policyType
     * @return array|null
     */
    public static function getActivePolicy($policyType)
    {
        $cacheKey = "security_policy.{$policyType}";
        
        return Cache::remember($cacheKey, 60 * 60, function () use ($policyType) {
            $policy = static::where('policy_type', $policyType)
                ->where('active', true)
                ->latest('update_date')
                ->first();

            return $policy ? $policy->configuration : null;
        });
    }

    /**
     * Create or update a security policy.
     *
     * @param string $policyType
     * @param array $configuration
     * @param string $name
     * @param bool $active
     * @return SecurityPolicy
     */
    public static function updatePolicy($policyType, $configuration, $name, $active = true)
    {
        // Si la política está activa, desactivar las anteriores
        if ($active) {
            static::where('policy_type', $policyType)
                ->where('active', true)
                ->update(['active' => false]);
        }
        
        // Determinar la nueva versión
        $lastVersion = static::where('policy_type', $policyType)
            ->latest('update_date')
            ->value('version') ?? '0.0.0';
        
        list($major, $minor, $patch) = array_map('intval', explode('.', $lastVersion));
        $patch++;
        $newVersion = "{$major}.{$minor}.{$patch}";
        
        // Crear la nueva política
        $policy = static::create([
            'name' => $name,
            'policy_type' => $policyType,
            'configuration' => $configuration,
            'active' => $active,
            'update_date' => now(),
            'version' => $newVersion
        ]);
        
        // Limpiar caché
        Cache::forget("security_policy.{$policyType}");
        
        return $policy;
    }

    /**
     * Validate password against the password policy.
     *
     * @param string $password
     * @return array|true Array of errors or true if valid
     */
    public static function validatePassword($password)
    {
        $policy = static::getActivePolicy('password');
        
        if (!$policy) {
            return true;
        }
        
        $errors = [];
        
        if (isset($policy['min_length']) && strlen($password) < $policy['min_length']) {
            $errors[] = "La contraseña debe tener al menos {$policy['min_length']} caracteres.";
        }
        
        if (isset($policy['require_numbers']) && $policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número.';
        }
        
        if (isset($policy['require_uppercase']) && $policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra mayúscula.';
        }
        
        if (isset($policy['require_lowercase']) && $policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra minúscula.';
        }
        
        if (isset($policy['require_special']) && $policy['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un carácter especial.';
        }
        
        return empty($errors) ? true : $errors;
    }
}