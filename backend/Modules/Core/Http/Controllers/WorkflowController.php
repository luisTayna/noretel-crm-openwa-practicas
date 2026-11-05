<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Entities\Workflow;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;

class WorkflowController extends Controller
{
    /**
     * Display a listing of workflows.
     *
     * @return Renderable
     */
    public function index()
    {
        $workflows = Workflow::orderBy('name')->paginate(15);

        return view('core::workflows.index', [
            'workflows' => $workflows
        ]);
    }

    /**
     * Show the form for creating a new workflow.
     *
     * @return Renderable
     */
    public function create()
    {
        return view('core::workflows.create');
    }

    /**
     * Store a newly created workflow.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workflow = Workflow::create($request->all());

        AuditLog::register(
            Auth::id(),
            'workflow_created',
            'workflows',
            "Workflow creado: {$workflow->name}",
            $request->ip(),
            null,
            $workflow->toArray()
        );

        return redirect()->route('core.workflows.index')
            ->with('success', 'Workflow creado correctamente');
    }

    /**
     * Display the specified workflow.
     *
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $workflow = Workflow::findOrFail($id);

        return view('core::workflows.show', [
            'workflow' => $workflow
        ]);
    }

    /**
     * Show the form for editing the specified workflow.
     *
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $workflow = Workflow::findOrFail($id);

        return view('core::workflows.edit', [
            'workflow' => $workflow
        ]);
    }

    /**
     * Update the specified workflow.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);
        $oldData = $workflow->toArray();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $workflow->update($request->all());

        AuditLog::register(
            Auth::id(),
            'workflow_updated',
            'workflows',
            "Workflow actualizado: {$workflow->name}",
            $request->ip(),
            $oldData,
            $workflow->toArray()
        );

        return redirect()->route('core.workflows.index')
            ->with('success', 'Workflow actualizado correctamente');
    }

    /**
     * Activate the specified workflow.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['active' => true]);

        AuditLog::register(
            Auth::id(),
            'workflow_activated',
            'workflows',
            "Workflow activado: {$workflow->name}",
            request()->ip()
        );

        return redirect()->back()
            ->with('success', 'Workflow activado correctamente');
    }

    /**
     * Deactivate the specified workflow.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivate($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['active' => false]);

        AuditLog::register(
            Auth::id(),
            'workflow_deactivated',
            'workflows',
            "Workflow desactivado: {$workflow->name}",
            request()->ip()
        );

        return redirect()->back()
            ->with('success', 'Workflow desactivado correctamente');
    }

    /**
     * Execute a workflow transition.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function executeTransition(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);

        // Lógica de transición de workflow aquí

        AuditLog::register(
            Auth::id(),
            'workflow_transition',
            'workflows',
            "Transición ejecutada en workflow: {$workflow->name}",
            $request->ip()
        );

        return redirect()->back()
            ->with('success', 'Transición ejecutada correctamente');
    }

    /**
     * Remove the specified workflow.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflowData = $workflow->toArray();

        $workflow->delete();

        AuditLog::register(
            Auth::id(),
            'workflow_deleted',
            'workflows',
            "Workflow eliminado: {$workflow->name}",
            request()->ip(),
            $workflowData,
            null
        );

        return redirect()->route('core.workflows.index')
            ->with('success', 'Workflow eliminado correctamente');
    }
}
