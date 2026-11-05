-- NoreTEL system-wide candidate duplicate audit.
-- SAFE / READ ONLY: USE and SELECT statements only.
-- This detects candidates by normalized DNI/RUC, phone, and exact normalized name.

USE `noretel_noretelbd`;

-- 01. Repeated exact normalized names, including customers without DNI or phone.
WITH normalized_customers AS (
    SELECT
        c.id,
        c.first_name,
        c.last_name,
        c.identity_document,
        c.phone,
        c.active,
        c.customer_status,
        c.created_at,
        LOWER(
            TRIM(
                REPLACE(
                    REPLACE(
                        REPLACE(CONCAT_WS(' ', c.first_name, c.last_name), '  ', ' '),
                        '-', ' '
                    ),
                    '.', ''
                )
            )
        ) AS normalized_name
    FROM `noretel_noretelbd`.`customers` c
)
SELECT
    normalized_name,
    COUNT(*) AS customer_count,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(CONCAT_WS(' ', first_name, last_name) ORDER BY id SEPARATOR ' | ') AS names,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(identity_document), ''), 'SIN DNI/RUC') ORDER BY id SEPARATOR ' | ') AS documents,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(phone), ''), 'SIN CELULAR') ORDER BY id SEPARATOR ' | ') AS phones
FROM normalized_customers
WHERE normalized_name <> ''
GROUP BY normalized_name
HAVING COUNT(*) > 1
ORDER BY customer_count DESC, normalized_name;

-- 02. Repeated normalized phone numbers.
WITH normalized_customers AS (
    SELECT
        c.id,
        c.first_name,
        c.last_name,
        c.identity_document,
        c.phone,
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(TRIM(c.phone), ' ', ''),
                        '-', ''
                    ),
                    '+', ''
                ),
                '(', ''
            ),
            ')', ''
        ) AS normalized_phone
    FROM `noretel_noretelbd`.`customers` c
)
SELECT
    normalized_phone,
    COUNT(*) AS customer_count,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(CONCAT_WS(' ', first_name, last_name) ORDER BY id SEPARATOR ' | ') AS customer_names,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(identity_document), ''), 'SIN DNI/RUC') ORDER BY id SEPARATOR ' | ') AS documents
FROM normalized_customers
WHERE normalized_phone <> ''
GROUP BY normalized_phone
HAVING COUNT(*) > 1
ORDER BY customer_count DESC, normalized_phone;

-- 03. Candidate duplicate pairs with a confidence level and match reasons.
WITH normalized_customers AS (
    SELECT
        c.id,
        CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
        c.identity_document,
        c.phone,
        c.active,
        c.customer_status,
        LOWER(
            TRIM(
                REPLACE(
                    REPLACE(
                        REPLACE(CONCAT_WS(' ', c.first_name, c.last_name), '  ', ' '),
                        '-', ' '
                    ),
                    '.', ''
                )
            )
        ) AS normalized_name,
        REPLACE(REPLACE(REPLACE(TRIM(c.identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document,
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(TRIM(c.phone), ' ', ''),
                        '-', ''
                    ),
                    '+', ''
                ),
                '(', ''
            ),
            ')', ''
        ) AS normalized_phone
    FROM `noretel_noretelbd`.`customers` c
)
SELECT
    CASE
        WHEN a.normalized_document <> '' AND a.normalized_document = b.normalized_document
             AND a.normalized_phone <> '' AND a.normalized_phone = b.normalized_phone
             AND a.normalized_name = b.normalized_name THEN 'VERY HIGH'
        WHEN a.normalized_document <> '' AND a.normalized_document = b.normalized_document
             AND a.normalized_name = b.normalized_name THEN 'HIGH'
        WHEN a.normalized_phone <> '' AND a.normalized_phone = b.normalized_phone
             AND a.normalized_name = b.normalized_name THEN 'HIGH'
        WHEN a.normalized_document <> '' AND a.normalized_document = b.normalized_document THEN 'REVIEW DOCUMENT CONFLICT'
        WHEN a.normalized_phone <> '' AND a.normalized_phone = b.normalized_phone THEN 'MEDIUM'
        WHEN a.normalized_name <> '' AND a.normalized_name = b.normalized_name THEN 'MEDIUM'
        ELSE 'LOW'
    END AS confidence,
    CONCAT_WS(', ',
        IF(a.normalized_document <> '' AND a.normalized_document = b.normalized_document, 'SAME DNI/RUC', NULL),
        IF(a.normalized_phone <> '' AND a.normalized_phone = b.normalized_phone, 'SAME PHONE', NULL),
        IF(a.normalized_name <> '' AND a.normalized_name = b.normalized_name, 'SAME NAME', NULL)
    ) AS match_reasons,
    a.id AS customer_a_id,
    a.customer_name AS customer_a_name,
    NULLIF(a.identity_document, '') AS customer_a_document,
    NULLIF(a.phone, '') AS customer_a_phone,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` ca WHERE ca.customer_id = a.id) AS customer_a_contracts,
    b.id AS customer_b_id,
    b.customer_name AS customer_b_name,
    NULLIF(b.identity_document, '') AS customer_b_document,
    NULLIF(b.phone, '') AS customer_b_phone,
    (SELECT COUNT(*) FROM `noretel_noretelbd`.`contracts` cb WHERE cb.customer_id = b.id) AS customer_b_contracts
FROM normalized_customers a
INNER JOIN normalized_customers b ON b.id > a.id
WHERE (
        a.normalized_document <> ''
        AND a.normalized_document = b.normalized_document
    )
    OR (
        a.normalized_phone <> ''
        AND a.normalized_phone = b.normalized_phone
    )
    OR (
        a.normalized_name <> ''
        AND a.normalized_name = b.normalized_name
    )
ORDER BY
    FIELD(confidence, 'VERY HIGH', 'HIGH', 'MEDIUM', 'REVIEW DOCUMENT CONFLICT', 'LOW'),
    a.id,
    b.id;

-- 04. Customers with missing DNI/RUC, including their contract and plan.
SELECT
    c.id AS customer_id,
    CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
    c.identity_document,
    c.phone,
    c.active,
    c.customer_status,
    ct.id AS contract_id,
    ct.status AS contract_status,
    p.name AS plan_name,
    ct.final_price,
    c.created_at
FROM `noretel_noretelbd`.`customers` c
LEFT JOIN `noretel_noretelbd`.`contracts` ct ON ct.customer_id = c.id
LEFT JOIN `noretel_noretelbd`.`plans` p ON p.id = ct.plan_id
WHERE c.identity_document IS NULL OR TRIM(c.identity_document) = ''
ORDER BY customer_name, c.id, ct.id;

-- 05. Exact-name groups where at least one customer has no DNI/RUC.
WITH normalized_customers AS (
    SELECT
        c.id,
        CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
        c.identity_document,
        c.phone,
        LOWER(
            TRIM(
                REPLACE(
                    REPLACE(
                        REPLACE(CONCAT_WS(' ', c.first_name, c.last_name), '  ', ' '),
                        '-', ' '
                    ),
                    '.', ''
                )
            )
        ) AS normalized_name
    FROM `noretel_noretelbd`.`customers` c
)
SELECT
    n.normalized_name,
    COUNT(*) AS customer_count,
    SUM(n.identity_document IS NULL OR TRIM(n.identity_document) = '') AS customers_without_document,
    GROUP_CONCAT(n.id ORDER BY n.id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(n.customer_name ORDER BY n.id SEPARATOR ' | ') AS names,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(n.identity_document), ''), 'SIN DNI/RUC') ORDER BY n.id SEPARATOR ' | ') AS documents,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(n.phone), ''), 'SIN CELULAR') ORDER BY n.id SEPARATOR ' | ') AS phones
FROM normalized_customers n
WHERE n.normalized_name <> ''
GROUP BY n.normalized_name
HAVING COUNT(*) > 1
   AND SUM(n.identity_document IS NULL OR TRIM(n.identity_document) = '') > 0
ORDER BY customer_count DESC, n.normalized_name;

-- 06. Same DNI/RUC assigned to different normalized names: data conflict, never auto-merge.
WITH normalized_customers AS (
    SELECT
        c.id,
        CONCAT_WS(' ', c.first_name, c.last_name) AS customer_name,
        c.identity_document,
        c.phone,
        LOWER(TRIM(CONCAT_WS(' ', c.first_name, c.last_name))) AS normalized_name,
        REPLACE(REPLACE(REPLACE(TRIM(c.identity_document), ' ', ''), '-', ''), '.', '') AS normalized_document
    FROM `noretel_noretelbd`.`customers` c
)
SELECT
    normalized_document,
    COUNT(*) AS customer_count,
    COUNT(DISTINCT normalized_name) AS different_name_count,
    GROUP_CONCAT(id ORDER BY id SEPARATOR ', ') AS customer_ids,
    GROUP_CONCAT(customer_name ORDER BY id SEPARATOR ' | ') AS names,
    GROUP_CONCAT(COALESCE(NULLIF(TRIM(phone), ''), 'SIN CELULAR') ORDER BY id SEPARATOR ' | ') AS phones
FROM normalized_customers
WHERE normalized_document <> ''
GROUP BY normalized_document
HAVING COUNT(*) > 1 AND COUNT(DISTINCT normalized_name) > 1
ORDER BY different_name_count DESC, normalized_document;

