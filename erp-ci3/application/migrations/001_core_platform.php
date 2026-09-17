<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Phase 1 — Core platform: companies, branches, users, RBAC, sessions, settings, sequences, audit, workflow.
 */
class Migration_Core_platform extends CI_Migration
{
    public function up()
    {
        $q = [];
        $q[] = "CREATE TABLE companies (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, legal_name VARCHAR(200) NULL,
            npwp VARCHAR(25) NULL, address TEXT NULL, phone VARCHAR(30) NULL, email VARCHAR(120) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_companies_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE branches (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL,
            branch_type ENUM('HQ','RETAIL','DISTRIBUTION','WAREHOUSE') NOT NULL DEFAULT 'RETAIL',
            address TEXT NULL, phone VARCHAR(30) NULL, license_no VARCHAR(80) NULL COMMENT 'nomor izin (apotek/PBF) - konfigurasi, bukan validasi hukum',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_branches_company_code (company_id, code),
            CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NULL,
            username VARCHAR(60) NOT NULL, email VARCHAR(150) NOT NULL, password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(150) NOT NULL, phone VARCHAR(30) NULL,
            is_superadmin TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0, password_changed_at DATETIME NULL,
            mfa_enabled TINYINT(1) NOT NULL DEFAULT 0, mfa_secret VARCHAR(255) NULL COMMENT 'MFA-ready: encrypted secret',
            last_login_at DATETIME NULL, last_login_ip VARCHAR(45) NULL, current_session_id VARCHAR(128) NULL,
            created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_users_username (username), UNIQUE KEY uq_users_email (email),
            KEY idx_users_company_active (company_id, is_active),
            CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id),
            CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_roles_company_code (company_id, code),
            CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE permissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(40) NOT NULL, resource VARCHAR(60) NOT NULL, action VARCHAR(20) NOT NULL,
            code VARCHAR(120) NOT NULL COMMENT 'module.resource.action', description VARCHAR(255) NULL,
            UNIQUE KEY uq_permissions_code (code), KEY idx_permissions_module (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE role_permissions (
            role_id INT UNSIGNED NOT NULL, permission_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE user_roles (
            user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NULL COMMENT 'NULL = semua cabang',
            PRIMARY KEY (user_id, role_id),
            CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_ur_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE ci_sessions (
            id VARCHAR(128) NOT NULL, ip_address VARCHAR(45) NOT NULL, timestamp INT UNSIGNED NOT NULL DEFAULT 0, data BLOB NOT NULL,
            PRIMARY KEY (id), KEY ci_sessions_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE login_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL, username_attempted VARCHAR(150) NOT NULL,
            status ENUM('SUCCESS','FAILED','LOCKED','LOGOUT','INACTIVE') NOT NULL,
            channel ENUM('web','api') NOT NULL DEFAULT 'web', ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(255) NULL,
            session_id VARCHAR(128) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_lh_user_created (user_id, created_at), KEY idx_lh_created (created_at),
            CONSTRAINT fk_lh_user FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE login_attempts (
            identifier VARCHAR(200) NOT NULL PRIMARY KEY COMMENT 'ip:username',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, locked_until DATETIME NULL, last_attempt_at DATETIME NOT NULL,
            KEY idx_la_locked (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE password_reset_tokens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_prt_token (token_hash), KEY idx_prt_user (user_id),
            CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE api_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL, jti CHAR(16) NOT NULL, token_type ENUM('refresh') NOT NULL DEFAULT 'refresh',
            expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_api_tokens_jti (jti), KEY idx_api_tokens_user (user_id),
            CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE rate_limits (
            bucket VARCHAR(150) NOT NULL PRIMARY KEY, window_start DATETIME NOT NULL, hits INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE system_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NULL COMMENT 'NULL = global default', setting_group VARCHAR(40) NOT NULL, setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT NULL, value_type ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
            description VARCHAR(255) NULL, is_editable TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_settings_scope_key (company_id, setting_key), KEY idx_settings_group (setting_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE document_sequences (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL, branch_id INT UNSIGNED NULL, doc_type VARCHAR(30) NOT NULL,
            period_key VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'e.g. 202606 for monthly reset',
            pattern VARCHAR(100) NOT NULL, last_number INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_seq (company_id, branch_id, doc_type, period_key),
            CONSTRAINT fk_seq_company FOREIGN KEY (company_id) REFERENCES companies(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NULL, branch_id INT UNSIGNED NULL, user_id INT UNSIGNED NULL, username VARCHAR(60) NULL,
            module VARCHAR(40) NOT NULL, action VARCHAR(40) NOT NULL, entity VARCHAR(60) NOT NULL, entity_id VARCHAR(40) NULL,
            reference_no VARCHAR(60) NULL, old_values JSON NULL, new_values JSON NULL, reason VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, channel VARCHAR(10) NULL, request_id CHAR(16) NULL,
            created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            KEY idx_audit_entity (entity, entity_id), KEY idx_audit_user_created (user_id, created_at),
            KEY idx_audit_created (created_at), KEY idx_audit_module_action (module, action), KEY idx_audit_ref (reference_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only. Aplikasi tidak memiliki path UPDATE/DELETE. Gunakan DB user tanpa hak DELETE.'";

        $q[] = "CREATE TABLE workflow_instances (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            doc_type VARCHAR(40) NOT NULL, doc_id BIGINT UNSIGNED NOT NULL, doc_no VARCHAR(60) NULL,
            current_state VARCHAR(30) NOT NULL, is_final TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wf_doc (doc_type, doc_id), KEY idx_wf_state (doc_type, current_state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $q[] = "CREATE TABLE workflow_actions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            instance_id BIGINT UNSIGNED NOT NULL, action VARCHAR(30) NOT NULL, from_state VARCHAR(30) NOT NULL, to_state VARCHAR(30) NOT NULL,
            actor_id INT UNSIGNED NULL, notes VARCHAR(500) NULL, acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_wfa_instance (instance_id),
            CONSTRAINT fk_wfa_instance FOREIGN KEY (instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
            CONSTRAINT fk_wfa_actor FOREIGN KEY (actor_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($q as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (['workflow_actions', 'workflow_instances', 'audit_logs', 'document_sequences', 'system_settings', 'rate_limits', 'api_tokens',
            'password_reset_tokens', 'login_attempts', 'login_history', 'ci_sessions', 'user_roles', 'role_permissions', 'permissions', 'roles', 'users', 'branches', 'companies'] as $t) {
            $this->db->query("DROP TABLE IF EXISTS `$t`");
        }
    }
}
