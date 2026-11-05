<?php

namespace Modules\Billing\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Billing\Entities\CreditNote;
use Modules\Billing\Interfaces\CreditNoteRepositoryInterface;

class CreditNoteRepository extends BaseRepository implements CreditNoteRepositoryInterface
{
    /**
     * CreditNoteRepository constructor.
     *
     * @param CreditNote $model
     */
    public function __construct(CreditNote $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener notas de crédito por factura
     *
     * @param int $invoiceId
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCreditNotesByInvoice($invoiceId, $columns = ['*'])
    {
        return $this->model->where('invoice_id', $invoiceId)->get($columns);
    }

    /**
     * Generar número de nota de crédito
     *
     * @return string
     */
    public function generateCreditNoteNumber()
    {
        $prefix = config('billing.credit_note.prefix', 'CN-');
        $digits = config('billing.credit_note.digits', 6);

        $lastCreditNoteNumber = $this->model
            ->where('credit_note_number', 'like', $prefix . '%')
            ->orderByDesc('credit_note_number')
            ->value('credit_note_number');
        $lastNumber = 0;

        if (is_string($lastCreditNoteNumber) && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastCreditNoteNumber, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        $newNumber = $lastNumber + 1;
        return $prefix . str_pad($newNumber, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Aplicar nota de crédito a una factura y actualizar estado
     *
     * @param int $id
     * @return bool
     */
    public function applyCreditNote($id)
    {
        $creditNote = $this->find($id);
        $invoice = $creditNote->invoice;

        // Actualizar estado de la nota de crédito
        $creditNote->update(['status' => 'applied']);

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

        return true;
    }
}
