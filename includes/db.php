<?php
/**
 * Database Connection & PDO Wrapper
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    private static function connect(): PDO {
        $pdo = null;
        
        // Attempt MySQL connection first if configured
        if (DB_DRIVER === 'mysql') {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (PDOException $e) {
                // If MySQL fails, fallback gracefully to SQLite so the application operates seamlessly
                error_log("MySQL Connection Failed: " . $e->getMessage() . ". Falling back to SQLite.");
            }
        }

        // SQLite Fallback / Direct Driver
        try {
            $isNewDatabase = !file_exists(SQLITE_FILE);
            $dsn = "sqlite:" . SQLITE_FILE;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if ($isNewDatabase) {
                self::initializeSQLiteSchema($pdo);
            }
            return $pdo;
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    private static function initializeSQLiteSchema(PDO $pdo): void {
        // SQLite Schema creation
        $sql = "
        CREATE TABLE IF NOT EXISTS roles (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          role_name TEXT NOT NULL UNIQUE,
          role_key TEXT NOT NULL UNIQUE,
          description TEXT DEFAULT '',
          permissions TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL,
          email TEXT NOT NULL UNIQUE,
          mobile TEXT NOT NULL,
          password TEXT NOT NULL,
          role_id INTEGER NOT NULL,
          profile_photo TEXT DEFAULT 'default-avatar.png',
          status TEXT DEFAULT 'active',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (role_id) REFERENCES roles(id)
        );

        CREATE TABLE IF NOT EXISTS work_types (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL UNIQUE,
          description TEXT NULL,
          code_prefix TEXT DEFAULT 'FMS',
          status TEXT DEFAULT 'active',
          created_by INTEGER NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS workflow_stages (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          work_type_id INTEGER NOT NULL,
          stage_order INTEGER NOT NULL,
          stage_name TEXT NOT NULL,
          assigned_role_id INTEGER NOT NULL,
          sla_hours INTEGER DEFAULT 24,
          required_documents TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (work_type_id) REFERENCES work_types(id),
          FOREIGN KEY (assigned_role_id) REFERENCES roles(id)
        );

        CREATE TABLE IF NOT EXISTS files (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          file_code TEXT NOT NULL UNIQUE,
          customer_name TEXT NOT NULL,
          customer_mobile TEXT NOT NULL,
          customer_email TEXT DEFAULT '',
          customer_address TEXT NULL,
          work_type_id INTEGER NOT NULL,
          current_stage_id INTEGER NULL,
          current_assigned_user INTEGER NULL,
          status TEXT DEFAULT 'pending',
          priority TEXT DEFAULT 'medium',
          created_by INTEGER NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (work_type_id) REFERENCES work_types(id),
          FOREIGN KEY (current_stage_id) REFERENCES workflow_stages(id),
          FOREIGN KEY (current_assigned_user) REFERENCES users(id),
          FOREIGN KEY (created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS file_documents (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          file_id INTEGER NOT NULL,
          document_name TEXT NOT NULL,
          file_path TEXT NOT NULL,
          file_type TEXT DEFAULT 'document',
          uploaded_by INTEGER NOT NULL,
          uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (file_id) REFERENCES files(id),
          FOREIGN KEY (uploaded_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS file_history (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          file_id INTEGER NOT NULL,
          from_user INTEGER NULL,
          to_user INTEGER NULL,
          stage_id INTEGER NULL,
          action_type TEXT DEFAULT 'transfer',
          remarks TEXT NULL,
          action_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (file_id) REFERENCES files(id),
          FOREIGN KEY (from_user) REFERENCES users(id),
          FOREIGN KEY (to_user) REFERENCES users(id),
          FOREIGN KEY (stage_id) REFERENCES workflow_stages(id)
        );

        CREATE TABLE IF NOT EXISTS whatsapp_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          file_id INTEGER NOT NULL,
          sent_to TEXT NOT NULL,
          template_used TEXT DEFAULT 'general',
          message TEXT NOT NULL,
          status TEXT DEFAULT 'sent',
          sent_by INTEGER NOT NULL,
          sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (file_id) REFERENCES files(id),
          FOREIGN KEY (sent_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS call_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          file_id INTEGER NOT NULL,
          called_by INTEGER NOT NULL,
          customer_mobile TEXT NOT NULL,
          call_summary TEXT NULL,
          duration_seconds INTEGER DEFAULT 0,
          called_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (file_id) REFERENCES files(id),
          FOREIGN KEY (called_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS activity_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NULL,
          action TEXT NOT NULL,
          details TEXT NULL,
          ip_address TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        $pdo->exec($sql);

        // Seed default roles & users
        $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
        
        $pdo->exec("
        INSERT INTO roles (id, role_name, role_key, description, permissions) VALUES
        (1, 'Super Admin', 'super_admin', 'Full system access', '[\"*\"]'),
        (2, 'Admin', 'admin', 'File creation, assignment, workflow control, reports', '[\"create_file\",\"edit_file\",\"assign_file\",\"forward_file\",\"view_all_files\",\"manage_users\",\"view_reports\"]'),
        (3, 'Manager', 'manager', 'Team file tracking, reassignment, reporting', '[\"create_file\",\"edit_file\",\"assign_file\",\"forward_file\",\"view_team_files\",\"view_reports\"]'),
        (4, 'Employee', 'employee', 'Process assigned files and forward to next stage', '[\"view_assigned_files\",\"edit_file\",\"forward_file\",\"upload_doc\",\"call_customer\",\"whatsapp_customer\"]'),
        (5, 'Front Desk / Receptionist', 'receptionist', 'Create new files, customer intake, doc upload/scan', '[\"create_file\",\"upload_doc\",\"forward_file\",\"view_assigned_files\"]'),
        (6, 'Accounts', 'accounts', 'Billing, payment verification, step approval', '[\"view_assigned_files\",\"edit_file\",\"forward_file\",\"upload_doc\"]');

        INSERT INTO users (id, name, email, mobile, password, role_id, status) VALUES
        (1, 'Rajesh Kumar (Super Admin)', 'admin@office.com', '9876543210', '$passwordHash', 1, 'active'),
        (2, 'Priya Sharma (Front Desk)', 'frontdesk@office.com', '9876543211', '$passwordHash', 5, 'active'),
        (3, 'Vikram Singh (Verification)', 'verification@office.com', '9876543212', '$passwordHash', 4, 'active'),
        (4, 'Amit Patel (Technical)', 'tech@office.com', '9876543213', '$passwordHash', 4, 'active'),
        (5, 'Suresh Verma (Accounts)', 'accounts@office.com', '9876543214', '$passwordHash', 6, 'active');

        INSERT INTO work_types (id, name, description, code_prefix, status, created_by) VALUES
        (1, 'New Fiber Connection', 'End-to-end processing for fresh broadband fiber installation', 'FIB', 'active', 1),
        (2, 'Name Transfer Request', 'Transfer existing connection ownership to new owner', 'TRF', 'active', 1),
        (3, 'TV / Cable Upgrade', 'Upgrade or install digital TV set-top box service', 'STB', 'active', 1),
        (4, 'Billing & Payment Complaint', 'Resolve customer billing disputes or payment mismatches', 'CMP', 'active', 1);

        INSERT INTO workflow_stages (id, work_type_id, stage_order, stage_name, assigned_role_id, sla_hours, required_documents) VALUES
        (1, 1, 1, 'Front Desk Intake & Document Upload', 5, 4, 'Aadhaar Card, Address Proof'),
        (2, 1, 2, 'Document Verification', 4, 12, 'Verified ID Proof'),
        (3, 1, 3, 'Technical Site Survey & Wiring', 4, 24, 'Feasibility Report'),
        (4, 1, 4, 'Accounts Payment Confirmation', 6, 8, 'Payment Receipt'),
        (5, 1, 5, 'Final Admin Approval & Activation', 2, 6, 'Completion Certificate'),
        (6, 2, 1, 'Request Submission & NOC Upload', 5, 6, 'NOC Letter, ID of Both Parties'),
        (7, 2, 2, 'Accounts Dues Verification', 6, 12, 'Clearance Receipt'),
        (8, 2, 3, 'Final Ownership Approval', 2, 12, 'Updated Agreement');

        INSERT INTO files (id, file_code, customer_name, customer_mobile, customer_email, customer_address, work_type_id, current_stage_id, current_assigned_user, status, priority, created_by) VALUES
        (1, 'FIB-2026-00101', 'Ramesh Gupta', '9822011223', 'ramesh@example.com', 'Flat 402, Sunshine Apartments, Civil Lines', 1, 1, 2, 'in_progress', 'high', 2),
        (2, 'FIB-2026-00102', 'Sunita Devi', '9822011224', 'sunita@example.com', 'House 12, Model Town, Sector 4', 1, 2, 3, 'in_progress', 'medium', 2),
        (3, 'TRF-2026-00103', 'Anil Mehta', '9822011225', 'anil@example.com', 'Shop 15, City Mall Market', 2, 7, 5, 'pending', 'low', 2),
        (4, 'FIB-2026-00104', 'Deepak Chopra', '9822011226', 'deepak@example.com', 'Plot 88, Industrial Area Phase 2', 1, 5, 1, 'completed', 'urgent', 2);

        INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks) VALUES
        (1, 2, 2, 1, 'created', 'Customer visited front desk. File created and assigned for document intake.'),
        (2, 2, 3, 2, 'forwarded', 'Documents uploaded successfully. Forwarded to Verification Team.'),
        (3, 2, 5, 7, 'forwarded', 'NOC uploaded. Forwarded to Accounts for dues clearance.'),
        (4, 4, 1, 5, 'approved', 'Site installation complete. Final approval granted by Admin.');
        ");
    }
}

// Global DB helper function
function getDB(): PDO {
    return Database::getConnection();
}
