<?php

namespace Modules\Network\Services;

use Carbon\Carbon;
use Modules\Network\Entities\Ont;

/**
 * Traduce una lectura óptica cruda al estado de señal que se guarda en el ONT.
 *
 * Es el único lugar del sistema que decide si una señal es buena o mala.
 * Las vistas leen `signal_status` ya calculado; nadie recalcula nada al pintar.
 */
class OntSignalEvaluator
{
    /**
     * Calcula el estado de señal a partir de la potencia recibida.
     *
     * @param  float|null  $rxPower    Potencia recibida en dBm; null si no hay lectura.
     * @param  float|null  $baseline   Línea base histórica del propio ONT, en dBm.
     * @param  Carbon|null $readAt     Momento de la lectura.
     */
    public function evaluate(?float $rxPower, ?float $baseline = null, ?Carbon $readAt = null): string
    {
        if ($rxPower === null) {
            return 'no_signal';
        }

        if ($readAt !== null && $readAt->lt($this->staleBefore())) {
            return 'unknown';
        }

        $thresholds = config('network.ont.thresholds');

        // Señal demasiado fuerte: satura el receptor y falla igual que una débil.
        if ($rxPower > $thresholds['overload_dbm']) {
            return 'critical';
        }

        if ($rxPower < $thresholds['warning_min_dbm']) {
            return 'critical';
        }

        if ($rxPower < $thresholds['good_min_dbm']) {
            return 'warning';
        }

        // Valor absoluto correcto, pero cayendo respecto de su propia historia.
        // Esta es la falla que el cliente todavía no reporta.
        if ($baseline !== null && ($baseline - $rxPower) >= $thresholds['degradation_db']) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * Aplica la lectura al ONT sin persistirla. Guardar es responsabilidad
     * de quien orquesta la sincronización.
     */
    public function apply(Ont $ont, ?float $rxPower, ?float $txPower = null, ?Carbon $readAt = null): Ont
    {
        $readAt = $readAt ?: Carbon::now();

        $ont->last_rx_power = $rxPower;
        $ont->last_tx_power = $txPower;
        $ont->last_seen_at = $rxPower === null ? $ont->last_seen_at : $readAt;
        $ont->signal_status = $this->evaluate($rxPower, $ont->baseline_rx_power, $readAt);

        return $ont;
    }

    private function staleBefore(): Carbon
    {
        return Carbon::now()->subMinutes((int) config('network.ont.reading_max_age_minutes', 60));
    }
}
