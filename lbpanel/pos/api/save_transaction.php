<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../../config/auth_guard.php';
}

header('Content-Type: application/json');
require_once '../../config/connection.php';

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Generate unique transaction reference ID
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    $transaction_ref = "LO-TXN-" . $timestamp . "-" . $random;
    
    // Insert main transaction
    $query = "INSERT INTO pos_transactions (
        subtotal, 
        tax, 
        total, 
        payment_method, 
        amount_tendered, 
        change_given, 
        items_sold,
        customer_name,
        customer_phone,
        payment_app,
        transaction_id,
        transaction_ref
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "dddsddssssss",
        $data['subtotal'],
        $data['tax'],
        $data['total'],
        $data['payment_method'],
        $data['amount_tendered'],
        $data['change_given'],
        json_encode($data['items']),
        $data['customer_name'],
        $data['customer_phone'],
        $data['payment_app'],
        $data['transaction_id'],
        $transaction_ref
    );
    $stmt->execute();
    
    $transactionId = $conn->insert_id;
    
    // Insert transaction items and update product quantities
    foreach ($data['items'] as $item) {
        // Insert transaction item
        $query = "INSERT INTO pos_transaction_items (
            transaction_id, 
            product_id, 
            product_name, 
            quantity, 
            unit_price, 
            total_price
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iisidd",
            $transactionId,
            $item['product_id'],
            $item['product_name'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_price']
        );
        $stmt->execute();
        
        // Update product quantity
        $query = "UPDATE products SET quantity = quantity - ?, sold_quantity = sold_quantity + ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "iii",
            $item['quantity'],
            $item['quantity'],
            $item['product_id']
        );
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'transaction_id' => $transactionId,
        'transaction_ref' => $transaction_ref
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>