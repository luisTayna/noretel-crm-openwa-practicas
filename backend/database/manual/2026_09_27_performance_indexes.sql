-- =====================================================================
-- Performance indexes — invoices / payments hot query paths
-- Ejecutar en phpMyAdmin sobre la base de datos de produccion (cPanel).
--
-- Equivale a esta migracion de Laravel:
--   Modules/Billing/Database/Migrations/2026_09_27_000001_add_performance_indexes_to_billing_tables.php
--
-- No borra ni modifica ninguna tabla existente. Solo agrega tres indices
-- compuestos sobre `invoices` y `payments`. Es idempotente: cada CREATE
-- INDEX se salta si el indice ya existe, por lo que se puede volver a
-- ejecutar sin riesgo.
--
-- NOTA: se evaluo tambien un indice invoices(contract_id, status) (para
-- Invoice::where('contract_id', ...)->whereIn('status', ...) en
-- ContractController::update), pero se descarto: cambia el orden de
-- retorno, no garantizado por SQL, de consultas sin ORDER BY explicito
-- sobre `invoices` filtradas por contract_id (por ejemplo
-- DuplicateContractRepairService::compareContractInvoices(), que arma
-- 'overlap_periods' a partir de ese orden). Agregar un indice no debe
-- cambiar comportamiento observable, asi que se dejo fuera; `contract_id`
-- ya tiene su indice de FK para los lookups por igualdad.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. invoices(status, due_date)
--    Cubre el dashboard de cobranzas y los recordatorios de pago:
--    whereIn('status', ['pending','partial','overdue'])->orderBy('due_date')
--    (InvoiceController::collections, PaymentReminderService, etc.)
-- ---------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND INDEX_NAME = 'idx_invoices_status_due_date'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX `idx_invoices_status_due_date` ON `invoices` (`status`, `due_date`)',
  'SELECT ''idx_invoices_status_due_date ya existe, se omite'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. payments(status, payment_date)
--    Cubre el listado de pagos y el dashboard de facturacion:
--    where('status', ...)->whereBetween/where('payment_date', ...)
--      ->orderBy('payment_date', 'desc')
--    (PaymentController::index, BillingController::dashboard)
-- ---------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_status_payment_date'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX `idx_payments_status_payment_date` ON `payments` (`status`, `payment_date`)',
  'SELECT ''idx_payments_status_payment_date ya existe, se omite'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 3. payments(invoice_id, status)
--    Cubre el calculo de "total pagado" por factura, que se repite en
--    recibos, reportes y creditos:
--    $invoice->payments()->where('status', 'completed')->sum('amount')
--    (Invoice::getTotalPaid, PaymentService, CustomerCreditService, etc.)
--    `invoice_id` ya tiene indice de FK, pero este compuesto evita el
--    filtro adicional por status en memoria.
-- ---------------------------------------------------------------------
SET @idx_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_invoice_id_status'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX `idx_payments_invoice_id_status` ON `payments` (`invoice_id`, `status`)',
  'SELECT ''idx_payments_invoice_id_status ya existe, se omite'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 4. Registrar la migracion como ya aplicada.
--
--    Sin esto, si algun dia se ejecuta `php artisan migrate` en el
--    servidor, Laravel intentara crear estos indices de nuevo (sin
--    problema, la migracion tambien es idempotente) pero quedaria
--    pendiente en la lista de migraciones.
-- ---------------------------------------------------------------------
-- 4a. Averiguar el numero de lote. Anotar el resultado.
--     SELECT MAX(`batch`) + 1 AS siguiente_lote FROM `migrations`;
--
-- 4b. Reemplazar el 99 por ese numero y ejecutar.
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_09_27_000001_add_performance_indexes_to_billing_tables', 99);


-- ---------------------------------------------------------------------
-- 5. Verificacion. Debe devolver 3 filas (una por indice nuevo).
-- ---------------------------------------------------------------------
-- SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
-- FROM INFORMATION_SCHEMA.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND INDEX_NAME IN (
--     'idx_invoices_status_due_date',
--     'idx_payments_status_payment_date',
--     'idx_payments_invoice_id_status'
--   )
-- GROUP BY TABLE_NAME, INDEX_NAME;
