<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Services\PaymentReceiptService;
use Modules\Billing\Services\PaymentReminderService;
use Modules\Customer\Entities\Customer;
use ReflectionClass;
use Tests\TestCase;

class PartialPaymentReceiptTest extends TestCase
{
    public function test_receipt_summary_marks_forty_soles_as_partial_and_keeps_twenty_pending(): void
    {
        $invoice = new Invoice([
            'total_amount' => 60,
            'billing_period' => 'Junio 2026',
        ]);
        $invoice->id = 100;

        $payment = new Payment([
            'amount' => 40,
            'status' => 'completed',
            'payment_method' => 'efectivo',
        ]);
        $payment->id = 200;
        $payment->setRelation('invoice', $invoice);

        $invoice->setRelation('payments', new Collection([$payment]));
        $invoice->setRelation('creditNotes', new Collection());

        $method = (new ReflectionClass(PaymentReceiptService::class))->getMethod('balanceSummary');
        $method->setAccessible(true);
        $summary = $method->invoke(app(PaymentReceiptService::class), new Collection([$payment]));

        $this->assertSame(60.0, $summary['invoice_total']);
        $this->assertSame(20.0, $summary['remaining_balance']);
        $this->assertTrue($summary['is_partial_payment']);
        $this->assertSame('PAGO PARCIAL', $summary['payment_status_label']);
    }

    public function test_thermal_receipt_shows_partial_payment_and_remaining_balance(): void
    {
        $payment = new Payment([
            'amount' => 40,
            'status' => 'completed',
            'payment_method' => 'efectivo',
        ]);

        $html = view('billing::payments.receipt-pdf', [
            'payment' => $payment,
            'intent' => null,
            'receipt' => [
                'receiptItems' => [[
                    'description' => 'Mensualidad - Junio 2026',
                    'period' => 'Junio 2026',
                    'amount' => 40,
                ]],
                'totalPaid' => 40,
                'totalReceived' => 40,
                'appliedToDebt' => 40,
                'creditAmount' => 0,
                'receipt_number' => 'PAY-000200',
                'paid_at' => Carbon::parse('2026-06-30 10:00:00'),
                'issued_at' => Carbon::parse('2026-06-30 10:00:00'),
                'payment_method' => 'Efectivo',
                'customer_name' => 'Gerónimo García Llamocca',
                'customer_document' => '42665130',
                'contract_label' => '#257',
                'isPartialPayment' => true,
                'paymentStatusLabel' => 'PAGO PARCIAL',
                'invoiceTotal' => 60,
                'remainingBalance' => 20,
            ],
        ])->render();

        $this->assertStringContainsString('PAGO PARCIAL', $html);
        $this->assertStringContainsString('IMPORTE JUNIO 2026', $html);
        $this->assertStringContainsString('ABONO RECIBIDO', $html);
        $this->assertStringContainsString('RESTA POR PAGAR', $html);
        $this->assertStringContainsString('S/ 20.00', $html);
        $this->assertStringContainsString('Este comprobante acredita un abono de S/ 40.00.', $html);
        $this->assertStringContainsString('Aún quedan S/ 20.00 por pagar de Junio 2026.', $html);
    }

    public function test_print_preview_matches_partial_payment_pdf(): void
    {
        $payment = new Payment([
            'amount' => 40,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $html = view('billing::payments.print', [
            'payment' => $payment,
            'intent' => null,
            'receipt' => [
                'receiptItems' => [[
                    'description' => 'Mensualidad - Junio 2026',
                    'period' => 'Junio 2026',
                    'amount' => 40,
                ]],
                'totalPaid' => 40,
                'totalReceived' => 40,
                'appliedToDebt' => 40,
                'creditAmount' => 0,
                'receipt_number' => 'PAY-000200',
                'paid_at' => Carbon::parse('2026-06-30 10:00:00'),
                'issued_at' => Carbon::parse('2026-06-30 10:00:00'),
                'payment_method' => 'Efectivo',
                'customer_name' => 'Gerónimo García Llamocca',
                'customer_document' => '42665130',
                'contract_label' => '#257',
                'isPartialPayment' => true,
                'paymentStatusLabel' => 'PAGO PARCIAL',
                'invoiceTotal' => 60,
                'remainingBalance' => 20,
            ],
        ])->render();

        $this->assertStringContainsString('PAGO PARCIAL', $html);
        $this->assertStringContainsString('IMPORTE JUNIO 2026', $html);
        $this->assertStringContainsString('ABONO RECIBIDO', $html);
        $this->assertStringContainsString('RESTA POR PAGAR', $html);
        $this->assertStringContainsString('S/ 20.00', $html);
        $this->assertStringContainsString('Pago realizado en Efectivo', $html);
        $this->assertStringContainsString('Este comprobante acredita un abono de S/ 40.00.', $html);
        $this->assertStringContainsString('Aún quedan S/ 20.00 por pagar de Junio 2026.', $html);
    }

    public function test_partial_invoice_becomes_overdue_after_grace_period(): void
    {
        config(['billing.invoice.grace_period_days' => 5]);
        Carbon::setTestNow(Carbon::parse('2026-07-05 10:00:00'));

        $invoice = new Invoice([
            'status' => 'partial',
            'due_date' => '2026-06-30',
        ]);

        $this->assertTrue($invoice->isOverdue());

        Carbon::setTestNow();
    }

    public function test_whatsapp_context_lists_june_twenty_plus_july_sixty_for_eighty_total(): void
    {
        $customer = new Customer([
            'first_name' => 'Gerónimo',
            'last_name' => 'García Llamocca',
        ]);
        $customer->id = 124;

        $juneInvoice = new Invoice([
            'contract_id' => 257,
            'total_amount' => 60,
            'billing_period' => 'Junio 2026',
            'due_date' => '2026-06-30',
            'status' => 'partial',
        ]);
        $juneInvoice->id = 300;
        $junePayment = new Payment([
            'amount' => 40,
            'status' => 'completed',
        ]);
        $juneInvoice->setRelation('payments', new Collection([$junePayment]));
        $juneInvoice->setRelation('creditNotes', new Collection());

        $julyInvoice = new Invoice([
            'contract_id' => 257,
            'total_amount' => 60,
            'billing_period' => 'Julio 2026',
            'due_date' => '2026-07-30',
            'status' => 'pending',
        ]);
        $julyInvoice->id = 301;
        $julyInvoice->setRelation('payments', new Collection());
        $julyInvoice->setRelation('creditNotes', new Collection());

        $method = (new ReflectionClass(PaymentReminderService::class))->getMethod('messageTemplateContext');
        $method->setAccessible(true);
        $context = $method->invoke(
            app(PaymentReminderService::class),
            $customer,
            new Collection([$juneInvoice, $julyInvoice]),
            [
                'contract_ids' => [257],
                'gross_debt' => 80,
                'credit_balance' => 0,
                'due_date' => '2026-07-30',
                'type' => 'before_due',
                'rule' => 'monthly_debt_distribution',
            ],
            80
        );

        $this->assertSame('80.00', $context['net_amount']);
        $this->assertSame('junio 2026 (S/20.00) y julio 2026 (S/60.00)', $context['invoice_details']);
        $this->assertTrue($context['has_multiple_invoices']);
    }

    public function test_overdue_whatsapp_message_explains_each_period_and_the_eighty_total(): void
    {
        $customer = new Customer([
            'first_name' => 'Gerónimo',
            'last_name' => 'García Llamocca',
        ]);
        $customer->id = 124;

        $juneInvoice = new Invoice([
            'contract_id' => 257,
            'total_amount' => 60,
            'billing_period' => 'Junio 2026',
            'due_date' => '2026-06-30',
            'status' => 'partial',
        ]);
        $junePayment = new Payment(['amount' => 40, 'status' => 'completed']);
        $juneInvoice->setRelation('payments', new Collection([$junePayment]));
        $juneInvoice->setRelation('creditNotes', new Collection());

        $julyInvoice = new Invoice([
            'contract_id' => 257,
            'total_amount' => 60,
            'billing_period' => 'Julio 2026',
            'due_date' => '2026-07-30',
            'status' => 'overdue',
        ]);
        $julyInvoice->setRelation('payments', new Collection());
        $julyInvoice->setRelation('creditNotes', new Collection());

        $method = (new ReflectionClass(PaymentReminderService::class))->getMethod('messagePayloadFor');
        $method->setAccessible(true);
        $payload = $method->invoke(
            app(PaymentReminderService::class),
            $customer,
            new Collection([$juneInvoice, $julyInvoice]),
            [
                'contract_ids' => [257],
                'gross_debt' => 80,
                'credit_balance' => 0,
                'due_date' => '2026-07-30',
                'type' => 'after_due',
                'rule' => 'overdue_debt_reminder',
                'overdue_day' => 5,
            ],
            80
        );

        $this->assertStringContainsString('junio 2026 (S/20.00)', $payload['message']);
        $this->assertStringContainsString('julio 2026 (S/60.00)', $payload['message']);
        $this->assertStringContainsString('S/80.00', $payload['message']);
        $this->assertStringContainsString('más antigu', $payload['message']);
    }

    public function test_filter_customer_facing_note_ignores_yape_system_notes(): void
    {
        $systemPayment = new Payment([
            'notes' => 'Intent Yape creado automáticamente al asociar captura por DNI. Decisión: oldest_invoices_sum_match.',
        ]);

        $customPayment = new Payment([
            'notes' => 'Pago recibido por transferencia y acordado descuento.',
        ]);

        $service = app(PaymentReceiptService::class);
        $method = (new ReflectionClass(PaymentReceiptService::class))->getMethod('filterCustomerFacingNote');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, $systemPayment));
        $this->assertSame('Pago recibido por transferencia y acordado descuento.', $method->invoke($service, $customPayment));
    }

    public function test_thermal_ticket_shows_cusco_location(): void
    {
        $html = view('billing::payments.print', [
            'payment' => new Payment(['amount' => 50, 'status' => 'completed']),
            'invoice' => new Invoice(),
            'receipt' => [
                'receiptItems' => collect(),
                'totalPaid' => 50,
                'totalReceived' => 50,
                'appliedToDebt' => 50,
                'creditAmount' => 0,
                'isPartialPayment' => false,
                'custom_note' => null,
            ],
        ])->render();

        $this->assertStringContainsString('Cusco, Perú', $html);
        $this->assertStringNotContainsString('Arequipa, Perú', $html);
    }

    public function test_overpayment_with_credit_option_calculates_remaining_amount_from_received_amount(): void
    {
        $data = [
            'amount' => 195.0,
            'received_amount' => 200.0,
            'save_overpayment_as_credit' => true,
        ];

        $rawAmount = round((float) $data['amount'], 2);
        $rawReceived = isset($data['received_amount']) ? round((float) $data['received_amount'], 2) : 0;
        $saveCredit = ! empty($data['save_overpayment_as_credit']);
        $remainingAmount = ($saveCredit && $rawReceived > $rawAmount) ? $rawReceived : $rawAmount;

        $this->assertSame(200.0, $remainingAmount);
    }
}
