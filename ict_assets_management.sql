-- =============================================
-- ICT ASSET MANAGEMENT SYSTEM
-- COMPLETE DATABASE SCRIPT (PRODUCTION READY)
-- =============================================

-- ---------------------------------------------
-- 1. CREATE DATABASE
-- ---------------------------------------------
CREATE DATABASE IF NOT EXISTS ict_asset_management;
USE ict_asset_management;

-- ---------------------------------------------
-- 2. MASTER TABLES
-- ---------------------------------------------

-- Asset Categories
CREATE TABLE asset_categories (
                                  category_id INT AUTO_INCREMENT PRIMARY KEY,
                                  category_name VARCHAR(100) NOT NULL UNIQUE,
                                  description TEXT,
                                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vendors
CREATE TABLE vendors (
                         vendor_id INT AUTO_INCREMENT PRIMARY KEY,
                         vendor_name VARCHAR(150) NOT NULL,
                         contact_person VARCHAR(100),
                         phone VARCHAR(20),
                         email VARCHAR(150),
                         address TEXT,
                         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Locations
CREATE TABLE locations (
                           location_id INT AUTO_INCREMENT PRIMARY KEY,
                           dept_name VARCHAR(150) NOT NULL,
                           building VARCHAR(100),
                           floor VARCHAR(50),
                           remarks TEXT,
                           created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Asset Status
CREATE TABLE asset_status (
                              status_id INT AUTO_INCREMENT PRIMARY KEY,
                              status_name VARCHAR(50) NOT NULL UNIQUE,
                              description TEXT
) ENGINE=InnoDB;

-- Users
CREATE TABLE users (
                       user_id INT AUTO_INCREMENT PRIMARY KEY,
                       name VARCHAR(150) NOT NULL,
                       email VARCHAR(150) UNIQUE,
                       phone VARCHAR(20),
                       role ENUM('Admin','ICT Staff','Employee') DEFAULT 'Employee',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------
-- 3. CORE TABLES
-- ---------------------------------------------

-- Assets
CREATE TABLE assets (
                        asset_id INT AUTO_INCREMENT PRIMARY KEY,
                        asset_name VARCHAR(150) NOT NULL,
                        serial_number VARCHAR(150) UNIQUE,
                        category_id INT,
                        vendor_id INT,
                        location_id INT,
                        status_id INT,
                        purchase_date DATE,
                        warranty_expiry DATE,
                        cost DECIMAL(12,2),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                        FOREIGN KEY (category_id) REFERENCES asset_categories(category_id)
                            ON DELETE SET NULL ON UPDATE CASCADE,

                        FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
                            ON DELETE SET NULL ON UPDATE CASCADE,

                        FOREIGN KEY (location_id) REFERENCES locations(location_id)
                            ON DELETE SET NULL ON UPDATE CASCADE,

                        FOREIGN KEY (status_id) REFERENCES asset_status(status_id)
                            ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Asset Assignments
CREATE TABLE asset_assignments (
                                   assignment_id INT AUTO_INCREMENT PRIMARY KEY,
                                   asset_id INT NOT NULL,
                                   user_id INT NOT NULL,
                                   assigned_date DATE,
                                   returned_date DATE,
                                   remarks TEXT,

                                   FOREIGN KEY (asset_id) REFERENCES assets(asset_id)
                                       ON DELETE CASCADE ON UPDATE CASCADE,

                                   FOREIGN KEY (user_id) REFERENCES users(user_id)
                                       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Maintenance Records
CREATE TABLE maintenance_records (
                                     maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
                                     asset_id INT NOT NULL,
                                     issue_description TEXT,
                                     maintenance_date DATE,
                                     cost DECIMAL(10,2),
                                     vendor_id INT,
                                     remarks TEXT,

                                     FOREIGN KEY (asset_id) REFERENCES assets(asset_id)
                                         ON DELETE CASCADE ON UPDATE CASCADE,

                                     FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
                                         ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Documents
CREATE TABLE documents (
                           document_id INT AUTO_INCREMENT PRIMARY KEY,
                           asset_id INT,
                           file_name VARCHAR(255),
                           file_path VARCHAR(255),
                           uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                           FOREIGN KEY (asset_id) REFERENCES assets(asset_id)
                               ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------
-- 4. MASTER DATA INSERTS
-- ---------------------------------------------

INSERT INTO asset_status (status_name) VALUES
                                           ('Working'),
                                           ('Under Repair'),
                                           ('Spare'),
                                           ('Condemned');

INSERT INTO asset_categories (category_name) VALUES
                                                 ('Laptop'),
                                                 ('Desktop'),
                                                 ('Printer'),
                                                 ('Router'),
                                                 ('Server');

-- ---------------------------------------------
-- 5. PERFORMANCE INDEXING (INDUSTRY LEVEL)
-- ---------------------------------------------

-- Foreign Key Indexes
CREATE INDEX idx_assets_category ON assets(category_id);
CREATE INDEX idx_assets_vendor ON assets(vendor_id);
CREATE INDEX idx_assets_location ON assets(location_id);
CREATE INDEX idx_assets_status ON assets(status_id);

CREATE INDEX idx_assign_asset ON asset_assignments(asset_id);
CREATE INDEX idx_assign_user ON asset_assignments(user_id);

CREATE INDEX idx_maint_asset ON maintenance_records(asset_id);
CREATE INDEX idx_maint_vendor ON maintenance_records(vendor_id);

CREATE INDEX idx_doc_asset ON documents(asset_id);

-- Search Indexes
CREATE INDEX idx_asset_name ON assets(asset_name);
CREATE INDEX idx_user_name ON users(name);
CREATE INDEX idx_vendor_name ON vendors(vendor_name);

-- Composite Indexes
CREATE INDEX idx_assets_status_location
    ON assets(status_id, location_id);

CREATE INDEX idx_assign_active
    ON asset_assignments(asset_id, returned_date);

-- ---------------------------------------------
-- 6. TABLE OPTIMIZATION
-- ---------------------------------------------
OPTIMIZE TABLE assets;
OPTIMIZE TABLE asset_assignments;

-- =============================================
-- END OF SCRIPT
-- =============================================