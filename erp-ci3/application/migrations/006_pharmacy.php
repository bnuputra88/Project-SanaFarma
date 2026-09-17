<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 4 (farmasi) — Resep & Dispensing: prescriptions (+ items, compound/racikan ingredients).
 * Dispensing membuat sale (sale_type=PRESCRIPTION) + pembayaran dan memutasi stok via Inventory_service (ISSUE, FEFO).
 * Nama dokter diketik langsung (tanpa master dokter). Etiket dicetak per item.
 */
class Migration_Pharmacy extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE prescriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, warehouse_id INT UNSIGNED NOT NULL,
            prescription_no VARCHAR(40) NOT NULL, prescription_date DATE NOT NULL, customer_id INT UNSIGNED NULL,
            doctor_name VARCHAR(150) NULL, doctor_sip VARCHAR(60) NULL,
            patient_name VARCHAR(150) NOT NULL, patient_age VARCHAR(30) NULL, patient_weight DECIMAL(6,2) NULL,
            diagnosis VARCHAR(300) NULL, notes VARCHAR(500) NULL,
            status ENUM('DRAFT','VERIFIED','DISPENSED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
            subtotal DECIMAL(18,2) NOT NULL DEFAULT 0, grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            sale_id BIGINT UNSIGNED NULL,
            verified_by INT UNSIGNED NULL, verified_at DATETIME NULL, dispensed_by INT UNSIGNED NULL, dispensed_at DATETIME NULL, cancel_reason VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rx_no (prescription_no), KEY idx_rx_status (company_id, status), KEY idx_rx_date (prescription_date), KEY idx_rx_customer (customer_id),
            CONSTRAINT fk_rx_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_rx_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
            CONSTRAINT fk_rx_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_rx_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
            CONSTRAINT fk_rx_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE prescription_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prescription_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            item_type ENUM('PRODUCT','COMPOUND') NOT NULL DEFAULT 'PRODUCT',
            product_id INT UNSIGNED NULL, compound_name VARCHAR(150) NULL,
            qty DECIMAL(18,4) NOT NULL, signa VARCHAR(200) NULL,
            label_color ENUM('WHITE','BLUE') NOT NULL DEFAULT 'WHITE', shake_well TINYINT(1) NOT NULL DEFAULT 0,
            unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, line_total DECIMAL(18,2) NOT NULL DEFAULT 0, compound_fee DECIMAL(18,2) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_rxi_line (prescription_id, line_no), KEY idx_rxi_product (product_id),
            CONSTRAINT fk_rxi_rx FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
            CONSTRAINT fk_rxi_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT chk_rxi_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE prescription_compound_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            prescription_item_id BIGINT UNSIGNED NOT NULL, line_no SMALLINT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL, qty DECIMAL(18,4) NOT NULL, unit_price DECIMAL(18,4) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
            UNIQUE KEY uq_rxci_line (prescription_item_id, line_no), KEY idx_rxci_product (product_id),
            CONSTRAINT fk_rxci_item FOREIGN KEY (prescription_item_id) REFERENCES prescription_items(id) ON DELETE CASCADE,
            CONSTRAINT fk_rxci_product FOREIGN KEY (product_id) REFERENCES products(id),
            CONSTRAINT chk_rxci_qty CHECK (qty > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (['prescription_compound_items', 'prescription_items', 'prescriptions'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
