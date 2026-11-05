<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\ConfigurationService;
use Modules\Core\Http\Requests\StoreConfigRequest;
use Modules\Core\Http\Requests\UpdateConfigRequest;
use Modules\Core\Http\Requests\ImportConfigRequest;
use Illuminate\Support\Facades\Auth;

class ConfigurationController extends Controller
{
    /**
     * @var ConfigurationService
     */
    protected $configService;

    /**
     * ConfigurationController constructor.
     *
     * @param ConfigurationService $configService
     */
    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request)
    {
        $module = $request->get('module');
        $search = $request->get('search');

        $allConfigs = $this->configService->getAllConfigs();

        // Si hay módulo seleccionado, filtrar configuraciones
        if ($module && isset($allConfigs[$module])) {
            $configs = [$module => $allConfigs[$module]];
        } else {
            $configs = $allConfigs;
        }

        // Si hay búsqueda, filtrar configuraciones
        if ($search) {
            $filteredConfigs = [];

            foreach ($configs as $moduleName => $moduleConfigs) {
                $matchingParams = [];

                foreach ($moduleConfigs as $paramName => $paramData) {
                    if (stripos($paramName, $search) !== false ||
                        stripos($moduleName, $search) !== false ||
                        (isset($paramData['description']) && stripos($paramData['description'], $search) !== false)) {
                        $matchingParams[$paramName] = $paramData;
                    }
                }

                if (!empty($matchingParams)) {
                    $filteredConfigs[$moduleName] = $matchingParams;
                }
            }

            $configs = $filteredConfigs;
        }

        // Obtener lista de módulos para el filtro
        $modules = array_keys($allConfigs);

        return view('core::config.index', compact('configs', 'modules', 'module', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        // Obtener lista de módulos para el selector
        $modules = array_keys($this->configService->getAllConfigs());

        // Agregar opción para crear un nuevo módulo
        $modules[] = 'new_module';

        return view('core::config.create', compact('modules'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreConfigRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreConfigRequest $request)
    {
        $data = $request->validated();

        // Si es un nuevo módulo, usar el nombre especificado
        if ($data['module'] === 'new_module') {
            $data['module'] = $data['new_module'];
        }

        // Convertir valor según el tipo de dato
        $value = $this->convertValueToType($data['value'], $data['data_type']);

        $result = $this->configService->setValue(
            $data['module'],
            $data['parameter'],
            $value,
            $data['data_type'],
            $data['editable'] ?? true,
            $data['description'] ?? null,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->withErrors(['message' => 'Error al guardar la configuración.'])->withInput();
        }

        return redirect()->route('core.config.index', ['module' => $data['module']])
            ->with('success', 'Configuración creada correctamente.');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        // Obtener configuración
        $config = $this->configService->findById($id);

        if (!$config) {
            return redirect()->route('core.config.index')
                ->with('error', 'Configuración no encontrada.');
        }

        return view('core::config.edit', compact('config'));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateConfigRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateConfigRequest $request, $id)
    {
        $data = $request->validated();

        // Obtener configuración actual
        $config = $this->configService->findById($id);

        if (!$config) {
            return redirect()->route('core.config.index')
                ->with('error', 'Configuración no encontrada.');
        }

        // Convertir valor según el tipo de dato
        $value = $this->convertValueToType($data['value'], $config['data_type']);

        $result = $this->configService->setValue(
            $config['module'],
            $config['parameter'],
            $value,
            $config['data_type'],
            $data['editable'] ?? $config['editable'],
            $data['description'] ?? $config['description'],
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->withErrors(['message' => 'Error al actualizar la configuración.'])->withInput();
        }

        return redirect()->route('core.config.index', ['module' => $config['module']])
            ->with('success', 'Configuración actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        // Obtener configuración
        $config = $this->configService->findById($id);

        if (!$config) {
            return redirect()->route('core.config.index')
                ->with('error', 'Configuración no encontrada.');
        }

        $result = $this->configService->deleteConfig(
            $id,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->route('core.config.index')
                ->with('error', $result['message']);
        }

        return redirect()->route('core.config.index')
            ->with('success', 'Configuración eliminada correctamente.');
    }

    /**
     * Reset configuration to default value.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function reset($id, Request $request)
    {
        // Obtener configuración
        $config = $this->configService->findById($id);

        if (!$config) {
            return redirect()->route('core.config.index')
                ->with('error', 'Configuración no encontrada.');
        }

        $result = $this->configService->resetToDefault(
            $config['module'],
            $config['parameter'],
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->route('core.config.index')
                ->with('error', $result['message']);
        }

        return redirect()->route('core.config.index', ['module' => $config['module']])
            ->with('success', 'Configuración restablecida a valores por defecto.');
    }

    /**
     * Import configurations from JSON.
     * @param ImportConfigRequest $request
     * @return \Illuminate\Http\Response
     */
    public function import(ImportConfigRequest $request)
    {
        $json = $request->file('json_file')->get();

        $result = $this->configService->importFromJson(
            $json,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->route('core.config.index')
            ->with('success', $result['message']);
    }

    /**
     * Export configurations to JSON.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        $modules = $request->get('modules');

        $result = $this->configService->exportToJson(
            $modules,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->withErrors(['message' => $result['message']]);
        }

        return response()->json(json_decode($result['data']), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="config_export_' . date('Y-m-d') . '.json"'
        ]);
    }

    /**
     * Convert a value to the specified data type.
     *
     * @param mixed $value
     * @param string $dataType
     * @return mixed
     */
    private function convertValueToType($value, $dataType)
    {
        switch ($dataType) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return $value;
            default:
                return $value;
        }
    }
}
