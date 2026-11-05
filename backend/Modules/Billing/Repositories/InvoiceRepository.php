<?php

namespace Modules\Billing\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Interfaces\InvoiceRepositoryInterface;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    /**
     * InvoiceRepository constructor.
     *
     * @param Invoice $model
     */
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener facturas por cliente
     *
     * @param int $customerId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoicesByCustomer($customerId, $columns = ['*'])
    {
        return $this->model->withAmountTotals()
            ->whereHas('contract', function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })->get($columns);
    }

    /**
     * Obtener facturas por contrato
     *
     * @param int $contractId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoicesByContract($contractId, $columns = ['*'])
    {
        return $this->model->where('contract_id', $contractId)->get($columns);
    }

    /**
     * Obtener facturas pendientes
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingInvoices($columns = ['*'])
    {
        return $this->model->whereIn('status', ['pending', 'partial', 'overdue'])->get($columns);
    }

    /**
     * Obtener facturas vencidas
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOverdueInvoices($columns = ['*'])
    {
        return $this->model->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', now())
            ->get($columns);
    }

    /**
     * Actualizar estado de factura
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status)
    {
        $invoice = $this->find($id);
        return $invoice->update(['status' => $status]);
    }

    /**
     * Generar número de factura
     *
     * @return string
     */
    public function generateInvoiceNumber()
    {
        $prefix = config('billing.invoice.prefix', 'INV-');
        $digits = config('billing.invoice.digits', 6);

        $lastInvoiceNumber = $this->model
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');
        $lastNumber = 0;

        if (is_string($lastInvoiceNumber) && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastInvoiceNumber, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        $newNumber = $lastNumber + 1;
        return $prefix . str_pad($newNumber, $digits, '0', STR_PAD_LEFT);
    }
}
