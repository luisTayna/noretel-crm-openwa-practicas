-- =====================================================================
-- Fase 0 — Monitoreo GPON: inventario OLT / tarjeta / puerto PON / ONT
-- Ejecutar en phpMyAdmin sobre la base de datos de produccion (cPanel).
--
-- Equivale a estas migraciones de Laravel:
--   Modules/Network/Database/Migrations/2026_08_17_000001_create_olts_table.php
--   Modules/Network/Database/Migrations/2026_08_17_000002_create_olt_boards_table.php
--   Modules/Network/Database/Migrations/2026_08_17_000003_create_olt_pon_ports_table.php
--   Modules/Network/Database/Migrations/2026_08_17_000004_create_onts_table.php
--
-- No borra ni modifica ninguna tabla existente. Solo crea cuatro tablas nuevas.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. OLT (chasis)
-- ---------------------------------------------------------------------
CREATE TABLE `olts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `brand` varchar(255) NOT NULL DEFAULT 'huawei',
  `model` varchar(255) NOT NULL DEFAULT 'MA5800',
  `management_ip` varchar(255) NOT NULL,
  `ssh_port` smallint unsigned NOT NULL DEFAULT 22,
  `ssh_username` varchar(255) DEFAULT NULL,
  `credentials` text COMMENT 'Credenciales cifradas del usuario de solo lectura',
  `snmp_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si esta activo, las metricas masivas van por SNMP en lugar de SSH',
  `snmp_port` smallint unsigned NOT NULL DEFAULT 161,
  `node_id` bigint unsigned DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `zone` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Ultima lectura correcta del colector',
  `last_sync_error` text,
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olts_code_unique` (`code`),
  KEY `olts_status_index` (`status`),
  KEY `olts_node_id_foreign` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Tarjetas de la OLT (frame/slot)
-- ---------------------------------------------------------------------
CREATE TABLE `olt_boards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_id` bigint unsigned NOT NULL,
  `frame` smallint unsigned NOT NULL DEFAULT 0,
  `slot` smallint unsigned NOT NULL,
  `board_type` varchar(255) DEFAULT NULL COMMENT 'Tipo de tarjeta reportado por la OLT, por ejemplo GPBD',
  `port_count` smallint unsigned NOT NULL DEFAULT 0,
  `status` enum('normal','failed','offline','unknown') NOT NULL DEFAULT 'unknown',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_boards_olt_id_frame_slot_unique` (`olt_id`,`frame`,`slot`),
  CONSTRAINT `olt_boards_olt_id_foreign` FOREIGN KEY (`olt_id`) REFERENCES `olts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Puertos PON
-- ---------------------------------------------------------------------
CREATE TABLE `olt_pon_ports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `olt_board_id` bigint unsigned NOT NULL,
  `port` smallint unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL COMMENT 'Zona o sector que alimenta este puerto',
  `status` enum('up','down','unknown') NOT NULL DEFAULT 'unknown',
  `max_onts` smallint unsigned NOT NULL DEFAULT 128,
  `ont_count` smallint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `olt_pon_ports_olt_board_id_port_unique` (`olt_board_id`,`port`),
  CONSTRAINT `olt_pon_ports_olt_board_id_foreign` FOREIGN KEY (`olt_board_id`) REFERENCES `olt_boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. ONT (equipo del cliente)
-- ---------------------------------------------------------------------
CREATE TABLE `onts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(255) NOT NULL COMMENT 'Numero de serie GPON, por ejemplo HWTC1A2B3C4D',
  `model` varchar(255) DEFAULT NULL,
  `olt_pon_port_id` bigint unsigned DEFAULT NULL,
  `ont_index` smallint unsigned DEFAULT NULL COMMENT 'ID del ONT dentro del puerto PON, 0-127',
  `customer_id` bigint unsigned DEFAULT NULL,
  `contract_id` bigint unsigned DEFAULT NULL,
  `installed_equipment_id` bigint unsigned DEFAULT NULL COMMENT 'Trazabilidad con el acta de instalacion; sin llave foranea a proposito',
  `status` enum('online','offline','los','dying_gasp','provisioned','unknown') NOT NULL DEFAULT 'unknown',
  `last_rx_power` decimal(6,2) DEFAULT NULL COMMENT 'Potencia optica recibida por el ONT, en dBm',
  `last_tx_power` decimal(6,2) DEFAULT NULL,
  `baseline_rx_power` decimal(6,2) DEFAULT NULL COMMENT 'Linea base propia del ONT, para detectar degradacion relativa',
  `distance_m` int unsigned DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `signal_status` enum('good','warning','critical','no_signal','unknown') NOT NULL DEFAULT 'unknown',
  `notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onts_serial_number_unique` (`serial_number`),
  UNIQUE KEY `onts_olt_pon_port_id_ont_index_unique` (`olt_pon_port_id`,`ont_index`),
  KEY `onts_signal_status_index` (`signal_status`),
  KEY `onts_status_index` (`status`),
  KEY `onts_customer_id_index` (`customer_id`),
  KEY `onts_contract_id_foreign` (`contract_id`),
  CONSTRAINT `onts_olt_pon_port_id_foreign` FOREIGN KEY (`olt_pon_port_id`) REFERENCES `olt_pon_ports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `onts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `onts_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. OPCIONAL — llave foranea de olts hacia nodes.
--
--    Ejecutar SOLO si la tabla `nodes` existe en produccion. Comprobar con:
--        SHOW TABLES LIKE 'nodes';
--    Si no devuelve nada, SALTAR este bloque. Todo lo demas funciona igual;
--    `olts.node_id` simplemente queda sin restriccion.
-- ---------------------------------------------------------------------
-- ALTER TABLE `olts`
--   ADD CONSTRAINT `olts_node_id_foreign`
--   FOREIGN KEY (`node_id`) REFERENCES `nodes` (`id`) ON DELETE SET NULL;


-- ---------------------------------------------------------------------
-- 6. Registrar las migraciones como ya aplicadas.
--
--    Sin esto, si algun dia se ejecuta `php artisan migrate` en el servidor,
--    Laravel intentara crear estas tablas de nuevo y fallara.
-- ---------------------------------------------------------------------
-- 6a. Averiguar el numero de lote. Anotar el resultado.
--     SELECT MAX(`batch`) + 1 AS siguiente_lote FROM `migrations`;
--
-- 6b. Reemplazar los cuatro 99 por ese numero y ejecutar.
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_08_17_000001_create_olts_table',          99),
  ('2026_08_17_000002_create_olt_boards_table',    99),
  ('2026_08_17_000003_create_olt_pon_ports_table', 99),
  ('2026_08_17_000004_create_onts_table',          99);


-- ---------------------------------------------------------------------
-- 7. Verificacion. Debe devolver exactamente 4 filas.
-- ---------------------------------------------------------------------
-- SELECT TABLE_NAME, TABLE_ROWS
-- FROM INFORMATION_SCHEMA.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('olts','olt_boards','olt_pon_ports','onts');
