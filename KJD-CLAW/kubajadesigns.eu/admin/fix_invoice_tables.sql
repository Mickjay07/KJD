-- Quick fix for missing invoice tables
-- Run this script to create the missing tables

-- Create invoice_counters table
CREATE TABLE IF NOT EXISTS invoice_counters (
    period VARCHAR(6) PRIMARY KEY,
    last_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create invoice_items table with correct structure
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price_without_vat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) DEFAULT 0.00,
    total_without_vat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_with_vat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id)
);

-- Create invoice_settings table
CREATE TABLE IF NOT EXISTS invoice_settings (
    id INT PRIMARY KEY DEFAULT 1,
    invoice_prefix VARCHAR(10) DEFAULT 'KJD',
    numbering_format VARCHAR(20) DEFAULT 'KJDYYYYMMNNN',
    company_name VARCHAR(255) DEFAULT 'KJD',
    company_address TEXT NULL,
    company_phone VARCHAR(50) NULL,
    company_email VARCHAR(255) NULL,
    company_ico VARCHAR(20) NULL,
    company_dic VARCHAR(20) NULL,
    bank_account VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings if not exists
INSERT IGNORE INTO invoice_settings (id) VALUES (1);

SELECT 'Missing invoice tables created successfully.' AS Status;
