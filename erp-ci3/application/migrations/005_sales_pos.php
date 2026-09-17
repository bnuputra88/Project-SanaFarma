<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 4 (core) — Retail POS: customers, cashier shifts, sales (+ items, payments), sales returns.
 * Penjualan memutasi stok via Inventory_service (ISSUE, FEFO); retur via RECEIPT. Dispensing/resep/racikan menyusul.
 */
class Migration_Sales_pos extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE customers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL,
            customer_type ENUM('WALK_IN','MEMBER','PATIENT','CORPORATE') NOT NULL DEFAULT 'WALK_IN',
            nik VARCHAR(30) NULL, phone VARCHAR(30) NULL, email VARCHAR(120) NULL, address TEXT NULL, date_of_birth DATE NULL,
            allergy_notes VARCHAR(500) NULL COMMENT 'catatan alergi/riwayat - data sensitif pasien',
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL,
            UNIQUE KEY uq_customers_company_code (company_id, code), KEY idx_customers_name (name), KEY idx_customers_phone (phone),
            CONSTRAINT fk_customers_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE cashier_shifts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL,
            shift_no VARCHAR(40) NOT NULL, cashier_id INT UNSIGNED NOT NULL,
            opened_at DATETIME NOT NULL, closed_at DATETIME NULL,
            opening_cash DECIMAL(18,2) NOT NULL DEFAULT 0, closing_cash DECIMAL(18,2) NULL, expected_cash DECIMAL(18,2) NULL, cash_variance DECIMAL(18,2) NULL,
            total_sales DECIMAL(18,2) NOT NULL DEFAULT 0, sale_count INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN', notes VARCHAR(500) NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_shift_no (shift_no), KEY idx_shift_open (warehouse_id, status), KEY idx_shift_cashier (cashier_id, status),
            CONSTRAINT fk_shift_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_shift_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_shift_cashier FOREIGN KEY (cashier_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE sales (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL, shift_id BIGINT UNSIGNED NULL,
            sale_no VARCHAR(40) NOT NULL, sale_datetime DATETIME NOT NULL, customer_id INT UNSIGNED NULL,
            sale_type ENUM('POS','PRESCRIPTION') NOT NULL DEFAULT 'POS',
            status ENUM('DRAFT','PAID','VOID','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, discount_total DECIMAL(18,2) NOT NULL DEFAULT 0, tax_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(18,2) NOT NULL DEFAULT 0, paid_total DECIMAL(18,2) NOT NULL DEFAULT 0, change_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            movement_id BIGINT UNSIGNED NULL, void_movement_id BIGINT UNSIGNED NULL, notes VARCHAR(500) NULL,
            cashier_id INT UNSIGNED NOT NULL, created_by INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            paid_at DATETIME NULL, voided_at DATETIME NULL, void_reason VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sale_no (sale_no), KEY idx_sale_status (company_id, status), KEY idx_sale_date (sale_datetime), KEY idx_sale_shift (shift_id), KEY idx_sale_customer (customer_id),
            CONSTRAINT fk_sale_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_sale_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_sale_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_sale_shift FOREIGN KEY (shift_id) REFERENCES cashier_shifts(id),
            CONSTRAINT fk_sale_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_sale_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id),
            CONSTRAINT fk_sale_cashier FOREIGN KEY (cashier_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE sale_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sale_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, uom_id INT UNSIGNED NULL,
            qty DECIMAL(18,4) NOT NULL, unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, discount_pct DECIMAL(7,3) NOT NULL DEFAULT 0, tax_pct DECIMAL(7,3) NOT NULL DEFAULT 0,
            line_total DECIMAL(18,2) NOT NULL DEFAULT 0, qty_returned DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_si_line (sale_id, line_no), KEY idx_si_product (product_id), KEY idx_si_batch (batch_id),
            CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_si_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_si_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE sale_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sale_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            method ENUM('CASH','CARD','TRANSFER','QRIS','OTHER') NOT NULL DEFAULT 'CASH', amount DECIMAL(18,2) NOT NULL, reference VARCHAR(80) NULL, paid_at DATETIME NOT NULL,
            UNIQUE KEY uq_sp_line (sale_id, line_no),
            CONSTRAINT fk_sp_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            CONSTRAINT chk_sp_amount CHECK (amount > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE sales_returns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NULL, customer_id INT UNSIGNED NULL,
            return_no VARCHAR(40) NOT NULL, return_date DATE NOT NULL, reason_code_id INT UNSIGNED NOT NULL, notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            movement_id BIGINT UNSIGNED NULL, total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            posted_by INT UNSIGNED NULL, posted_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_srt_no (return_no), KEY idx_srt_status (company_id, status), KEY idx_srt_sale (sale_id),
            CONSTRAINT fk_srt_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_srt_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_srt_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_srt_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
            CONSTRAINT fk_srt_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_srt_reason FOREIGN KEY (reason_code_id) REFERENCES reason_codes(id),
            CONSTRAINT fk_srt_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE sales_return_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            return_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL, sale_item_id BIGINT UNSIGNED NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty DECIMAL(18,4) NOT NULL, unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_srti_line (return_id, line_no), KEY idx_srti_product (product_id),
            CONSTRAINT fk_srti_return FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
            CONSTRAINT fk_srti_saleitem FOREIGN KEY (sale_item_id) REFERENCES sale_items(id),
            CONSTRAINT fk_srti_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_srti_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_srti_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (['sales_return_items', 'sales_returns', 'sale_payments', 'sale_items', 'sales', 'cashier_shifts', 'customers'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
