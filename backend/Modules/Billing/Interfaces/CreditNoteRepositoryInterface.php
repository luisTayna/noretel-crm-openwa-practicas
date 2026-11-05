<?php

namespace Modules\Billing\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface CreditNoteRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener notas de crédito por factura
     *
     * @param int $invoiceId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCreditNotesByInvoice($invoiceId, $columns = ['*']);

    /**
     * Generar número de nota de crédito
     *
     * @return string
     */
    public function generateCreditNoteNumber();

    /**
     * Aplicar nota de crédito a una factura y actualizar estado
     *
     * @param int $id
     * @return bool
     */
    public function applyCreditNote($id);
}
