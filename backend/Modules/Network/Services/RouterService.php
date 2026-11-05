<?php

namespace Modules\Network\Services;

use Modules\Network\Entities\Router;
use Modules\Network\Entities\RouterLog;
use Modules\Network\Adapters\MikroTikRestAdapter;
use Modules\Network\Adapters\HuaweiAdapter;
use Modules\Network\Adapters\CiscoAdapter;
use Exception;
use Illuminate\Support\Facades\Log;

class RouterService
{
    /**
     * Obtener adapter apropiado según la marca del router
     */
    protected function getAdapter(Router $router)
    {
        $credentials = $router->credentials ?? [];

        // Agregar valores por defecto desde .env si no existen
        if (empty($credentials['username'])) {
            $credentials['username'] = config('network.default_username', 'admin');
        }

        if (empty($credentials['password'])) {
            $credentials['password'] = config('network.default_password', '');
        }

        switch (strtolower($router->brand)) {
            case 'mikrotik':
                return new MikroTikRestAdapter($router->ip_address, $credentials);

            case 'huawei':
                return new HuaweiAdapter($router->ip_address, $credentials);

            case 'cisco':
                return new CiscoAdapter($router->ip_address, $credentials);

            default:
                throw new Exception("Marca de router no soportada: {$router->brand}");
        }
    }

    /**
     * Obtener estado actual del router
     */
    public function getStatus(Router $router)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $status = $adapter->getStatus();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'get_status', true, $status, $executionTime);

            return $status;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'get_status', false, [
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Reiniciar router
     */
    public function reboot(Router $router)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $result = $adapter->reboot();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Actualizar última fecha de reinicio
            $router->update(['last_reboot' => now()]);

            // Registrar log exitoso
            $this->logAction($router, 'reboot', true, $result, $executionTime);

            return $result;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'reboot', false, [
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Crear cliente PPPoE
     */
    public function createClient(Router $router, $username, $password, $profile = 'default', $downloadMbps = 10, $uploadMbps = 10)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);

            // Crear cliente
            $result = $adapter->createPPPoEClient($username, $password, $profile);

            // Configurar ancho de banda
            $adapter->setBandwidthLimit($username, $downloadMbps, $uploadMbps);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'create_client', true, [
                'username' => $username,
                'profile' => $profile,
                'download' => $downloadMbps,
                'upload' => $uploadMbps
            ], $executionTime);

            return array_merge($result, [
                'bandwidth' => "{$downloadMbps}M/{$uploadMbps}M"
            ]);

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'create_client', false, [
                'username' => $username,
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Eliminar cliente PPPoE
     */
    public function deleteClient(Router $router, $username)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $result = $adapter->deletePPPoEClient($username);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'delete_client', true, [
                'username' => $username
            ], $executionTime);

            return $result;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'delete_client', false, [
                'username' => $username,
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Suspender cliente
     */
    public function suspendClient(Router $router, $username)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $result = $adapter->suspendClient($username);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'suspend_client', true, [
                'username' => $username
            ], $executionTime);

            return $result;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'suspend_client', false, [
                'username' => $username,
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Reactivar cliente
     */
    public function activateClient(Router $router, $username)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $result = $adapter->activateClient($username);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'activate_client', true, [
                'username' => $username
            ], $executionTime);

            return $result;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'activate_client', false, [
                'username' => $username,
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Ajustar ancho de banda
     */
    public function setBandwidthLimit(Router $router, $username, $downloadMbps, $uploadMbps)
    {
        $startTime = microtime(true);

        try {
            $adapter = $this->getAdapter($router);
            $result = $adapter->setBandwidthLimit($username, $downloadMbps, $uploadMbps);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log exitoso
            $this->logAction($router, 'set_bandwidth', true, [
                'username' => $username,
                'download' => $downloadMbps,
                'upload' => $uploadMbps
            ], $executionTime);

            return $result;

        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Registrar log fallido
            $this->logAction($router, 'set_bandwidth', false, [
                'username' => $username,
                'error' => $e->getMessage()
            ], $executionTime);

            throw $e;
        }
    }

    /**
     * Test de conexión
     */
    public function testConnection(Router $router)
    {
        try {
            $adapter = $this->getAdapter($router);
            return $adapter->testConnection();

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Health check de todos los routers activos
     */
    public function healthCheckAll()
    {
        $routers = Router::where('status', 'active')->get();
        $results = [];

        foreach ($routers as $router) {
            try {
                $status = $this->getStatus($router);

                $router->update([
                    'cpu_usage' => $status['cpu_usage'],
                    'memory_usage' => $status['memory_usage'],
                    'connected_clients' => $status['connected_clients'],
                    'last_health_check' => now(),
                ]);

                $results[] = [
                    'router_id' => $router->id,
                    'name' => $router->name,
                    'status' => 'healthy',
                ];

            } catch (Exception $e) {
                $router->update([
                    'status' => 'offline',
                    'last_health_check' => now(),
                ]);

                $results[] = [
                    'router_id' => $router->id,
                    'name' => $router->name,
                    'status' => 'offline',
                    'error' => $e->getMessage(),
                ];

                Log::error("Health check failed for router {$router->name}", [
                    'router_id' => $router->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Registrar acción en los logs
     */
    protected function logAction(Router $router, $action, $success, $data = [], $executionTime = null)
    {
        RouterLog::create([
            'router_id' => $router->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'success' => $success,
            'request_data' => $data,
            'response_data' => $success ? $data : null,
            'error_message' => $success ? null : ($data['error'] ?? 'Error desconocido'),
            'execution_time' => $executionTime,
            'ip_address' => request()->ip(),
        ]);
    }
}
