<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\Billing\Services\InvoiceService;
use ReflectionClass;
use Tests\TestCase;

class InvoiceBillingPeriodTest extends TestCase
{
    /** @dataProvider equivalentPeriods */
    public function test_legacy_period_formats_resolve_to_the_same_month(
        string $billingPeriod,
        string $expected
    ): void {
        $service = app(InvoiceService::class);
        $method = (new ReflectionClass($service))->getMethod('resolveBillingPeriodStart');
        $method->setAccessible(true);

        /** @var Carbon $period */
        $period = $method->invoke($service, $billingPeriod, '2026-01-15');

        $this->assertSame($expected, $period->format('Y-m'));
    }

    public static function equivalentPeriods(): array
    {
        return [
            'Spanish January' => ['enero 2026', '2026-01'],
            'English February' => ['February 2026', '2026-02'],
            'Spanish March uppercase' => ['MARZO 2026', '2026-03'],
            'English April abbreviation' => ['Apr 2026', '2026-04'],
            'Spanish May' => ['mayo 2026', '2026-05'],
            'English June' => ['June 2026', '2026-06'],
            'Spanish July mixed case' => ['Julio 2026', '2026-07'],
            'English August abbreviation' => ['Aug 2026', '2026-08'],
            'Spanish September with accent' => ['septiembre 2026', '2026-09'],
            'English October' => ['October 2026', '2026-10'],
            'Spanish November abbreviation' => ['nov 2026', '2026-11'],
            'English December' => ['December 2026', '2026-12'],
            'canonical YYYY-MM' => ['2027-01', '2027-01'],
            'numeric MM/YYYY' => ['07/2026', '2026-07'],
            'date range takes closing month' => ['30/06/2026 - 30/07/2026', '2026-07'],
            'prorated range' => ['01/07/2026 - 30/07/2026 (prorrateado)', '2026-07'],
        ];
    }
}
