<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 3 — Procurement: suppliers, supplier catalog + price history, PR, PO, GR (batch capture + put-away),
 * purchase returns, dan AP invoice foundation. Menyusul pola Phase 1/2 (InnoDB, FK, immutable ledger via Inventory_service).
 */
class Migration_Procurement extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE suppliers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, legal_name VARCHAR(200) NULL,
            supplier_type ENUM('DISTRIBUTOR','MANUFACTURER','PBF','IMPORTER','OTHER') NOT NULL DEFAULT 'DISTRIBUTOR',
            npwp VARCHAR(25) NULL, license_no VARCHAR(80) NULL COMMENT 'nomor izin PBF/distributor - konfigurasi, bukan validasi hukum',
            contact_person VARCHAR(120) NULL, phone VARCHAR(30) NULL, email VARCHAR(120) NULL, address TEXT NULL,
            payment_term_days SMALLINT UNSIGNED NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'IDR',
            is_active TINYINT(1) NOT NULL DEFAULT 1, notes VARCHAR(255) NULL,
            created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_suppliers_company_code (company_id, code), KEY idx_suppliers_name (name), KEY idx_suppliers_active (company_id, is_active),
            CONSTRAINT fk_suppliers_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // Kaitkan batch ke supplier (kolom sudah disiapkan di Phase 2)
        $q[] = "ALTER TABLE batches ADD CONSTRAINT fk_batches_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)";

        $q[] = "CREATE TABLE supplier_products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
            supplier_sku VARCHAR(60) NULL, supplier_product_name VARCHAR(200) NULL,
            last_price DECIMAL(18,4) NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'IDR',
            min_order_qty DECIMAL(18,4) NOT NULL DEFAULT 0, lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            is_preferred TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, last_purchased_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sp_supplier_product (supplier_id, product_id), KEY idx_sp_product (product_id),
            CONSTRAINT fk_sp_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_sp_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE supplier_price_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
            price DECIMAL(18,4) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'IDR',
            source_ref_type VARCHAR(30) NULL COMMENT 'purchase_orders|goods_receipts|manual', source_ref_id BIGINT UNSIGNED NULL, source_ref_no VARCHAR(60) NULL,
            po_price DECIMAL(18,4) NULL COMMENT 'harga PO saat GR (untuk deteksi varian)', effective_date DATE NOT NULL,
            created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sph_supplier_product (supplier_id, product_id, effective_date), KEY idx_sph_product (product_id, effective_date),
            CONSTRAINT fk_sph_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_sph_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only riwayat harga beli per supplier.'";

        $q[] = "CREATE TABLE purchase_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NULL,
            pr_no VARCHAR(40) NOT NULL, request_date DATE NOT NULL, required_date DATE NULL, notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','CLOSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            total_estimated DECIMAL(18,2) NOT NULL DEFAULT 0,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            closed_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pr_no (pr_no), KEY idx_pr_status (company_id, status), KEY idx_pr_date (request_date),
            CONSTRAINT fk_pr_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_pr_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_pr_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE purchase_request_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            pr_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, uom_id INT UNSIGNED NULL, qty DECIMAL(18,4) NOT NULL,
            estimated_price DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_pri_line (pr_id, line_no), KEY idx_pri_product (product_id),
            CONSTRAINT fk_pri_pr FOREIGN KEY (pr_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_pri_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT chk_pri_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE purchase_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL,
            warehouse_id INT UNSIGNED NOT NULL COMMENT 'gudang penerima', pr_id BIGINT UNSIGNED NULL,
            po_no VARCHAR(40) NOT NULL, order_date DATE NOT NULL, expected_date DATE NULL,
            payment_term_days SMALLINT UNSIGNED NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'IDR', notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','ORDERED','PARTIAL','RECEIVED','CLOSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, discount_total DECIMAL(18,2) NOT NULL DEFAULT 0, tax_total DECIMAL(18,2) NOT NULL DEFAULT 0, grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            ordered_by INT UNSIGNED NULL, ordered_at DATETIME NULL, closed_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_po_no (po_no), KEY idx_po_status (company_id, status), KEY idx_po_supplier (supplier_id), KEY idx_po_date (order_date),
            CONSTRAINT fk_po_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_po_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_po_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_po_pr FOREIGN KEY (pr_id) REFERENCES purchase_requests(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE purchase_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            po_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, uom_id INT UNSIGNED NULL,
            qty_ordered DECIMAL(18,4) NOT NULL, qty_received DECIMAL(18,4) NOT NULL DEFAULT 0,
            unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, discount_pct DECIMAL(7,3) NOT NULL DEFAULT 0, tax_pct DECIMAL(7,3) NOT NULL DEFAULT 0,
            line_total DECIMAL(18,2) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_poi_line (po_id, line_no), KEY idx_poi_product (product_id),
            CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT chk_poi_qty CHECK (qty_ordered > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE goods_receipts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL,
            po_id BIGINT UNSIGNED NULL, warehouse_id INT UNSIGNED NOT NULL,
            gr_no VARCHAR(40) NOT NULL, receipt_date DATE NOT NULL, supplier_do_no VARCHAR(60) NULL COMMENT 'no. surat jalan', supplier_invoice_no VARCHAR(60) NULL,
            notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','REVERSED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            movement_id BIGINT UNSIGNED NULL, reversal_movement_id BIGINT UNSIGNED NULL, total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            posted_by INT UNSIGNED NULL, posted_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gr_no (gr_no), KEY idx_gr_status (company_id, status), KEY idx_gr_supplier (supplier_id), KEY idx_gr_po (po_id),
            CONSTRAINT fk_gr_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_gr_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_gr_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_gr_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
            CONSTRAINT fk_gr_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_gr_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE goods_receipt_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            gr_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL, po_item_id BIGINT UNSIGNED NULL,
            product_id INT UNSIGNED NOT NULL, location_id INT UNSIGNED NULL COMMENT 'put-away location',
            batch_no VARCHAR(60) NULL, manufacture_date DATE NULL, expiry_date DATE NULL,
            qty_received DECIMAL(18,4) NOT NULL, unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE') NOT NULL DEFAULT 'GOOD',
            inspection_result ENUM('ACCEPTED','QUARANTINE','REJECTED') NOT NULL DEFAULT 'ACCEPTED',
            batch_id BIGINT UNSIGNED NULL COMMENT 'diisi setelah posting', notes VARCHAR(255) NULL,
            UNIQUE KEY uq_gri_line (gr_id, line_no), KEY idx_gri_product (product_id), KEY idx_gri_poitem (po_item_id),
            CONSTRAINT fk_gri_gr FOREIGN KEY (gr_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
            CONSTRAINT fk_gri_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_gri_poitem FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id),
            CONSTRAINT fk_gri_location FOREIGN KEY (location_id) REFERENCES locations(id),
            CONSTRAINT chk_gri_qty CHECK (qty_received > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE purchase_returns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL,
            warehouse_id INT UNSIGNED NOT NULL, gr_id BIGINT UNSIGNED NULL,
            return_no VARCHAR(40) NOT NULL, return_date DATE NOT NULL, reason_code_id INT UNSIGNED NOT NULL, notes VARCHAR(500) NULL,
            status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','REJECTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            movement_id BIGINT UNSIGNED NULL, total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
            submitted_by INT UNSIGNED NULL, submitted_at DATETIME NULL, approved_by INT UNSIGNED NULL, approved_at DATETIME NULL,
            posted_by INT UNSIGNED NULL, posted_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_prt_no (return_no), KEY idx_prt_status (company_id, status), KEY idx_prt_supplier (supplier_id),
            CONSTRAINT fk_prt_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_prt_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_prt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_prt_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_prt_gr FOREIGN KEY (gr_id) REFERENCES goods_receipts(id),
            CONSTRAINT fk_prt_reason FOREIGN KEY (reason_code_id) REFERENCES reason_codes(id),
            CONSTRAINT fk_prt_movement FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE purchase_return_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            return_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NULL, location_id INT UNSIGNED NULL,
            condition_code ENUM('GOOD','DAMAGED','QUARANTINE','EXPIRED') NOT NULL DEFAULT 'GOOD',
            qty DECIMAL(18,4) NOT NULL, unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_prti_line (return_id, line_no), KEY idx_prti_product (product_id), KEY idx_prti_batch (batch_id),
            CONSTRAINT fk_prti_return FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
            CONSTRAINT fk_prti_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT fk_prti_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
            CONSTRAINT chk_prti_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE ap_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL,
            po_id BIGINT UNSIGNED NULL, gr_id BIGINT UNSIGNED NULL,
            ap_no VARCHAR(40) NOT NULL, supplier_invoice_no VARCHAR(60) NULL, invoice_date DATE NOT NULL, due_date DATE NULL,
            subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, tax_total DECIMAL(18,2) NOT NULL DEFAULT 0, grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0,
            status ENUM('DRAFT','OPEN','PARTIAL','PAID','CANCELLED') NOT NULL DEFAULT 'DRAFT', currency CHAR(3) NOT NULL DEFAULT 'IDR', notes VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ap_no (ap_no), KEY idx_ap_status (company_id, status), KEY idx_ap_supplier (supplier_id), KEY idx_ap_due (due_date),
            CONSTRAINT fk_ap_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_ap_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_ap_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            CONSTRAINT fk_ap_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
            CONSTRAINT fk_ap_gr FOREIGN KEY (gr_id) REFERENCES goods_receipts(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AP foundation. Pembayaran & posting GL menyusul Phase 6.'";

        $q[] = "CREATE TABLE ap_invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ap_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NULL, description VARCHAR(200) NOT NULL, qty DECIMAL(18,4) NOT NULL DEFAULT 1,
            unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, tax_pct DECIMAL(7,3) NOT NULL DEFAULT 0, line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_api_line (ap_id, line_no), KEY idx_api_product (product_id),
            CONSTRAINT fk_api_ap FOREIGN KEY (ap_id) REFERENCES ap_invoices(id) ON DELETE CASCADE,
            CONSTRAINT fk_api_product FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE batches DROP FOREIGN KEY fk_batches_supplier');
        foreach (['ap_invoice_items', 'ap_invoices', 'purchase_return_items', 'purchase_returns', 'goods_receipt_items', 'goods_receipts',
            'purchase_order_items', 'purchase_orders', 'purchase_request_items', 'purchase_requests', 'supplier_price_history', 'supplier_products', 'suppliers'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
