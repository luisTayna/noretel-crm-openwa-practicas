<?php

namespace Modules\Network\Adapters;

use Modules\Network\Entities\Router;
use phpseclib3\Net\SSH2;

/**
 * Default RouterShellClient implementation, backed by phpseclib3.
 */
class SshRouterShellClient implements RouterShellClient
{
    public function execute(Router $router, string $command): string
    {
        $credentials = $router->credentials ?? [];
        $username = $credentials['username'] ?? 'admin';
        $password = $credentials['password'] ?? '';

        $ssh = new SSH2($router->ip_address);

        if (!$ssh->login($username, $password)) {
            throw new \Exception('Error de autenticación SSH');
        }

        $output = $ssh->exec($command);

        $ssh->disconnect();

        return (string) $output;
    }
}
