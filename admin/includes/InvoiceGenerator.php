<?php
/**
 * Invoice Generator Class
 * Automatically creates invoices from orders
 */

class InvoiceGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create invoice from order
     * 
     * @param string $orderId Order ID
     * @return int Invoice ID
     * @throws Exception if order not found
     */
    public function createFromOrder($orderId) {
        // Get order data
        $stmt = $this->pdo->prepare("
            SELECT * FROM orders WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order $orderId not found");
        }
        
        // Check if invoice already exists for this order
        $stmt = $this->pdo->prepare("SELECT id FROM invoices WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $existingInvoice = $stmt->fetch();
        
        if ($existingInvoice) {
            throw new Exception("Invoice already exists for this order");
        }
        
        // Parse order details
        $orderData = json_decode($order['order_details'], true);
        
        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber();
        
        // Calculate totals
        $totalWithoutVat = 0;
        $vatTotal = 0;
        $items = [];
        
        // Process cart items
        if (isset($orderData['cart']) && is_array($orderData['cart'])) {
            foreach ($orderData['cart'] as $item) {
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = floatval($item['price'] ?? 0);
                $vatRate = 21.00; // Default VAT rate for CZ
                
                $totalItemWithoutVat = $quantity * $unitPrice;
                $vatAmount = $totalItemWithoutVat * ($vatRate / 100);
                $totalItemWithVat = $totalItemWithoutVat + $vatAmount;
                
                $totalWithoutVat += $totalItemWithoutVat;
                $vatTotal += $vatAmount;
                
                $items[] = [
                    'name' => $item['name'] ?? $item['title'] ?? 'Product',
                    'quantity' => $quantity,
                    'unit_price_without_vat' => $unitPrice,
                    'vat_rate' => $vatRate,
                    'total_without_vat' => $totalItemWithoutVat,
                    'vat_amount' => $vatAmount,
                    'total_with_vat' => $totalItemWithVat
                ];
            }
        }
        
        // Add shipping if present
        $shippingPrice = floatval($order['shipping_price'] ?? 0);
        if ($shippingPrice > 0) {
            $shippingVat = $shippingPrice * 0.21;
            $totalWithoutVat += $shippingPrice;
            $vatTotal += $shippingVat;
            
            $items[] = [
                'name' => 'Doprava',
                'quantity' => 1,
                'unit_price_without_vat' => $shippingPrice,
                'vat_rate' => 21.00,
                'total_without_vat' => $shippingPrice,
                'vat_amount' => $shippingVat,
                'total_with_vat' => $shippingPrice + $shippingVat
            ];
        }
        
        $totalWithVat = $totalWithoutVat + $vatTotal;
        
        // Handle wallet deduction
        $walletUsed = !empty($order['wallet_used']) ? 1 : 0;
        $walletAmount = floatval($order['wallet_amount'] ?? 0);
        $amountToPay = $totalWithVat - $walletAmount;
        
        // Insert invoice
        $stmt = $this->pdo->prepare("
            INSERT INTO invoices (
                invoice_number, order_id, issue_date, due_date, currency,
                total_without_vat, vat_total, total_with_vat,
                buyer_name, buyer_address1, buyer_city, buyer_zip,
                buyer_country, buyer_email, buyer_phone,
                status, payment_method,
                wallet_used, wallet_amount, amount_to_pay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $invoiceNumber,
            $orderId,
            date('Y-m-d'), // Issue date
            date('Y-m-d', strtotime('+14 days')), // Due date
            'CZK',
            $totalWithoutVat,
            $vatTotal,
            $totalWithVat,
            $order['full_name'] ?? $order['name'] ?? '',
            $order['address'] ?? '',
            $order['city'] ?? '',
            $order['zip'] ?? '',
            $order['country'] ?? 'Česká republika',
            $order['email'] ?? '',
            $order['phone'] ?? '',
            'issued', // Auto-mark as issued
            $order['payment_method'] ?? 'bank_transfer',
            $walletUsed,
            $walletAmount,
            $amountToPay
        ]);
        
        $invoiceId = $this->pdo->lastInsertId();
        
        // Insert invoice items
        $itemStmt = $this->pdo->prepare("
            INSERT INTO invoice_items (
                invoice_id, name, quantity, unit_price_without_vat,
                vat_rate, total_without_vat, vat_amount, total_with_vat
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $itemStmt->execute([
                $invoiceId,
                $item['name'],
                $item['quantity'],
                $item['unit_price_without_vat'],
                $item['vat_rate'],
                $item['total_without_vat'],
                $item['vat_amount'],
                $item['total_with_vat']
            ]);
        }
        
        return $invoiceId;
    }
    
    /**
     * Generate unique invoice number
     * 
     * @return string Invoice number
     */
    private function generateInvoiceNumber() {
        $stmt = $this->pdo->query("SELECT invoice_prefix, numbering_format FROM invoice_settings WHERE id = 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $prefix = $settings['invoice_prefix'] ?? 'KJD';
        $format = $settings['numbering_format'] ?? 'KJDYYYYMMNNN';
        
        $year = date('Y');
        $month = date('m');
        $period = $year . $month;
        
        // Get or create counter for this period
        $stmt = $this->pdo->prepare("
            INSERT INTO invoice_counters (period, last_number) 
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE last_number = last_number + 1
        ");
        $stmt->execute([$period]);
        
        $stmt = $this->pdo->prepare("SELECT last_number FROM invoice_counters WHERE period = ?");
        $stmt->execute([$period]);
        $number = $stmt->fetchColumn();
        
        // Format number
        $invoiceNumber = str_replace(
            ['YYYY', 'MM', 'NNN'],
            [$year, $month, str_pad($number, 3, '0', STR_PAD_LEFT)],
            $format
        );
        
        return $invoiceNumber;
    }
    
    /**
     * Update invoice status
     * 
     * @param int $invoiceId Invoice ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateStatus($invoiceId, $status) {
        $validStatuses = ['draft', 'issued', 'paid', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status: $status");
        }
        
        $stmt = $this->pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $invoiceId]);
    }
}
