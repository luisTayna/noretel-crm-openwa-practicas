-- NoreTEL system-wide audit for canonical customers and multiple services.
-- SAFE / READ ONLY: this file contains USE, SET, and SELECT statements only.
-- It explicitly selects the NoreTEL production database to avoid information_schema.

USE `noretel_noretelbd`;

SET @billing_period = 'julio 2026';
SET @period_start = '2026-07-01';
SET @period_end = '2026-08-01';

-- 00. Selected database and server.
SELECT
    'noretel_noretelbd' AS audited_database,
    DATABASE() AS phpmyadmin_session_database,
    VERSION() AS database_version,
    NOW() AS audited_at;

-- 01. Global customer and contract totals.
SELECT
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`customers`) AS total_customers,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`customers` WHERE active = 1) AS active_customers,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts`) AS total_contracts,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` WHERE status = 'active') AS active_contracts,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`invoices`) AS total_invoices,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`payments` WHERE status = 'completed') AS completed_payments;

-- 02. Every duplicated DNI/RUC in the system.
SELECT
    REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document,
    COUNT(*) AS customer_count,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(CONCAT_WS(' ', first_name, last_name) ORDER BY id SEPARATOR ' | ') AS customer_names
FROM `noretel_noretelbd`.`customers`
WHERE identity_document IS NOT NULL
  AND TRIM(identity_document) <> ''
GROUP BY REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '')
HAVING COUNT(*) > 1
ORDER BY customer_count DESC, normalized_document;

-- 03. Detailed records for every duplicated DNI/RUC.
SELECT
    d.normalized_document,
    c.id AS customer_id,
    c.customer_type,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    c.email,
    c.phone,
    c.active,
    c.customer_status,
    c.user_id,
    c.created_at,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` ct WHERE ct.customer_id = c.id) AS contracts,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` ct WHERE ct.customer_id = c.id AND ct.status = 'active') AS active_contracts,
    (
        SELECT COUNT(*)
        FROM `noretel_noretelbd`.`invoices` i
        INNER JOIN `noretel_noretelbd`.`contracts` ct ON ct.id = i.contract_id
        WHERE ct.customer_id = c.id
    ) AS invoices,
    (
        SELECT COUNT(*)
        FROM `noretel_noretelbd`.`payments` pay
        INNER JOIN `noretel_noretelbd`.`invoices` i ON i.id = pay.invoice_id
        INNER JOIN `noretel_noretelbd`.`contracts` ct ON ct.id = i.contract_id
        WHERE ct.customer_id = c.id AND pay.status = 'completed'
    ) AS completed_payments,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`yape_payment_intents` ypi WHERE ypi.customer_id = c.id) AS yape_intents,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`customer_credit_movements` cm WHERE cm.customer_id = c.id) AS credit_movements
FROM `noretel_noretelbd`.`customers` c
INNER JOIN (
    SELECT
        REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document
    FROM `noretel_noretelbd`.`customers`
    WHERE identity_document IS NOT NULL AND TRIM(identity_document) <> ''
    GROUP BY REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '')
    HAVING COUNT(*) > 1
) d ON d.normalized_document = REPLACE(REPLACE(REPLACE(TRIM(c.identity_document), ' ', ''), '-', ''), '.', '')
ORDER BY d.normalized_document, c.id;

-- 04. Every customer that already has multiple contracts/services.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    COUNT(DISTINCT ct.id) AS contract_count,
    SUM(ct.status = 'active') AS active_contract_count,
    GROUP_CONCAT(
        DISTINCT CONCAT(
            'Contrato #', ct.id,
            ' | ', COALESCE(p.name, 'Sin plan'),
            ' | S/', FORMAT(COALESCE(ct.final_price, p.price, 0), 2),
            ' | ', ct.status
        )
        ORDER BY ct.id SEPARATOR ' || '
    ) AS services
FROM `noretel_noretelbd`.`customers` c
INNER JOIN `noretel_noretelbd`.`contracts` ct ON ct.customer_id = c.id
LEFT JOIN `noretel_noretelbd`.`plans` p ON p.id = ct.plan_id
GROUP BY c.id, c.first_name, c.last_name, c.identity_document
HAVING COUNT(DISTINCT ct.id) > 1
ORDER BY active_contract_count DESC, contract_count DESC, c.id;

-- 05. Contract and plan inventory for all customers.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    ct.id AS contract_id,
    ct.status AS contract_status,
    ct.start_date,
    ct.end_date,
    ct.plan_id,
    p.name AS plan_name,
    p.price AS catalog_price,
    ct.final_price,
    ct.node_id,
    ct.assigned_ip,
    ct.vlan
FROM `noretel_noretelbd`.`customers` c
INNER JOIN `noretel_noretelbd`.`contracts` ct ON ct.customer_id = c.id
LEFT JOIN `noretel_noretelbd`.`plans` p ON p.id = ct.plan_id
ORDER BY c.id, ct.id;

-- 06. Financial summary for every customer.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    COUNT(DISTINCT ct.id) AS contracts,
    COUNT(DISTINCT i.id) AS invoices,
    ROUND(COALESCE(SUM(CASE WHEN i.id IS NOT NULL THEN i.total_amount ELSE 0 END), 0), 2) AS issued_total,
    ROUND(COALESCE((
        SELECT SUM(pay.amount)
        FROM `noretel_noretelbd`.`payments` pay
        INNER JOIN `noretel_noretelbd`.`invoices` pi ON pi.id = pay.invoice_id
        INNER JOIN `noretel_noretelbd`.`contracts` pct ON pct.id = pi.contract_id
        WHERE pct.customer_id = c.id AND pay.status = 'completed'
    ), 0), 2) AS paid_total,
    ROUND(COALESCE((
        SELECT SUM(
            inv.total_amount
            - COALESCE((SELECT SUM(pp.amount) FROM `noretel_noretelbd`.`payments` pp WHERE pp.invoice_id = inv.id AND pp.status = 'completed'), 0)
            - COALESCE((SELECT SUM(cn.amount) FROM `noretel_noretelbd`.`credit_notes` cn WHERE cn.invoice_id = inv.id AND cn.status = 'applied'), 0)
        )
        FROM `noretel_noretelbd`.`invoices` inv
        INNER JOIN `noretel_noretelbd`.`contracts` ict ON ict.id = inv.contract_id
        WHERE ict.customer_id = c.id
          AND inv.status IN ('pending', 'partial', 'overdue')
    ), 0), 2) AS pending_debt
FROM `noretel_noretelbd`.`customers` c
LEFT JOIN `noretel_noretelbd`.`contracts` ct ON ct.customer_id = c.id
LEFT JOIN `noretel_noretelbd`.`invoices` i ON i.contract_id = ct.id
GROUP BY c.id, c.first_name, c.last_name, c.identity_document
ORDER BY contracts DESC, c.id;

-- 07. Active contracts missing a July 2026 invoice after the monthly cron.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    ct.id AS contract_id,
    p.name AS plan_name,
    ct.final_price
FROM `noretel_noretelbd`.`contracts` ct
INNER JOIN `noretel_noretelbd`.`customers` c ON c.id = ct.customer_id
LEFT JOIN `noretel_noretelbd`.`plans` p ON p.id = ct.plan_id
WHERE ct.status = 'active'
  AND NOT EXISTS (
      SELECT 1
      FROM `noretel_noretelbd`.`invoices` i
      WHERE i.contract_id = ct.id
        AND (
            LOWER(TRIM(i.billing_period)) = LOWER(@billing_period)
            OR (i.issue_date >= @period_start AND i.issue_date < @period_end)
        )
        AND i.status NOT IN ('cancelled', 'void')
  )
ORDER BY c.id, ct.id;

-- 08. Customers with multiple contracts and their current pending invoices.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    ct.id AS contract_id,
    p.name AS plan_name,
    i.id AS invoice_id,
    i.invoice_number,
    i.billing_period,
    i.status AS invoice_status,
    i.total_amount,
    ROUND(
        i.total_amount
        - COALESCE((SELECT SUM(pay.amount) FROM `noretel_noretelbd`.`payments` pay WHERE pay.invoice_id = i.id AND pay.status = 'completed'), 0)
        - COALESCE((SELECT SUM(cn.amount) FROM `noretel_noretelbd`.`credit_notes` cn WHERE cn.invoice_id = i.id AND cn.status = 'applied'), 0),
        2
    ) AS pending_amount
FROM `noretel_noretelbd`.`customers` c
INNER JOIN `noretel_noretelbd`.`contracts` ct ON ct.customer_id = c.id
LEFT JOIN `noretel_noretelbd`.`plans` p ON p.id = ct.plan_id
INNER JOIN `noretel_noretelbd`.`invoices` i ON i.contract_id = ct.id
WHERE i.status IN ('pending', 'partial', 'overdue')
  AND c.id IN (
      SELECT customer_id
      FROM `noretel_noretelbd`.`contracts`
      GROUP BY customer_id
      HAVING COUNT(*) > 1
  )
ORDER BY c.id, i.due_date, ct.id, i.id;

-- 09. Duplicate customer groups that would collide on the same router after a merge.
SELECT
    d.normalized_document,
    rc.router_id,
    COUNT(DISTINCT c.id) AS duplicate_customers_on_router,
    GROUP_CONCAT(DISTINCT c.id ORDER BY c.id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(DISTINCT rc.contract_id ORDER BY rc.contract_id SEPARATOR ', ') AS contract_ids
FROM `noretel_noretelbd`.`customers` c
INNER JOIN (
    SELECT
        REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document
    FROM `noretel_noretelbd`.`customers`
    WHERE identity_document IS NOT NULL AND TRIM(identity_document) <> ''
    GROUP BY REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '')
    HAVING COUNT(*) > 1
) d ON d.normalized_document = REPLACE(REPLACE(REPLACE(TRIM(c.identity_document), ' ', ''), '-', ''), '.', '')
INNER JOIN `noretel_noretelbd`.`router_customer` rc ON rc.customer_id = c.id
GROUP BY d.normalized_document, rc.router_id
HAVING COUNT(DISTINCT c.id) > 1
ORDER BY d.normalized_document, rc.router_id;

-- 10. Duplicate groups with more than one portal user account.
SELECT
    REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document,
    COUNT(DISTINCT user_id) AS portal_user_count,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(DISTINCT user_id ORDER BY user_id SEPARATOR ', ') AS user_ids
FROM `noretel_noretelbd`.`customers`
WHERE identity_document IS NOT NULL
  AND TRIM(identity_document) <> ''
  AND user_id IS NOT NULL
GROUP BY REPLACE(REPLACE(REPLACE(TRIM(identity_document), ' ', ''), '-', ''), '.', '')
HAVING COUNT(*) > 1 OR COUNT(DISTINCT user_id) > 1
ORDER BY portal_user_count DESC, normalized_document;

-- 11. Yape status summary across the complete system.
SELECT
    status,
    payment_mode,
    COUNT(*) AS intent_count,
    ROUND(SUM(expected_amount), 2) AS expected_amount_total,
    MIN(created_at) AS oldest_intent,
    MAX(created_at) AS newest_intent
FROM `noretel_noretelbd`.`yape_payment_intents`
GROUP BY status, payment_mode
ORDER BY status, payment_mode;

-- 12. Direct foreign keys to customers that must be considered during merging.
SELECT
    kcu.table_name,
    kcu.column_name,
    kcu.constraint_name,
    rc.update_rule,
    rc.delete_rule
FROM information_schema.key_column_usage kcu
INNER JOIN information_schema.referential_constraints rc
    ON rc.constraint_schema = kcu.constraint_schema
   AND rc.constraint_name = kcu.constraint_name
WHERE kcu.constraint_schema = 'noretel_noretelbd'
  AND kcu.referenced_table_name = 'customers'
  AND kcu.referenced_column_name = 'id'
ORDER BY kcu.table_name, kcu.column_name;

-- 13. Existing indexes on customer identity and router ownership.
SELECT
    table_name,
    index_name,
    non_unique,
    seq_in_index,
    column_name
FROM information_schema.statistics
WHERE table_schema = 'noretel_noretelbd'
  AND table_name IN ('customers', 'router_customer')
ORDER BY table_name, index_name, seq_in_index;

