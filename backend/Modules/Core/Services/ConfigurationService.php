<?php

namespace Modules\Core\Services;

use Modules\Core\Repositories\SystemConfigurationRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Cache;

class ConfigurationService
{
    /**
     * @var SystemConfigurationRepository
     */
    protected $configRepository;

    /**
     * ConfigurationService constructor.
     *
     * @param SystemConfigurationRepository $configRepository
     */
    public function __construct(SystemConfigurationRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * Get a configuration value
     *
     * @param string $module
     * @param string $parameter
     * @param mixed $default
     * @return mixed
     */
    public function getValue($module, $parameter, $default = null)
    {
        return $this->configRepository->getValue($module, $parameter, $default);
    }

    /**
     * Set a configuration value
     *
     * @param string $module
     * @param string $parameter
     * @param mixed $value
     * @param string $dataType
     * @param bool $editable
     * @param string|null $description
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function setValue($module, $parameter, $value, $dataType, $editable, $description, $updatedBy, $ip)
    {
        // Obtener configuración anterior
        $oldConfig = $this->configRepository->findBy('parameter', $parameter);
        
        // Establecer nuevo valor
        $config = $this->configRepository->setValue($module, $parameter, $value, $dataType, $editable, $description);
        
        // Registrar acción
        AuditLog::register(
            $updatedBy,
            'config_updated',
            'configuration',
            "Configuración actualizada: {$module}.{$parameter}",
            $ip,
            $oldConfig ? ['value' => $oldConfig->value] : null,
            ['value' => $config->value]
        );
        
        return [
            'success' => true,
            'config' => $config
        ];
    }

    /**
     * Get all configurations for a module
     *
     * @param string $module
     * @return array
     */
    public function getModuleConfig($module)
    {
        return $this->configRepository->getModuleConfig($module);
    }

    /**
     * Get all configurations grouped by module
     *
     * @return array
     */
    public function getAllConfigs()
    {
        return $this->configRepository->getAllConfigsByModule();
    }

    /**
     * Import configurations from JSON
     *
     * @param string $json
     * @param int $importedBy
     * @param string $ip
     * @return array
     */
    public function importFromJson($json, $importedBy, $ip)
    {
        try {
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'El formato JSON es inválido: ' . json_last_error_msg()
                ];
            }
            
            $count = $this->configRepository->importConfigs($data);
            
            // Registrar acción
            AuditLog::register(
                $importedBy,
                'config_imported',
                'configuration',
                "Configuraciones importadas: {$count}",
                $ip
            );
            
            return [
                'success' => true,
                'message' => "Se importaron {$count} configuraciones correctamente."
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al importar configuraciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Export configurations to JSON
     *
     * @param array $modules Lista de módulos a exportar, null para exportar todos
     * @param int $exportedBy
     * @param string $ip
     * @return array
     */
    public function exportToJson($modules = null, $exportedBy, $ip)
    {
        try {
            $configs = $this->configRepository->exportConfigs();
            
            // Filtrar por módulos si es necesario
            if ($modules) {
                $filteredConfigs = [];
                foreach ($modules as $module) {
                    if (isset($configs[$module])) {
                        $filteredConfigs[$module] = $configs[$module];
                    }
                }
                $configs = $filteredConfigs;
            }
            
            $json = json_encode($configs, JSON_PRETTY_PRINT);
            
            // Registrar acción
            AuditLog::register(
                $exportedBy,
                'config_exported',
                'configuration',
                "Configuraciones exportadas: " . count($configs),
                $ip
            );
            
            return [
                'success' => true,
                'data' => $json
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al exportar configuraciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reset a configuration to default
     *
     * @param string $module
     * @param string $parameter
     * @param int $resetBy
     * @param string $ip
     * @return array
     */
    public function resetToDefault($module, $parameter, $resetBy, $ip)
    {
        // Obtener configuración anterior
        $oldConfig = $this->configRepository->findBy('parameter', $parameter);
        
        if (!$oldConfig) {
            return [
                'success' => false,
                'message' => 'La configuración no existe.'
            ];
        }
        
        // Eliminar configuración para que use el valor por defecto del sistema
        $oldConfig->delete();
        
        // Limpiar caché
        Cache::forget("config.{$module}.{$parameter}");
        Cache::forget("config.{$module}");
        
        // Registrar acción
        AuditLog::register(
            $resetBy,
            'config_reset',
            'configuration',
            "Configuración restablecida: {$module}.{$parameter}",
            $ip,
            ['value' => $oldConfig->value],
            null
        );
        
        return [
            'success' => true,
            'message' => 'Configuración restablecida a valores por defecto.'
        ];
    }
}