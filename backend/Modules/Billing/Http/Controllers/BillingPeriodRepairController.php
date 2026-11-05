<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Billing\Services\BillingPeriodRepairService;
use Throwable;

class BillingPeriodRepairController extends Controller
{
    public function index(BillingPeriodRepairService $service): Renderable
    {
        $groups = $service->duplicateGroups();
        $safeGroups = $groups->where('safe', true);
        $manualGroups = $groups->where('safe', false);

        return view('billing::period-repair.index', compact('groups', 'safeGroups', 'manualGroups'));
    }

    public function repairSafe(BillingPeriodRepairService $service): RedirectResponse
    {
        try {
            $result = $service->repairAllSafeDuplicates(auth()->id(), request()->ip());

            return redirect()->route('billing.period-repair.index')->with(
                'success',
                "Corrección terminada: {$result['invoices']} recibos duplicados anulados en {$result['groups']} contratos/períodos."
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('billing.period-repair.index')->with(
                'error',
                'La corrección se detuvo porque un caso cambió o necesita revisión manual. No se crearon pagos nuevos.'
            );
        }
    }
}
