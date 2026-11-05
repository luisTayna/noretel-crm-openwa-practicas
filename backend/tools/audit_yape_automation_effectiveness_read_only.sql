-- NoreTEL audit: did the Yape payment automation actually work since 2026-06-26?
-- SAFE / READ ONLY: this file contains USE, SET, and SELECT statements only.
-- Run it in phpMyAdmin (cPanel) against the production database.
--
-- Evidence model used by this audit:
--   * yape_payment_intents.status                        -> funnel outcome per intent.
--   * yape_payment_intents.payment_id                    -> intent actually became money in the CRM.
--   * yape_payment_intents.validation_result.manual_override
--                                                        -> TRUE means a human closed it from the panel
--                                                           (manual validation shipped 2026-06-30),
--                                                           so it does NOT count as automation working.
--   * payments.payment_details.yape_intent_id            -> payment row created by the Yape flow.
--                                                           Do NOT use payment_details.source here:
--                                                           only registerYapeMultiInvoicePayment() writes it,
--                                                           so single-invoice Yape payments would be missed.
--   * payments.payment_details.cashier_context           -> 'automatic_yape_validation' when reconciliation
--                                                           closed it alone, 'manual_yape_validation' when a
--                                                           human closed it from the panel.

USE `noretel_noretelbd`;

-- Automation cutoff requested by the business.
SET @cutoff = '2026-06-26 00:00:00';
-- Earlier band, when the automation was first declared functional (commit 86b798d, 2026-06-02).
SET @early_start = '2026-06-02 00:00:00';
-- Manual Yape validation shipped on this date (commit a854820).
SET @manual_feature = '2026-06-30 00:00:00';

-- 00. Environment stamp.
SELECT
    'noretel_noretelbd'    AS audited_database,
    DATABASE()             AS phpmyadmin_session_database,
    VERSION()              AS database_version,
    NOW()                  AS audited_at,
    @cutoff                AS automation_cutoff;

-- 01. Headline funnel since the cutoff.
--     conversion_rate = share of intents that ended as a real payment.
SELECT
    COUNT(*)                                                              AS intents_created,
    SUM(status = 'validated')                                             AS validated,
    SUM(status = 'observed')                                              AS observed,
    SUM(status = 'cancelled')                                             AS cancelled,
    SUM(status = 'waiting_client_evidence')                               AS stuck_waiting_client,
    SUM(status = 'waiting_receiver_event')                                AS stuck_waiting_receiver,
    SUM(payment_id IS NOT NULL)                                           AS became_payment,
    ROUND(100 * SUM(payment_id IS NOT NULL) / NULLIF(COUNT(*), 0), 2)     AS conversion_rate_pct
FROM `noretel_noretelbd`.`yape_payment_intents`
WHERE created_at >= @cutoff;

-- 02. The decisive split: automatic close vs human rescue.
--     If manual_pct is high, the pipeline exists but the automation is NOT doing the work.
SELECT
    COUNT(*)                                                                AS validated_intents,
    SUM(is_manual = 0)                                                      AS closed_automatically,
    SUM(is_manual = 1)                                                      AS closed_by_human,
    ROUND(100 * SUM(is_manual = 0) / NULLIF(COUNT(*), 0), 2)                AS automatic_pct,
    ROUND(100 * SUM(is_manual = 1) / NULLIF(COUNT(*), 0), 2)                AS manual_pct
FROM (
    SELECT
        CASE
            WHEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(validation_result, '$.manual_override')), 'false')
                 IN ('true', '1') THEN 1
            ELSE 0
        END AS is_manual
    FROM `noretel_noretelbd`.`yape_payment_intents`
    WHERE created_at >= @cutoff
      AND status = 'validated'
) AS v;

-- 03. Month-by-month trend: is the automation improving, flat, or degrading?
SELECT
    DATE_FORMAT(created_at, '%Y-%m')                                        AS month,
    COUNT(*)                                                                AS intents,
    SUM(status = 'validated')                                               AS validated,
    SUM(status = 'observed')                                                AS observed,
    SUM(status IN ('waiting_client_evidence', 'waiting_receiver_event'))    AS never_finished,
    SUM(payment_id IS NOT NULL)                                             AS became_payment,
    ROUND(100 * SUM(payment_id IS NOT NULL) / NULLIF(COUNT(*), 0), 2)       AS conversion_rate_pct,
    SUM(
        CASE
            WHEN status = 'validated'
             AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(validation_result, '$.manual_override')), 'false')
                 IN ('true', '1') THEN 1
            ELSE 0
        END
    )                                                                       AS closed_by_human
FROM `noretel_noretelbd`.`yape_payment_intents`
WHERE created_at >= @early_start
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month;

-- 04. Speed: how long an intent took to become a validated payment.
--     The business case for the automation is minutes, not hours.
SELECT
    COUNT(*)                                                                        AS validated_with_timestamp,
    ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, validated_at)), 1)                  AS avg_minutes,
    MIN(TIMESTAMPDIFF(MINUTE, created_at, validated_at))                            AS min_minutes,
    MAX(TIMESTAMPDIFF(MINUTE, created_at, validated_at))                            AS max_minutes,
    SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) <= 5)                       AS within_5_min,
    SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) BETWEEN 6 AND 60)           AS within_1_hour,
    SUM(TIMESTAMPDIFF(MINUTE, created_at, validated_at) > 60)                       AS over_1_hour
FROM `noretel_noretelbd`.`yape_payment_intents`
WHERE created_at >= @cutoff
  AND status = 'validated'
  AND validated_at IS NOT NULL;

-- 05. Share of the collection operation that the automation really carries,
--     and how much of the Yape work a human still has to close by hand.
SELECT
    DATE_FORMAT(p.payment_date, '%Y-%m')                                            AS month,
    COUNT(*)                                                                        AS completed_payments,
    SUM(is_yape)                                                                    AS yape_payments,
    SUM(is_yape_auto)                                                               AS yape_automatic,
    SUM(is_yape_manual)                                                             AS yape_manual,
    ROUND(100 * SUM(is_yape) / NULLIF(COUNT(*), 0), 2)                              AS yape_share_pct,
    ROUND(100 * SUM(is_yape_auto) / NULLIF(SUM(is_yape), 0), 2)                     AS automatic_pct,
    ROUND(SUM(p.amount), 2)                                                         AS total_amount,
    ROUND(SUM(CASE WHEN is_yape = 1 THEN p.amount ELSE 0 END), 2)                   AS yape_amount
FROM (
    SELECT
        payment_date,
        amount,
        -- Every Yape payment carries yape_intent_id. The `source` key only exists on
        -- multi-invoice payments, so it would silently undercount the single-invoice ones.
        CASE WHEN JSON_EXTRACT(payment_details, '$.yape_intent_id') IS NOT NULL
             THEN 1 ELSE 0 END AS is_yape,
        CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.cashier_context')) = 'automatic_yape_validation'
             THEN 1 ELSE 0 END AS is_yape_auto,
        CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.cashier_context')) = 'manual_yape_validation'
             THEN 1 ELSE 0 END AS is_yape_manual
    FROM `noretel_noretelbd`.`payments`
    WHERE status = 'completed'
      AND payment_date >= DATE(@early_start)
) AS p
GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
ORDER BY month;

-- 06. Why it failed, when it failed. Top observed reasons.
SELECT
    LEFT(COALESCE(observed_reason, '(sin motivo registrado)'), 160)                  AS observed_reason,
    COUNT(*)                                                                        AS occurrences,
    MIN(created_at)                                                                 AS first_seen,
    MAX(created_at)                                                                 AS last_seen
FROM `noretel_noretelbd`.`yape_payment_intents`
WHERE created_at >= @cutoff
  AND status = 'observed'
GROUP BY LEFT(COALESCE(observed_reason, '(sin motivo registrado)'), 160)
ORDER BY occurrences DESC;

-- 07. Abandoned intents: opened and never closed by anyone.
--     These are the silent cost of the automation (clients left hanging).
SELECT
    status,
    COUNT(*)                                                                        AS intents,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())), 1)                           AS avg_age_hours,
    SUM(client_evidence_id IS NULL)                                                 AS without_client_capture,
    SUM(receiver_event_id IS NULL)                                                  AS without_receiver_event
FROM `noretel_noretelbd`.`yape_payment_intents`
WHERE created_at >= @cutoff
  AND status IN ('waiting_client_evidence', 'waiting_receiver_event')
GROUP BY status
ORDER BY intents DESC;

-- 08. Pipeline health on the input side: WhatsApp captures received vs usable.
SELECT
    status                                                                          AS client_evidence_status,
    COUNT(*)                                                                        AS evidences,
    SUM(yape_payment_intent_id IS NULL)                                             AS orphan_evidences,
    SUM(parsed_amount IS NULL)                                                      AS amount_not_parsed,
    SUM(operation_code IS NULL)                                                     AS operation_code_not_parsed,
    ROUND(AVG(confidence), 2)                                                       AS avg_confidence
FROM `noretel_noretelbd`.`yape_client_evidences`
WHERE created_at >= @cutoff
GROUP BY status
ORDER BY evidences DESC;

-- 09. Pipeline health on the receiver side: Yape notifications captured vs matched.
SELECT
    status                                                                          AS receiver_event_status,
    COUNT(*)                                                                        AS events,
    SUM(yape_payment_intent_id IS NULL)                                             AS unmatched_events,
    SUM(parsed_amount IS NULL)                                                      AS amount_not_parsed,
    ROUND(AVG(confidence), 2)                                                       AS avg_confidence
FROM `noretel_noretelbd`.`yape_receiver_events`
WHERE created_at >= @cutoff
GROUP BY status
ORDER BY events DESC;

-- 10. Adoption: how many real customers the automation actually served.
SELECT
    COUNT(DISTINCT i.customer_id)                                                   AS customers_with_intents,
    COUNT(DISTINCT CASE WHEN i.payment_id IS NOT NULL THEN i.customer_id END)       AS customers_paid_via_yape,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` WHERE status = 'active')  AS active_contracts
FROM `noretel_noretelbd`.`yape_payment_intents` i
WHERE i.created_at >= @cutoff;

-- 11. Before vs after the cutoff, on the same 54-day span, for the payment mix.
--     Answers "did anything change on 2026-06-26" without trusting the funnel alone.
SELECT
    CASE WHEN payment_date < DATE(@cutoff) THEN 'before_cutoff' ELSE 'after_cutoff' END AS band,
    COUNT(*)                                                                            AS completed_payments,
    SUM(
        CASE WHEN JSON_EXTRACT(payment_details, '$.yape_intent_id') IS NOT NULL
             THEN 1 ELSE 0 END
    )                                                                                   AS yape_payments,
    SUM(
        CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$.cashier_context')) = 'automatic_yape_validation'
             THEN 1 ELSE 0 END
    )                                                                                   AS yape_automatic,
    ROUND(SUM(amount), 2)                                                               AS total_amount
FROM `noretel_noretelbd`.`payments`
WHERE status = 'completed'
  AND payment_date >= DATE_SUB(DATE(@cutoff), INTERVAL 54 DAY)
  AND payment_date <  DATE_ADD(DATE(@cutoff), INTERVAL 54 DAY)
GROUP BY CASE WHEN payment_date < DATE(@cutoff) THEN 'before_cutoff' ELSE 'after_cutoff' END
ORDER BY band DESC;

-- 12. Raw sample of the last 40 intents, to eyeball what the numbers above summarize.
SELECT
    i.id,
    i.intent_number,
    i.created_at,
    i.validated_at,
    i.status,
    i.expected_amount,
    i.match_score,
    i.payment_id,
    CASE
        WHEN COALESCE(JSON_UNQUOTE(JSON_EXTRACT(i.validation_result, '$.manual_override')), 'false')
             IN ('true', '1') THEN 'human'
        WHEN i.payment_id IS NOT NULL THEN 'automation'
        ELSE '-'
    END                                                                             AS closed_by,
    LEFT(COALESCE(i.observed_reason, ''), 120)                                      AS observed_reason,
    CONCAT_WS(' ', c.first_name, c.last_name)                                       AS customer_name
FROM `noretel_noretelbd`.`yape_payment_intents` i
LEFT JOIN `noretel_noretelbd`.`customers` c ON c.id = i.customer_id
WHERE i.created_at >= @cutoff
ORDER BY i.id DESC
LIMIT 40;
