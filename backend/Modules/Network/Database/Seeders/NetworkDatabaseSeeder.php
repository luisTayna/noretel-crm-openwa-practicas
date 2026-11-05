<?php

namespace Modules\Network\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Network\Entities\Node;
use Modules\Network\Entities\Router;
use Modules\Network\Entities\AutomationRule;

class NetworkDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->seedNodes();
        $this->seedRouters();
        $this->seedAutomationRules();
    }

    /**
     * Crear nodos de red
     */
    protected function seedNodes()
    {
        $nodes = [
            [
                'name' => 'Nodo Central Cusco',
                'code' => 'NOD-CUS-0001',
                'type' => 'core',
                'location' => 'Av. El Sol 123',
                'zone' => 'Centro',
                'district' => 'Cusco',
                'province' => 'Cusco',
                'department' => 'Cusco',
                'latitude' => -13.5226,
                'longitude' => -71.9673,
                'capacity' => 500,
                'current_load' => 0,
                'coverage_radius' => 2000,
                'status' => 'active',
                'is_operational' => true,
                'activated_at' => now()->subMonths(12),
            ],
            [
                'name' => 'Nodo San Sebastián',
                'code' => 'NOD-SBS-0001',
                'type' => 'distribution',
                'location' => 'Av. De la Cultura 456',
                'zone' => 'San Sebastián',
                'district' => 'San Sebastián',
                'province' => 'Cusco',
                'department' => 'Cusco',
                'latitude' => -13.5271,
                'longitude' => -71.9388,
                'capacity' => 300,
                'current_load' => 0,
                'coverage_radius' => 1500,
                'status' => 'active',
                'is_operational' => true,
                'activated_at' => now()->subMonths(8),
            ],
            [
                'name' => 'Nodo Wanchaq',
                'code' => 'NOD-WAN-0001',
                'type' => 'access',
                'location' => 'Av. Tullumayo 789',
                'zone' => 'Wanchaq',
                'district' => 'Wanchaq',
                'province' => 'Cusco',
                'department' => 'Cusco',
                'latitude' => -13.5183,
                'longitude' => -71.9778,
                'capacity' => 200,
                'current_load' => 0,
                'coverage_radius' => 1000,
                'status' => 'active',
                'is_operational' => true,
                'activated_at' => now()->subMonths(6),
            ],
        ];

        foreach ($nodes as $nodeData) {
            Node::create($nodeData);
        }

        $this->command->info('Nodos creados exitosamente');
    }

    /**
     * Crear routers de ejemplo
     */
    protected function seedRouters()
    {
        $nodes = Node::all();

        $routers = [
            // Routers MikroTik
            [
                'name' => 'Router Principal Centro',
                'code' => 'MK-CEN-0001',
                'brand' => 'MikroTik',
                'model' => 'RB4011iGS+',
                'serial_number' => 'MT-' . strtoupper(uniqid()),
                'ip_address' => '192.0.2.10',
                'mac_address' => '00:0C:29:12:34:56',
                'credentials' => [
                    'username' => 'router-prueba1',
                    'password' => 'change-me-before-use',
                    'api_port' => 8728,
                ],
                'location' => 'Nodo Central - Rack 1',
                'zone' => 'Centro',
                'latitude' => -13.5226,
                'longitude' => -71.9673,
                'status' => 'active',
                'firmware_version' => '6.49.6',
                'max_clients' => 100,
                'connected_clients' => rand(40, 80),
                'node_id' => $nodes->where('zone', 'Centro')->first()->id,
                'installed_at' => now()->subMonths(10),
            ],
            [
                'name' => 'Router Secundario Centro',
                'code' => 'MK-CEN-0002',
                'brand' => 'MikroTik',
                'model' => 'RB3011UiAS-RM',
                'serial_number' => 'MT-' . strtoupper(uniqid()),
                'ip_address' => '192.0.2.11',
                'mac_address' => '00:0C:29:12:34:57',
                'credentials' => [
                    'username' => 'router-prueba2',
                    'password' => 'change-me-before-use',
                    'api_port' => 8728,
                ],
                'location' => 'Nodo Central - Rack 2',
                'zone' => 'Centro',
                'latitude' => -13.5226,
                'longitude' => -71.9673,
                'status' => 'active',
                'firmware_version' => '6.49.6',
                'max_clients' => 80,
                'connected_clients' => rand(30, 60),
                'node_id' => $nodes->where('zone', 'Centro')->first()->id,
                'installed_at' => now()->subMonths(8),
            ],
            
            // Routers Huawei
            [
                'name' => 'Router San Sebastián 1',
                'code' => 'HW-SBS-0001',
                'brand' => 'Huawei',
                'model' => 'AR2220',
                'serial_number' => 'HW-' . strtoupper(uniqid()),
                'ip_address' => '192.0.2.12',
                'mac_address' => '00:0C:29:12:34:58',
                'api_endpoint' => 'http://192.0.2.12',
                'credentials' => [
                    'username' => 'router-prueba3',
                    'password' => 'change-me-before-use',
                ],
                'location' => 'Nodo San Sebastián',
                'zone' => 'San Sebastián',
                'latitude' => -13.5271,
                'longitude' => -71.9388,
                'status' => 'active',
                'firmware_version' => 'V200R010C00',
                'max_clients' => 60,
                'connected_clients' => rand(20, 50),
                'node_id' => $nodes->where('zone', 'San Sebastián')->first()->id,
                'installed_at' => now()->subMonths(6),
            ],
            
            // Router Wanchaq
            [
                'name' => 'Router Wanchaq Principal',
                'code' => 'MK-WAN-0001',
                'brand' => 'MikroTik',
                'model' => 'hEX S',
                'serial_number' => 'MT-' . strtoupper(uniqid()),
                'ip_address' => '192.0.2.13',
                'mac_address' => '00:0C:29:12:34:59',
                'credentials' => [
                    'username' => 'router-prueba4',
                    'password' => 'change-me-before-use',
                    'api_port' => 8728,
                ],
                'location' => 'Nodo Wanchaq',
                'zone' => 'Wanchaq',
                'latitude' => -13.5183,
                'longitude' => -71.9778,
                'status' => 'active',
                'firmware_version' => '6.49.6',
                'max_clients' => 50,
                'connected_clients' => rand(15, 40),
                'node_id' => $nodes->where('zone', 'Wanchaq')->first()->id,
                'installed_at' => now()->subMonths(5),
            ],
        ];

        foreach ($routers as $routerData) {
            Router::create($routerData);
        }

        $this->command->info('Routers creados exitosamente');
    }

    /**
     * Crear reglas de automatización
     */
    protected function seedAutomationRules()
    {
        $rules = [
            [
                'name' => 'Reinicio automático ante solicitud',
                'description' => 'Reinicia automáticamente el router cuando un cliente solicita reinicio',
                'trigger_type' => 'service_request',
                'trigger_conditions' => [
                    'request_type' => 'router_reboot'
                ],
                'action_type' => 'router_reboot',
                'action_config' => [
                    'wait_time' => 0,
                    'notify_customer' => true,
                ],
                'scope' => 'all_routers',
                'scope_config' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Health Check Semanal',
                'description' => 'Verifica el estado de todos los routers cada semana',
                'trigger_type' => 'schedule',
                'trigger_conditions' => null,
                'action_type' => 'run_script',
                'action_config' => [
                    'script' => 'health_check_all',
                ],
                'scope' => 'all_routers',
                'scope_config' => null,
                'schedule_cron' => '0 3 * * 0', // Domingos a las 3 AM
                'next_execution' => now()->next('Sunday')->setTime(3, 0),
                'is_active' => true,
            ],
            [
                'name' => 'Reinicio programado mensual',
                'description' => 'Reinicio preventivo de routers el primer domingo de cada mes',
                'trigger_type' => 'schedule',
                'trigger_conditions' => null,
                'action_type' => 'router_reboot',
                'action_config' => [
                    'notify_customers' => true,
                    'notification_advance' => 24, // horas
                ],
                'scope' => 'zone',
                'scope_config' => [
                    'zone' => 'Centro'
                ],
                'schedule_cron' => '0 4 1-7 * 0', // Primer domingo del mes a las 4 AM
                'is_active' => false, // Desactivado por defecto
            ],
        ];

        foreach ($rules as $ruleData) {
            AutomationRule::create($ruleData);
        }

        $this->command->info('Reglas de automatización creadas exitosamente');
    }
}
