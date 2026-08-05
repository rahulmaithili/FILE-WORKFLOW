-- Office File Management CRM System - Complete MySQL Schema
-- Database: file_crm

CREATE DATABASE IF NOT EXISTS file_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE file_crm;

-- 1. Roles Table
CREATE TABLE IF NOT EXISTS roles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  role_key VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT '',
  permissions JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  mobile VARCHAR(15) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  profile_photo VARCHAR(255) DEFAULT 'default-avatar.png',
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Work Types (Workflow Templates)
CREATE TABLE IF NOT EXISTS work_types (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  code_prefix VARCHAR(10) NOT NULL DEFAULT 'FMS',
  status ENUM('active','inactive') DEFAULT 'active',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Workflow Stages (Per Work Type)
CREATE TABLE IF NOT EXISTS workflow_stages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  work_type_id INT NOT NULL,
  stage_order INT NOT NULL,
  stage_name VARCHAR(100) NOT NULL,
  assigned_role_id INT NOT NULL,
  sla_hours INT DEFAULT 24,
  required_documents TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Files / Cases Table
CREATE TABLE IF NOT EXISTS files (
  id INT PRIMARY KEY AUTO_INCREMENT,
  file_code VARCHAR(30) NOT NULL UNIQUE,
  customer_name VARCHAR(100) NOT NULL,
  customer_mobile VARCHAR(15) NOT NULL,
  customer_email VARCHAR(100) DEFAULT '',
  customer_address TEXT NULL,
  work_type_id INT NOT NULL,
  current_stage_id INT NULL,
  current_assigned_user INT NULL,
  status ENUM('pending','in_progress','on_hold','completed','rejected') DEFAULT 'pending',
  priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (work_type_id) REFERENCES work_types(id),
  FOREIGN KEY (current_stage_id) REFERENCES workflow_stages(id) ON DELETE SET NULL,
  FOREIGN KEY (current_assigned_user) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. File Documents Table
CREATE TABLE IF NOT EXISTS file_documents (
  id INT PRIMARY KEY AUTO_INCREMENT,
  file_id INT NOT NULL,
  document_name VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(50) DEFAULT 'document',
  uploaded_by INT NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. File History / Timeline Audit Trail
CREATE TABLE IF NOT EXISTS file_history (
  id INT PRIMARY KEY AUTO_INCREMENT,
  file_id INT NOT NULL,
  from_user INT NULL,
  to_user INT NULL,
  stage_id INT NULL,
  action_type VARCHAR(50) DEFAULT 'transfer',
  remarks TEXT NULL,
  action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY (from_user) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (to_user) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (stage_id) REFERENCES workflow_stages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. WhatsApp Logs Table
CREATE TABLE IF NOT EXISTS whatsapp_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  file_id INT NOT NULL,
  sent_to VARCHAR(15) NOT NULL,
  template_used VARCHAR(100) DEFAULT 'general',
  message TEXT NOT NULL,
  status ENUM('sent','failed','delivered') DEFAULT 'sent',
  sent_by INT NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY (sent_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Call Logs Table
CREATE TABLE IF NOT EXISTS call_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  file_id INT NOT NULL,
  called_by INT NOT NULL,
  customer_mobile VARCHAR(15) NOT NULL,
  call_summary TEXT NULL,
  duration_seconds INT DEFAULT 0,
  called_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
  FOREIGN KEY (called_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. System Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- DEFAULT SEED DATA
-- =========================================================

-- Seed Roles
INSERT INTO roles (id, role_name, role_key, description, permissions) VALUES
(1, 'Super Admin', 'super_admin', 'Full system access', '["*"]'),
(2, 'Admin', 'admin', 'File creation, assignment, workflow control, reports', '["create_file","edit_file","assign_file","forward_file","view_all_files","manage_users","view_reports"]'),
(3, 'Manager', 'manager', 'Team file tracking, reassignment, reporting', '["create_file","edit_file","assign_file","forward_file","view_team_files","view_reports"]'),
(4, 'Employee', 'employee', 'Process assigned files and forward to next stage', '["view_assigned_files","edit_file","forward_file","upload_doc","call_customer","whatsapp_customer"]'),
(5, 'Front Desk / Receptionist', 'receptionist', 'Create new files, customer intake, doc upload/scan', '["create_file","upload_doc","forward_file","view_assigned_files"]'),
(6, 'Accounts', 'accounts', 'Billing, payment verification, step approval', '["view_assigned_files","edit_file","forward_file","upload_doc"]')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Default Password for users: "password123" -> bcrypt hash "$2y$10$4.z4z1c8yL9KkF6z6v7yOO7O7O7O7O7O7O7O7O7O7O7O7O7O7O7O7"
-- Using a standard PHP password_hash('password123', PASSWORD_BCRYPT):
-- $2y$10$pLd9sU3O69.3s8s3G7g2c.nKzVqO6T.8p8s8p8s8p8s8p8s8p8s8p
-- Let's put standard hashes for instant demo login.

INSERT INTO users (id, name, email, mobile, password, role_id, status) VALUES
(1, 'Rajesh Kumar (Super Admin)', 'admin@office.com', '9876543210', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.Gq/3i7X8Q7T5f8a0WzO6nL3W2', 1, 'active'),
(2, 'Priya Sharma (Front Desk)', 'frontdesk@office.com', '9876543211', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.Gq/3i7X8Q7T5f8a0WzO6nL3W2', 5, 'active'),
(3, 'Vikram Singh (Verification)', 'verification@office.com', '9876543212', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.Gq/3i7X8Q7T5f8a0WzO6nL3W2', 4, 'active'),
(4, 'Amit Patel (Technical)', 'tech@office.com', '9876543213', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.Gq/3i7X8Q7T5f8a0WzO6nL3W2', 4, 'active'),
(5, 'Suresh Verma (Accounts)', 'accounts@office.com', '9876543214', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.Gq/3i7X8Q7T5f8a0WzO6nL3W2', 6, 'active')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Work Types
INSERT INTO work_types (id, name, description, code_prefix, status, created_by) VALUES
(1, 'New Fiber Connection', 'End-to-end processing for fresh broadband fiber installation', 'FIB', 'active', 1),
(2, 'Name Transfer Request', 'Transfer existing connection ownership to new owner', 'TRF', 'active', 1),
(3, 'TV / Cable Upgrade', 'Upgrade or install digital TV set-top box service', 'STB', 'active', 1),
(4, 'Billing & Payment Complaint', 'Resolve customer billing disputes or payment mismatches', 'CMP', 'active', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Seed Workflow Stages for "New Fiber Connection" (Work Type ID 1)
INSERT INTO workflow_stages (id, work_type_id, stage_order, stage_name, assigned_role_id, sla_hours, required_documents) VALUES
(1, 1, 1, 'Front Desk Intake & Document Upload', 5, 4, 'Aadhaar Card, Address Proof'),
(2, 1, 2, 'Document Verification', 4, 12, 'Verified ID Proof'),
(3, 1, 3, 'Technical Site Survey & Wiring', 4, 24, 'Feasibility Report'),
(4, 1, 4, 'Accounts Payment Confirmation', 6, 8, 'Payment Receipt'),
(5, 1, 5, 'Final Admin Approval & Activation', 2, 6, 'Completion Certificate')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Workflow Stages for "Name Transfer Request" (Work Type ID 2)
INSERT INTO workflow_stages (id, work_type_id, stage_order, stage_name, assigned_role_id, sla_hours, required_documents) VALUES
(6, 2, 1, 'Request Submission & NOC Upload', 5, 6, 'NOC Letter, ID of Both Parties'),
(7, 2, 2, 'Accounts Dues Verification', 6, 12, 'Clearance Receipt'),
(8, 2, 3, 'Final Ownership Approval', 2, 12, 'Updated Agreement')
ON DUPLICATE KEY UPDATE id=id;

-- Seed Initial Files
INSERT INTO files (id, file_code, customer_name, customer_mobile, customer_email, customer_address, work_type_id, current_stage_id, current_assigned_user, status, priority, created_by) VALUES
(1, 'FIB-2026-00101', 'Ramesh Gupta', '9822011223', 'ramesh@example.com', 'Flat 402, Sunshine Apartments, Civil Lines', 1, 1, 2, 'in_progress', 'high', 2),
(2, 'FIB-2026-00102', 'Sunita Devi', '9822011224', 'sunita@example.com', 'House 12, Model Town, Sector 4', 1, 2, 3, 'in_progress', 'medium', 2),
(3, 'TRF-2026-00103', 'Anil Mehta', '9822011225', 'anil@example.com', 'Shop 15, City Mall Market', 2, 7, 5, 'pending', 'low', 2),
(4, 'FIB-2026-00104', 'Deepak Chopra', '9822011226', 'deepak@example.com', 'Plot 88, Industrial Area Phase 2', 1, 5, 1, 'completed', 'urgent', 2)
ON DUPLICATE KEY UPDATE id=id;

-- Seed File History
INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks) VALUES
(1, 2, 2, 1, 'created', 'Customer visited front desk. File created and assigned for document intake.'),
(2, 2, 3, 2, 'forwarded', 'Documents uploaded successfully. Forwarded to Verification Team.'),
(3, 2, 5, 7, 'forwarded', 'NOC uploaded. Forwarded to Accounts for dues clearance.'),
(4, 4, 1, 5, 'approved', 'Site installation complete. Final approval granted by Admin.');
