<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 1/2 — Master data framework: categories, UOM, products, product units, warehouses, locations, reason codes.
 */
class Migration_Master_data extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE product_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, parent_id INT UNSIGNED NULL, code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pc_company_code (company_id, code), KEY idx_pc_parent (parent_id),
            CONSTRAINT fk_pc_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_pc_parent FOREIGN KEY (parent_id) REFERENCES product_categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE uoms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(15) NOT NULL, name VARCHAR(50) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_uoms_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE drug_classifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL,
            requires_prescription TINYINT(1) NOT NULL DEFAULT 0, is_controlled TINYINT(1) NOT NULL DEFAULT 0,
            notes VARCHAR(255) NULL COMMENT 'Golongan obat: configurable. REGULATORY VERIFICATION REQUIRED untuk kewajiban pelaporan tiap golongan.',
            UNIQUE KEY uq_dc_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            sku VARCHAR(40) NOT NULL, barcode VARCHAR(60) NULL, name VARCHAR(200) NOT NULL, generic_name VARCHAR(200) NULL,
            brand VARCHAR(100) NULL, manufacturer_name VARCHAR(150) NULL, principal_name VARCHAR(150) NULL,
            category_id INT UNSIGNED NULL, subcategory_id INT UNSIGNED NULL, classification_id INT UNSIGNED NULL,
            dosage_form VARCHAR(60) NULL COMMENT 'bentuk sediaan: tablet, kapsul, sirup...', preparation_type VARCHAR(60) NULL COMMENT 'jenis sediaan',
            strength VARCHAR(60) NULL COMMENT 'kekuatan/dosis, mis. 500 mg', packaging VARCHAR(100) NULL,
            base_uom_id INT UNSIGNED NOT NULL COMMENT 'satuan terkecil (stock unit)',
            purchase_price DECIMAL(18,4) NOT NULL DEFAULT 0, selling_price DECIMAL(18,4) NOT NULL DEFAULT 0, margin_pct DECIMAL(7,3) NOT NULL DEFAULT 0,
            tax_code VARCHAR(20) NULL COMMENT 'FK ke tax engine (Phase 6), configurable',
            is_taxable TINYINT(1) NOT NULL DEFAULT 1,
            min_stock DECIMAL(18,4) NOT NULL DEFAULT 0, max_stock DECIMAL(18,4) NOT NULL DEFAULT 0, reorder_point DECIMAL(18,4) NOT NULL DEFAULT 0,
            safety_stock DECIMAL(18,4) NOT NULL DEFAULT 0, lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            is_batch_tracked TINYINT(1) NOT NULL DEFAULT 1, is_expiry_tracked TINYINT(1) NOT NULL DEFAULT 1, is_serial_tracked TINYINT(1) NOT NULL DEFAULT 0,
            requires_prescription TINYINT(1) NOT NULL DEFAULT 0, is_cold_chain TINYINT(1) NOT NULL DEFAULT 0, is_controlled TINYINT(1) NOT NULL DEFAULT 0,
            storage_min_temp DECIMAL(5,2) NULL, storage_max_temp DECIMAL(5,2) NULL,
            status ENUM('ACTIVE','INACTIVE','DISCONTINUED') NOT NULL DEFAULT 'ACTIVE',
            created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_products_company_sku (company_id, sku), KEY idx_products_barcode (barcode), KEY idx_products_name (name),
            KEY idx_products_generic (generic_name), KEY idx_products_category (category_id), KEY idx_products_status (company_id, status),
            CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id),
            CONSTRAINT fk_products_subcategory FOREIGN KEY (subcategory_id) REFERENCES product_categories(id),
            CONSTRAINT fk_products_classification FOREIGN KEY (classification_id) REFERENCES drug_classifications(id),
            CONSTRAINT fk_products_base_uom FOREIGN KEY (base_uom_id) REFERENCES uoms(id),
            CONSTRAINT chk_products_prices CHECK (purchase_price >= 0 AND selling_price >= 0),
            CONSTRAINT chk_products_stock_levels CHECK (min_stock >= 0 AND max_stock >= 0 AND reorder_point >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE product_units (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL, uom_id INT UNSIGNED NOT NULL,
            conversion_factor DECIMAL(18,6) NOT NULL COMMENT 'qty base uom per 1 unit ini', barcode VARCHAR(60) NULL,
            is_purchase_uom TINYINT(1) NOT NULL DEFAULT 0, is_sales_uom TINYINT(1) NOT NULL DEFAULT 0,
            selling_price DECIMAL(18,4) NULL,
            UNIQUE KEY uq_pu_product_uom (product_id, uom_id), KEY idx_pu_barcode (barcode),
            CONSTRAINT fk_pu_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_pu_uom FOREIGN KEY (uom_id) REFERENCES uoms(id),
            CONSTRAINT chk_pu_factor CHECK (conversion_factor > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE warehouses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL,
            warehouse_type ENUM('MAIN','RETAIL','TRANSIT','QUARANTINE','RETURN','COLD') NOT NULL DEFAULT 'MAIN',
            is_cold_chain TINYINT(1) NOT NULL DEFAULT 0, allow_negative_stock TINYINT(1) NOT NULL DEFAULT 0,
            address TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wh_company_code (company_id, code), KEY idx_wh_branch (branch_id),
            CONSTRAINT fk_wh_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_wh_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE locations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            warehouse_id INT UNSIGNED NOT NULL, code VARCHAR(30) NOT NULL, name VARCHAR(100) NULL,
            location_type ENUM('ZONE','RACK','BIN') NOT NULL DEFAULT 'BIN', parent_id INT UNSIGNED NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0, is_quarantine TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_loc_wh_code (warehouse_id, code), KEY idx_loc_parent (parent_id),
            CONSTRAINT fk_loc_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
            CONSTRAINT fk_loc_parent FOREIGN KEY (parent_id) REFERENCES locations(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE reason_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, reason_type ENUM('ADJUSTMENT','RETURN','CANCELLATION','QUARANTINE','REVERSAL','OPNAME') NOT NULL,
            code VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL, requires_note TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_rc_company_type_code (company_id, reason_type, code),
            CONSTRAINT fk_rc_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (['reason_codes', 'locations', 'warehouses', 'product_units', 'products', 'drug_classifications', 'uoms', 'product_categories'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
