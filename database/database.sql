CREATE DATABASE IF NOT EXISTS great_solomon_ct4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE great_solomon_ct4;

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(60) NOT NULL DEFAULT 'Staff',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS admin_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 type VARCHAR(40) NOT NULL DEFAULT 'feedback',
 title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL,
 sender_name VARCHAR(120) NULL,
 sender_role VARCHAR(60) NULL,
 sender_user_id INT UNSIGNED NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id), INDEX(type), INDEX(is_read), INDEX(created_at),
 CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
ALTER TABLE admin_notifications ADD COLUMN IF NOT EXISTS sender_user_id INT UNSIGNED NULL AFTER sender_role;
ALTER TABLE admin_notifications ADD INDEX IF NOT EXISTS idx_notification_sender_user (sender_user_id);
CREATE TABLE IF NOT EXISTS archive_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 item_type VARCHAR(40) NOT NULL,
 source_table VARCHAR(120) NOT NULL,
 source_id BIGINT UNSIGNED NULL,
 item_name VARCHAR(255) NOT NULL,
 payload LONGTEXT NOT NULL,
 file_data LONGBLOB NULL,
 file_type VARCHAR(180) NULL,
 deleted_by INT UNSIGNED NULL,
 deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(item_type), INDEX(source_table), INDEX(source_id), INDEX(deleted_at),
 CONSTRAINT fk_archive_user FOREIGN KEY(deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 module VARCHAR(120) NOT NULL,
 action VARCHAR(120) NOT NULL,
 details TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id), INDEX(module), INDEX(created_at),
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 email VARCHAR(190) NOT NULL,
 status ENUM('Success','Failed','OTP Pending') NOT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id), INDEX(email), INDEX(login_at),
 CONSTRAINT fk_login_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 otp_hash VARCHAR(255) NOT NULL,
 expires_at DATETIME NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(user_id), INDEX(expires_at),
 CONSTRAINT fk_otp_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS safety_incidents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 employee_name VARCHAR(120) NOT NULL,
 incident_date DATE NOT NULL,
 severity ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
 status ENUM('Open','Under Investigation','Closed') NOT NULL DEFAULT 'Open',
 description TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS health_records (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_name VARCHAR(120) NOT NULL,
 checkup_date DATE NOT NULL,
 record_type VARCHAR(120),
 fitness_status ENUM('Fit','Fit with Restrictions','Unfit','Pending') NOT NULL DEFAULT 'Pending',
 notes TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS health_safety_files (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_name VARCHAR(120) NOT NULL,
 requester_user_id INT UNSIGNED NULL,
 file_name VARCHAR(180) NOT NULL,
 file_type VARCHAR(120) NULL,
 storage_file_id BIGINT UNSIGNED NULL,
 action_type ENUM('Requested','Released') NOT NULL,
 request_date DATE NOT NULL,
 notes TEXT NULL,
 released_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(employee_name), INDEX(action_type), INDEX(request_date)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS compliance_obligations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 report_name VARCHAR(120) NULL,
 report_role VARCHAR(120) NULL,
 contact_no VARCHAR(60) NULL,
 compliance_note TEXT NULL,
 reported_at DATETIME NULL,
 category VARCHAR(120),
 owner VARCHAR(120),
 due_date DATE NOT NULL,
 priority ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
 status ENUM('Open','In Progress','Compliant','Overdue') NOT NULL DEFAULT 'Open',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS compliance_audits (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 audit_date DATE NOT NULL,
 auditor VARCHAR(120),
 status ENUM('Scheduled','In Progress','Completed','Closed') NOT NULL DEFAULT 'Scheduled',
 findings TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS security_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 event_type VARCHAR(100) NOT NULL,
 severity VARCHAR(30) NOT NULL DEFAULT 'Info',
 description TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_security_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS assets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_tag VARCHAR(80) NOT NULL UNIQUE,
 name VARCHAR(180) NOT NULL,
 category VARCHAR(100),
 serial_number VARCHAR(120),
 quantity INT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('Available','Issued','Maintenance','Retired') NOT NULL DEFAULT 'Available',
 location VARCHAR(180),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- Migration for existing installations created before quantity was added.
ALTER TABLE assets ADD COLUMN IF NOT EXISTS quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER serial_number;
CREATE TABLE IF NOT EXISTS asset_issuances (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_id INT UNSIGNED NOT NULL,
 employee_name VARCHAR(120) NOT NULL,
 issued_date DATE NOT NULL,
 expected_return DATE NULL,
 return_date DATE NULL,
 status ENUM('Issued','Returned','Overdue','Not Returned') NOT NULL DEFAULT 'Issued',
 notes TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_issuance_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS maintenance_records (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 asset_id INT UNSIGNED NOT NULL,
 service_date DATE NOT NULL,
 service_type VARCHAR(120),
 cost DECIMAL(12,2) DEFAULT 0,
 status VARCHAR(60) DEFAULT 'Completed',
 notes TEXT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_maintenance_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE users SET role='Staff' WHERE role NOT IN ('Administrator','Staff');

INSERT INTO users(name,email,password_hash,role,active) VALUES
('Admin','adminct4@gmail.com','$2y$12$W3CxFvVU6NqcmG5VEempMeY4/gfboeUJdjQgxrzfLtgNCiFpDShQu','Administrator',1)
ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role='Administrator', active=1;

INSERT INTO users(name,email,password_hash,role,active) VALUES
('Staff','ct4staff@gmail.com','$2y$12$W3CxFvVU6NqcmG5VEempMeY4/gfboeUJdjQgxrzfLtgNCiFpDShQu','Staff',1)
ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), role='Staff', active=1;

INSERT INTO safety_incidents(title,employee_name,incident_date,severity,status,description) VALUES
('Safety inspection finding','Juan Dela Cruz',CURDATE(),'Medium','Open','Initial sample incident for the database.'),
('Minor workplace injury','Maria Santos',DATE_SUB(CURDATE(),INTERVAL 2 DAY),'Low','Closed','Sample closed incident.');
INSERT INTO health_records(employee_name,checkup_date,record_type,fitness_status,notes) VALUES
('Juan Dela Cruz',CURDATE(),'Annual Checkup','Fit','Sample health record.');
INSERT INTO compliance_obligations(title,category,owner,due_date,priority,status) VALUES
('Annual workplace compliance review','Regulatory','Compliance Officer',DATE_ADD(CURDATE(),INTERVAL 30 DAY),'High','Open'),
('Permit renewal','Permit','Administration',DATE_ADD(CURDATE(),INTERVAL 10 DAY),'Medium','In Progress');
INSERT INTO compliance_audits(title,audit_date,auditor,status,findings) VALUES
('Annual compliance audit',DATE_ADD(CURDATE(),INTERVAL 7 DAY),'Internal Audit','Scheduled','Sample audit schedule.');
INSERT INTO assets(asset_tag,name,category,serial_number,status,quantity,location) VALUES
('AST-0001','Laptop - Admin','Computer','SN-GSMS-0001','Issued',1,'Head Office'),
('AST-0002','Desktop Workstation','Computer','SN-GSMS-0002','Available',1,'Head Office'),
('AST-0003','Network Printer','Printer','SN-GSMS-0003','Maintenance',1,'IT Room')
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO asset_issuances(asset_id,employee_name,issued_date,expected_return,status,notes) VALUES
(1,'Admin User',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 365 DAY),'Issued','Sample issuance.');
UPDATE assets SET status='Issued' WHERE asset_tag='AST-0001';

-- Additional demo issuance history records
INSERT INTO assets(asset_tag,name,category,serial_number,status,quantity,location) VALUES
('AST-0004','Company Tablet','Mobile Device','SN-GSMS-0004','Available',1,'Head Office'),
('AST-0005','Projector','Presentation','SN-GSMS-0005','Issued',1,'Training Room'),
('AST-0006','Office Laptop','Computer','SN-GSMS-0006','Available',1,'Operations')
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO asset_issuances(asset_id,employee_name,issued_date,expected_return,status,notes)
SELECT id,'Maria Santos',DATE_SUB(CURDATE(),INTERVAL 5 DAY),DATE_ADD(CURDATE(),INTERVAL 10 DAY),'Returned','Demo returned issuance.'
FROM assets WHERE asset_tag='AST-0004'
AND NOT EXISTS (SELECT 1 FROM asset_issuances i WHERE i.asset_id=assets.id AND i.employee_name='Maria Santos');
INSERT INTO asset_issuances(asset_id,employee_name,issued_date,expected_return,status,notes)
SELECT id,'Juan Dela Cruz',DATE_SUB(CURDATE(),INTERVAL 3 DAY),DATE_ADD(CURDATE(),INTERVAL 7 DAY),'Not Returned','Demo active issuance.'
FROM assets WHERE asset_tag='AST-0005'
AND NOT EXISTS (SELECT 1 FROM asset_issuances i WHERE i.asset_id=assets.id AND i.employee_name='Juan Dela Cruz');

UPDATE assets SET status='Issued' WHERE asset_tag='AST-0005';

-- CT4 Data Storage: files/data received from other branches
CREATE TABLE IF NOT EXISTS data_storage (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 file_name VARCHAR(255) NOT NULL,
 file_type VARCHAR(150) NOT NULL DEFAULT 'application/octet-stream',
 file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
 source_branch VARCHAR(180) NULL,
 uploaded_by INT UNSIGNED NULL,
 file_data LONGBLOB NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(uploaded_by), INDEX(created_at),
 CONSTRAINT fk_storage_user FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
