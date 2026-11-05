<?php

namespace Modules\Billing\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Interfaces\PaymentRepositoryInterface;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    /**
     * PaymentRepository constructor.
     *
     * @param Payment $model
     */
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener pagos por factura
     *
     * @param int $invoiceId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentsByInvoice($invoiceId, $columns = ['*'])
    {
        return $this->model->where('invoice_id', $invoiceId)->get($columns);
    }

    /**
     * Obtener pagos por cliente
     *
     * @param int $customerId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentsByCustomer($customerId, $columns = ['*'])
    {
        return $this->model->whereHas('invoice.contract', function ($query) use ($customerId) {
            $query->where('customer_id', $customerId);
        })->get($columns);
    }

    /**
     * Crear un pago y actualizar estado de la factura
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createPaymentAndUpdateInvoice(array $data)
    {
        $payment = $this->create($data);

        // Buscar la factura
        $invoice = $payment->invoice;

        // Calcular el monto pagado total
        $totalPaid = $invoice->payments()->where('status', 'completed')->sum('amount');

        // Calcular el monto de notas de crédito aplicadas
        $creditNoteAmount = $invoice->creditNotes()->where('status', 'applied')->sum('amount');

        // Verificar si se ha pagado completamente o parcialmente
        if ($totalPaid + $creditNoteAmount >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } else if ($totalPaid + $creditNoteAmount > 0) {
            $invoice->update(['status' => 'partial']);
        }

        return $payment;
    }
}
