<?php

namespace Modules\Network\Adapters;

/**
 * Interfaz para adapters de routers
 *
 * Todos los adapters deben implementar estos métodos básicos
 */
interface RouterAdapterInterface
{
    /**
     * Obtener estado actual del router
     *
     * @return array [cpu_usage, memory_usage, uptime, connected_clients, etc]
     */
    public function getStatus();

    /**
     * Reiniciar el router
     *
     * @return array [success, message]
     */
    public function reboot();

    /**
     * Crear nuevo cliente PPPoE
     *
     * @param string $username
     * @param string $password
     * @param string $profile
     * @param string $service
     * @return array [success, client_id, username]
     */
    public function createPPPoEClient($username, $password, $profile, $service = 'any');

    /**
     * Eliminar cliente PPPoE
     *
     * @param string $username
     * @return array [success, message]
     */
    public function deletePPPoEClient($username);

    /**
     * Suspender cliente (deshabilitar acceso)
     *
     * @param string $username
     * @return array [success, message]
     */
    public function suspendClient($username);

    /**
     * Reactivar cliente (habilitar acceso)
     *
     * @param string $username
     * @return array [success, message]
     */
    public function activateClient($username);

    /**
     * Ajustar límite de ancho de banda
     *
     * @param string $username
     * @param int $downloadMbps
     * @param int $uploadMbps
     * @return array [success, message]
     */
    public function setBandwidthLimit($username, $downloadMbps, $uploadMbps);

    /**
     * Test de conexión al router
     *
     * @return array [success, message, router_info]
     */
    public function testConnection();
}
