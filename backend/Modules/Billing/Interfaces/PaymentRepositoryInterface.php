<?php

namespace Modules\Billing\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface PaymentRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener pagos por factura
     *
     * @param int $invoiceId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentsByInvoice($invoiceId, $columns = ['*']);

    /**
     * Obtener pagos por cliente
     *
     * @param int $customerId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentsByCustomer($customerId, $columns = ['*']);

    /**
     * Crear un pago y actualizar estado de la factura
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createPaymentAndUpdateInvoice(array $data);
}
