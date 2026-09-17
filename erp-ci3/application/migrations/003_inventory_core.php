<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 2 — Inventory core: batches, balances, immutable ledger, movements, reservations, adjustments, transfers, opname.
 */
class Migration_Inventory_core extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
            batch_no VARCHAR(60) NOT NULL, manufacture_date DATE NULL, expiry_date DATE NULL,
            supplier_id INT UNSIGNED NULL COMMENT 'FK ditambahkan Phase 3 (suppliers)', source_ref_type VARCHAR(30) NULL, source_ref_id BIGINT UNSIGNED NULL,
            source_ref_no VARCHAR(60) NULL, received_at DATETIME NULL, unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
            status ENUM('ACTIVE','QUARANTINE','RECALLED','EXPIRED','DEPLETED') NOT NULL DEFAULT 'ACTIVE',
            notes VARCHAR(255) NULL, created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_batches_product_no (product_id, batch_no), KEY idx_batches_expiry (expiry_date), KEY idx_batches_status (status),
            KEY idx_batches_source (source_ref_type, source_ref_id),
            CONSTRAINT fk_batches_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_batches_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_balances (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            warehouse_id INT UNSIGNED NOT NULL, location_id INT UNSIGNED NULL, product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty_on_hand DECIMAL(18,4) NOT NULL DEFAULT 0, qty_reserved DECIMAL(18,4) NOT NULL DEFAULT 0, qty_in_transit DECIMAL(18,4) NOT NULL DEFAULT 0,
            avg_cost DECIMAL(18,4) NOT NULL DEFAULT 0, version INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'optimistic lock helper',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sb_key (warehouse_id, location_id, product_id, batch_id, condition_code),
            KEY idx_sb_product_wh (product_id, warehouse_id), KEY idx_sb_batch (batch_id),
            CONSTRAINT fk_sb_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_sb_loc FOREIGN KEY (location_id) REFERENCES locations(id),
            CONSTRAINT fk_sb_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_sb_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_sb_reserved CHECK (qty_reserved >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_movements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NULL, movement_no VARCHAR(40) NOT NULL,
            movement_type VARCHAR(30) NOT NULL, ref_type VARCHAR(40) NULL, ref_id BIGINT UNSIGNED NULL, ref_no VARCHAR(60) NULL,
            status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED', reversal_of_id BIGINT UNSIGNED NULL, reversed_by_id BIGINT UNSIGNED NULL,
            reason_code_id INT UNSIGNED NULL, notes VARCHAR(500) NULL,
            posted_by INT UNSIGNED NOT NULL, posted_at DATETIME(3) NOT NULL,
            UNIQUE KEY uq_sm_no (movement_no), KEY idx_sm_ref (ref_type, ref_id), KEY idx_sm_posted (posted_at), KEY idx_sm_type (movement_type, posted_at),
            CONSTRAINT fk_sm_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_sm_reversal_of FOREIGN KEY (reversal_of_id) REFERENCES stock_movements(id),
            CONSTRAINT fk_sm_posted_by FOREIGN KEY (posted_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable header; pembatalan via reversal movement'";

        $q[] = "CREATE TABLE stock_movement_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            movement_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            warehouse_id INT UNSIGNED NOT NULL, location_id INT UNSIGNED NULL, product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty DECIMAL(18,4) NOT NULL COMMENT 'positif = masuk, negatif = keluar', unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_smi_line (movement_id, line_no), KEY idx_smi_product (product_id), KEY idx_smi_batch (batch_id),
            CONSTRAINT fk_smi_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id),
            CONSTRAINT fk_smi_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_smi_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_smi_batch FOREIGN KEY (batch_id) REFERENCES batches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_ledger (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            movement_id BIGINT UNSIGNED NOT NULL, movement_item_id BIGINT UNSIGNED NOT NULL, movement_type VARCHAR(30) NOT NULL,
            warehouse_id INT UNSIGNED NOT NULL, location_id INT UNSIGNED NULL, product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty_in DECIMAL(18,4) NOT NULL DEFAULT 0, qty_out DECIMAL(18,4) NOT NULL DEFAULT 0, balance_after DECIMAL(18,4) NOT NULL,
            unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0, total_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
            ref_type VARCHAR(40) NULL, ref_id BIGINT UNSIGNED NULL, ref_no VARCHAR(60) NULL,
            posted_by INT UNSIGNED NOT NULL, posted_at DATETIME(3) NOT NULL,
            KEY idx_sl_product_wh_time (product_id, warehouse_id, posted_at, id), KEY idx_sl_batch_time (batch_id, posted_at),
            KEY idx_sl_movement (movement_id), KEY idx_sl_ref (ref_type, ref_id), KEY idx_sl_posted (posted_at),
            CONSTRAINT fk_sl_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id),
            CONSTRAINT fk_sl_item FOREIGN KEY (movement_item_id) REFERENCES stock_movement_items(id),
            CONSTRAINT fk_sl_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only kartu stok. Partisi RANGE(posted_at) direkomendasikan saat > 50 juta baris.'";

        $q[] = "CREATE TABLE stock_reservations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            balance_id BIGINT UNSIGNED NOT NULL, qty DECIMAL(18,4) NOT NULL, ref_type VARCHAR(40) NOT NULL, ref_id BIGINT UNSIGNED NOT NULL,
            status ENUM('ACTIVE','CONSUMED','RELEASED') NOT NULL DEFAULT 'ACTIVE', expires_at DATETIME NULL,
            created_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, released_at DATETIME NULL,
            KEY idx_sr_ref (ref_type, ref_id), KEY idx_sr_balance_status (balance_id, status),
            CONSTRAINT fk_sr_balance FOREIGN KEY (balance_id) REFERENCES stock_balances(id),
            CONSTRAINT chk_sr_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_adjustments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL,
            adjustment_no VARCHAR(40) NOT NULL, adjustment_date DATE NOT NULL, reason_code_id INT UNSIGNED NOT NULL, notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            total_value DECIMAL(18,2) NOT NULL DEFAULT 0, movement_id BIGINT UNSIGNED NULL,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            posted_by INT UNSIGNED NULL, posted_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sa_no (adjustment_no), KEY idx_sa_status (company_id, status), KEY idx_sa_date (adjustment_date),
            CONSTRAINT fk_sa_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_sa_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_sa_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_sa_reason FOREIGN KEY (reason_code_id) REFERENCES reason_codes(id),
            CONSTRAINT fk_sa_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_adjustment_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            adjustment_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, location_id INT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty_change DECIMAL(18,4) NOT NULL COMMENT '+ tambah, - kurang', unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_sai_line (adjustment_id, line_no),
            CONSTRAINT fk_sai_adj FOREIGN KEY (adjustment_id) REFERENCES stock_adjustments(id) ON DELETE CASCADE,
            CONSTRAINT fk_sai_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_sai_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_sai_qty CHECK (qty_change <> 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_transfers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, transfer_no VARCHAR(40) NOT NULL, transfer_date DATE NOT NULL,
            from_warehouse_id INT UNSIGNED NOT NULL, to_warehouse_id INT UNSIGNED NOT NULL, from_branch_id INT UNSIGNED NOT NULL, to_branch_id INT UNSIGNED NOT NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','IN_TRANSIT','RECEIVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            notes VARCHAR(500) NULL, out_movement_id BIGINT UNSIGNED NULL, in_movement_id BIGINT UNSIGNED NULL,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            shipped_by INT UNSIGNED NULL, shipped_at DATETIME NULL, received_by INT UNSIGNED NULL, received_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_st_no (transfer_no), KEY idx_st_status (company_id, status), KEY idx_st_from (from_warehouse_id), KEY idx_st_to (to_warehouse_id),
            CONSTRAINT fk_st_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_st_from_wh FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_st_to_wh FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT chk_st_diff_wh CHECK (from_warehouse_id <> to_warehouse_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_transfer_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            transfer_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, from_location_id INT UNSIGNED NULL, to_location_id INT UNSIGNED NULL,
            qty_requested DECIMAL(18,4) NOT NULL, qty_shipped DECIMAL(18,4) NOT NULL DEFAULT 0, qty_received DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_sti_line (transfer_id, line_no),
            CONSTRAINT fk_sti_transfer FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
            CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_sti_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_sti_qty CHECK (qty_requested > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_opnames (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL,
            opname_no VARCHAR(40) NOT NULL, opname_date DATE NOT NULL, opname_type ENUM('FULL','CYCLE') NOT NULL DEFAULT 'FULL',
            status ENUM('DRAFT','COUNTING','SUBMITTED','APPROVED','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            notes VARCHAR(500) NULL, movement_id BIGINT UNSIGNED NULL,
            approved_by INT UNSIGNED NULL, approved_at DATETIME NULL, posted_by INT UNSIGNED NULL, posted_at DATETIME NULL,
            created_by INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_so_no (opname_no), KEY idx_so_status (company_id, status),
            CONSTRAINT fk_so_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_so_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE stock_opname_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            opname_id BIGINT UNSIGNED NOT NULL, balance_id BIGINT UNSIGNED NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, location_id INT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty_system DECIMAL(18,4) NOT NULL DEFAULT 0, qty_counted DECIMAL(18,4) NULL, qty_variance DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0, counted_by INT UNSIGNED NULL, counted_at DATETIME NULL, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_soi (opname_id, product_id, batch_id, location_id, condition_code),
            CONSTRAINT fk_soi_opname FOREIGN KEY (opname_id) REFERENCES stock_opnames(id) ON DELETE CASCADE,
            CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (['stock_opname_items', 'stock_opnames', 'stock_transfer_items', 'stock_transfers', 'stock_adjustment_items', 'stock_adjustments',
            'stock_reservations', 'stock_ledger', 'stock_movement_items', 'stock_movements', 'stock_balances', 'batches'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
