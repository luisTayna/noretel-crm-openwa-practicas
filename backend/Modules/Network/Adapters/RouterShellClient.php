<?php

namespace Modules\Network\Adapters;

use Modules\Network\Entities\Router;

/**
 * Abstraction over a router's remote management shell (SSH).
 *
 * Kept as an interface so controllers can be unit/feature tested
 * without opening a real SSH connection.
 */
interface RouterShellClient
{
    /**
     * Execute a command on the router's shell and return its output.
     *
     * @throws \Exception when the connection or command execution fails.
     */
    public function execute(Router $router, string $command): string;
}
