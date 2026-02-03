-- Ultra simple update script for vouchers table
-- Just add the recipient_email column

-- Add recipient_email column
ALTER TABLE vouchers ADD COLUMN recipient_email VARCHAR(255) NULL COMMENT 'Optional: email where voucher was sent';

-- Add a comment to the table
ALTER TABLE vouchers COMMENT = 'Vouchers table - supports both email-specific and universal vouchers';
