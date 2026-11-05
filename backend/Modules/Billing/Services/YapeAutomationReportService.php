<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\YapePaymentIntent;

/**
 * Measures whether the Yape payment automation is actually doing the work,
 * instead of only proving that the pipeline exists.
 *
 * Evidence model (see YapeReconciliationService::completePayment):
 *   - payments.payment_details->yape_intent_id   : the payment came from the Yape flow.
 *   - payments.payment_details->cashier_context  : 'automatic_yape_validation' when the
 *     reconciliation closed it alone, 'manual_yape_validation' when a human closed it
 *     from the panel. A validated intent is NOT proof of automation on its own.
 *   - yape_payment_intents.validation_result->manual_override : same distinction at intent level.
 */
class YapeAutomationReportService
{
    /**
     * Date the automation was first declared functional in production.
     */
    public const AUTOMATION_GO_LIVE = '2026-06-02';

    private const IS_YAPE_PAYMENT = "JSON_EXTRACT(payment_details, '$.yape_intent_id') IS NOT NULL";
    private const CASHIER_CONTEXT = "JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.cashier_context'))";
    private const IS_MANUAL_INTENT = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(validation_result, '$.manual_override')), 'false') IN ('true', '1')";

    public function build(?string $from = null, ?string $to = null): array
    {
        $from = $this->parseDate($from, Carbon::parse(self::AUTOMATION_GO_LIVE));
        $to = $this->parseDate($to, Carbon::today());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'funnel' => $this->funnel($from, $to),
            'closure' => $this->closure($from, $to),
            'monthly' => $this->monthlyPaymentMix($from, $to),
            'speed' => $this->validationSpeed($from, $to),
            'stuck' => $this->stuckIntents($from, $to),
            'observed_reasons' => $this->observedReasons($from, $to),
        ];
    }

    /**
     * Intent funnel: how many were opened and how many ended as real money.
     */
    private function funnel(Carbon $from, Carbon $to): array
    {
        $row = DB::table('yape_payment_intents')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('COUNT(*) AS intents_created')
            ->selectRaw("SUM(status = 'validated') AS validated")
            ->selectRaw("SUM(status = 'observed') AS observed")
            ->selectRaw("SUM(status = 'cancelled') AS cancelled")
            ->selectRaw("SUM(status = 'waiting_client_evidence') AS waiting_client")
            ->selectRaw("SUM(status = 'waiting_receiver_event') AS waiting_receiver")
            ->selectRaw('SUM(payment_id IS NOT NULL) AS became_payment')
            ->first();

        $created = (int) ($row->intents_created ?? 0);

        return [
            'intents_created' => $created,
            'validated' => (int) ($row->validated ?? 0),
            'observed' => (int) ($row->observed ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'waiting_client' => (int) ($row->waiting_client ?? 0),
            'waiting_receiver' => (int) ($row->waiting_receiver ?? 0),
            'became_payment' => (int) ($row->became_payment ?? 0),
            'conversion_pct' => $this->percentage((int) ($row->became_payment ?? 0), $created),
        ];
    }

    /**
     * The decisive split: did the automation close the case, or did a human rescue it?
     */
    private function closure(Carbon $from, Carbon $to): array
    {
        $row = DB::table('yape_payment_intents')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', YapePaymentIntent::STATUS_VALIDATED)
            ->selectRaw('COUNT(*) AS validated')
            ->selectRaw('SUM(' . self::IS_MANUAL_INTENT . ') AS closed_by_human')
            ->first();

        $validated = (int) ($row->validated ?? 0);
        $byHuman = (int) ($row->closed_by_human ?? 0);
        $byAutomation = max(0, $validated - $byHuman);

        return [
            'validated' => $validated,
            'closed_automatically' => $byAutomation,
            'closed_by_human' => $byHuman,
            'automatic_pct' => $this->percentage($byAutomation, $validated),
            'manual_pct' => $this->percentage($byHuman, $validated),
        ];
    }

    /**
     * Month-by-month payment mix. The manual share inside Yape payments is the
     * metric that answers "is the automation improving?", because it does not
     * depend on how many non-Yape payments were registered that month.
     */
    private function monthlyPaymentMix(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('payments')
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') AS month")
            ->selectRaw('COUNT(*) AS total_payments')
            ->selectRaw('SUM(CASE WHEN ' . self::IS_YAPE_PAYMENT . ' THEN 1 ELSE 0 END) AS yape_payments')
            ->selectRaw('SUM(CASE WHEN ' . self::CASHIER_CONTEXT . " = 'automatic_yape_validation' THEN 1 ELSE 0 END) AS yape_automatic")
            ->selectRaw('SUM(CASE WHEN ' . self::CASHIER_CONTEXT . " = 'manual_yape_validation' THEN 1 ELSE 0 END) AS yape_manual")
            ->selectRaw('SUM(CASE WHEN ' . self::IS_YAPE_PAYMENT . ' THEN amount ELSE 0 END) AS yape_amount')
            ->selectRaw('SUM(amount) AS total_amount')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(function ($row) {
            $yape = (int) $row->yape_payments;

            return [
                'month' => $row->month,
                'label' => $this->monthLabel($row->month),
                'total_payments' => (int) $row->total_payments,
                'yape_payments' => $yape,
                'yape_automatic' => (int) $row->yape_automatic,
                'yape_manual' => (int) $row->yape_manual,
                'automatic_pct' => $this->percentage((int) $row->yape_automatic, $yape),
                'manual_pct' => $this->percentage((int) $row->yape_manual, $yape),
                'yape_share_pct' => $this->percentage($yape, (int) $row->total_payments),
                'yape_amount' => (float) $row->yape_amount,
                'total_amount' => (float) $row->total_amount,
            ];
        })->all();
    }

    /**
     * How long an intent took to become a validated payment.
     */
    private function validationSpeed(Carbon $from, Carbon $to): array
    {
        $row = DB::table('yape_payment_intents')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', YapePaymentIntent::STATUS_VALIDATED)
            ->whereNotNull('validated_at')
            ->selectRaw('COUNT(*) AS measured')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, validated_at)) AS avg_minutes')
            ->selectRaw('MAX(TIMESTAMPDIFF(MINUTE, created_at, validated_at)) AS max_minutes')
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) <= 5) AS within_5_min')
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) BETWEEN 6 AND 60) AS within_1_hour')
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) > 60) AS over_1_hour')
            ->first();

        return [
            'measured' => (int) ($row->measured ?? 0),
            'avg_minutes' => $row->avg_minutes !== null ? round((float) $row->avg_minutes, 1) : null,
            'max_minutes' => (int) ($row->max_minutes ?? 0),
            'within_5_min' => (int) ($row->within_5_min ?? 0),
            'within_1_hour' => (int) ($row->within_1_hour ?? 0),
            'over_1_hour' => (int) ($row->over_1_hour ?? 0),
        ];
    }

    /**
     * Intents nobody ever closed. The silent cost of the automation:
     * the customer already paid and is still waiting.
     */
    private function stuckIntents(Carbon $from, Carbon $to): array
    {
        return DB::table('yape_payment_intents as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->whereBetween('i.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('i.status', [
                YapePaymentIntent::STATUS_WAITING_CLIENT,
                YapePaymentIntent::STATUS_WAITING_RECEIVER,
            ])
            ->selectRaw('i.id, i.intent_number, i.status, i.expected_amount, i.created_at, i.whatsapp_phone')
            ->selectRaw('TIMESTAMPDIFF(HOUR, i.created_at, NOW()) AS age_hours')
            ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)), ''), i.customer_name) AS customer_name")
            ->orderByDesc('age_hours')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * Why it failed when it failed.
     */
    private function observedReasons(Carbon $from, Carbon $to): array
    {
        return DB::table('yape_payment_intents')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', YapePaymentIntent::STATUS_OBSERVED)
            ->selectRaw("COALESCE(NULLIF(TRIM(observed_reason), ''), 'Sin motivo registrado') AS reason")
            ->selectRaw('COUNT(*) AS occurrences')
            ->selectRaw('MAX(created_at) AS last_seen')
            ->groupBy('reason')
            ->orderByDesc('occurrences')
            ->get()
            ->all();
    }

    private function percentage(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(100 * $part / $total, 2);
    }

    private function monthLabel(string $month): string
    {
        $months = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Setiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        [$year, $monthNumber] = array_pad(explode('-', $month), 2, '01');

        return ($months[$monthNumber] ?? $month) . ' ' . $year;
    }

    private function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (empty($value)) {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return $fallback->copy();
        }
    }
}
