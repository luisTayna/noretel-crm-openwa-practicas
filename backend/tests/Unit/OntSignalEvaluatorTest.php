<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\Network\Services\OntSignalEvaluator;
use Tests\TestCase;

class OntSignalEvaluatorTest extends TestCase
{
    private function evaluator(): OntSignalEvaluator
    {
        config([
            'network.ont.thresholds' => [
                'overload_dbm' => -8.0,
                'good_min_dbm' => -25.0,
                'warning_min_dbm' => -27.0,
                'degradation_db' => 3.0,
            ],
            'network.ont.reading_max_age_minutes' => 60,
        ]);

        return new OntSignalEvaluator();
    }

    /** @dataProvider absoluteReadings */
    public function test_it_classifies_a_reading_by_absolute_power(?float $rxPower, string $expected): void
    {
        $this->assertSame($expected, $this->evaluator()->evaluate($rxPower));
    }

    public static function absoluteReadings(): array
    {
        return [
            'sin lectura' => [null, 'no_signal'],
            'saturada' => [-5.0, 'critical'],
            'buena holgada' => [-18.4, 'good'],
            'buena en el límite' => [-25.0, 'good'],
            'débil' => [-26.1, 'warning'],
            'muy débil' => [-28.7, 'critical'],
        ];
    }

    public function test_a_reading_within_range_but_falling_from_its_baseline_is_a_warning(): void
    {
        // Valor absoluto cómodo, pero perdió 3.5 dB contra su propia historia:
        // esta es la degradación que el cliente todavía no reporta.
        $status = $this->evaluator()->evaluate(-21.5, -18.0);

        $this->assertSame('warning', $status);
    }

    public function test_a_small_drop_from_the_baseline_is_still_good(): void
    {
        $this->assertSame('good', $this->evaluator()->evaluate(-19.5, -18.0));
    }

    public function test_a_stale_reading_is_reported_as_unknown_not_as_good(): void
    {
        $status = $this->evaluator()->evaluate(-18.0, null, Carbon::now()->subHours(3));

        $this->assertSame('unknown', $status);
    }
}
