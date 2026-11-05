<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\InteractionService;
use Modules\Customer\Http\Requests\StoreInteractionRequest;
use Modules\Customer\Entities\Interaction;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\Auth;

class InteractionController extends Controller
{
    /**
     * @var InteractionService
     */
    protected $interactionService;

    /**
     * InteractionController constructor.
     *
     * @param InteractionService $interactionService
     */
    public function __construct(InteractionService $interactionService)
    {
        $this->interactionService = $interactionService;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request)
    {
        $customerId = $request->input('customer_id');
        $type = $request->input('type');
        $channel = $request->input('channel');
        $followUp = $request->input('follow_up');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $query = Interaction::query()->with(['customer', 'createdBy']);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        if ($type) {
            $query->where('interaction_type', $type);
        }
        
        if ($channel) {
            $query->where('channel', $channel);
        }
        
        if ($followUp) {
            $query->where('follow_up_required', true);
        }
        
        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }
        
        $interactions = $query->orderBy('date', 'desc')->paginate(15);
        
        $customers = Customer::orderBy('first_name')->get();
        $interactionTypes = Interaction::distinct()->pluck('interaction_type');
        $channels = Interaction::distinct()->pluck('channel');
        
        return view('customer::interactions.index', compact(
            'interactions', 
            'customers', 
            'interactionTypes', 
            'channels', 
            'customerId', 
            'type', 
            'channel',
            'followUp',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @param Request $request
     * @return Renderable
     */
    public function create(Request $request)
    {
        $customerId = $request->input('customer_id');
        $customer = null;
        
        if ($customerId) {
            $customer = Customer::findOrFail($customerId);
        }
        
        $customers = Customer::orderBy('first_name')->get();
        $interactionTypes = [
            'call' => 'Llamada telefónica',
            'email' => 'Correo electrónico',
            'meeting' => 'Reunión presencial',
            'visit' => 'Visita técnica',
            'social' => 'Redes sociales',
            'chat' => 'Chat en línea',
            'other' => 'Otro'
        ];
        
        $channels = [
            'phone' => 'Teléfono',
            'email' => 'Email',
            'office' => 'Oficina',
            'field' => 'Terreno',
            'social_media' => 'Redes sociales',
            'chat' => 'Chat',
            'other' => 'Otro'
        ];
        
        return view('customer::interactions.create', compact('customers', 'interactionTypes', 'channels', 'customer'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreInteractionRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreInteractionRequest $request)
    {
        $data = $request->validated();
        
        $result = $this->interactionService->registerInteraction(
            $data,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.interactions.show', $result['interaction']->id)
            ->with('success', 'Interacción registrada exitosamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $interaction = Interaction::with(['customer', 'createdBy'])
            ->findOrFail($id);
        
        return view('customer::interactions.show', compact('interaction'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $interaction = Interaction::with(['customer'])
            ->findOrFail($id);
        
        $interactionTypes = [
            'call' => 'Llamada telefónica',
            'email' => 'Correo electrónico',
            'meeting' => 'Reunión presencial',
            'visit' => 'Visita técnica',
            'social' => 'Redes sociales',
            'chat' => 'Chat en línea',
            'other' => 'Otro'
        ];
        
        $channels = [
            'phone' => 'Teléfono',
            'email' => 'Email',
            'office' => 'Oficina',
            'field' => 'Terreno',
            'social_media' => 'Redes sociales',
            'chat' => 'Chat',
            'other' => 'Otro'
        ];
        
        return view('customer::interactions.edit', compact('interaction', 'interactionTypes', 'channels'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'interaction_type' => 'required|string|max:100',
            'date' => 'required|date',
            'channel' => 'required|string|max:100',
            'description' => 'required|string',
            'result' => 'nullable|string',
            'follow_up_required' => 'boolean',
        ]);
        
        $data = $request->only([
            'interaction_type', 'date', 'channel', 'description', 'result', 'follow_up_required'
        ]);
        
        $result = $this->interactionService->updateInteraction(
            $id,
            $data,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.interactions.show', $id)
            ->with('success', 'Interacción actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $result = $this->interactionService->deleteInteraction(
            $id,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.interactions.index')
            ->with('success', $result['message']);
    }

    /**
     * Mark interaction as requiring follow-up.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function markForFollowUp($id, Request $request)
    {
        $result = $this->interactionService->markForFollowUp(
            $id,
            true,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }
        
        return redirect()->back()
            ->with('success', $result['message']);
    }

    /**
     * Unmark interaction as requiring follow-up.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function unmarkForFollowUp($id, Request $request)
    {
        $result = $this->interactionService->markForFollowUp(
            $id,
            false,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }
        
        return redirect()->back()
            ->with('success', $result['message']);
    }
}
