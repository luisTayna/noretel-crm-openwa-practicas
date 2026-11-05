<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Services\InvoiceService;
use Modules\Billing\Services\OpenWaService;
use Modules\Billing\Services\PaymentReminderService;
use ReflectionClass;
use Tests\TestCase;

class PaymentReminderCycleTest extends TestCase
{
    public function test_monthly_due_date_uses_day_30(): void
    {
        config(['billing.invoice.fixed_due_day' => 30]);

        $dueDate = app(InvoiceService::class)
            ->calculateMonthlyDueDate(Carbon::parse('2026-06-15'));

        $this->assertSame('2026-06-30', $dueDate->toDateString());
    }

    public function test_monthly_due_date_uses_last_day_when_month_has_no_day_30(): void
    {
        config(['billing.invoice.fixed_due_day' => 30]);

        $dueDate = app(InvoiceService::class)
            ->calculateMonthlyDueDate(Carbon::parse('2026-02-15'));

        $this->assertSame('2026-02-28', $dueDate->toDateString());
    }

    public function test_openwa_chat_id_uses_default_country_code_for_local_phone(): void
    {
        config(['billing.yape.openwa.default_country_code' => '51']);

        $chatId = app(OpenWaService::class)->chatIdFromPhone('984082956');

        $this->assertSame('51984082956@c.us', $chatId);
    }

    public function test_payment_reminders_have_multiple_contextual_text_variants(): void
    {
        $reflection = new ReflectionClass(PaymentReminderService::class);
        $method = $reflection->getMethod('messageTemplatesFor');
        $method->setAccessible(true);

        $service = app(PaymentReminderService::class);
        $beforeDue = $method->invoke($service, [
            'is_overdue' => false,
            'has_credit_balance' => false,
            'has_multiple_invoices' => false,
        ]);
        $beforeDueMultiple = $method->invoke($service, [
            'is_overdue' => false,
            'has_credit_balance' => false,
            'has_multiple_invoices' => true,
        ]);
        $overdue = $method->invoke($service, [
            'is_overdue' => true,
            'overdue_day' => 1,
            'has_multiple_invoices' => false,
        ]);

        $this->assertCount(4, $beforeDue);
        $this->assertCount(3, $beforeDueMultiple);
        $this->assertCount(3, $overdue);
    }

    public function test_before_due_variants_keep_date_without_hard_deadline_language(): void
    {
        $reflection = new ReflectionClass(PaymentReminderService::class);
        $method = $reflection->getMethod('messageTemplatesFor');
        $method->setAccessible(true);

        $templates = $method->invoke(app(PaymentReminderService::class), [
            'is_overdue' => false,
            'has_credit_balance' => false,
            'has_multiple_invoices' => false,
        ]);

        foreach ($templates as $template) {
            $body = mb_strtolower($template['body']);

            $this->assertStringContainsString('{{due_date}}', $body);
            $this->assertStringNotContainsString('vence', $body);
            $this->assertStringNotContainsString('hasta el', $body);
            $this->assertStringNotContainsString('última fecha', $body);
        }
    }

    public function test_payment_instructions_use_configured_yape_and_same_whatsapp(): void
    {
        $reflection = new ReflectionClass(PaymentReminderService::class);
        $service = app(PaymentReminderService::class);

        $formatPhone = $reflection->getMethod('formatPhoneForMessage');
        $formatPhone->setAccessible(true);
        $paymentInstructions = $reflection->getMethod('paymentInstructionsMessage');
        $paymentInstructions->setAccessible(true);

        $phone = $formatPhone->invoke($service, '51000000000');
        $message = $paymentInstructions->invoke($service, [
            'yape_phone' => $phone,
            'yape_receiver_name' => 'NORETEL',
        ]);

        $this->assertSame('000 000 000', $phone);
        $this->assertStringContainsString('Pago por Yape:', $message);
        $this->assertStringContainsString('000 000 000', $message);
        $this->assertStringContainsString('A nombre de: NORETEL', $message);
        $this->assertStringContainsString('responde a este mismo WhatsApp', $message);
        $this->assertStringContainsString('te enviaremos tu comprobante', $message);
    }

    public function test_after_grace_reminder_window_waits_five_days_after_due_date(): void
    {
        config([
            'billing.invoice.grace_period_days' => 5,
            'billing.payment_reminders.before_due_days' => [],
            'billing.payment_reminders.after_grace_days' => [0, 3, 7],
        ]);

        $reflection = new ReflectionClass(PaymentReminderService::class);
        $method = $reflection->getMethod('reminderWindowsFor');
        $method->setAccessible(true);

        $windows = $method->invoke(app(PaymentReminderService::class), Carbon::parse('2026-07-05'));

        $this->assertSame([
            [
                'type' => 'after_due',
                'rule' => 'after_grace_0',
                'days' => 0,
                'due_date' => '2026-06-30',
            ],
            [
                'type' => 'after_due',
                'rule' => 'after_grace_3',
                'days' => 3,
                'due_date' => '2026-06-27',
            ],
            [
                'type' => 'after_due',
                'rule' => 'after_grace_7',
                'days' => 7,
                'due_date' => '2026-06-23',
            ],
        ], $windows);
    }

    public function test_invoice_is_not_overdue_during_grace_period(): void
    {
        config(['billing.invoice.grace_period_days' => 5]);
        Carbon::setTestNow(Carbon::parse('2026-07-04 10:00:00'));

        $invoice = new Invoice([
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        $this->assertFalse($invoice->isOverdue());
        $this->assertSame(1, $invoice->daysUntilDue());

        Carbon::setTestNow();
    }

    public function test_invoice_is_overdue_when_grace_period_ends(): void
    {
        config(['billing.invoice.grace_period_days' => 5]);
        Carbon::setTestNow(Carbon::parse('2026-07-05 10:00:00'));

        $invoice = new Invoice([
            'status' => 'pending',
            'due_date' => '2026-06-30',
        ]);

        $this->assertTrue($invoice->isOverdue());
        $this->assertSame(0, $invoice->daysOverdue());

        Carbon::setTestNow();
    }
}
