<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\PaymentReminder;
use Modules\Customer\Entities\Customer;

class PaymentReminderService
{
    public function __construct(
        protected OpenWaService $openWaService
    ) {
        ini_set('serialize_precision', '-1');
    }

    public function generateReminders(?Carbon $date = null): array
    {
        $date = ($date ?: now())->copy()->startOfDay();

        if (! (bool) config('billing.payment_reminders.enabled', true)) {
            return $this->summary(message: 'Recordatorios OpenWA desactivados por configuración.');
        }

        $dueDate = $this->monthlyDueDateFor($date);
        $dailyLimit = max(1, (int) config('billing.payment_reminders.daily_limit', 150));
        $autoDistribute = (bool) config('billing.payment_reminders.auto_distribute', true);
        $candidates = $this->customersWithRealNetDebt($dueDate);
        $distributionDays = $this->distributionDaysFor($candidates->count(), $dailyLimit);
        $windowStart = $dueDate->copy()->subDays($distributionDays);
        $firstScheduleDate = $date->greaterThan($windowStart) ? $date->copy() : $windowStart->copy();
        $lastScheduleDate = $dueDate->copy()->subDay();
        $availableDates = collect();

        for ($day = $firstScheduleDate->copy(); $day->lessThanOrEqualTo($lastScheduleDate); $day->addDay()) {
            $availableDates->push($day->copy());
        }

        $capacity = $availableDates->count() * $dailyLimit;

        Log::info('payment_reminder_due_date_resolved', [
            'base_date' => $date->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'auto_distribute' => $autoDistribute,
            'candidate_count' => $candidates->count(),
            'distribution_days_before' => $distributionDays,
            'daily_limit' => $dailyLimit,
            'capacity' => $capacity,
        ]);

        $summary = $this->summary();
        $summary['success'] = true;
        $summary['due_date'] = $dueDate->toDateString();
        $summary['auto_distribute'] = $autoDistribute;
        $summary['candidate_count'] = $candidates->count();
        $summary['distribution_days_before'] = $distributionDays;
        $summary['daily_limit'] = $dailyLimit;
        $summary['capacity'] = $capacity;

        if ($availableDates->isEmpty()) {
            $summary['message'] = 'No hay días disponibles antes del vencimiento para programar recordatorios.';

            return $summary;
        }

        Log::info('payment_reminder_distribution_started', [
            'base_date' => $date->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'auto_distribute' => $autoDistribute,
            'candidate_count' => $candidates->count(),
            'first_schedule_date' => $availableDates->first()?->toDateString(),
            'last_schedule_date' => $availableDates->last()?->toDateString(),
            'available_days' => $availableDates->count(),
            'daily_limit' => $dailyLimit,
            'capacity' => $capacity,
        ]);

        $candidates->take($capacity)->values()->each(function (array $candidate, int $index) use (&$summary, $date, $dueDate, $availableDates, $dailyLimit) {
            $dayIndex = intdiv($index, $dailyLimit);
            $scheduledDate = $availableDates->get($dayIndex);

            if (! $scheduledDate) {
                $summary['skipped']++;
                $summary['details'][] = [
                    'status' => 'skipped',
                    'reason' => 'Sin día disponible dentro de la ventana de distribución.',
                    'customer_id' => $candidate['customer']->id,
                ];

                return;
            }

            $scheduledFor = $this->scheduledDateTimeFor($scheduledDate, $index % $dailyLimit);

            Log::info('payment_reminder_distribution_day_assigned', [
                'customer_id' => $candidate['customer']->id,
                'contract_ids' => $candidate['contract_ids'],
                'invoice_ids' => $candidate['invoice_ids'],
                'gross_debt' => $candidate['gross_debt'],
                'credit_balance' => $candidate['credit_balance'],
                'net_amount' => $candidate['net_amount'],
                'due_date' => $dueDate->toDateString(),
                'scheduled_for' => $scheduledFor->toDateTimeString(),
                'distribution_day' => '-' . $scheduledFor->copy()->startOfDay()->diffInDays($dueDate),
            ]);

            $result = $this->createReminderForCustomer(
                $candidate['customer'],
                $candidate['invoices'],
                [
                    'type' => 'before_due',
                    'rule' => 'monthly_debt_distribution',
                    'rules' => ['monthly_debt_distribution'],
                    'days' => $scheduledFor->copy()->startOfDay()->diffInDays($dueDate),
                    'due_date' => $dueDate->toDateString(),
                    'reminder_key' => $this->reminderKeyFor($candidate['customer'], $candidate['contract_ids'], $dueDate),
                    'generated_for' => $date->toDateString(),
                    'scheduled_for' => $scheduledFor,
                    'gross_debt' => $candidate['gross_debt'],
                    'credit_balance' => $candidate['credit_balance'],
                    'net_amount' => $candidate['net_amount'],
                    'contract_ids' => $candidate['contract_ids'],
                ],
                $scheduledDate
            );

            $summary[$result['status']]++;
            $summary['details'][] = $result;
        });

        if ($candidates->count() > $capacity) {
            $notScheduled = $candidates->count() - $capacity;

            Log::warning('payment_reminder_generation_capacity_exceeded', [
                'candidate_count' => $candidates->count(),
                'capacity' => $capacity,
                'not_scheduled' => $notScheduled,
                'daily_limit' => $dailyLimit,
                'distribution_days_before' => $availableDates->count(),
                'due_date' => $dueDate->toDateString(),
            ]);

            $summary['skipped'] += $notScheduled;
            $summary['message'] = "Capacidad excedida: {$notScheduled} cliente(s) con deuda no fueron programados.";
        }

        return $summary;
    }

    public function generateAllReminders(?Carbon $date = null): array
    {
        $preventive = $this->generateReminders($date);
        $overdue = $this->generateOverdueReminders($date);

        return [
            'success' => (bool) ($preventive['success'] ?? false) && (bool) ($overdue['success'] ?? false),
            'message' => trim(($preventive['message'] ?? '') . ' ' . ($overdue['message'] ?? '')),
            'generated' => (int) ($preventive['generated'] ?? 0) + (int) ($overdue['generated'] ?? 0),
            'skipped' => (int) ($preventive['skipped'] ?? 0) + (int) ($overdue['skipped'] ?? 0),
            'failed' => (int) ($preventive['failed'] ?? 0) + (int) ($overdue['failed'] ?? 0),
            'details' => [
                'preventive' => $preventive,
                'overdue' => $overdue,
            ],
        ];
    }

    public function generateOverdueReminders(?Carbon $date = null): array
    {
        $date = ($date ?: now())->copy()->startOfDay();
        $summary = $this->summary();
        $summary['success'] = true;
        $summary['rule'] = 'overdue_debt_reminder';

        if (! (bool) config('billing.overdue_reminders.enabled', true)) {
            $summary['message'] = 'Recordatorios de deuda vencida desactivados por configuración.';

            return $summary;
        }

        $dailyLimit = max(1, (int) config('billing.overdue_reminders.daily_limit', config('billing.payment_reminders.daily_limit', 150)));
        $matchingDays = $this->matchingOverdueDaysFor($date);

        Log::info('overdue_reminder_generation_started', [
            'date' => $date->toDateString(),
            'matching_overdue_days' => $matchingDays,
            'daily_limit' => $dailyLimit,
            'openwa_session_type' => 'yape',
            'session_id' => config('billing.yape.openwa.session'),
        ]);

        if (empty($matchingDays)) {
            $summary['message'] = 'Hoy no corresponde generar recordatorios de deuda vencida.';

            return $summary;
        }

        $generatedToday = 0;

        foreach ($matchingDays as $overdueDay) {
            $dueDate = $date->copy()->subDays($overdueDay);
            $candidates = $this->customersWithOverdueNetDebt($date, $dueDate);

            foreach ($candidates as $index => $candidate) {
                if ($generatedToday >= $dailyLimit) {
                    $summary['message'] = 'Límite diario de recordatorios vencidos alcanzado.';
                    break 2;
                }

                $scheduledFor = $this->scheduledDateTimeFor($date, $generatedToday);
                $result = $this->createReminderForCustomer(
                    $candidate['customer'],
                    $candidate['invoices'],
                    [
                        'type' => 'after_due',
                        'rule' => 'overdue_debt_reminder',
                        'rules' => ['overdue_debt_reminder'],
                        'days' => $overdueDay,
                        'overdue_day' => $overdueDay,
                        'due_date' => $dueDate->toDateString(),
                        'reminder_key' => $this->overdueReminderKeyFor($candidate['customer'], $candidate['contract_ids'], $dueDate, $overdueDay),
                        'generated_for' => $date->toDateString(),
                        'scheduled_for' => $scheduledFor,
                        'gross_debt' => $candidate['gross_debt'],
                        'credit_balance' => $candidate['credit_balance'],
                        'net_amount' => $candidate['net_amount'],
                        'contract_ids' => $candidate['contract_ids'],
                    ],
                    $date
                );

                $summary[$result['status']]++;
                $summary['details'][] = $result;

                if ($result['status'] === 'generated') {
                    $generatedToday++;
                }
            }
        }

        return $summary;
    }

    public function generateTestReminder(int $customerId, ?Carbon $date = null, bool $scheduledNow = false, bool $dryRun = false, string $type = 'all', ?int $overdueDay = null): array
    {
        $date = ($date ?: now())->copy()->startOfDay();
        $type = $type === 'overdue' ? 'overdue' : 'preventive';
        $dueDate = $this->monthlyDueDateFor($date);
        $summary = $this->summary();
        $summary['success'] = true;
        $summary['test_mode'] = true;
        $summary['type'] = $type;
        $summary['dry_run'] = $dryRun;
        $summary['customer_id'] = $customerId;
        $summary['due_date'] = $dueDate->toDateString();

        Log::info('payment_reminder_test_generation_started', [
            'customer_id' => $customerId,
            'due_date' => $dueDate->toDateString(),
            'type' => $type,
            'overdue_day' => $overdueDay,
            'scheduled_now' => $scheduledNow,
            'dry_run' => $dryRun,
            'test_mode' => true,
            'openwa_session_type' => 'yape',
            'session_id' => config('billing.yape.openwa.session'),
        ]);

        if (! (bool) config('billing.payment_reminders.enabled', true)) {
            $summary['message'] = 'Recordatorios OpenWA desactivados por configuración.';

            return $summary;
        }

        $customer = Customer::query()
            ->with(['contracts' => function ($query) {
                $query->where('status', 'active')
                    ->with(['invoices.payments', 'invoices.creditNotes']);
            }])
            ->where('id', $customerId)
            ->where('active', true)
            ->first();

        if (! $customer) {
            $summary['skipped']++;
            $summary['message'] = 'Cliente activo no encontrado.';
            $summary['details'][] = [
                'status' => 'skipped',
                'reason' => $summary['message'],
                'customer_id' => $customerId,
            ];

            Log::info('payment_reminder_generation_skipped_no_debt', [
                'customer_id' => $customerId,
                'reason' => $summary['message'],
                'test_mode' => true,
            ]);

            return $summary;
        }

        if ($type === 'overdue') {
            $overdueDay = max(1, (int) ($overdueDay ?: 1));
            $dueDate = $date->copy()->subDays($overdueDay);
            $candidate = $this->overdueDebtCandidateForCustomer($customer, $date, $dueDate);
        } else {
            $candidate = $this->debtCandidateForCustomer($customer, $dueDate);
        }

        if (! $candidate) {
            $summary['skipped']++;
            $summary['message'] = $type === 'overdue'
                ? 'Cliente no cumple condiciones para recordatorio vencido: sin deuda vencida neta real, sin contrato activo o teléfono inválido.'
                : 'Cliente no cumple condiciones para recordatorio: sin deuda neta real, sin contrato activo o teléfono inválido.';
            $summary['details'][] = [
                'status' => 'skipped',
                'reason' => $summary['message'],
                'customer_id' => $customerId,
            ];

            return $summary;
        }

        $scheduledFor = $scheduledNow ? now()->subSecond() : $this->scheduledDateTimeFor($type === 'overdue' ? $date : $dueDate->copy()->subDay(), 0);
        $reminderDate = $scheduledFor->copy()->startOfDay();
        $window = [
            'type' => $type === 'overdue' ? 'after_due' : 'before_due',
            'rule' => $type === 'overdue' ? 'overdue_debt_reminder' : 'test_monthly_debt_reminder',
            'rules' => [$type === 'overdue' ? 'overdue_debt_reminder' : 'test_monthly_debt_reminder'],
            'days' => $type === 'overdue' ? $overdueDay : max(0, $scheduledFor->copy()->startOfDay()->diffInDays($dueDate, false)),
            'overdue_day' => $type === 'overdue' ? $overdueDay : null,
            'due_date' => $dueDate->toDateString(),
            'reminder_key' => $type === 'overdue'
                ? 'test_overdue_day_' . $overdueDay . ':customer_' . $customer->id . ':' . $dueDate->format('Y-m')
                : 'test_monthly_due:customer_' . $customer->id . ':' . $dueDate->format('Y-m'),
            'generated_for' => $date->toDateString(),
            'scheduled_for' => $scheduledFor,
            'gross_debt' => $candidate['gross_debt'],
            'credit_balance' => $candidate['credit_balance'],
            'net_amount' => $candidate['net_amount'],
            'contract_ids' => $candidate['contract_ids'],
            'test_mode' => true,
        ];
        $messagePayload = $this->messagePayloadFor($customer, $candidate['invoices'], $window, $candidate['net_amount']);

        $dryRunDetail = [
            'status' => 'dry_run',
            'rule' => $window['rule'],
            'overdue_day' => $type === 'overdue' ? $overdueDay : null,
            'customer_id' => $customer->id,
            'contract_ids' => $candidate['contract_ids'],
            'invoice_ids' => $candidate['invoice_ids'],
            'target_phone' => $customer->phone,
            'gross_debt' => $candidate['gross_debt'],
            'credit_balance' => $candidate['credit_balance'],
            'net_amount' => $candidate['net_amount'],
            'due_date' => $dueDate->toDateString(),
            'scheduled_for' => $scheduledFor->toDateTimeString(),
            'reminder_key' => $window['reminder_key'],
            'template_key' => $messagePayload['template_key'],
            'template_group' => $messagePayload['template_group'],
            'message_variant' => $messagePayload['message_variant'],
            'message' => $messagePayload['message'],
            'metadata' => [
                'template_key' => $messagePayload['template_key'],
                'template_group' => $messagePayload['template_group'],
                'message_variant' => $messagePayload['message_variant'],
                'invoice_periods' => $messagePayload['invoice_periods'],
                'invoice_details' => $messagePayload['invoice_details'],
                'overdue_day' => $type === 'overdue' ? $overdueDay : null,
            ],
            'test_mode' => true,
            'openwa_session_type' => 'yape',
            'session_id' => config('billing.yape.openwa.session'),
        ];

        if ($dryRun) {
            Log::info($type === 'overdue' ? 'overdue_reminder_dry_run' : 'payment_reminder_dry_run', $dryRunDetail);
            $summary['details'][] = $dryRunDetail;
            $summary['message'] = 'Dry-run: no se insertó ningún recordatorio.';

            return $summary;
        }

        $result = $this->createReminderForCustomer(
            $customer,
            $candidate['invoices'],
            $window,
            $reminderDate
        );

        $summary[$result['status']]++;
        $summary['details'][] = $result;

        Log::info('payment_reminder_test_generated', array_merge($dryRunDetail, [
            'status' => $result['status'],
            'reminder_id' => $result['reminder_id'] ?? null,
        ]));

        return $summary;
    }

    public function sendPendingReminders(int $limit = 20, bool $ignoreHours = false, bool $ignoreInterval = false): array
    {
        if (! (bool) config('billing.payment_reminders.enabled', true)) {
            return [
                'success' => true,
                'batch_id' => null,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'details' => [],
                'message' => 'Recordatorios OpenWA desactivados por configuración.',
            ];
        }

        $now = now();
        $batchId = (string) Str::uuid();
        $this->recoverStaleSendingReminders($now);

        if ($ignoreHours) {
            Log::info('payment_reminder_ignore_hours_enabled', [
                'batch_id' => $batchId,
                'date_time' => $now->toDateTimeString(),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
            ]);
        }

        if (! $ignoreHours && ! $this->withinAllowedSendWindow($now)) {
            return [
                'success' => true,
                'batch_id' => $batchId,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'details' => [],
                'message' => 'Fuera del horario permitido para enviar recordatorios.',
            ];
        }

        if (! $ignoreInterval) {
            $intervalGate = $this->paymentReminderSendIntervalGate($now, $batchId);

            if (! $intervalGate['allowed']) {
                return [
                    'success' => true,
                    'batch_id' => $batchId,
                    'total' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'details' => [$intervalGate],
                    'message' => $intervalGate['message'],
                ];
            }
        } else {
            Log::info('payment_reminder_ignore_interval_enabled', [
                'batch_id' => $batchId,
                'date_time' => $now->toDateTimeString(),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
            ]);
        }

        $dailyLimit = max(1, (int) config('billing.payment_reminders.daily_limit', 150));
        $sentToday = PaymentReminder::query()
            ->where('channel', 'openwa')
            ->where('status', 'sent')
            ->whereDate('sent_at', $now->toDateString())
            ->count();
        $remainingToday = max($dailyLimit - $sentToday, 0);

        if ($remainingToday <= 0) {
            Log::info('payment_reminder_daily_limit_reached', [
                'daily_limit' => $dailyLimit,
                'sent_today' => $sentToday,
                'date' => $now->toDateString(),
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'details' => [],
                'message' => 'Límite diario de recordatorios alcanzado.',
            ];
        }

        $limit = max(1, min(
            $limit,
            1,
            (int) config('billing.payment_reminders.batch_size', 1),
            $remainingToday
        ));
        $maxAttempts = (int) config('billing.payment_reminders.max_attempts', 3);

        $reminders = PaymentReminder::query()
            ->with(['invoice.contract.customer', 'customer'])
            ->where('channel', 'openwa')
            ->whereIn('status', ['pending', 'failed'])
            ->whereDate('reminder_date', '<=', $now->toDateString())
            ->where(function ($query) use ($now) {
                $query->whereNull('scheduled_for')
                    ->orWhere('scheduled_for', '<=', $now);
            })
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('scheduled_for')
            ->orderBy('reminder_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $summary = [
            'success' => true,
            'batch_id' => $batchId,
            'total' => $reminders->count(),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        Log::info('payment_reminder_send_started', [
            'batch_id' => $batchId,
            'limit' => $limit,
            'daily_limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'remaining_today' => $remainingToday,
            'pending_count' => $reminders->count(),
            'ignore_hours' => $ignoreHours,
            'openwa_session_type' => 'yape',
            'session_id' => config('billing.yape.openwa.session'),
            'openwa_session' => config('billing.yape.openwa.session'),
        ]);

        foreach ($reminders as $index => $reminder) {
            if (($sentToday + $summary['sent']) >= $dailyLimit) {
                Log::info('payment_reminder_daily_limit_reached', [
                    'batch_id' => $batchId,
                    'daily_limit' => $dailyLimit,
                    'sent_today' => $sentToday + $summary['sent'],
                ]);
                break;
            }

            $sendable = $this->refreshReminderBeforeSending($reminder);

            if (! $sendable['send']) {
                $summary['skipped']++;
                $summary['details'][] = [
                    'reminder_id' => $reminder->id,
                    'status' => 'skipped',
                    'reason' => $sendable['reason'],
                ];
                continue;
            }

            $attempts = (int) $reminder->attempts + 1;

            Log::info('payment_reminder_send_attempt', [
                'batch_id' => $batchId,
                'reminder_id' => $reminder->id,
                'customer_id' => $reminder->customer_id,
                'target_phone' => $reminder->target_phone,
                'attempts' => $attempts,
                'rule' => data_get($reminder->metadata, 'rule'),
                'overdue_day' => data_get($reminder->metadata, 'overdue_day'),
                'template_key' => data_get($reminder->metadata, 'template_key'),
                'template_group' => data_get($reminder->metadata, 'template_group'),
                'message_variant' => data_get($reminder->metadata, 'message_variant'),
                'test_mode' => (bool) data_get($reminder->metadata, 'test_mode', false),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
                'openwa_session' => config('billing.yape.openwa.session'),
            ]);

            if (data_get($reminder->metadata, 'rule') === 'overdue_debt_reminder') {
                Log::info('overdue_reminder_send_attempt', [
                    'batch_id' => $batchId,
                    'reminder_id' => $reminder->id,
                    'customer_id' => $reminder->customer_id,
                    'target_phone' => $reminder->target_phone,
                    'attempts' => $attempts,
                    'overdue_day' => data_get($reminder->metadata, 'overdue_day'),
                    'template_key' => data_get($reminder->metadata, 'template_key'),
                    'template_group' => data_get($reminder->metadata, 'template_group'),
                    'message_variant' => data_get($reminder->metadata, 'message_variant'),
                    'openwa_session_type' => 'yape',
                    'session_id' => config('billing.yape.openwa.session'),
                ]);
            }

            $response = $this->openWaService->sendTextToPhone(
                $reminder->target_phone ?: $reminder->customer?->phone,
                (string) $reminder->message,
                [
                    'source' => 'payment_reminder',
                    'reminder_id' => $reminder->id,
                    'batch_id' => $batchId,
                    'openwa_session' => 'yape',
                    'openwa_session_type' => 'yape',
                    'session_id' => config('billing.yape.openwa.session'),
                    'template_key' => data_get($reminder->metadata, 'template_key'),
                    'template_group' => data_get($reminder->metadata, 'template_group'),
                    'message_variant' => data_get($reminder->metadata, 'message_variant'),
                ]
            );

            $success = (bool) ($response['success'] ?? false);
            $reminder->update([
                'batch_id' => $batchId,
                'attempts' => $attempts,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => $success ? now() : null,
                'openwa_response' => $response,
                'error_message' => $success ? null : ($response['error'] ?? $response['body'] ?? 'OpenWA no aceptó el mensaje.'),
            ]);

            $sendLogContext = [
                'batch_id' => $batchId,
                'reminder_id' => $reminder->id,
                'customer_id' => $reminder->customer_id,
                'target_phone' => $reminder->target_phone,
                'attempts' => $attempts,
                'http_status' => $response['status'] ?? null,
                'error' => $success ? null : ($response['error'] ?? $response['body'] ?? null),
                'template_key' => data_get($reminder->metadata, 'template_key'),
                'template_group' => data_get($reminder->metadata, 'template_group'),
                'message_variant' => data_get($reminder->metadata, 'message_variant'),
                'test_mode' => (bool) data_get($reminder->metadata, 'test_mode', false),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
                'openwa_session' => config('billing.yape.openwa.session'),
            ];

            if ($success) {
                Log::info('payment_reminder_sent', $sendLogContext);
                if (data_get($reminder->metadata, 'rule') === 'overdue_debt_reminder') {
                    Log::info('overdue_reminder_sent', $sendLogContext);
                }
            } else {
                Log::warning('payment_reminder_failed', $sendLogContext);
                if (data_get($reminder->metadata, 'rule') === 'overdue_debt_reminder') {
                    Log::warning('overdue_reminder_failed', $sendLogContext);
                }
            }

            if ($success) {
                $summary['sent']++;
                $this->storeNextAllowedPaymentReminderSendAt(now(), $batchId);
            } else {
                $summary['failed']++;
            }

            $summary['details'][] = [
                'reminder_id' => $reminder->id,
                'status' => $success ? 'sent' : 'failed',
                'attempts' => $attempts,
                'phone' => $reminder->target_phone,
                'template_key' => data_get($reminder->metadata, 'template_key'),
            ];

            if ($index < $reminders->count() - 1) {
                $this->applyRandomDelay();
            }
        }

        return $summary;
    }

    protected function paymentReminderSendIntervalGate(Carbon $now, string $batchId): array
    {
        $cacheKey = $this->paymentReminderSendIntervalCacheKey();

        try {
            $cachedNextAllowedSendAt = Cache::get($cacheKey);
            $lastSentAt = $this->lastSuccessfulPaymentReminderSentAt();
        } catch (Throwable $exception) {
            Log::warning('payment_reminder_send_interval_cache_failed', [
                'batch_id' => $batchId,
                'cache_key' => $cacheKey,
                'now' => $now->toDateTimeString(),
                'action' => 'block',
                'error' => $exception->getMessage(),
            ]);

            return [
                'allowed' => false,
                'status' => 'interval_cache_failed',
                'reason' => 'No se pudo leer el control de intervalo. Por seguridad no se envía en esta corrida.',
                'message' => 'No se pudo leer el control de intervalo. Por seguridad no se envía en esta corrida.',
            ];
        }

        $nextAllowedSendAt = $this->parseNextAllowedSendAt($cachedNextAllowedSendAt);
        $randomIntervalSeconds = $this->cachedRandomIntervalSeconds($cachedNextAllowedSendAt);

        if (! $nextAllowedSendAt && $lastSentAt) {
            $randomIntervalSeconds = $this->randomPaymentReminderSendIntervalSeconds();
            $nextAllowedSendAt = $lastSentAt->copy()->addSeconds($randomIntervalSeconds);

            Log::info('payment_reminder_random_interval_selected', [
                'batch_id' => $batchId,
                'last_sent_at' => $lastSentAt->toDateTimeString(),
                'random_interval_seconds' => $randomIntervalSeconds,
                'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'action' => 'recover_missing_cache',
            ]);

            $storedNextAllowedSendAt = $this->cacheNextAllowedPaymentReminderSendAt(
                $nextAllowedSendAt,
                $batchId,
                $lastSentAt,
                $randomIntervalSeconds,
                $now,
                'recover_missing_cache'
            );

            if (! $storedNextAllowedSendAt) {
                return [
                    'allowed' => false,
                    'status' => 'interval_cache_failed',
                    'reason' => 'No se pudo guardar el control de intervalo. Por seguridad no se envía en esta corrida.',
                    'message' => 'No se pudo guardar el control de intervalo. Por seguridad no se envía en esta corrida.',
                ];
            }
        }

        if ($nextAllowedSendAt && $now->lessThan($nextAllowedSendAt)) {
            Log::info('payment_reminder_send_interval_wait', [
                'batch_id' => $batchId,
                'last_sent_at' => $lastSentAt?->toDateTimeString(),
                'random_interval_seconds' => $randomIntervalSeconds,
                'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'action' => 'wait',
            ]);

            return [
                'allowed' => false,
                'status' => 'waiting_interval',
                'last_sent_at' => $lastSentAt?->toDateTimeString(),
                'random_interval_seconds' => $randomIntervalSeconds,
                'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'reason' => 'Aún no toca enviar por intervalo aleatorio.',
                'message' => 'Aún no toca enviar por intervalo aleatorio. Próximo envío permitido: ' . $nextAllowedSendAt->toDateTimeString(),
            ];
        }

        return [
            'allowed' => true,
            'status' => 'interval_allowed',
            'last_sent_at' => $lastSentAt?->toDateTimeString(),
            'next_allowed_send_at' => $nextAllowedSendAt?->toDateTimeString(),
            'now' => $now->toDateTimeString(),
        ];
    }

    protected function storeNextAllowedPaymentReminderSendAt(Carbon $lastSentAt, string $batchId): ?Carbon
    {
        $now = now();
        $randomIntervalSeconds = $this->randomPaymentReminderSendIntervalSeconds();
        $nextAllowedSendAt = $lastSentAt->copy()->addSeconds($randomIntervalSeconds);

        Log::info('payment_reminder_random_interval_selected', [
            'batch_id' => $batchId,
            'last_sent_at' => $lastSentAt->toDateTimeString(),
            'random_interval_seconds' => $randomIntervalSeconds,
            'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'action' => 'set_after_successful_send',
        ]);

        return $this->cacheNextAllowedPaymentReminderSendAt(
            $nextAllowedSendAt,
            $batchId,
            $lastSentAt,
            $randomIntervalSeconds,
            $now,
            'set_after_successful_send'
        );
    }

    protected function cacheNextAllowedPaymentReminderSendAt(
        Carbon $nextAllowedSendAt,
        string $batchId,
        ?Carbon $lastSentAt,
        ?int $randomIntervalSeconds,
        Carbon $now,
        string $action
    ): ?Carbon {
        $cacheKey = $this->paymentReminderSendIntervalCacheKey();

        try {
            Cache::forever($cacheKey, [
                'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
                'last_sent_at' => $lastSentAt?->toDateTimeString(),
                'random_interval_seconds' => $randomIntervalSeconds,
                'stored_at' => $now->toDateTimeString(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('payment_reminder_next_allowed_send_at_cache_failed', [
                'batch_id' => $batchId,
                'cache_key' => $cacheKey,
                'last_sent_at' => $lastSentAt?->toDateTimeString(),
                'random_interval_seconds' => $randomIntervalSeconds,
                'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
                'now' => $now->toDateTimeString(),
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        Log::info('payment_reminder_next_allowed_send_at_set', [
            'batch_id' => $batchId,
            'cache_key' => $cacheKey,
            'last_sent_at' => $lastSentAt?->toDateTimeString(),
            'random_interval_seconds' => $randomIntervalSeconds,
            'next_allowed_send_at' => $nextAllowedSendAt->toDateTimeString(),
            'now' => $now->toDateTimeString(),
            'action' => $action,
        ]);

        return $nextAllowedSendAt;
    }

    protected function lastSuccessfulPaymentReminderSentAt(): ?Carbon
    {
        $sentAt = PaymentReminder::query()
            ->where('channel', 'openwa')
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->max('sent_at');

        return $sentAt ? Carbon::parse($sentAt) : null;
    }

    protected function parseNextAllowedSendAt(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            if (is_array($value)) {
                $value = $value['next_allowed_send_at'] ?? null;
            }

            if (! $value) {
                return null;
            }

            return $value instanceof Carbon
                ? $value->copy()
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function cachedRandomIntervalSeconds(mixed $value): ?int
    {
        if (! is_array($value) || ! isset($value['random_interval_seconds'])) {
            return null;
        }

        $seconds = (int) $value['random_interval_seconds'];

        return $seconds > 0 ? $seconds : null;
    }

    protected function randomPaymentReminderSendIntervalSeconds(): int
    {
        $min = max(1, (int) config('billing.payment_reminders.send_interval_min_seconds', 75));
        $max = max($min, (int) config('billing.payment_reminders.send_interval_max_seconds', 210));

        return random_int($min, $max);
    }

    protected function paymentReminderSendIntervalCacheKey(): string
    {
        return (string) config('billing.payment_reminders.next_allowed_send_cache_key', 'billing_payment_reminders_next_allowed_send_at');
    }

    public function monthlyDueDateFor(?Carbon $date = null): Carbon
    {
        $base = ($date ?: now())->copy()->startOfDay();
        $dueDay = max(1, (int) config('billing.payment_reminders.due_day', config('billing.invoice.fixed_due_day', 30)));
        $dueDate = $this->dueDateForMonth($base, $dueDay);

        if ($base->greaterThan($dueDate)) {
            $dueDate = $this->dueDateForMonth($base->copy()->addMonthNoOverflow()->startOfMonth(), $dueDay);
        }

        return $dueDate;
    }

    protected function dueDateForMonth(Carbon $date, int $dueDay): Carbon
    {
        $month = $date->copy()->startOfMonth();
        $day = min($dueDay, $month->copy()->endOfMonth()->day);

        return $month->copy()->day($day)->startOfDay();
    }

    protected function distributionDaysFor(int $candidateCount, int $dailyLimit): int
    {
        if ((bool) config('billing.payment_reminders.auto_distribute', true)) {
            return max(1, (int) ceil(max($candidateCount, 1) / max($dailyLimit, 1)));
        }

        return max(1, (int) config('billing.payment_reminders.distribution_days_before', 6));
    }

    protected function reminderKeyFor(Customer $customer, array $contractIds, Carbon $dueDate): string
    {
        $period = $dueDate->format('Y-m');

        if (count($contractIds) === 1) {
            return 'monthly_due:contract_' . $contractIds[0] . ':' . $period;
        }

        return 'monthly_due:customer_' . $customer->id . ':' . $period;
    }

    protected function overdueReminderKeyFor(Customer $customer, array $contractIds, Carbon $dueDate, int $overdueDay): string
    {
        $period = $dueDate->format('Y-m');

        if (count($contractIds) === 1) {
            return 'overdue_day_' . $overdueDay . ':contract_' . $contractIds[0] . ':' . $period;
        }

        return 'overdue_day_' . $overdueDay . ':customer_' . $customer->id . ':' . $period;
    }

    protected function matchingOverdueDaysFor(Carbon $date): array
    {
        return collect($this->configuredOverdueDays())
            ->filter(function (int $overdueDay) use ($date) {
                $candidateDueDate = $date->copy()->subDays($overdueDay);

                return $candidateDueDate->isSameDay($this->dueDateForMonth(
                    $candidateDueDate,
                    max(1, (int) config('billing.payment_reminders.due_day', config('billing.invoice.fixed_due_day', 30)))
                ));
            })
            ->values()
            ->all();
    }

    protected function configuredOverdueDays(): array
    {
        $days = (string) config('billing.overdue_reminders.days', '1,3,5');

        return collect(explode(',', $days))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn (int $day) => $day > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function customersWithRealNetDebt(Carbon $dueDate): Collection
    {
        Log::info('payment_reminder_generation_started', [
            'due_date' => $dueDate->toDateString(),
        ]);

        return Customer::query()
            ->with(['contracts' => function ($query) {
                $query->whereIn('status', ['active', 'suspended', 'inactive'])
                    ->with(['invoices.payments', 'invoices.creditNotes']);
            }])
            ->whereHas('contracts', function ($query) {
                $query->whereIn('status', ['active', 'suspended', 'inactive'])
                    ->whereHas('invoices', function ($invoiceQuery) {
                        $invoiceQuery->whereIn('status', ['pending', 'partial', 'overdue']);
                    });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => $this->debtCandidateForCustomer($customer, $dueDate))
            ->filter()
            ->values();
    }

    protected function customersWithOverdueNetDebt(Carbon $date, Carbon $dueDate): Collection
    {
        return Customer::query()
            ->with(['contracts' => function ($query) {
                $query->whereIn('status', ['active', 'suspended', 'inactive'])
                    ->with(['invoices.payments', 'invoices.creditNotes']);
            }])
            ->whereHas('contracts', function ($query) use ($date) {
                $query->whereIn('status', ['active', 'suspended', 'inactive'])
                    ->whereHas('invoices', function ($invoiceQuery) use ($date) {
                        $invoiceQuery->whereIn('status', ['pending', 'partial', 'overdue'])
                            ->whereDate('due_date', '<', $date->toDateString());
                    });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => $this->overdueDebtCandidateForCustomer($customer, $date, $dueDate))
            ->filter()
            ->values();
    }

    protected function debtCandidateForCustomer(Customer $customer, Carbon $dueDate): ?array
    {
        $invoices = $this->pendingInvoicesForCustomer($customer);

        if ($invoices->isEmpty()) {
            Log::info('payment_reminder_generation_skipped_no_debt', [
                'customer_id' => $customer->id,
            ]);

            return null;
        }

        $phone = trim((string) $customer->phone);

        if ($this->openWaService->chatIdFromPhone($phone) === null) {
            Log::info('payment_reminder_generation_skipped_invalid_phone', [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ]);

            return null;
        }

        $grossDebt = $this->money($invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice)));
        $creditBalance = $this->money($this->availableCreditForInvoices($customer, $invoices));
        $netAmount = $this->money(max($grossDebt - $creditBalance, 0));

        if ($netAmount <= 0.009) {
            Log::info('payment_reminder_generation_skipped_credit_covered', [
                'customer_id' => $customer->id,
                'invoice_ids' => $invoices->pluck('id')->values()->all(),
                'gross_debt' => $grossDebt,
                'credit_balance' => $creditBalance,
                'net_amount' => $netAmount,
                'due_date' => $dueDate->toDateString(),
            ]);

            return null;
        }

        return [
            'customer' => $customer,
            'invoices' => $invoices,
            'invoice_ids' => $invoices->pluck('id')->values()->all(),
            'contract_ids' => $invoices->pluck('contract_id')->unique()->values()->all(),
            'gross_debt' => $grossDebt,
            'credit_balance' => $creditBalance,
            'net_amount' => $netAmount,
        ];
    }

    protected function overdueDebtCandidateForCustomer(Customer $customer, Carbon $date, Carbon $dueDate): ?array
    {
        $invoices = $this->pendingOverdueInvoicesForCustomer($customer, $date);

        if ($invoices->isEmpty()) {
            Log::info('overdue_reminder_skipped_no_overdue_debt', [
                'customer_id' => $customer->id,
                'due_date' => $dueDate->toDateString(),
            ]);

            return null;
        }

        $phone = trim((string) $customer->phone);

        if ($this->openWaService->chatIdFromPhone($phone) === null) {
            Log::info('overdue_reminder_skipped_invalid_phone', [
                'customer_id' => $customer->id,
                'phone' => $phone,
                'due_date' => $dueDate->toDateString(),
            ]);

            return null;
        }

        $grossDebt = $this->money($invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice)));
        $creditBalance = $this->money($this->availableCreditForInvoices($customer, $invoices));
        $netAmount = $this->money(max($grossDebt - $creditBalance, 0));

        if ($netAmount <= 0.009) {
            Log::info('overdue_reminder_skipped_credit_covered', [
                'customer_id' => $customer->id,
                'invoice_ids' => $invoices->pluck('id')->values()->all(),
                'gross_debt' => $grossDebt,
                'credit_balance' => $creditBalance,
                'net_amount' => $netAmount,
                'due_date' => $dueDate->toDateString(),
            ]);

            return null;
        }

        return [
            'customer' => $customer,
            'invoices' => $invoices,
            'invoice_ids' => $invoices->pluck('id')->values()->all(),
            'contract_ids' => $invoices->pluck('contract_id')->unique()->values()->all(),
            'gross_debt' => $grossDebt,
            'credit_balance' => $creditBalance,
            'net_amount' => $netAmount,
        ];
    }

    protected function pendingInvoicesForCustomer(Customer $customer): Collection
    {
        return Invoice::query()
            ->with(['contract.customer', 'payments', 'creditNotes'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['active', 'suspended', 'inactive']))
            ->orderBy('due_date')
            ->orderBy('issue_date')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => ! $this->isExcludedByCustomerDeactivation($invoice, $customer))
            ->filter(fn (Invoice $invoice) => $this->pendingAmount($invoice) > 0.0)
            ->values();
    }

    protected function pendingOverdueInvoicesForCustomer(Customer $customer, Carbon $date): Collection
    {
        return Invoice::query()
            ->with(['contract.customer', 'payments', 'creditNotes'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', $date->toDateString())
            ->whereHas('contract', fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['active', 'suspended', 'inactive']))
            ->orderBy('due_date')
            ->orderBy('issue_date')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice) => ! $this->isExcludedByCustomerDeactivation($invoice, $customer))
            ->filter(fn (Invoice $invoice) => $this->pendingAmount($invoice) > 0.0)
            ->values();
    }

    protected function isExcludedByCustomerDeactivation(Invoice $invoice, Customer $customer): bool
    {
        if ($customer->active || ! $customer->deactivated_at || ! $invoice->issue_date) {
            return false;
        }

        return $invoice->issue_date->copy()->startOfMonth()
            ->greaterThanOrEqualTo($customer->deactivated_at->copy()->startOfMonth());
    }

    protected function createReminderForCustomer(Customer $customer, Collection $invoices, array $window, Carbon $reminderDate): array
    {
        $invoices = $invoices->values();
        $invoiceIds = $invoices->pluck('id')->values()->all();
        $reminderKey = $window['reminder_key'] ?? ('customer:' . $customer->id . ':' . $window['rule'] . ':' . $reminderDate->toDateString());
        $existing = PaymentReminder::where('reminder_key', $reminderKey)->first();

        if ($existing) {
            Log::info('payment_reminder_generation_skipped_duplicate', [
                'reminder_id' => $existing->id,
                'customer_id' => $customer->id,
                'reminder_key' => $reminderKey,
            ]);

            if (($window['rule'] ?? null) === 'overdue_debt_reminder') {
                Log::info('overdue_reminder_skipped_duplicate', [
                    'reminder_id' => $existing->id,
                    'customer_id' => $customer->id,
                    'reminder_key' => $reminderKey,
                    'overdue_day' => $window['overdue_day'] ?? null,
                    'due_date' => $window['due_date'] ?? null,
                    'openwa_session_type' => 'yape',
                    'session_id' => config('billing.yape.openwa.session'),
                ]);
            }

            return [
                'status' => 'skipped',
                'reason' => 'Recordatorio ya existe para cliente y vencimiento.',
                'reminder_id' => $existing->id,
                'customer_id' => $customer->id,
                'invoice_ids' => $invoiceIds,
            ];
        }

        $phone = trim((string) $customer->phone);
        $netAmount = $this->money($window['net_amount'] ?? $invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice)));

        if ($netAmount <= 0.009) {
            Log::warning('payment_reminder_generation_failed_invalid_amount', [
                'customer_id' => $customer->id,
                'reminder_key' => $reminderKey,
                'invoice_ids' => $invoiceIds,
                'net_amount' => $netAmount,
                'rule' => $window['rule'] ?? null,
            ]);

            return [
                'status' => 'failed',
                'reason' => 'Monto neto inválido para generar recordatorio.',
                'customer_id' => $customer->id,
                'invoice_ids' => $invoiceIds,
                'rule' => $window['rule'] ?? null,
            ];
        }

        $messagePayload = $this->messagePayloadFor($customer, $invoices, $window, $netAmount);
        $message = $messagePayload['message'];

        $reminder = PaymentReminder::create([
            'invoice_id' => $invoices->first()->id,
            'customer_id' => $customer->id,
            'reminder_date' => $reminderDate->toDateString(),
            'scheduled_for' => $window['scheduled_for'] ?? now(),
            'status' => 'pending',
            'type' => $window['type'],
            'reminder_key' => $reminderKey,
            'message' => $message,
            'channel' => 'openwa',
            'target_phone' => $phone,
            'attempts' => 0,
            'error_message' => null,
            'metadata' => [
                'rule' => $window['rule'],
                'rules' => $window['rules'] ?? [$window['rule']],
                'reminder_key' => $reminderKey,
                'days' => $window['days'],
                'overdue_day' => $window['overdue_day'] ?? null,
                'due_date' => $window['due_date'],
                'invoice_ids' => $invoiceIds,
                'contract_ids' => $window['contract_ids'] ?? $invoices->pluck('contract_id')->unique()->values()->all(),
                'invoice_count' => $invoices->count(),
                'gross_debt' => $this->money($window['gross_debt'] ?? $invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice))),
                'credit_balance' => $this->money($window['credit_balance'] ?? 0),
                'net_amount' => $netAmount,
                'total_pending' => $netAmount,
                'template_key' => $messagePayload['template_key'],
                'template_group' => $messagePayload['template_group'],
                'message_variant' => $messagePayload['message_variant'],
                'invoice_periods' => $messagePayload['invoice_periods'],
                'invoice_details' => $messagePayload['invoice_details'],
                'generated_for' => $window['generated_for'] ?? now()->toDateString(),
                'scheduled_for' => optional($window['scheduled_for'] ?? null)->toDateTimeString(),
                'openwa_session' => 'yape',
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
                'test_mode' => (bool) ($window['test_mode'] ?? false),
            ],
        ]);

        Log::info('payment_reminder_generated', [
            'reminder_id' => $reminder->id,
            'customer_id' => $customer->id,
            'contract_ids' => data_get($reminder->metadata, 'contract_ids'),
            'invoice_ids' => $invoiceIds,
            'target_phone' => $phone,
            'gross_debt' => data_get($reminder->metadata, 'gross_debt'),
            'credit_balance' => data_get($reminder->metadata, 'credit_balance'),
            'net_amount' => $netAmount,
            'due_date' => $window['due_date'],
            'overdue_day' => $window['overdue_day'] ?? null,
            'template_key' => $messagePayload['template_key'],
            'template_group' => $messagePayload['template_group'],
            'message_variant' => $messagePayload['message_variant'],
            'scheduled_for' => optional($window['scheduled_for'] ?? null)->toDateTimeString(),
            'status' => 'pending',
            'rule' => $window['rule'],
            'openwa_session_type' => 'yape',
            'session_id' => config('billing.yape.openwa.session'),
            'openwa_session' => 'yape',
        ]);

        if (($window['rule'] ?? null) === 'overdue_debt_reminder') {
            Log::info('overdue_reminder_generated', [
                'reminder_id' => $reminder->id,
                'rule' => 'overdue_debt_reminder',
                'overdue_day' => $window['overdue_day'] ?? null,
                'customer_id' => $customer->id,
                'contract_ids' => data_get($reminder->metadata, 'contract_ids'),
                'invoice_ids' => $invoiceIds,
                'target_phone' => $phone,
                'gross_debt' => data_get($reminder->metadata, 'gross_debt'),
                'credit_balance' => data_get($reminder->metadata, 'credit_balance'),
                'net_amount' => $netAmount,
                'due_date' => $window['due_date'],
                'template_key' => $messagePayload['template_key'],
                'template_group' => $messagePayload['template_group'],
                'message_variant' => $messagePayload['message_variant'],
                'scheduled_for' => optional($window['scheduled_for'] ?? null)->toDateTimeString(),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
            ]);
        }

        return [
            'status' => 'generated',
            'reminder_id' => $reminder->id,
            'customer_id' => $customer->id,
            'phone' => $phone,
            'invoice_ids' => $invoiceIds,
            'rule' => $window['rule'],
            'scheduled_for' => optional($window['scheduled_for'] ?? null)->toDateTimeString(),
            'template_key' => $messagePayload['template_key'],
            'template_group' => $messagePayload['template_group'],
            'message_variant' => $messagePayload['message_variant'],
        ];
    }

    protected function refreshReminderBeforeSending(PaymentReminder $reminder): array
    {
        $metadata = $reminder->metadata ?? [];
        $customer = $reminder->customer;
        $isOverdue = ($metadata['rule'] ?? null) === 'overdue_debt_reminder';

        if (! $customer) {
            return $this->skipReminder($reminder, 'Cliente no encontrado.', $isOverdue ? 'overdue_reminder_skipped_already_paid' : 'payment_reminder_skipped_already_paid');
        }

        $phone = trim((string) ($reminder->target_phone ?: $customer->phone));

        if ($this->openWaService->chatIdFromPhone($phone) === null) {
            return $this->skipReminder($reminder, 'Cliente sin teléfono válido para OpenWA.', $isOverdue ? 'overdue_reminder_skipped_invalid_phone' : 'payment_reminder_skipped_invalid_phone');
        }

        $invoices = $isOverdue
            ? $this->pendingOverdueInvoicesForCustomer($customer, now()->startOfDay())
            : $this->pendingInvoicesForCustomer($customer);

        if ($invoices->isEmpty()) {
            return $this->skipReminder($reminder, 'Sin deuda pendiente al momento del envío.', $isOverdue ? 'overdue_reminder_skipped_already_paid' : 'payment_reminder_skipped_already_paid');
        }

        $grossDebt = $this->money($invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice)));
        $creditBalance = $this->money($this->availableCreditForInvoices($customer, $invoices));
        $netAmount = $this->money(max($grossDebt - $creditBalance, 0));

        if ($netAmount <= 0.009) {
            return $this->skipReminder($reminder, 'Deuda cubierta por pagos o saldo a favor.', $isOverdue ? 'overdue_reminder_skipped_already_paid' : 'payment_reminder_skipped_credit_covered', [
                'gross_debt' => $grossDebt,
                'credit_balance' => $creditBalance,
                'net_amount' => $netAmount,
            ]);
        }

        $window = [
            'type' => $reminder->type,
            'rule' => $metadata['rule'] ?? $reminder->type,
            'days' => $metadata['days'] ?? null,
            'overdue_day' => $metadata['overdue_day'] ?? null,
            'due_date' => $metadata['due_date'] ?? optional($invoices->first()?->due_date)->toDateString(),
            'reminder_key' => $metadata['reminder_key'] ?? $reminder->reminder_key,
            'gross_debt' => $grossDebt,
            'credit_balance' => $creditBalance,
            'net_amount' => $netAmount,
        ];

        $messagePayload = $this->messagePayloadFor($customer, $invoices, $window, $netAmount);

        $reminder->update([
            'target_phone' => $phone,
            'message' => $messagePayload['message'],
            'metadata' => array_merge($metadata, [
                'invoice_ids' => $invoices->pluck('id')->values()->all(),
                'contract_ids' => $invoices->pluck('contract_id')->unique()->values()->all(),
                'invoice_count' => $invoices->count(),
                'gross_debt' => $grossDebt,
                'credit_balance' => $creditBalance,
                'net_amount' => $netAmount,
                'total_pending' => $netAmount,
                'template_key' => $messagePayload['template_key'],
                'template_group' => $messagePayload['template_group'],
                'message_variant' => $messagePayload['message_variant'],
                'invoice_periods' => $messagePayload['invoice_periods'],
                'invoice_details' => $messagePayload['invoice_details'],
                'refreshed_at' => now()->toDateTimeString(),
                'openwa_session' => 'yape',
            ]),
        ]);

        return ['send' => true];
    }

    protected function skipReminder(PaymentReminder $reminder, string $reason, string $logEvent, array $extra = []): array
    {
        $metadata = array_merge($reminder->metadata ?? [], [
            'skipped_at' => now()->toDateTimeString(),
            'skip_reason' => $reason,
        ], $extra);

        $reminder->update([
            'status' => 'failed',
            'attempts' => (int) config('billing.payment_reminders.max_attempts', 3),
            'error_message' => $reason,
            'metadata' => $metadata,
        ]);

        Log::info($logEvent, array_merge([
            'reminder_id' => $reminder->id,
            'customer_id' => $reminder->customer_id,
            'reason' => $reason,
        ], $extra));

        return [
            'send' => false,
            'reason' => $reason,
        ];
    }

    protected function recoverStaleSendingReminders(Carbon $now): int
    {
        $staleBefore = $now->copy()->subMinutes(15);
        $staleReminders = PaymentReminder::query()
            ->where('channel', 'openwa')
            ->where('status', 'sending')
            ->where('updated_at', '<=', $staleBefore)
            ->get();

        foreach ($staleReminders as $reminder) {
            $attempts = (int) $reminder->attempts + 1;

            $reminder->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'error_message' => 'stale sending recovered',
                'metadata' => array_merge($reminder->metadata ?? [], [
                    'stale_sending_recovered_at' => $now->toDateTimeString(),
                ]),
            ]);

            Log::warning('payment_reminder_stale_sending_recovered', [
                'reminder_id' => $reminder->id,
                'customer_id' => $reminder->customer_id,
                'attempts' => $attempts,
                'updated_at' => optional($reminder->updated_at)->toDateTimeString(),
                'openwa_session_type' => 'yape',
                'session_id' => config('billing.yape.openwa.session'),
            ]);
        }

        return $staleReminders->count();
    }

    protected function messageFor(?Customer $customer, Collection $invoices, array $window, float $netAmount): string
    {
        return $this->messagePayloadFor($customer, $invoices, $window, $netAmount)['message'];
    }

    protected function messagePayloadFor(?Customer $customer, Collection $invoices, array $window, float $netAmount): array
    {
        $context = $this->messageTemplateContext($customer, $invoices, $window, $netAmount);
        $template = $this->selectMessageTemplate($context);

        Log::info('payment_reminder_template_selected', [
            'customer_id' => $context['customer_id'],
            'rule' => $context['rule'],
            'type' => $context['type'],
            'template_key' => $template['key'],
            'template_group' => $template['group'],
            'message_variant' => $template['variant'],
            'overdue_day' => $context['overdue_day'],
            'invoice_count' => $context['invoice_count'],
            'invoice_periods' => $context['invoice_periods'],
            'invoice_details' => $context['invoice_details'],
            'gross_debt' => $context['gross_debt'],
            'credit_balance' => $context['credit_balance'],
            'net_amount' => $context['net_amount'],
        ]);

        $rendered = $this->renderMessageTemplate($template['body'], $context);
        $message = trim($rendered['message']) . "\n\n" . $this->paymentInstructionsMessage($context);

        Log::info('payment_reminder_message_rendered', [
            'customer_id' => $context['customer_id'],
            'rule' => $context['rule'],
            'type' => $context['type'],
            'template_key' => $template['key'],
            'template_group' => $template['group'],
            'message_variant' => $template['variant'],
            'missing_variables' => $rendered['missing_variables'],
            'has_unresolved_placeholders' => $rendered['has_unresolved_placeholders'],
            'message_length' => strlen($message),
        ]);

        return [
            'message' => $message,
            'template_key' => $template['key'],
            'template_group' => $template['group'],
            'message_variant' => $template['variant'],
            'invoice_periods' => $context['invoice_periods'],
            'invoice_details' => $context['invoice_details'],
        ];
    }

    protected function messageTemplateContext(?Customer $customer, Collection $invoices, array $window, float $netAmount): array
    {
        $contractIds = collect($window['contract_ids'] ?? $invoices->pluck('contract_id')->unique()->values()->all())
            ->filter()
            ->values()
            ->all();
        $grossDebt = $this->money(data_get($window, 'gross_debt', $netAmount));
        $creditBalance = $this->money(data_get($window, 'credit_balance', 0));
        $dueDate = $this->formatDueDate($window['due_date'] ?? optional($invoices->first()?->due_date)->toDateString());
        $invoicePeriods = $this->invoicePeriodsText($invoices);
        $invoiceDetails = $this->invoiceDetailsText($invoices);
        $isOverdue = ($window['rule'] ?? null) === 'overdue_debt_reminder' || ($window['type'] ?? null) === 'after_due';
        $customerName = trim((string) ($customer?->full_name ?: 'cliente'));

        return [
            'customer_id' => (int) ($customer?->id ?? 0),
            'customer_name' => $customerName !== '' ? $customerName : 'cliente',
            'net_amount' => $this->moneyText($netAmount),
            'gross_debt' => $this->moneyText($grossDebt),
            'credit_balance' => $this->moneyText($creditBalance),
            'due_date' => $dueDate !== '' ? $dueDate : 'la fecha programada',
            'invoice_periods' => $invoicePeriods !== '' ? $invoicePeriods : 'tu recibo pendiente',
            'invoice_details' => $invoiceDetails !== '' ? $invoiceDetails : 'tu recibo pendiente',
            'invoice_count' => max(1, $invoices->count()),
            'yape_phone' => $this->formatPhoneForMessage((string) config('billing.yape.receiver_phone')),
            'yape_receiver_name' => trim((string) config('billing.yape.receiver_name', 'NORETEL')) ?: 'NORETEL',
            'contract_id' => count($contractIds) === 1 ? (string) $contractIds[0] : 'varios servicios',
            'overdue_day' => max(1, (int) ($window['overdue_day'] ?? $window['days'] ?? 1)),
            'type' => (string) ($window['type'] ?? ($isOverdue ? 'after_due' : 'before_due')),
            'rule' => (string) ($window['rule'] ?? ($isOverdue ? 'overdue_debt_reminder' : 'monthly_debt_distribution')),
            'is_overdue' => $isOverdue,
            'has_credit_balance' => $creditBalance > 0.009 && $grossDebt > ($netAmount + 0.009),
            'has_multiple_invoices' => $invoices->count() > 1,
        ];
    }

    protected function paymentInstructionsMessage(array $context): string
    {
        if (!isset($context['yape_phone']) || !is_string($context['yape_phone']) || trim($context['yape_phone']) === '') {
            return '';
        }

        return "Pago por Yape:\n"
            . "📱 {$context['yape_phone']}\n"
            . "👤 A nombre de: {$context['yape_receiver_name']}\n\n"
            . "Después de pagar, responde a este mismo WhatsApp enviando la captura. "
            . "La validaremos y te enviaremos tu comprobante.\n\n"
            . "Si ya realizaste el pago, puedes omitir este mensaje.\n\n"
            . 'Gracias por confiar en NoreTel.';
    }

    protected function formatPhoneForMessage(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($digits) === 11 && str_starts_with($digits, '51')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            return implode(' ', str_split($digits, 3));
        }

        return $digits;
    }

    protected function invoicePeriodsText(Collection $invoices): string
    {
        return $this->joinForMessage($invoices
            ->map(fn (Invoice $invoice) => $this->invoicePeriodLabel($invoice))
            ->filter()
            ->unique()
            ->values()
            ->all());
    }

    protected function invoiceDetailsText(Collection $invoices): string
    {
        return $this->joinForMessage($invoices
            ->map(fn (Invoice $invoice) => $this->invoicePeriodLabel($invoice) . ' (S/' . $this->moneyText($this->pendingAmount($invoice)) . ')')
            ->filter()
            ->values()
            ->all());
    }

    protected function invoicePeriodLabel(Invoice $invoice): string
    {
        $period = trim((string) $invoice->billing_period);
        $date = null;

        try {
            if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
                $date = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            } elseif ($period !== '') {
                $date = Carbon::parse($period);
            } elseif ($invoice->due_date) {
                $date = Carbon::parse($invoice->due_date);
            }
        } catch (Throwable) {
            $date = $invoice->due_date ? Carbon::parse($invoice->due_date) : null;
        }

        if (! $date) {
            return 'recibo #' . $invoice->id;
        }

        return $this->spanishMonthName((int) $date->month) . ' ' . $date->format('Y');
    }

    protected function spanishMonthName(int $month): string
    {
        return [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ][$month] ?? 'mes';
    }

    protected function joinForMessage(array $items): string
    {
        $items = array_values(array_filter($items, fn ($item) => trim((string) $item) !== ''));
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return (string) $items[0];
        }

        return implode(', ', array_slice($items, 0, -1)) . ' y ' . $items[$count - 1];
    }

    protected function selectMessageTemplate(array $context): array
    {
        $templates = $this->messageTemplatesFor($context);
        $seed = (int) $context['customer_id'];

        if ((bool) $context['is_overdue']) {
            $seed += (int) $context['overdue_day'];
        }

        $index = $seed % count($templates);

        return $templates[$index];
    }

    protected function messageTemplatesFor(array $context): array
    {
        if ((bool) $context['is_overdue']) {
            $overdueDay = (int) $context['overdue_day'];

            if ((bool) $context['has_multiple_invoices']) {
                return [
                    [
                        'key' => 'overdue_multiple_1',
                        'group' => 'overdue',
                        'variant' => 1,
                        'body' => "Hola, {{customer_name}} 👋\nTienes saldos pendientes correspondientes a: {{invoice_details}}.\n\nTotal pendiente: S/{{net_amount}}.\n\nEl pago se aplicará primero al recibo más antiguo.",
                    ],
                    [
                        'key' => 'overdue_multiple_2',
                        'group' => 'overdue',
                        'variant' => 2,
                        'body' => "Buen día, {{customer_name}} 👋\nTu saldo pendiente se compone de: {{invoice_details}}.\n\nTotal por pagar: S/{{net_amount}}.\n\nEl pago será aplicado comenzando por el recibo más antiguo.",
                    ],
                    [
                        'key' => 'overdue_multiple_3',
                        'group' => 'overdue',
                        'variant' => 3,
                        'body' => "Hola, {{customer_name}} 👋\nEste recordatorio corresponde a {{invoice_count}} recibos con saldo pendiente: {{invoice_details}}.\n\nTotal por regularizar: S/{{net_amount}}.\n\nEl pago se distribuirá comenzando por el recibo más antiguo.",
                    ],
                ];
            }

            if ($overdueDay >= 5) {
                return [
                    [
                        'key' => 'overdue_day_5_1',
                        'group' => 'overdue',
                        'variant' => 1,
                        'body' => "Hola, {{customer_name}} 👋\nTu recibo de {{invoice_periods}} por S/{{net_amount}} continúa pendiente.\n\nEn el recibo figura la fecha {{due_date}}. Te agradeceremos regularizarlo para mantener tu servicio al día.",
                    ],
                    [
                        'key' => 'overdue_day_5_2',
                        'group' => 'overdue',
                        'variant' => 2,
                        'body' => "Hola, {{customer_name}} 👋\nTenemos pendiente tu recibo de {{invoice_periods}} por S/{{net_amount}}.\n\nLa fecha indicada en el recibo es {{due_date}}. Agradeceremos que puedas ponerte al día.",
                    ],
                    [
                        'key' => 'overdue_day_5_3',
                        'group' => 'overdue',
                        'variant' => 3,
                        'body' => "Buen día, {{customer_name}} 👋\nTu cuenta registra {{invoice_count}} recibo(s) pendiente(s) por S/{{net_amount}}.\n\nEn el recibo figura la fecha {{due_date}}. Te enviamos este recordatorio para ayudarte a mantener tu servicio al día.",
                    ],
                ];
            }

            if ($overdueDay >= 3) {
                return [
                    [
                        'key' => 'overdue_day_3_1',
                        'group' => 'overdue',
                        'variant' => 1,
                        'body' => "Hola, {{customer_name}} 👋\nAún registras un saldo pendiente de S/{{net_amount}} por tu servicio NoreTel.\n\nEl recibo tiene indicada la fecha {{due_date}}.",
                    ],
                    [
                        'key' => 'overdue_day_3_2',
                        'group' => 'overdue',
                        'variant' => 2,
                        'body' => "Buen día, {{customer_name}} 👋\nTu cuenta mantiene un saldo pendiente de S/{{net_amount}} correspondiente a {{invoice_periods}}.\n\nQueremos ayudarte a mantener tu servicio al día.",
                    ],
                    [
                        'key' => 'overdue_day_3_3',
                        'group' => 'overdue',
                        'variant' => 3,
                        'body' => "Hola, {{customer_name}} 👋\nEste es un recordatorio de tu recibo de {{invoice_periods}} por S/{{net_amount}}, que todavía aparece pendiente.\n\nLa fecha indicada en el recibo es {{due_date}}.",
                    ],
                ];
            }

            return [
                [
                    'key' => 'overdue_day_1_1',
                    'group' => 'overdue',
                    'variant' => 1,
                    'body' => "Hola, {{customer_name}} 👋\nQueremos recordarte que tu recibo de {{invoice_periods}} por S/{{net_amount}} todavía aparece pendiente.\n\nEn el recibo figura la fecha {{due_date}}.",
                ],
                [
                    'key' => 'overdue_day_1_2',
                    'group' => 'overdue',
                    'variant' => 2,
                    'body' => "Buen día, {{customer_name}} 👋\nRevisamos tu cuenta y encontramos pendiente el recibo de {{invoice_periods}} por S/{{net_amount}}.\n\nLa fecha indicada en el recibo es {{due_date}}.",
                ],
                [
                    'key' => 'overdue_day_1_3',
                    'group' => 'overdue',
                    'variant' => 3,
                    'body' => "Hola, {{customer_name}} 👋\nTu cuenta registra {{invoice_count}} recibo(s) pendiente(s) por un total de S/{{net_amount}}.\n\nTe enviamos este recordatorio para que puedas mantener tu servicio al día.",
                ],
            ];
        }

        if ((bool) $context['has_credit_balance']) {
            return [
                [
                    'key' => 'before_due_credit_1',
                    'group' => 'before_due',
                    'variant' => 1,
                    'body' => "Hola, {{customer_name}} 👋\nRegistras un importe de S/{{gross_debt}} y un saldo a favor de S/{{credit_balance}}.\n\nMonto pendiente neto: S/{{net_amount}}.\n\nTe recordamos con anticipación este recibo, cuya fecha indicada es el {{due_date}}.",
                ],
                [
                    'key' => 'before_due_credit_2',
                    'group' => 'before_due',
                    'variant' => 2,
                    'body' => "Buen día, {{customer_name}} 👋\nDespués de aplicar tu saldo a favor de S/{{credit_balance}}, queda pendiente S/{{net_amount}}.\n\nEn tu recibo figura la fecha {{due_date}}.",
                ],
            ];
        }

        if ((bool) $context['has_multiple_invoices']) {
            return [
                [
                    'key' => 'before_due_multiple_1',
                    'group' => 'before_due',
                    'variant' => 1,
                    'body' => "Hola, {{customer_name}} 👋\nTe recordamos que tienes {{invoice_count}} recibo(s) pendiente(s): {{invoice_details}}.\n\nTotal pendiente: S/{{net_amount}}.\n\nEl pago se aplicará comenzando por el recibo más antiguo.",
                ],
                [
                    'key' => 'before_due_multiple_2',
                    'group' => 'before_due',
                    'variant' => 2,
                    'body' => "Buen día, {{customer_name}} 👋\nActualmente tienes {{invoice_count}} recibo(s) pendiente(s), correspondientes a: {{invoice_details}}.\n\nTotal pendiente: S/{{net_amount}}.\n\nLa fecha indicada en el recibo más reciente es {{due_date}}.",
                ],
                [
                    'key' => 'before_due_multiple_3',
                    'group' => 'before_due',
                    'variant' => 3,
                    'body' => "Hola, {{customer_name}} 👋\nEste recordatorio corresponde a los siguientes periodos: {{invoice_details}}.\n\nTotal por regularizar: S/{{net_amount}}.\n\nLos pagos se aplicarán comenzando por el recibo más antiguo.",
                ],
            ];
        }

        return [
            [
                'key' => 'before_due_1',
                'group' => 'before_due',
                'variant' => 1,
                'body' => "Hola, {{customer_name}} 👋\nTe recordamos con anticipación tu recibo de {{invoice_periods}} por S/{{net_amount}}, cuya fecha indicada es el {{due_date}}.",
            ],
            [
                'key' => 'before_due_2',
                'group' => 'before_due',
                'variant' => 2,
                'body' => "Buen día, {{customer_name}} 👋\nQueremos recordarte que tienes pendiente el recibo de {{invoice_periods}} por S/{{net_amount}}.\n\nEn el recibo figura la fecha {{due_date}}.",
            ],
            [
                'key' => 'before_due_3',
                'group' => 'before_due',
                'variant' => 3,
                'body' => "Hola, {{customer_name}} 👋\nTe enviamos este recordatorio para que tengas presente tu recibo de {{invoice_periods}} por S/{{net_amount}}, con fecha indicada {{due_date}}.",
            ],
            [
                'key' => 'before_due_4',
                'group' => 'before_due',
                'variant' => 4,
                'body' => "Hola, {{customer_name}} 👋\nEsperamos que estés teniendo un buen día. Tienes pendiente el recibo de {{invoice_periods}} por S/{{net_amount}}, cuya fecha indicada es el {{due_date}}.",
            ],
        ];
    }

    protected function renderMessageTemplate(string $template, array $context): array
    {
        $missingVariables = [];
        $message = preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($context, &$missingVariables) {
            $key = $matches[1];
            $value = $context[$key] ?? null;

            if ($value === null || $value === '') {
                $missingVariables[] = $key;

                return $this->messageTemplateFallback($key);
            }

            return (string) $value;
        }, $template);

        $hasUnresolvedPlaceholders = preg_match('/{{\s*[a-zA-Z0-9_]+\s*}}/', (string) $message) === 1;
        $missingVariables = array_values(array_unique($missingVariables));

        if (! empty($missingVariables) || $hasUnresolvedPlaceholders) {
            Log::warning('payment_reminder_template_missing_variables', [
                'missing_variables' => $missingVariables,
                'has_unresolved_placeholders' => $hasUnresolvedPlaceholders,
            ]);
        }

        return [
            'message' => (string) $message,
            'missing_variables' => $missingVariables,
            'has_unresolved_placeholders' => $hasUnresolvedPlaceholders,
        ];
    }

    protected function messageTemplateFallback(string $key): string
    {
        return match ($key) {
            'customer_name' => 'cliente',
            'net_amount', 'gross_debt', 'credit_balance' => '0.00',
            'due_date' => 'la fecha programada',
            'invoice_count', 'overdue_day' => '1',
            'contract_id' => 'servicio',
            'invoice_periods', 'invoice_details' => 'tu recibo pendiente',
            default => '',
        };
    }

    protected function availableCreditForInvoices(Customer $customer, Collection $invoices): float
    {
        if (! class_exists(CustomerCreditService::class)) {
            return 0.0;
        }

        $creditService = app(CustomerCreditService::class);

        return $this->money($invoices
            ->pluck('contract_id')
            ->unique()
            ->values()
            ->sum(fn ($contractId) => (float) $creditService->getBalance($customer->id, (int) $contractId)));
    }

    protected function pendingAmount(Invoice $invoice): float
    {
        $paidAmount = $invoice->relationLoaded('payments')
            ? $invoice->payments->where('status', 'completed')->sum('amount')
            : $invoice->payments()->where('status', 'completed')->sum('amount');

        $creditNoteAmount = $invoice->relationLoaded('creditNotes')
            ? $invoice->creditNotes->where('status', 'applied')->sum('amount')
            : $invoice->creditNotes()->where('status', 'applied')->sum('amount');

        return $this->money(max((float) $invoice->total_amount - (float) $paidAmount - (float) $creditNoteAmount, 0));
    }

    protected function money($amount): float
    {
        return (float) number_format(round((float) $amount, 2), 2, '.', '');
    }

    protected function moneyText($amount): string
    {
        return number_format($this->money($amount), 2, '.', '');
    }

    protected function scheduledDateTimeFor(Carbon $date, int $dailyIndex): Carbon
    {
        [$hour, $minute] = $this->timeParts((string) config('billing.payment_reminders.allowed_start', '09:00'));

        return $date->copy()
            ->setTime($hour, $minute)
            ->addMinutes($dailyIndex);
    }

    protected function withinAllowedSendWindow(Carbon $dateTime): bool
    {
        [$startHour, $startMinute] = $this->timeParts((string) config('billing.payment_reminders.allowed_start', '09:00'));
        [$endHour, $endMinute] = $this->timeParts((string) config('billing.payment_reminders.allowed_end', '18:00'));
        $start = $dateTime->copy()->setTime($startHour, $startMinute);
        $end = $dateTime->copy()->setTime($endHour, $endMinute);

        return $dateTime->betweenIncluded($start, $end);
    }

    protected function applyRandomDelay(): void
    {
        $min = max(0, (int) config('billing.payment_reminders.delay_min_seconds', 12));
        $max = max($min, (int) config('billing.payment_reminders.delay_max_seconds', 25));
        $delay = random_int($min, $max);

        Log::info('payment_reminder_delay_applied', [
            'delay_seconds' => $delay,
            'min_seconds' => $min,
            'max_seconds' => $max,
        ]);

        if ($delay > 0) {
            sleep($delay);
        }
    }

    protected function timeParts(string $time): array
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return [8, 0];
        }

        return [
            min(max((int) $matches[1], 0), 23),
            min(max((int) $matches[2], 0), 59),
        ];
    }

    protected function formatDueDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    protected function summary(string $message = ''): array
    {
        return [
            'success' => false,
            'message' => $message,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];
    }
}
