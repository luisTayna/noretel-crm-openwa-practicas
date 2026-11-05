<?php

namespace Modules\Network\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Network\Entities\Node;
use Modules\Network\Entities\Router;

class NodeController extends Controller
{
    /**
     * Mostrar lista de nodos
     */
    public function index(Request $request)
    {
        $query = Node::with('routers');

        // Filtros
        if ($request->filled('zone')) {
            $query->where('zone', $request->zone);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $nodes = $query->paginate(20);

        $zones = Node::distinct('zone')->pluck('zone');
        $types = ['core', 'distribution', 'access', 'backbone'];

        return view('network::nodes.index', compact('nodes', 'zones', 'types'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $parentNodes = Node::where('status', 'active')->get();
        $types = ['core', 'distribution', 'access', 'backbone'];

        return view('network::nodes.create', compact('parentNodes', 'types'));
    }

    /**
     * Guardar nuevo nodo
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:nodes,code',
            'type' => 'required|in:core,distribution,access,backbone',
            'location' => 'required|string',
            'zone' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'coverage_radius' => 'required|numeric|min:0',
        ]);

        try {
            $node = Node::create($request->all());

            return redirect()
                ->route('network.nodes.show', $node)
                ->with('success', 'Nodo creado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al crear nodo', [
                'action' => 'node.store',
                'data' => $request->all(),
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo crear el nodo. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Mostrar detalles del nodo
     */
    public function show(Node $node)
    {
        $node->load(['routers', 'parentNode', 'childNodes']);

        return view('network::nodes.show', compact('node'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Node $node)
    {
        $parentNodes = Node::where('status', 'active')
            ->where('id', '!=', $node->id)
            ->get();
        $types = ['core', 'distribution', 'access', 'backbone'];

        return view('network::nodes.edit', compact('node', 'parentNodes', 'types'));
    }

    /**
     * Actualizar nodo
     */
    public function update(Request $request, Node $node)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:nodes,code,' . $node->id,
            'type' => 'required|in:core,distribution,access,backbone',
            'location' => 'required|string',
            'zone' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'coverage_radius' => 'required|numeric|min:0',
        ]);

        try {
            $node->update($request->all());

            return redirect()
                ->route('network.nodes.show', $node)
                ->with('success', 'Nodo actualizado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al actualizar nodo', [
                'action' => 'node.update',
                'node_id' => $node->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo actualizar el nodo. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Eliminar nodo
     */
    public function destroy(Node $node)
    {
        try {
            // Verificar que no tenga routers activos
            if ($node->routers()->where('status', 'active')->exists()) {
                return redirect()
                    ->back()
                    ->with('error', 'No se puede eliminar un nodo con routers activos');
            }

            $node->delete();

            return redirect()
                ->route('network.nodes.index')
                ->with('success', 'Nodo eliminado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al eliminar nodo', [
                'action' => 'node.destroy',
                'node_id' => $node->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo eliminar el nodo. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Obtener routers del nodo
     */
    public function routers(Node $node)
    {
        $routers = $node->routers()->with('customers')->paginate(20);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $routers
            ]);
        }

        return view('network::nodes.routers', compact('node', 'routers'));
    }

    /**
     * Mapa de cobertura del nodo
     */
    public function coverageMap(Node $node)
    {
        $node->load('routers');

        return view('network::nodes.coverage-map', compact('node'));
    }
}
