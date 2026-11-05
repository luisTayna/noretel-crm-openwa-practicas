<?php

namespace Modules\Core\Entities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\Permission\Traits\HasRoles; // 👈 AGREGAR ESTO
use Modules\Core\Services\PermissionService;

class User extends Authenticatable implements Auditable
{
    use HasApiTokens, HasFactory, Notifiable, AuditableTrait, HasRoles; // 👈 AGREGAR HasRoles

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'username',
        'email',
        'password',
        'status',
        'requires_2fa',
        'last_access',
        'preferences',
        'email_verified_at', // 👈 AGREGAR ESTO
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_access' => 'datetime',
        'preferences' => 'array',
        'requires_2fa' => 'boolean'
    ];

    /**
     * Get the employee record associated with the user.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the customer associated with the user.
     */
    public function customer()
    {
        return $this->hasOne(\Modules\Customer\Entities\Customer::class);
    }

    /**
     * Get the audit logs for the user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is customer
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    /**
     * Check if user is technician
     */
    public function isTechnician(): bool
    {
        return $this->hasRole('technician');
    }

    /**
     * Check if the user has a specific permission (usando PermissionService personalizado).
     *
     * @param string $permission
     * @param string $module
     * @param array $context
     * @return bool
     */
    public function hasPermission($permission, $module, $context = [])
    {
        // El rol admin concentra los permisos de administración del sistema.
        if ($this->hasRole('admin')) {
            return true;
        }


        // Usar el servicio de permisos personalizado si existe
        if (class_exists(PermissionService::class)) {
            $permissionService = app(PermissionService::class);
            return $permissionService->hasPermission($this->id, $permission, $module, $context);
        }

        // Fallback a Spatie
        return $this->hasPermissionTo($module . '.view');
    }

        /**
     * Check if the user can view a module.
     *
     * @param string $module
     * @param array $context
     * @return bool
     */
    public function canViewModule($module, $context = [])
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        // Intentar con PermissionService personalizado
        if (class_exists(PermissionService::class)) {
            try {
                $permissionService = app(PermissionService::class);
                return $permissionService->canViewModule($this->id, $module, $context);
            } catch (\Exception $e) {
                // Si falla, continuar con Spatie
            }
        }

        // Intentar con Spatie Permission
        try {
            return $this->hasPermissionTo("{$module}.view");
        } catch (\Exception $e) {
            // Si el permiso no existe, denegar por defecto
            return false;
        }
    }

    /**
     * Check if the user can create in a module.
     *
     * @param string $module
     * @param array $context
     * @return bool
     */
    public function canCreateInModule($module, $context = [])
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if (class_exists(PermissionService::class)) {
            $permissionService = app(PermissionService::class);
            return $permissionService->canCreateInModule($this->id, $module, $context);
        }

        return $this->can("{$module}.create");
    }

    /**
     * Check if the user can edit in a module.
     *
     * @param string $module
     * @param array $context
     * @return bool
     */
    public function canEditInModule($module, $context = [])
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if (class_exists(PermissionService::class)) {
            $permissionService = app(PermissionService::class);
            return $permissionService->canEditInModule($this->id, $module, $context);
        }

        return $this->can("{$module}.edit");
    }

    /**
     * Check if the user can delete in a module.
     *
     * @param string $module
     * @param array $context
     * @return bool
     */
    public function canDeleteInModule($module, $context = [])
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if (class_exists(PermissionService::class)) {
            $permissionService = app(PermissionService::class);
            return $permissionService->canDeleteInModule($this->id, $module, $context);
        }

        return $this->can("{$module}.delete");
    }

    /**
     * Get full name (if has customer)
     */
    public function getFullNameAttribute(): string
    {
        if ($this->customer) {
            return $this->customer->full_name;
        }

        if ($this->employee) {
            return "{$this->employee->first_name} {$this->employee->last_name}";
        }

        return $this->username;
    }
}
