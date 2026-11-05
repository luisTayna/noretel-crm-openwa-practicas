<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Http\Controllers\PaymentController;
use Modules\Billing\Services\InvoiceService;
use Modules\Billing\Services\PaymentService;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\ContractedService;
use Modules\Services\Entities\Plan;
use ReflectionClass;
use Tests\TestCase;

class ContractCustomPricingTest extends TestCase
{
    public function test_invoice_pricing_uses_custom_price_and_includes_services_at_zero(): void
    {
        $contract = $this->contract(0.0, 65.0, [15.0, 20.0]);
        $service = app(InvoiceService::class);

        $this->assertSame(0.0, $this->invoke($service, 'planAmountForContract', [$contract]));

        foreach ($contract->contractedServices as $contractedService) {
            $this->assertSame(
                0.0,
                $this->invoke($service, 'serviceAmountForContract', [$contract, $contractedService])
            );
        }
    }

    public function test_invoice_pricing_falls_back_to_plan_and_service_prices(): void
    {
        $contract = $this->contract(null, 65.0, [15.0, 20.0]);
        $service = app(InvoiceService::class);

        $this->assertSame(65.0, $this->invoke($service, 'planAmountForContract', [$contract]));
        $this->assertSame(
            [15.0, 20.0],
            $contract->contractedServices
                ->map(fn ($item) => $this->invoke($service, 'serviceAmountForContract', [$contract, $item]))
                ->all()
        );
    }

    public function test_payment_monthly_amount_uses_custom_price_even_when_zero(): void
    {
        $service = app(PaymentService::class);

        $this->assertSame(
            0.0,
            $this->invoke($service, 'monthlyAmountForContract', [$this->contract(0.0, 65.0, [15.0])])
        );
        $this->assertSame(
            150.0,
            $this->invoke($service, 'monthlyAmountForContract', [$this->contract(150.0, 65.0, [15.0])])
        );
    }

    public function test_payment_monthly_amount_falls_back_to_plan_plus_services(): void
    {
        $service = app(PaymentService::class);

        $this->assertSame(
            100.0,
            $this->invoke($service, 'monthlyAmountForContract', [$this->contract(null, 65.0, [15.0, 20.0])])
        );
    }

    public function test_exact_advance_payment_months_accepts_only_exact_monthly_multiples(): void
    {
        $service = app(PaymentService::class);
        $contract = $this->contract(null, 65.0, []);
        $contract->status = 'active';

        $this->assertSame(1, $service->exactAdvancePaymentMonths($contract, 65.0));
        $this->assertSame(3, $service->exactAdvancePaymentMonths($contract, 195.0));
        $this->assertNull($service->exactAdvancePaymentMonths($contract, 70.0));
        $this->assertNull($service->exactAdvancePaymentMonths($contract, 1625.0));
    }

    public function test_exact_advance_payment_months_rejects_inactive_contracts(): void
    {
        $service = app(PaymentService::class);
        $contract = $this->contract(null, 65.0, []);
        $contract->status = 'inactive';

        $this->assertNull($service->exactAdvancePaymentMonths($contract, 65.0));
    }

    public function test_advance_payment_starts_after_latest_existing_invoice(): void
    {
        $contract = $this->contract(null, 60.0, []);
        $contract->setRelation('invoices', new Collection([
            new Invoice(['status' => 'paid', 'issue_date' => '2026-11-01']),
            new Invoice(['status' => 'paid', 'issue_date' => '2026-12-01']),
            new Invoice(['status' => 'void', 'issue_date' => '2027-01-01']),
        ]));

        $this->assertSame(
            '12/2026',
            $this->invoke(app(PaymentController::class), 'advancePaymentBasePeriod', [$contract])
        );
    }

    private function contract(?float $finalPrice, float $planPrice, array $servicePrices): Contract
    {
        $contract = new Contract(['final_price' => $finalPrice]);
        $contract->setRelation('plan', new Plan(['price' => $planPrice]));
        $contract->setRelation(
            'contractedServices',
            new Collection(array_map(
                static fn (float $price) => new ContractedService(['price' => $price]),
                $servicePrices
            ))
        );

        return $contract;
    }

    private function invoke(object $target, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($target);
        $reflectedMethod = $reflection->getMethod($method);
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invokeArgs($target, $arguments);
    }
}
