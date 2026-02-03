<?php
/**
 * Invoice REST API
 * Provides endpoints for invoice management and PDF generation
 * 
 * Endpoints:
 * - GET ?action=get&id=X - Get invoice data
 * - GET ?action=list - List all invoices
 * - POST ?action=create - Create new invoice
 * - POST ?action=generate_pdf - Generate PDF for invoice
 * - POST ?action=send_email - Send invoice via email
 * - POST ?action=update_status - Update invoice status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db_connect.php';

// Helper function to send JSON response
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            // Get single invoice
            $id = $_GET['id'] ?? null;
            if (!$id) {
                sendResponse(false, null, 'Invoice ID is required', 400);
            }
            
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       GROUP_CONCAT(
                           JSON_OBJECT(
                               'id', ii.id,
                               'name', ii.name,
                               'quantity', ii.quantity,
                               'unit_price_without_vat', ii.unit_price_without_vat,
                               'vat_rate', ii.vat_rate,
                               'total_without_vat', ii.total_without_vat,
                               'vat_amount', ii.vat_amount,
                               'total_with_vat', ii.total_with_vat
                           )
                       ) as items
                FROM invoices i
                LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
                WHERE i.id = ?
                GROUP BY i.id
            ");
            $stmt->execute([$id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                sendResponse(false, null, 'Invoice not found', 404);
            }
            
            // Parse items JSON
            if ($invoice['items']) {
                $invoice['items'] = json_decode('[' . $invoice['items'] . ']', true);
            } else {
                $invoice['items'] = [];
            }
            
            sendResponse(true, $invoice, 'Invoice retrieved successfully');
            break;
            
        case 'list':
            // List all invoices with pagination
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $offset = ($page - 1) * $limit;
            
            $status = $_GET['status'] ?? null;
            $search = $_GET['search'] ?? '';
            
            $where = ['1=1'];
            $params = [];
            
            if ($status) {
                $where[] = 'status = ?';
                $params[] = $status;
            }
            
            if ($search) {
                $where[] = '(invoice_number LIKE ? OR buyer_name LIKE ? OR buyer_email LIKE ?)';
                $searchParam = '%' . $search . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get invoices
            $stmt = $pdo->prepare("
                SELECT * FROM invoices 
                WHERE $whereClause
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $stmt->execute($params);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse(true, [
                'invoices' => $invoices,
                'pagination' => [
                    'total' => $total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ], 'Invoices retrieved successfully');
            break;
            
        case 'create':
            // Create new invoice from order or manually
            $input = json_decode(file_get_contents('php://input'), true);
            
            $orderId = $input['order_id'] ?? null;
            
            if ($orderId) {
                // Create from order
                require_once __DIR__ . '/../../includes/InvoiceGenerator.php';
                $generator = new InvoiceGenerator($pdo);
                $invoiceId = $generator->createFromOrder($orderId);
                
                sendResponse(true, ['invoice_id' => $invoiceId], 'Invoice created from order successfully');
            } else {
                // Manual creation
                $required = ['buyer_name', 'items'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        sendResponse(false, null, "Field '$field' is required", 400);
                    }
                }
                
                // Generate invoice number
                $invoiceNumber = generateInvoiceNumber($pdo);
                
                // Calculate totals
                $totalWithoutVat = 0;
                $vatTotal = 0;
                
                foreach ($input['items'] as $item) {
                    $totalWithoutVat += $item['total_without_vat'];
                    $vatTotal += $item['vat_amount'];
                }
                
                $totalWithVat = $totalWithoutVat + $vatTotal;
                
                // Insert invoice
                $stmt = $pdo->prepare("
                    INSERT INTO invoices (
                        invoice_number, order_id, issue_date, due_date, currency,
                        total_without_vat, vat_total, total_with_vat,
                        buyer_name, buyer_address1, buyer_city, buyer_zip,
                        buyer_country, buyer_email, buyer_phone,
                        status, payment_method
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $invoiceNumber,
                    $orderId,
                    $input['issue_date'] ?? date('Y-m-d'),
                    $input['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
                    $input['currency'] ?? 'CZK',
                    $totalWithoutVat,
                    $vatTotal,
                    $totalWithVat,
                    $input['buyer_name'],
                    $input['buyer_address1'] ?? '',
                    $input['buyer_city'] ?? '',
                    $input['buyer_zip'] ?? '',
                    $input['buyer_country'] ?? 'Česká republika',
                    $input['buyer_email'] ?? '',
                    $input['buyer_phone'] ?? '',
                    'draft',
                    $input['payment_method'] ?? 'bank_transfer'
                ]);
                
                $invoiceId = $pdo->lastInsertId();
                
                // Insert items
                $itemStmt = $pdo->prepare("
                    INSERT INTO invoice_items (
                        invoice_id, name, quantity, unit_price_without_vat,
                        vat_rate, total_without_vat, vat_amount, total_with_vat
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($input['items'] as $item) {
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
                
                sendResponse(true, ['invoice_id' => $invoiceId], 'Invoice created successfully');
            }
            break;
            
        case 'update_status':
            // Update invoice status
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? null;
            $status = $input['status'] ?? null;
            
            if (!$id || !$status) {
                sendResponse(false, null, 'Invoice ID and status are required', 400);
            }
            
            $validStatuses = ['draft', 'issued', 'paid', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                sendResponse(false, null, 'Invalid status', 400);
            }
            
            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            sendResponse(true, null, 'Invoice status updated successfully');
            break;
            
        default:
            sendResponse(false, null, 'Invalid action', 400);
    }
    
} catch (PDOException $e) {
    error_log('Invoice API Error: ' . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log('Invoice API Error: ' . $e->getMessage());
    sendResponse(false, null, 'Error: ' . $e->getMessage(), 500);
}

/**
 * Generate invoice number based on settings
 */
function generateInvoiceNumber($pdo) {
    $stmt = $pdo->query("SELECT invoice_prefix, numbering_format FROM invoice_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $prefix = $settings['invoice_prefix'] ?? 'KJD';
    $format = $settings['numbering_format'] ?? 'KJDYYYYMMNNN';
    
    $year = date('Y');
    $month = date('m');
    $period = $year . $month;
    
    // Get or create counter for this period
    $stmt = $pdo->prepare("
        INSERT INTO invoice_counters (period, last_number) 
        VALUES (?, 1)
        ON DUPLICATE KEY UPDATE last_number = last_number + 1
    ");
    $stmt->execute([$period]);
    
    $stmt = $pdo->prepare("SELECT last_number FROM invoice_counters WHERE period = ?");
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
