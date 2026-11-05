<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Services\CustomerMergeService;

class CustomerMergeController extends Controller
{
    public function __construct(private readonly CustomerMergeService $mergeService)
    {
    }

    public function index(int $id)
    {
        $target = Customer::withCount('contracts')->findOrFail($id);
        $candidates = $this->mergeService->candidates($target);

        return view('customer::customers.merge', compact('target', 'candidates'));
    }

    public function preview(Request $request, int $id)
    {
        $validated = $request->validate([
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'required|integer|distinct|exists:customers,id',
        ]);
        $target = Customer::withCount('contracts')->findOrFail($id);
        $candidates = $this->mergeService->candidates($target);
        $preview = $this->mergeService->preview($target, $validated['source_ids']);

        return view('customer::customers.merge', compact('target', 'candidates', 'preview'));
    }

    public function store(Request $request, int $id)
    {
        $validated = $request->validate([
            'source_ids' => 'required|array|min:1',
            'source_ids.*' => 'required|integer|distinct|exists:customers,id',
            'confirmation' => 'required|in:CONSOLIDAR',
        ], [
            'confirmation.in' => 'Escriba CONSOLIDAR para confirmar la operación.',
        ]);
        $target = Customer::findOrFail($id);

        try {
            $result = $this->mergeService->merge(
                $target,
                $validated['source_ids'],
                (int) auth()->id(),
                $request->ip()
            );
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->back()
                ->withInput()
                ->with('error', 'No se realizó la consolidación: '.$exception->getMessage());
        }

        return redirect()->route('customer.customers.show', $result['target']->id)
            ->with('success', 'Clientes consolidados correctamente. Contratos, facturas y pagos conservaron su historial.');
    }
}
