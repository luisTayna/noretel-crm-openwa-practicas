<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Routing\Controller;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Http\Requests\StoreHistoricalCustomerRequest;
use Modules\Customer\Services\HistoricalCustomerRegistrationService;
use Modules\Services\Entities\Plan;

class HistoricalCustomerController extends Controller
{
    public function create(): Renderable
    {
        $customerOptions = Customer::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'identity_document', 'phone'])
            ->map(function (Customer $customer) {
                $name = trim($customer->full_name);
                $document = trim((string) $customer->identity_document);
                $phone = trim((string) $customer->phone);
                $initials = mb_substr((string) $customer->first_name, 0, 1)
                    . mb_substr((string) $customer->last_name, 0, 1);

                return [
                    'id' => $customer->id,
                    'name' => $name !== '' ? $name : 'Cliente sin nombre',
                    'document' => $document,
                    'phone' => $phone,
                    'initials' => mb_strtoupper($initials !== '' ? $initials : 'CL'),
                    'search' => mb_strtolower(trim($name . ' ' . $document . ' ' . $phone)),
                ];
            })
            ->values();

        $plans = Plan::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'download_speed', 'upload_speed']);

        return view('customer::customers.historical-create', compact('customerOptions', 'plans'));
    }

    public function store(
        StoreHistoricalCustomerRequest $request,
        HistoricalCustomerRegistrationService $service
    ) {
        try {
            $result = $service->register(
                $request->validated(),
                auth()->id(),
                $request->ip()
            );
        } catch (\Throwable $exception) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No se pudo registrar el cliente histórico: ' . $exception->getMessage());
        }

        $count = count($result['invoices']);
        $message = $count > 0
            ? "Cliente histórico registrado con {$count} recibo(s) de deuda anterior."
            : 'Cliente histórico registrado sin deuda anterior.';

        return redirect()
            ->route('customer.customers.show', $result['customer']->id)
            ->with('success', $message);
    }
}
