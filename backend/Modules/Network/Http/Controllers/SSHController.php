<?php

namespace Modules\Network\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\AuditLog;
use Modules\Network\Adapters\RouterShellClient;
use Modules\Network\Entities\Router;
use Illuminate\Support\Facades\Log;

class SSHController extends Controller
{
    public function __construct(private RouterShellClient $shellClient)
    {
    }

    /**
     * Mostrar terminal SSH
     */
    public function show(Router $router)
    {
        return view('network::ssh-terminal', compact('router'));
    }

    /**
     * Ejecutar comando SSH
     */
    public function execute(Request $request, Router $router)
    {
        $validated = $request->validate([
            'command' => 'required|string|max:500',
        ]);

        $command = $validated['command'];
        $userId = auth()->id();
        $ip = $request->ip() ?: 'unknown';

        try {
            $output = $this->shellClient->execute($router, $command);

            Log::info("SSH command executed on router {$router->name}", [
                'router_id' => $router->id,
                'command' => $command,
                'user_id' => $userId,
            ]);

            AuditLog::register(
                $userId,
                'router_ssh_command_executed',
                'routers',
                "Comando SSH ejecutado en router {$router->name} (#{$router->id}): {$command}",
                $ip
            );

            return response()->json([
                'success' => true,
                'output' => $output ?: 'Comando ejecutado correctamente (sin salida)'
            ]);

        } catch (\Throwable $e) {
            Log::error("SSH command failed on router {$router->name}", [
                'router_id' => $router->id,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            AuditLog::register(
                $userId,
                'router_ssh_command_failed',
                'routers',
                "Fallo al ejecutar comando SSH en router {$router->name} (#{$router->id}): {$command}. Error: {$e->getMessage()}",
                $ip
            );

            return response()->json([
                'success' => false,
                'message' => 'No se pudo ejecutar el comando en el router. Contacte al administrador.'
            ], 500);
        }
    }
}
