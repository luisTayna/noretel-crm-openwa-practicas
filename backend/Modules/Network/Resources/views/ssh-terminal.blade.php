@extends('core::layouts.app')

@section('title', 'Terminal SSH - ' . $router->name)

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">
                    <i class="fas fa-terminal"></i> Terminal SSH: {{ $router->name }}
                </h1>
                <a href="{{ route('network.routers.show', $router) }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-server"></i>
                    <strong>{{ $router->name }}</strong>
                    <span class="ml-2 text-muted">{{ $router->ip_address }}</span>
                </div>
                <div>
                    <button id="clearBtn" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                    <button id="disconnectBtn" class="btn btn-sm btn-danger">
                        <i class="fas fa-power-off"></i> Desconectar
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0" style="background: #1e1e1e;">
            <!-- Formulario para ejecutar comandos -->
            <form id="sshForm" class="p-3">
                @csrf
                <div class="form-group mb-3">
                    <label for="command" class="text-white">Comando:</label>
                    <div class="input-group">
                        <input type="text"
                               class="form-control bg-dark text-white border-secondary"
                               id="command"
                               name="command"
                               placeholder="Escribe un comando (ej: /system resource print)"
                               autocomplete="off"
                               style="font-family: 'Courier New', monospace;">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Ejecutar
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">
                        Comandos rápidos:
                        <a href="#" class="quick-cmd text-info" data-cmd="/system resource print">Resources</a> |
                        <a href="#" class="quick-cmd text-info" data-cmd="/ppp secret print">PPPoE Clients</a> |
                        <a href="#" class="quick-cmd text-info" data-cmd="/ppp active print">Active</a> |
                        <a href="#" class="quick-cmd text-info" data-cmd="/ip address print">IPs</a>
                    </small>
                </div>
            </form>

            <!-- Terminal output -->
            <div id="terminal"
                 style="
                     height: 500px;
                     overflow-y: auto;
                     background: #1e1e1e;
                     color: #00ff00;
                     padding: 15px;
                     font-family: 'Courier New', monospace;
                     font-size: 14px;
                     line-height: 1.5;
                 ">
                <div class="text-success">╔════════════════════════════════════════╗</div>
                <div class="text-success">║     Terminal SSH - NoreTel CRM         ║</div>
                <div class="text-success">╚════════════════════════════════════════╝</div>
                <div class="mt-2">Conectado a: <span class="text-warning">{{ $router->ip_address }}</span></div>
                <div>Router: <span class="text-warning">{{ $router->name }}</span></div>
                <div class="mb-3">Estado: <span class="text-success">✓ Conectado</span></div>
                <div id="output"></div>
            </div>
        </div>
    </div>
</div>

<style>
#terminal {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.cmd-output {
    margin: 10px 0;
    padding: 10px;
    border-left: 3px solid #00ff00;
    background: rgba(0, 255, 0, 0.05);
}

.cmd-input {
    color: #ffff00;
    font-weight: bold;
}

.cmd-result {
    color: #00ff00;
}

.cmd-error {
    color: #ff5555;
    border-left-color: #ff5555;
    background: rgba(255, 85, 85, 0.05);
}

.quick-cmd:hover {
    text-decoration: underline;
    cursor: pointer;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('sshForm');
    const commandInput = document.getElementById('command');
    const output = document.getElementById('output');
    const terminal = document.getElementById('terminal');
    const clearBtn = document.getElementById('clearBtn');
    const disconnectBtn = document.getElementById('disconnectBtn');

    // Historial de comandos
    let commandHistory = [];
    let historyIndex = -1;

    // Ejecutar comando
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const command = commandInput.value.trim();
        if (!command) return;

        // Agregar a historial
        commandHistory.push(command);
        historyIndex = commandHistory.length;

        // Mostrar comando en terminal
        appendToTerminal('input', command);

        // Enviar comando al backend
        executeCommand(command);

        // Limpiar input
        commandInput.value = '';
    });

    // Comandos rápidos
    document.querySelectorAll('.quick-cmd').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            commandInput.value = this.dataset.cmd;
            form.dispatchEvent(new Event('submit'));
        });
    });

    // Historial con flechas
    commandInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historyIndex > 0) {
                historyIndex--;
                commandInput.value = commandHistory[historyIndex];
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex < commandHistory.length - 1) {
                historyIndex++;
                commandInput.value = commandHistory[historyIndex];
            } else {
                historyIndex = commandHistory.length;
                commandInput.value = '';
            }
        }
    });

    // Limpiar terminal
    clearBtn.addEventListener('click', function() {
        output.innerHTML = '';
    });

    // Desconectar
    disconnectBtn.addEventListener('click', function() {
        if (confirm('¿Desea cerrar la terminal SSH?')) {
            window.location.href = '{{ route("network.routers.show", $router) }}';
        }
    });

    // Función para ejecutar comando
    function executeCommand(command) {
        fetch('{{ route("network.ssh.execute", $router) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ command: command })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendToTerminal('output', data.output);
            } else {
                appendToTerminal('error', data.message || 'Error al ejecutar comando');
            }
        })
        .catch(error => {
            appendToTerminal('error', 'Error de conexión: ' + error.message);
        });
    }

    // Función para agregar al terminal
    function appendToTerminal(type, text) {
        const div = document.createElement('div');
        div.className = 'cmd-output';

        if (type === 'input') {
            div.classList.add('cmd-input');
            div.innerHTML = `<strong>$ ${escapeHtml(text)}</strong>`;
        } else if (type === 'error') {
            div.classList.add('cmd-error');
            div.textContent = '✗ ' + text;
        } else {
            div.classList.add('cmd-result');
            div.textContent = text;
        }

        output.appendChild(div);
        terminal.scrollTop = terminal.scrollHeight;
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endsection
