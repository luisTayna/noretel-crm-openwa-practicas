<?php

namespace Modules\Billing\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface InvoiceRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener facturas por cliente
     *
     * @param int $customerId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoicesByCustomer($customerId, $columns = ['*']);

    /**
     * Obtener facturas por contrato
     *
     * @param int $contractId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoicesByContract($contractId, $columns = ['*']);

    /**
     * Obtener facturas pendientes
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingInvoices($columns = ['*']);

    /**
     * Obtener facturas vencidas
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOverdueInvoices($columns = ['*']);

    /**
     * Actualizar estado de factura
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status);

    /**
     * Generar número de factura
     *
     * @return string
     */
    public function generateInvoiceNumber();
}
