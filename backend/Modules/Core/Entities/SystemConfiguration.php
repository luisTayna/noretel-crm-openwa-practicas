<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class SystemConfiguration extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'module',
        'parameter',
        'value',
        'data_type',
        'editable',
        'description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'editable' => 'boolean'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saved(function ($configuration) {
            // Limpiar la caché cuando se actualiza una configuración
            Cache::forget("config.{$configuration->module}.{$configuration->parameter}");
            Cache::forget("config.{$configuration->module}");
        });

        static::deleted(function ($configuration) {
            // Limpiar la caché cuando se elimina una configuración
            Cache::forget("config.{$configuration->module}.{$configuration->parameter}");
            Cache::forget("config.{$configuration->module}");
        });
    }

    /**
     * Get a configuration value by module and parameter.
     *
     * @param string $module
     * @param string $parameter
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($module, $parameter, $default = null)
    {
        // Intentar obtener de caché primero
        $cacheKey = "config.{$module}.{$parameter}";
        
        return Cache::remember($cacheKey, 60 * 60, function () use ($module, $parameter, $default) {
            $config = static::where('module', $module)
                ->where('parameter', $parameter)
                ->first();

            if (!$config) {
                return $default;
            }

            // Convertir el valor al tipo de dato correcto
            switch ($config->data_type) {
                case 'integer':
                    return (int) $config->value;
                case 'float':
                    return (float) $config->value;
                case 'boolean':
                    return $config->value === 'true' || $config->value === '1';
                case 'json':
                    return json_decode($config->value, true);
                default:
                    return $config->value;
            }
        });
    }

    /**
     * Set a configuration value for a module and parameter.
     *
     * @param string $module
     * @param string $parameter
     * @param mixed $value
     * @param string $dataType
     * @param bool $editable
     * @param string|null $description
     * @return SystemConfiguration
     */
    public static function setValue($module, $parameter, $value, $dataType = 'string', $editable = true, $description = null)
    {
        // Preparar el valor para almacenar según el tipo de dato
        $storedValue = $value;
        if ($dataType === 'json' && !is_string($value)) {
            $storedValue = json_encode($value);
        } elseif ($dataType === 'boolean') {
            $storedValue = $value ? 'true' : 'false';
        }

        $config = static::updateOrCreate(
            ['module' => $module, 'parameter' => $parameter],
            [
                'value' => $storedValue,
                'data_type' => $dataType,
                'editable' => $editable,
                'description' => $description
            ]
        );

        // Limpiar la caché
        Cache::forget("config.{$module}.{$parameter}");
        Cache::forget("config.{$module}");

        return $config;
    }

    /**
     * Get all configurations for a specific module.
     *
     * @param string $module
     * @return array
     */
    public static function getModuleConfig($module)
    {
        $cacheKey = "config.{$module}";
        
        return Cache::remember($cacheKey, 60 * 60, function () use ($module) {
            $configs = static::where('module', $module)->get();
            $result = [];

            foreach ($configs as $config) {
                // Convertir el valor al tipo de dato correcto
                switch ($config->data_type) {
                    case 'integer':
                        $value = (int) $config->value;
                        break;
                    case 'float':
                        $value = (float) $config->value;
                        break;
                    case 'boolean':
                        $value = $config->value === 'true' || $config->value === '1';
                        break;
                    case 'json':
                        $value = json_decode($config->value, true);
                        break;
                    default:
                        $value = $config->value;
                }

                $result[$config->parameter] = $value;
            }

            return $result;
        });
    }
}