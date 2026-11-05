<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Entities\User;
use Modules\Core\Repositories\AuditRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    /**
     * @var AuditRepository
     */
    protected $auditRepository;

    /**
     * AuditController constructor.
     *
     * @param AuditRepository $auditRepository
     */
    public function __construct(AuditRepository $auditRepository)
    {
        $this->auditRepository = $auditRepository;
    }

    /**
     * Display a listing of audit logs.
     *
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request)
    {
        // Obtener logs filtrados
        $logs = $this->auditRepository->getFilteredLogs($request->all());

        // Obtener datos para filtros
        $users = User::orderBy('username')->get();
        $modules = AuditLog::distinct()->pluck('module');
        $actionTypes = AuditLog::distinct()->pluck('action_type');

        return view('core::audit.index', [
            'logs' => $logs,
            'users' => $users,
            'modules' => $modules,
            'actionTypes' => $actionTypes,
            'filters' => $request->all()
        ]);
    }

    /**
     * Display detailed information about audit log entry.
     *
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $log = $this->auditRepository->find($id);

        return view('core::audit.show', [
            'log' => $log
        ]);
    }

    /**
     * Display dashboard with audit statistics.
     *
     * @param Request $request
     * @return Renderable
     */
    public function dashboard(Request $request)
    {
        // Período de tiempo para las estadísticas (predeterminado: 30 días)
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        // Actividad por módulo
        $moduleStats = $this->auditRepository->getModuleStats($startDate);

        // Actividad por tipo de acción
        $actionStats = $this->auditRepository->getActionStats($startDate);

        // Actividad por usuario (top 10)
        $userStats = $this->auditRepository->getUserStats($startDate);

        // Actividad por día
        $dailyStats = $this->auditRepository->getDailyStats($startDate);

        return view('core::audit.dashboard', [
            'moduleStats' => $moduleStats,
            'actionStats' => $actionStats,
            'userStats' => $userStats,
            'dailyStats' => $dailyStats,
            'days' => $days
        ]);
    }

    /**
     * Export audit logs to CSV.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        // Obtener todos los registros para exportar
        $logs = $this->auditRepository->getAllFilteredLogs($request->all());

        // Crear archivo CSV
        $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
        $tempFile = storage_path('app/temp/' . $filename);

        // Asegurar que el directorio exista
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $file = fopen($tempFile, 'w');

        // Encabezados CSV
        fputcsv($file, [
            'ID',
            'Usuario',
            'Fecha',
            'Tipo de Acción',
            'Módulo',
            'Detalle',
            'IP',
        ]);

        // Datos CSV
        foreach ($logs as $log) {
            fputcsv($file, [
                $log->id,
                $log->user ? $log->user->username : 'N/A',
                $log->action_date,
                $log->action_type,
                $log->module,
                $log->action_detail,
                $log->source_ip,
            ]);
        }

        fclose($file);

        // Registrar la acción de exportación
        $this->auditRepository->register(
            Auth::id(),
            'audit_export',
            'audit',
            'Exportación de logs de auditoría',
            $request->ip(),
            ['filters' => $request->all()],
            ['count' => $logs->count()]
        );

        // Descargar archivo
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }
}
