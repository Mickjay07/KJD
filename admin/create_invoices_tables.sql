-- Create invoices table if it doesn't exist
-- This script creates the basic invoices table structure

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    order_id VARCHAR(50) NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency VARCHAR(3) DEFAULT 'CZK',
    total_without_vat DECIMAL(10,2) DEFAULT 0.00,
    vat_total DECIMAL(10,2) DEFAULT 0.00,
    total_with_vat DECIMAL(10,2) DEFAULT 0.00,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_address1 VARCHAR(255) NULL,
    buyer_address2 VARCHAR(255) NULL,
    buyer_city VARCHAR(100) NULL,
    buyer_zip VARCHAR(20) NULL,
    buyer_country VARCHAR(100) DEFAULT 'Česká republika',
    buyer_email VARCHAR(255) NULL,
    buyer_phone VARCHAR(50) NULL,
    status ENUM('draft', 'issued', 'paid', 'cancelled') DEFAULT 'draft',
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    wallet_used TINYINT(1) DEFAULT 0 COMMENT 'Whether user used wallet balance',
    wallet_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Amount deducted from wallet',
    amount_to_pay DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Final amount to be paid after wallet deduction',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_order_id (order_id),
    INDEX idx_issue_date (issue_date),
    INDEX idx_status (status)
);

-- Create invoice_items table if it doesn't exist
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

-- Create invoice_counters table if it doesn't exist
CREATE TABLE IF NOT EXISTS invoice_counters (
    period VARCHAR(6) PRIMARY KEY,
    last_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create invoice_settings table if it doesn't exist
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

SELECT 'Invoices tables created successfully.' AS Status;
