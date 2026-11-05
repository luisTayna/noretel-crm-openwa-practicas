<?php

namespace Modules\Network\Adapters;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MikroTik REST API Adapter
 *
 * Usa la REST API de MikroTik RouterOS v7.1+
 * Documentación: https://help.mikrotik.com/docs/spaces/ROS/pages/47579162/REST+API
 */
class MikroTikRestAdapter implements RouterAdapterInterface
{
    protected $routerIp;
    protected $username;
    protected $password;
    protected $port;
    protected $useHttps;
    protected $timeout = 30;
    protected $baseUrl;

    public function __construct($routerIp, $credentials)
    {
        $this->routerIp = $routerIp;
        $this->username = $credentials['username'] ?? 'admin';
        $this->password = $credentials['password'] ?? '';
        $this->port = $credentials['rest_port'] ?? null;
        $this->useHttps = $credentials['use_https'] ?? true;

        // Construir URL base
        $protocol = $this->useHttps ? 'https' : 'http';
        $port = $this->port ? ':' . $this->port : '';
        $this->baseUrl = "{$protocol}://{$this->routerIp}{$port}/rest";
    }

    /**
     * Obtener estado del router
     */
    public function getStatus()
    {
        try {
            // Obtener recursos del sistema
            $resources = $this->request('GET', '/system/resource');

            // Obtener clientes PPPoE conectados
            $pppoeActive = $this->request('GET', '/ppp/active');

            return [
                'success' => true,
                'cpu_usage' => floatval($resources[0]['cpu-load'] ?? 0),
                'memory_usage' => $this->calculateMemoryUsage($resources[0]),
                'temperature' => $resources[0]['cpu-temperature'] ?? null,
                'uptime' => $resources[0]['uptime'] ?? 'N/A',
                'version' => $resources[0]['version'] ?? 'N/A',
                'board_name' => $resources[0]['board-name'] ?? 'N/A',
                'connected_clients' => count($pppoeActive),
                'bandwidth_in' => null,
                'bandwidth_out' => null,
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (getStatus)', [
                'router' => $this->routerIp,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al obtener estado del router: " . $e->getMessage());
        }
    }

    /**
     * Reiniciar el router
     */
    public function reboot()
    {
        try {
            $this->request('POST', '/system/reboot', []);

            return [
                'success' => true,
                'message' => 'Router reiniciándose...'
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (reboot)', [
                'router' => $this->routerIp,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al reiniciar router: " . $e->getMessage());
        }
    }

    /**
     * Crear nuevo cliente PPPoE
     */
    public function createPPPoEClient($username, $password, $profile, $service = 'any')
    {
        try {
            $response = $this->request('PUT', '/ppp/secret', [
                'name' => $username,
                'password' => $password,
                'service' => $service,
                'profile' => $profile,
            ]);

            return [
                'success' => true,
                'client_id' => $response['.id'] ?? null,
                'username' => $username,
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (createPPPoEClient)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al crear cliente PPPoE: " . $e->getMessage());
        }
    }

    /**
     * Eliminar cliente PPPoE
     */
    public function deletePPPoEClient($username)
    {
        try {
            // Buscar el cliente por nombre
            $secrets = $this->request('POST', '/ppp/secret/print', [
                '.proplist' => ['.id', 'name']
            ]);

            $clientId = null;
            foreach ($secrets as $secret) {
                if ($secret['name'] === $username) {
                    $clientId = $secret['.id'];
                    break;
                }
            }

            if (!$clientId) {
                throw new Exception("Cliente no encontrado: {$username}");
            }

            // Eliminar el cliente
            $this->request('DELETE', "/ppp/secret/{$clientId}");

            return [
                'success' => true,
                'message' => "Cliente {$username} eliminado correctamente"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (deletePPPoEClient)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al eliminar cliente PPPoE: " . $e->getMessage());
        }
    }

    /**
     * Suspender cliente (deshabilitar)
     */
    public function suspendClient($username)
    {
        try {
            $clientId = $this->findClientId($username);

            if (!$clientId) {
                throw new Exception("Cliente no encontrado: {$username}");
            }

            // Deshabilitar el cliente
            $this->request('PATCH', "/ppp/secret/{$clientId}", [
                'disabled' => 'true'
            ]);

            // Desconectar si está activo
            $this->disconnectActiveSession($username);

            return [
                'success' => true,
                'message' => "Cliente {$username} suspendido"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (suspendClient)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al suspender cliente: " . $e->getMessage());
        }
    }

    /**
     * Reactivar cliente (habilitar)
     */
    public function activateClient($username)
    {
        try {
            $clientId = $this->findClientId($username);

            if (!$clientId) {
                throw new Exception("Cliente no encontrado: {$username}");
            }

            // Habilitar el cliente
            $this->request('PATCH', "/ppp/secret/{$clientId}", [
                'disabled' => 'false'
            ]);

            return [
                'success' => true,
                'message' => "Cliente {$username} reactivado"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (activateClient)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al reactivar cliente: " . $e->getMessage());
        }
    }

    /**
     * Ajustar límite de ancho de banda (crear/actualizar queue)
     */
    public function setBandwidthLimit($username, $downloadMbps, $uploadMbps)
    {
        try {
            $downloadBps = $downloadMbps . 'M';
            $uploadBps = $uploadMbps . 'M';

            // Buscar si existe una queue simple para este usuario
            $queues = $this->request('POST', '/queue/simple/print', [
                '.proplist' => ['.id', 'name', 'target']
            ]);

            $queueId = null;
            foreach ($queues as $queue) {
                if ($queue['name'] === $username || strpos($queue['target'], $username) !== false) {
                    $queueId = $queue['.id'];
                    break;
                }
            }

            if ($queueId) {
                // Actualizar queue existente
                $this->request('PATCH', "/queue/simple/{$queueId}", [
                    'max-limit' => "{$uploadBps}/{$downloadBps}"
                ]);
            } else {
                // Crear nueva queue
                $this->request('PUT', '/queue/simple', [
                    'name' => $username,
                    'target' => $username,
                    'max-limit' => "{$uploadBps}/{$downloadBps}"
                ]);
            }

            return [
                'success' => true,
                'message' => "Ancho de banda ajustado: {$downloadMbps}M down / {$uploadMbps}M up"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (setBandwidthLimit)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al ajustar ancho de banda: " . $e->getMessage());
        }
    }

    /**
     * Desconectar sesión activa de un cliente
     */
    public function disconnectActiveSession($username)
    {
        try {
            // Buscar sesiones activas del usuario
            $activeSessions = $this->request('POST', '/ppp/active/print', [
                '.proplist' => ['.id', 'name']
            ]);

            foreach ($activeSessions as $session) {
                if ($session['name'] === $username) {
                    $this->request('DELETE', "/ppp/active/{$session['.id']}");
                }
            }

            return [
                'success' => true,
                'message' => "Sesiones de {$username} desconectadas"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (disconnectActiveSession)', [
                'router' => $this->routerIp,
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            // No lanzar excepción, solo registrar
            return ['success' => false, 'message' => 'No se pudo desconectar sesión'];
        }
    }

    /**
     * Obtener lista de clientes PPPoE
     */
    public function getPPPoEClients()
    {
        try {
            $secrets = $this->request('GET', '/ppp/secret');

            return [
                'success' => true,
                'clients' => $secrets
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (getPPPoEClients)', [
                'router' => $this->routerIp,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al obtener clientes: " . $e->getMessage());
        }
    }

    /**
     * Obtener clientes activos (conectados)
     */
    public function getActiveClients()
    {
        try {
            $active = $this->request('GET', '/ppp/active');

            return [
                'success' => true,
                'active_clients' => $active,
                'count' => count($active)
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (getActiveClients)', [
                'router' => $this->routerIp,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al obtener clientes activos: " . $e->getMessage());
        }
    }

    /**
     * Crear perfil PPPoE
     */
    public function createPPPoEProfile($name, $localAddress, $remoteAddress, $dnsServer)
    {
        try {
            $this->request('PUT', '/ppp/profile', [
                'name' => $name,
                'local-address' => $localAddress,
                'remote-address' => $remoteAddress,
                'dns-server' => $dnsServer,
            ]);

            return [
                'success' => true,
                'message' => "Perfil {$name} creado correctamente"
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (createPPPoEProfile)', [
                'router' => $this->routerIp,
                'profile' => $name,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al crear perfil: " . $e->getMessage());
        }
    }

    /**
     * Ejecutar comando personalizado
     */
    public function executeCommand($command, $params = [])
    {
        try {
            $response = $this->request('POST', $command, $params);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            Log::error('MikroTik REST API Error (executeCommand)', [
                'router' => $this->routerIp,
                'command' => $command,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Error al ejecutar comando: " . $e->getMessage());
        }
    }

    /**
     * Hacer petición HTTP a la REST API
     */
    protected function request($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        // Configuración básica
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");

        // SSL
        if ($this->useHttps) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Método HTTP
        switch ($method) {
            case 'GET':
                // GET es el método por defecto
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                }
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                }
                break;

            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }

        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? $errorData['detail'] ?? 'Error desconocido';
            throw new Exception("HTTP {$httpCode}: {$errorMsg}");
        }

        // Respuesta vacía es válida para DELETE
        if (empty($response)) {
            return [];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Buscar ID de cliente por nombre de usuario
     */
    protected function findClientId($username)
    {
        $secrets = $this->request('POST', '/ppp/secret/print', [
            '.proplist' => ['.id', 'name']
        ]);

        foreach ($secrets as $secret) {
            if ($secret['name'] === $username) {
                return $secret['.id'];
            }
        }

        return null;
    }

    /**
     * Calcular porcentaje de uso de memoria
     */
    protected function calculateMemoryUsage($resources)
    {
        if (!isset($resources['total-memory']) || !isset($resources['free-memory'])) {
            return 0;
        }

        $total = floatval($resources['total-memory']);
        $free = floatval($resources['free-memory']);

        if ($total == 0) {
            return 0;
        }

        $used = $total - $free;
        return round(($used / $total) * 100, 2);
    }

    /**
     * Test de conexión
     */
    public function testConnection()
    {
        try {
            $resources = $this->request('GET', '/system/resource');

            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'router_info' => [
                    'board_name' => $resources[0]['board-name'] ?? 'N/A',
                    'version' => $resources[0]['version'] ?? 'N/A',
                    'uptime' => $resources[0]['uptime'] ?? 'N/A',
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage()
            ];
        }
    }
}
