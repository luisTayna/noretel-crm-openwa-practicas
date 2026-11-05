<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\InvoiceItem;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Contract\Repositories\ContractRepository;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Repositories\CustomerRepository;

class InvoiceService
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var ContractRepository
     */
    protected $contractRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * InvoiceService constructor.
     */
    public function __construct(
        InvoiceRepository $invoiceRepository,
        ContractRepository $contractRepository,
        CustomerRepository $customerRepository,
        protected InvoicePeriodGuardService $invoicePeriodGuard
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->contractRepository = $contractRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Crear factura con sus items
     *
     * @return array
     */
    public function createInvoice(array $data)
    {
        try {
            DB::beginTransaction();

            $periodCheck = $this->invoicePeriodGuard->checkInsideTransaction(
                (int) $data['contract_id'],
                $data['billing_period'] ?? null,
                $data['issue_date'] ?? null,
                true
            );
            if ($periodCheck['skipped']) {
                DB::commit();

                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => $periodCheck['reason'],
                    'invoice' => $periodCheck['invoice'],
                ];
            }

            // Generar número de factura si no se proporcionó
            if (! isset($data['invoice_number']) || empty($data['invoice_number'])) {
                $data['invoice_number'] = $this->invoiceRepository->generateInvoiceNumber();
            }

            // Calcular fecha de vencimiento si no se proporcionó
            if (! isset($data['due_date']) || empty($data['due_date'])) {
                $dueDays = config('billing.invoice.due_days', 15);
                $data['due_date'] = Carbon::parse($data['issue_date'])->addDays($dueDays);
            }

            // Extraer los items de la factura DESPUÉS de obtener los totales
            $items = $data['items'] ?? [];

            // Asegurar que los totales estén presentes
            if (! isset($data['amount']) || ! isset($data['taxes']) || ! isset($data['total_amount'])) {
                // Recalcular totales si no están presentes
                $totals = $this->calculateTotals($items);
                $data = array_merge($data, $totals);
            }

            // Ahora remover los items para crear la factura
            unset($data['items']);

            // Establecer valores por defecto para campos requeridos
            $data['status'] = $data['status'] ?? 'pending';
            $data['document_type'] = $data['document_type'] ?? 'invoice';
            $data['sent'] = $data['sent'] ?? false;

            // Crear la factura
            $invoice = $this->invoiceRepository->create($data);

            // Crear los items
            foreach ($items as $item) {
                $item['invoice_id'] = $invoice->id;

                // Asegurar que los campos calculados estén presentes
                if (! isset($item['tax_amount']) || ! isset($item['amount'])) {
                    $itemCalculations = $this->calculateItemTotals($item);
                    $item = array_merge($item, $itemCalculations);
                }

                InvoiceItem::create($item);
            }

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'invoice_created',
                'invoices',
                "Recibo creado: {$invoice->invoice_number}",
                request()->ip(),
                null,
                $invoice->toArray()
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Recibo creado correctamente',
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Error al crear el recibo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Calcular totales de la factura
     *
     * @return array
     */
    private function calculateTotals(array $items)
    {
        $amount = 0;
        $taxes = 0;
        $total_amount = 0;

        foreach ($items as $item) {
            $quantity = floatval($item['quantity'] ?? 0);
            $unitPrice = floatval($item['unit_price'] ?? 0);
            $taxRate = 0; // IGV ya está incluido en el precio unitario
            $discount = floatval($item['discount'] ?? 0);

            // Calcular subtotal (cantidad * precio unitario)
            $subtotal = $quantity * $unitPrice;

            // Aplicar descuento si existe
            $afterDiscount = $subtotal - $discount;

            // Calcular impuesto
            $taxAmount = 0;

            // Sumar a totales
            $amount += $afterDiscount;
            $taxes += $taxAmount;
            $total_amount += $afterDiscount + $taxAmount;
        }

        return [
            'amount' => round($amount, 2),
            'taxes' => round($taxes, 2),
            'total_amount' => round($total_amount, 2),
        ];
    }

    /**
     * Calcular totales de un item individual
     *
     * @return array
     */
    private function calculateItemTotals(array $item)
    {
        $quantity = floatval($item['quantity'] ?? 0);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        $taxRate = floatval($item['tax_rate'] ?? 0);
        $discount = floatval($item['discount'] ?? 0);

        // Calcular subtotal (cantidad * precio unitario)
        $subtotal = $quantity * $unitPrice;

        // Aplicar descuento si existe
        $afterDiscount = $subtotal - $discount;

        // Calcular impuesto
        $taxAmount = ($afterDiscount * $taxRate) / 100;

        // Calcular monto total del ítem
        $amount = $afterDiscount + $taxAmount;

        return [
            'tax_amount' => round($taxAmount, 2),
            'amount' => round($amount, 2),
        ];
    }

    /**
     * Actualizar factura con sus items
     *
     * @return array
     */
    public function updateInvoice(int $invoiceId, array $data)
    {
        try {
            DB::beginTransaction();

            $invoice = $this->invoiceRepository->find($invoiceId);

            if (! $invoice) {
                return [
                    'success' => false,
                    'message' => 'Recibo no encontrado',
                ];
            }

            // Extraer los items de la factura DESPUÉS de obtener los totales
            $items = $data['items'] ?? [];

            // Asegurar que los totales estén presentes
            if (! isset($data['amount']) || ! isset($data['taxes']) || ! isset($data['total_amount'])) {
                $totals = $this->calculateTotals($items);
                $data = array_merge($data, $totals);
            }

            // Remover los items para actualizar la factura
            unset($data['items']);

            // Actualizar la factura
            $this->invoiceRepository->update($invoiceId, $data);

            // Eliminar items existentes
            $invoice->items()->delete();

            // Crear los nuevos items
            foreach ($items as $item) {
                $item['invoice_id'] = $invoiceId;

                // Asegurar que los campos calculados estén presentes
                if (! isset($item['tax_amount']) || ! isset($item['amount'])) {
                    $itemCalculations = $this->calculateItemTotals($item);
                    $item = array_merge($item, $itemCalculations);
                }

                InvoiceItem::create($item);
            }

            // Recargar la factura actualizada
            $invoice = $this->invoiceRepository->find($invoiceId);

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'invoice_updated',
                'invoices',
                "Recibo actualizado: {$invoice->invoice_number}",
                request()->ip(),
                null,
                $invoice->toArray()
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Recibo actualizado correctamente',
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Error al actualizar el recibo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generar factura automática para un contrato
     *
     * @param  int  $contractId
     * @param  string  $billingPeriod
     * @param  Carbon|null  $issueDate
     * @return array
     */
    public function generateInvoiceForContract($contractId, $billingPeriod, $issueDate = null)
    {
        try {
            // Obtener el contrato
            $contract = $this->contractRepository->find($contractId);
            $contract->loadMissing(['customer', 'plan', 'contractedServices.additionalService']);

            // Verificar si el contrato está activo
            if ($contract->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'No se puede generar recibo para un contrato inactivo',
                ];
            }

            if (! $contract->customer || ! $contract->customer->active) {
                return [
                    'success' => false,
                    'message' => 'No se puede generar recibo para un cliente inactivo',
                ];
            }


            DB::beginTransaction();

            // Segunda verificación obligatoria dentro de la transacción.
            // El lock del contrato serializa cron, peticiones manuales y pagos adelantados.
            $periodCheck = $this->invoicePeriodGuard->checkInsideTransaction(
                (int) $contractId,
                $billingPeriod,
                $issueDate,
                true
            );
            if ($periodCheck['skipped']) {
                DB::commit();

                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => $periodCheck['reason'],
                    'invoice' => $periodCheck['invoice'],
                ];
            }
            $contract = $periodCheck['contract'];
            $contract->loadMissing(['customer', 'plan', 'contractedServices.additionalService']);

            if ($contract->status !== 'active' || ! $contract->customer || ! $contract->customer->active) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No se puede generar recibo para un cliente o contrato inactivo',
                ];
            }

            // Establecer fecha de emisión
            $issueDate = $issueDate ? Carbon::parse($issueDate) : Carbon::now();

            // Calcular fecha de vencimiento del ciclo mensual fijo:
            // día 30 o último día del mes si el mes no tiene día 30.
            $dueDate = $this->calculateMonthlyDueDate($issueDate);

            // Obtener el cliente para datos de facturación
            $customer = $contract->customer;

            // Crear la factura
            $invoiceData = [
                'contract_id' => $contractId,
                'invoice_number' => $this->invoiceRepository->generateInvoiceNumber(),
                'issue_date' => $issueDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => 'pending',
                'billing_name' => $customer->full_name,
                'billing_address' => $customer->primary_address,
                'billing_document' => $customer->identity_document,
                'billing_email' => $customer->email,
                'generation_type' => 'automatic',
                'billing_period' => $billingPeriod,
                'amount' => 0, // Se calculará con los items
                'taxes' => 0, // Se calculará con los items
                'total_amount' => 0, // Se calculará con los items
            ];

            // Crear la factura
            $invoice = $this->invoiceRepository->create($invoiceData);

            // Preparar los items según el plan y servicios adicionales
            $items = [];
            $subtotal = 0;
            $taxTotal = 0;

            // Agregar el plan base
            $plan = $contract->plan;
            $defaultTaxRate = 0; // IGV ya incluido en el precio
            $hasCustomPrice = $contract->final_price !== null;

            if ($plan) {
                $planAmount = $this->planAmountForContract($contract);
                $taxAmount = 0;

                $items[] = [
                    'invoice_id' => $invoice->id,
                    'description' => "Plan {$plan->name}",
                    'quantity' => 1,
                    'unit_price' => $planAmount,
                    'tax_rate' => $defaultTaxRate,
                    'tax_amount' => $taxAmount,
                    'discount' => 0,
                    'amount' => $planAmount + $taxAmount,
                    'item_type' => 'plan',
                    'service_id' => $plan->id,
                    'period_start' => null, // Se puede calcular según el período
                    'period_end' => null, // Se puede calcular según el período
                ];

                $subtotal += $planAmount;
                $taxTotal += $taxAmount;
            }

            // Agregar servicios adicionales
            foreach ($contract->contractedServices as $service) {
                $serviceAmount = $this->serviceAmountForContract($contract, $service);
                $taxAmount = 0;

                $additionalService = $service->additionalService;
                $description = $additionalService ? $additionalService->name : 'Servicio adicional';
                if ($hasCustomPrice) {
                    $description .= ' (Incluido)';
                }

                $items[] = [
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $serviceAmount,
                    'tax_rate' => $defaultTaxRate,
                    'tax_amount' => $taxAmount,
                    'discount' => 0,
                    'amount' => $serviceAmount + $taxAmount,
                    'item_type' => 'additional_service',
                    'service_id' => $service->additional_service_id,
                    'period_start' => null,
                    'period_end' => null,
                ];

                $subtotal += $serviceAmount;
                $taxTotal += $taxAmount;
            }

            // Crear los items
            foreach ($items as $item) {
                InvoiceItem::create($item);
            }

            // Actualizar los totales de la factura
            $invoice->update([
                'amount' => $subtotal,
                'taxes' => $taxTotal,
                'total_amount' => $subtotal + $taxTotal,
            ]);

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id() ?: 1, // Si es automático, usar usuario administrador
                'invoice_generated',
                'invoices',
                "Recibo mensual generado automáticamente: {$invoice->invoice_number}",
                request()->ip(),
                null,
                $invoice->toArray()
            );

            DB::commit();

            // Aplicar saldo a favor automático si el cliente tiene saldo
            try {
                $creditService = app(\Modules\Billing\Services\CustomerCreditService::class);
                $appliedPayments = $creditService->applyAvailableCreditToPendingInvoices($customer, $contract, true);

                // Si se generó un pago con el saldo a favor, enviar la constancia por WhatsApp
                if (! empty($appliedPayments)) {
                    foreach ($appliedPayments as $payment) {
                        $phone = $contract->whatsapp_phone ?: $customer->phone ?: $customer->whatsapp_phone;
                        if ($phone) {
                            app(\Modules\Billing\Services\OpenWaService::class)->sendPaymentReceiptDirect($payment, $phone);
                        }
                    }
                }
            } catch (\Throwable $creditEx) {
                \Log::error('Error aplicando saldo a favor automático al generar recibo', [
                    'contract_id' => $contractId,
                    'error' => $creditEx->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Recibo mensual generado correctamente',
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollback();
            }

            \Log::error('Error al generar recibo para contrato', [
                'contract_id' => $contractId,
                'billing_period' => $billingPeriod,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo generar el recibo. Revisa que el contrato esté activo y tenga un plan válido.',
            ];
        }
    }

    /**
     * Resolve a display billing period to a canonical month.
     *
     * Legacy invoices use several textual formats, so duplicate detection must
     * compare the represented month instead of the raw billing_period string.
     *
     * @param mixed $fallbackDate
     */
    protected function resolveBillingPeriodStart(?string $billingPeriod, $fallbackDate = null): Carbon
    {
        $canonicalKey = $this->invoicePeriodGuard->canonicalKey($billingPeriod, $fallbackDate);
        if ($canonicalKey) {
            return Carbon::createFromFormat('Y-m', $canonicalKey)->startOfMonth();
        }

        $billingPeriod = trim((string) $billingPeriod);

        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];

            return Carbon::create((int) $targetMatch[3], (int) $targetMatch[2], 1)->startOfMonth();
        }

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $billingPeriod, $matches)) {
            return Carbon::create((int) $matches[2], (int) $matches[1], 1)->startOfMonth();
        }

        $normalized = strtr(strtolower($billingPeriod), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11,
            'december' => 12,
        ];

        foreach ($months as $name => $month) {
            if (preg_match('/\b'.preg_quote($name, '/').'\s+(\d{4})\b/', $normalized, $match)) {
                return Carbon::create((int) $match[1], $month, 1)->startOfMonth();
            }
        }

        return Carbon::parse($fallbackDate ?: now())->startOfMonth();
    }

    protected function planAmountForContract($contract): float
    {
        return $contract->final_price !== null
            ? (float) $contract->final_price
            : (float) optional($contract->plan)->price;
    }

    protected function serviceAmountForContract($contract, $service): float
    {
        return $contract->final_price !== null ? 0.0 : (float) $service->price;
    }

    /**
     * Anular una factura
     *
     * @param  int  $id
     * @param  string  $reason
     * @return array
     */
    public function voidInvoice($id, $reason)
    {
        try {
            DB::beginTransaction();

            $invoice = $this->invoiceRepository->find($id);

            // Verificar si la factura puede anularse
            if (in_array($invoice->status, ['paid', 'void'])) {
                return [
                    'success' => false,
                    'message' => 'No se puede anular un recibo pagado o ya anulado',
                ];
            }

            // Guardar datos anteriores para auditoría
            $oldData = $invoice->toArray();

            // Anular la factura
            $invoice->update([
                'status' => 'void',
                'notes' => $invoice->notes."\n[".now().'] Recibo anulado. Motivo: '.$reason,
            ]);

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'invoice_voided',
                'invoices',
                "Recibo anulado: {$invoice->invoice_number}. Motivo: {$reason}",
                request()->ip(),
                $oldData,
                $invoice->toArray()
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Recibo anulado correctamente',
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Error al anular el recibo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Marcar una factura como enviada
     *
     * @param  int  $id
     * @return array
     */
    public function markAsSent($id)
    {
        try {
            $invoice = $this->invoiceRepository->find($id);

            // Verificar si la factura ya está marcada como enviada
            if ($invoice->sent) {
                return [
                    'success' => false,
                    'message' => 'El recibo ya está marcado como enviado',
                ];
            }

            // Actualizar la factura
            $invoice->update([
                'sent' => true,
                'sent_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Recibo marcado como enviado',
                'invoice' => $invoice,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al marcar el recibo como enviado: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generar facturas para contratos que deben facturarse hoy
     *
     * @return array
     */
    public function generateScheduledInvoices($issueDate = null)
    {
        $issueDate = $issueDate ? Carbon::parse($issueDate) : Carbon::now();

        // Este método será llamado por un cron job
        $results = [
            'success' => true,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        // Obtener contratos activos que deben facturarse hoy
        // Esto dependerá de cómo manejes los ciclos de facturación
        $contracts = $this->contractRepository->getContractsForBilling();

        foreach ($contracts as $contract) {
            // Determinar período de facturación
            $month = $issueDate->locale('es')->translatedFormat('F');
            $year = $issueDate->format('Y');
            $billingPeriod = "{$month} {$year}";

            // Generar factura
            $result = $this->generateInvoiceForContract(
                $contract->id,
                $billingPeriod,
                $issueDate->copy()
            );

            if ($result['skipped'] ?? false) {
                $results['skipped']++;
            } elseif ($result['success']) {
                $results['generated']++;
            } else {
                $results['failed']++;
                $results['success'] = false;
            }

            $results['details'][] = [
                'contract_id' => $contract->id,
                'customer' => $contract->customer->full_name,
                'result' => $result,
            ];
        }

        return $results;
    }

    public function calculateMonthlyDueDate(Carbon $issueDate): Carbon
    {
        $dueDay = (int) config('billing.invoice.fixed_due_day', 30);
        $lastDayOfMonth = (int) $issueDate->copy()->endOfMonth()->format('d');
        $day = min(max($dueDay, 1), $lastDayOfMonth);

        return $issueDate->copy()->day($day)->startOfDay();
    }
}
