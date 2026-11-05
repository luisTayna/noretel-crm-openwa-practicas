<?php

namespace Modules\Operations\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Entities\Customer;
use Modules\Network\Entities\ServiceRequest;
use Modules\Operations\Entities\OnboardingOrder;

class CustomerOperationFlowService
{
    public function statusOptions(): array
    {
        return [
            'all' => 'Todos',
            'without_order' => 'Sin orden de instalación',
            'installation_scheduled' => 'Instalación programada',
            'installed' => 'Instalado',
            'activation_pending' => 'Habilitación pendiente',
            'contract_pending_30' => 'Contrato pendiente día 30',
            'pending_invoice' => 'Pendiente de recibo mensual',
            'pending_payment' => 'Pendiente de pago',
            'up_to_date' => 'Al día',
        ];
    }

    public function build(Customer $customer): array
    {
        $order = $this->currentOnboardingOrder($customer);
        $contract = $order?->contract ?: $this->currentContract($customer);
        $installation = $order?->installation ?: ($contract ? $this->currentInstallation($contract) : null);
        $activationRequest = $order?->activationRequest;
        $invoice = $order?->invoice ?: ($contract ? $this->currentInvoice($contract) : null);
        $billingSummary = $contract ? $this->billingSummary($contract) : $this->emptyBillingSummary();

        $steps = $this->buildSteps($customer, $order, $contract, $installation, $activationRequest, $invoice, $billingSummary);
        $nextAction = $this->resolveNextAction($customer, $order, $contract, $installation, $activationRequest, $invoice, $billingSummary);

        return [
            'status_key' => $nextAction['status_key'],
            'status_label' => $nextAction['status_label'],
            'status_class' => $nextAction['status_class'],
            'customer' => $customer,
            'onboarding_order' => $order,
            'contract' => $contract,
            'installation' => $installation,
            'activation_request' => $activationRequest,
            'invoice' => $invoice,
            'billing_summary' => $billingSummary,
            'steps' => $steps,
            'next_action' => $nextAction,
        ];
    }

    public function auditLogsFor(Customer $customer, ?Contract $contract, ?OnboardingOrder $order = null): Collection
    {
        return AuditLog::query()
            ->where(function ($query) use ($customer, $contract, $order) {
                $query->where('action_detail', 'like', "%{$customer->full_name}%")
                    ->orWhere('new_data->customer_id', $customer->id)
                    ->orWhere('previous_data->customer_id', $customer->id);

                if ($contract) {
                    $query->orWhere('new_data->contract_id', $contract->id)
                        ->orWhere('previous_data->contract_id', $contract->id)
                        ->orWhere('action_detail', 'like', "%contrato #{$contract->id}%")
                        ->orWhere('action_detail', 'like', "%contrato {$contract->id}%");
                }

                if ($order) {
                    $query->orWhere('new_data->onboarding_order_id', $order->id)
                        ->orWhere('previous_data->onboarding_order_id', $order->id)
                        ->orWhere('action_detail', 'like', "%{$order->order_number}%");
                }
            })
            ->latest('action_date')
            ->limit(10)
            ->get();
    }

    protected function currentOnboardingOrder(Customer $customer): ?OnboardingOrder
    {
        $orders = $customer->onboardingOrders ?? collect();
        $activeStatuses = [
            OnboardingOrder::STATUS_CREATED,
            OnboardingOrder::STATUS_INSTALLATION_SCHEDULED,
            OnboardingOrder::STATUS_INSTALLED,
            OnboardingOrder::STATUS_ACTIVATION_PENDING,
            OnboardingOrder::STATUS_ACTIVATED,
            OnboardingOrder::STATUS_CONTRACT_PENDING_30,
            OnboardingOrder::STATUS_ACTIVATED,
            OnboardingOrder::STATUS_CONTRACTED,
            OnboardingOrder::STATUS_INVOICED,
        ];

        return $orders->sortByDesc('created_at')->first(
            fn (OnboardingOrder $order) => in_array($order->status, $activeStatuses, true)
        ) ?: $orders->sortByDesc('created_at')->first();
    }

    protected function currentContract(Customer $customer): ?Contract
    {
        return $customer->contracts
            ->sortByDesc('created_at')
            ->sortByDesc(fn (Contract $contract) => $contract->status === 'active' ? 1 : 0)
            ->first();
    }

    protected function currentInstallation(Contract $contract): ?Installation
    {
        return $contract->installations
            ->sortByDesc('created_at')
            ->sortByDesc(fn (Installation $installation) => in_array($installation->status, ['scheduled', 'in_progress'], true) ? 1 : 0)
            ->first();
    }

    protected function currentInvoice(Contract $contract): ?Invoice
    {
        $pendingInvoice = $contract->invoices
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('due_date')
            ->first();

        if ($pendingInvoice) {
            return $pendingInvoice;
        }

        return $contract->invoices
            ->where('status', 'paid')
            ->sortByDesc('issue_date')
            ->sortByDesc('id')
            ->first() ?: $contract->invoices->sortByDesc('created_at')->first();
    }

    protected function buildSteps(
        Customer $customer,
        ?OnboardingOrder $order,
        ?Contract $contract,
        ?Installation $installation,
        ?ServiceRequest $activationRequest,
        ?Invoice $invoice,
        array $billingSummary
    ): array {
        $installationDone = $installation && $installation->status === 'completed';

        return [
            [
                'key' => 'customer',
                'label' => 'Cliente creado',
                'description' => $customer->registration_date
                    ? 'Registrado el '.$customer->registration_date->format('d/m/Y')
                    : 'Datos básicos registrados',
                'state' => 'done',
                'icon' => 'fa-user-check',
            ],
            [
                'key' => 'order',
                'label' => 'Orden de instalación',
                'description' => $order
                    ? "{$order->order_number} · ".optional($order->plan)->name.' · '.$order->status_label
                    : ($contract ? 'Contrato legado sin orden de instalación' : 'Falta seleccionar plan y registrar la orden operativa'),
                'state' => ($order || $contract) ? 'done' : 'current',
                'icon' => 'fa-clipboard-list',
            ],
            [
                'key' => 'installation',
                'label' => 'Instalación',
                'description' => $installation
                    ? $this->installationStatusLabel($installation->status).($installation->scheduled_date ? ' · '.$installation->scheduled_date->format('d/m/Y H:i') : '')
                    : ($order
                        ? 'Falta agendar instalación · '.optional($order->plan)->name
                        : ($contract ? 'Contrato legado pendiente de instalación' : 'Primero registra la orden de instalación')),
                'state' => $installationDone ? 'done' : (($order || $contract) ? 'current' : 'pending'),
                'icon' => 'fa-tools',
            ],
            [
                'key' => 'activation',
                'label' => 'Habilitar equipo',
                'description' => 'Etapa preparada, deshabilitada por ahora',
                'state' => 'disabled',
                'icon' => 'fa-network-wired',
            ],
            [
                'key' => 'contract',
                'label' => 'Contrato día 30',
                'description' => $contract
                    ? 'Contrato #'.$contract->id.' · '.$this->contractStatusLabel($contract->status)
                    : ($order?->expected_contract_date
                        ? 'Programado para '.$order->expected_contract_date->format('d/m/Y')
                        : 'Se genera después de completar la instalación'),
                'state' => $contract ? 'done' : ($order && ($order->status === OnboardingOrder::STATUS_CONTRACT_PENDING_30 || $installationDone) ? 'current' : 'pending'),
                'icon' => 'fa-file-contract',
            ],
            [
                'key' => 'billing',
                'label' => 'Recibo mensual',
                'description' => $invoice
                    ? 'Recibo '.$invoice->invoice_number.' · '.$this->invoiceStatusLabel($invoice->status)
                    : 'Falta generar el recibo mensual después del contrato',
                'state' => $invoice ? 'done' : ($contract && $contract->status === 'active' ? 'current' : 'pending'),
                'icon' => 'fa-file-invoice-dollar',
            ],
            [
                'key' => 'payment',
                'label' => 'Pago',
                'description' => $billingSummary['pending_count'] > 0
                    ? $billingSummary['pending_count'].' mes(es) pendiente(s) · S/ '.number_format((float) $billingSummary['total_pending'], 2)
                    : ($billingSummary['paid_until'] ? 'Al día hasta '.$billingSummary['paid_until'] : 'Sin recibo pendiente'),
                'state' => $billingSummary['pending_count'] > 0 ? 'current' : ($invoice ? 'done' : 'pending'),
                'icon' => 'fa-money-bill-wave',
            ],
            [
                'key' => 'audit',
                'label' => 'Auditoría',
                'description' => 'Trazabilidad disponible desde Core/Audit',
                'state' => 'done',
                'icon' => 'fa-clipboard-check',
            ],
        ];
    }

    protected function resolveNextAction(
        Customer $customer,
        ?OnboardingOrder $order,
        ?Contract $contract,
        ?Installation $installation,
        ?ServiceRequest $activationRequest,
        ?Invoice $invoice,
        array $billingSummary
    ): array {
        if (! $order && ! $contract) {
            return [
                'status_key' => 'without_order',
                'status_label' => 'Sin orden de instalación',
                'status_class' => 'secondary',
                'title' => 'Registrar orden de instalación',
                'description' => 'El cliente ya existe. El siguiente paso es definir plan y fecha de contrato para programar la instalación.',
                'route' => route('operations.onboarding-orders.create', ['customer_id' => $customer->id]),
                'method' => 'GET',
                'button_class' => 'btn-success',
                'icon' => 'fa-clipboard-list',
            ];
        }

        if ($order && ! $installation) {
            return [
                'status_key' => 'installation_scheduled',
                'status_label' => 'Pendiente de instalación',
                'status_class' => 'warning',
                'title' => 'Agendar instalación',
                'description' => 'La orden de instalación está registrada. Ahora agenda la visita técnica sin crear contrato borrador.',
                'route' => route('contract.installations.create', ['onboarding_order_id' => $order->id]),
                'method' => 'GET',
                'button_class' => 'btn-warning',
                'icon' => 'fa-calendar-plus',
            ];
        }

        if ($contract && $contract->status === 'pending_installation' && ! $installation) {
            return [
                'status_key' => 'installation_scheduled',
                'status_label' => 'Pendiente de instalación',
                'status_class' => 'warning',
                'title' => 'Agendar instalación',
                'description' => 'Contrato legado pendiente de instalación.',
                'route' => route('contract.installations.create', ['contract_id' => $contract->id]),
                'method' => 'GET',
                'button_class' => 'btn-warning',
                'icon' => 'fa-calendar-plus',
            ];
        }

        if ($installation && in_array($installation->status, ['scheduled', 'in_progress'], true)) {
            return [
                'status_key' => 'installation_scheduled',
                'status_label' => 'Instalación programada',
                'status_class' => 'info',
                'title' => 'Completar instalación',
                'description' => $order
                    ? 'La instalación está en proceso. Al completarla el flujo pasará a contrato día 30, sin habilitación de equipo por ahora.'
                    : 'La instalación está en proceso. Completa este paso para continuar el flujo legado.',
                'route' => route('contract.installations.complete-form', $installation->id),
                'method' => 'GET',
                'button_class' => 'btn-info',
                'icon' => 'fa-tools',
            ];
        }

        if ($order && $installation && $installation->status === 'completed' && ! $contract) {
            if ($order->expected_contract_date && $order->expected_contract_date->isFuture()) {
                return [
                    'status_key' => 'contract_pending_30',
                    'status_label' => 'Contrato en cola',
                    'status_class' => 'info',
                    'title' => 'Esperar día 30',
                    'description' => 'La instalación ya está completada. El contrato se genera desde el '.$order->expected_contract_date->format('d/m/Y').'.',
                    'route' => route('operations.customers.flow', $customer->id).'#contract-panel',
                    'method' => 'GET',
                    'button_class' => 'btn-info',
                    'icon' => 'fa-clock',
                ];
            }

            return [
                'status_key' => 'contract_pending_30',
                'status_label' => 'Listo para contrato',
                'status_class' => 'primary',
                'title' => 'Generar contrato',
                'description' => 'La instalación ya está completada. Genera el contrato real y asócialo a la instalación.',
                'route' => route('operations.onboarding-orders.generate-contract', $order->id),
                'method' => 'POST',
                'payload' => [],
                'button_class' => 'btn-primary',
                'icon' => 'fa-file-contract',
            ];
        }

        if ($contract && $contract->status === 'active' && (! $invoice || in_array($invoice->status, ['cancelled', 'void'], true))) {
            return [
                'status_key' => 'pending_invoice',
                'status_label' => 'Pendiente de recibo mensual',
                'status_class' => 'primary',
                'title' => 'Generar recibo mensual',
                'description' => 'El contrato real está activo. Ahora se puede generar el recibo mensual desde el día 30.',
                'route' => route('billing.invoices.generate'),
                'method' => 'POST',
                'payload' => [
                    'contract_id' => $contract->id,
                    'billing_period' => optional($contract->start_date)->locale('es')->translatedFormat('F Y') ?: now()->locale('es')->translatedFormat('F Y'),
                ],
                'button_class' => 'btn-primary',
                'icon' => 'fa-file-invoice-dollar',
            ];
        }

        if ($invoice && in_array($invoice->status, ['pending', 'partial', 'overdue'], true)) {
            $pendingCount = (int) $billingSummary['pending_count'];

            return [
                'status_key' => 'pending_payment',
                'status_label' => $pendingCount > 1 ? "Debe {$pendingCount} meses" : 'Pendiente de pago',
                'status_class' => 'danger',
                'title' => 'Registrar pago',
                'description' => $pendingCount > 1
                    ? 'Existe deuda acumulada de S/ '.number_format((float) $billingSummary['total_pending'], 2).'. El pago se aplicará desde el recibo pendiente más antiguo.'
                    : 'Existe un recibo pendiente. El siguiente paso es registrar el cobro, incluido Yape si corresponde.',
                'route' => route('billing.payments.create', ['invoice_id' => $invoice->id]),
                'method' => 'GET',
                'button_class' => 'btn-danger',
                'icon' => 'fa-money-bill-wave',
            ];
        }

        return [
            'status_key' => 'up_to_date',
            'status_label' => $billingSummary['paid_until'] ? 'Al día hasta '.$billingSummary['paid_until'] : 'Al día',
            'status_class' => 'success',
            'title' => 'Cliente operativo',
            'description' => $billingSummary['paid_until']
                ? 'El cliente no tiene deuda pendiente. Servicio pagado hasta '.$billingSummary['paid_until'].'.'
                : 'El cliente no tiene acciones críticas pendientes en el flujo de alta.',
            'route' => route('customer.customers.show', $customer->id),
            'method' => 'GET',
            'button_class' => 'btn-success',
            'icon' => 'fa-check-circle',
        ];
    }

    protected function billingSummary(Contract $contract): array
    {
        $invoices = $contract->invoices;
        $pendingInvoices = $invoices->whereIn('status', ['pending', 'partial', 'overdue']);
        $paidUntilInvoice = $invoices
            ->where('status', 'paid')
            ->sortByDesc('issue_date')
            ->sortByDesc('id')
            ->first();

        return [
            'pending_count' => $pendingInvoices->count(),
            'total_pending' => $pendingInvoices->sum(fn (Invoice $invoice) => max((float) $invoice->pending_amount, 0)),
            'paid_until' => $paidUntilInvoice ? $this->paidUntilLabel($paidUntilInvoice) : null,
            'paid_until_invoice' => $paidUntilInvoice,
        ];
    }

    protected function emptyBillingSummary(): array
    {
        return [
            'pending_count' => 0,
            'total_pending' => 0,
            'paid_until' => null,
            'paid_until_invoice' => null,
        ];
    }

    protected function paidUntilLabel(Invoice $invoice): string
    {
        if ($invoice->billing_period) {
            return $invoice->billing_period;
        }

        return optional($invoice->issue_date)->locale('es')->translatedFormat('F Y') ?: 'período pagado';
    }

    protected function contractStatusLabel(?string $status): string
    {
        return [
            'pending_installation' => 'Pendiente de instalación',
            'active' => 'Activo',
            'cancelled' => 'Cancelado',
            'expired' => 'Vencido',
            'renewed' => 'Renovado',
        ][$status] ?? ucfirst((string) $status);
    }

    protected function installationStatusLabel(?string $status): string
    {
        return [
            'scheduled' => 'Programada',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'postponed' => 'Postergada',
        ][$status] ?? ucfirst((string) $status);
    }

    protected function serviceRequestStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Solicitud pendiente',
            'in_progress' => 'Solicitud en proceso',
            'completed' => 'Solicitud completada',
            'failed' => 'Solicitud fallida',
            'cancelled' => 'Solicitud cancelada',
        ][$status] ?? ucfirst((string) $status);
    }

    protected function invoiceStatusLabel(?string $status): string
    {
        return [
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'partial' => 'Parcial',
            'overdue' => 'Vencida',
            'cancelled' => 'Cancelada',
            'void' => 'Anulada',
        ][$status] ?? ucfirst((string) $status);
    }
}
