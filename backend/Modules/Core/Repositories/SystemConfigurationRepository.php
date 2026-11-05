<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\SystemConfiguration;
use Illuminate\Support\Facades\Cache;

class SystemConfigurationRepository extends BaseRepository
{
    /**
     * SystemConfigurationRepository constructor.
     *
     * @param SystemConfiguration $model
     */
    public function __construct(SystemConfiguration $model)
    {
        parent::__construct($model);
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
        return SystemConfiguration::getValue($module, $parameter, $default);
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
     * @return SystemConfiguration
     */
    public function setValue($module, $parameter, $value, $dataType = 'string', $editable = true, $description = null)
    {
        return SystemConfiguration::setValue($module, $parameter, $value, $dataType, $editable, $description);
    }

    /**
     * Get all configurations for a module
     *
     * @param string $module
     * @return array
     */
    public function getModuleConfig($module)
    {
        return SystemConfiguration::getModuleConfig($module);
    }

    /**
     * Get all configurations grouped by module
     *
     * @return array
     */
    public function getAllConfigsByModule()
    {
        return Cache::remember('all_configs_by_module', 60, function () {
            $configs = $this->all();
            $result = [];
            
            foreach ($configs as $config) {
                if (!isset($result[$config->module])) {
                    $result[$config->module] = [];
                }
                
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
                
                $result[$config->module][$config->parameter] = [
                    'value' => $value,
                    'editable' => $config->editable,
                    'description' => $config->description,
                    'data_type' => $config->data_type
                ];
            }
            
            return $result;
        });
    }

    /**
     * Import configurations from array
     *
     * @param array $configs
     * @return int Number of imported configs
     */
    public function importConfigs(array $configs)
    {
        $count = 0;
        
        foreach ($configs as $module => $parameters) {
            foreach ($parameters as $parameter => $data) {
                $this->setValue(
                    $module,
                    $parameter,
                    $data['value'],
                    $data['data_type'] ?? 'string',
                    $data['editable'] ?? true,
                    $data['description'] ?? null
                );
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Export all configs to array
     *
     * @return array
     */
    public function exportConfigs()
    {
        return $this->getAllConfigsByModule();
    }
}