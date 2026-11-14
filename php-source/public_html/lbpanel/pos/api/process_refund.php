<?php
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../config/session.php';
wdx_session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
$required = ['transaction_id', 'reason', 'items'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$transactionId = (int)$_POST['transaction_id'];
$reason = trim($_POST['reason']);
$items = json_decode($_POST['items'], true);

if (!is_array($items) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid items data']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Validate each item can be refunded
    foreach ($items as $item) {
        // Check if item exists in original transaction
        $stmt = $conn->prepare("
            SELECT ti.quantity, COALESCE(SUM(ri.quantity), 0) as refunded_qty
            FROM pos_transaction_items ti
            LEFT JOIN pos_refund_items ri ON ri.transaction_item_id = ti.id
            WHERE ti.id = ? AND ti.transaction_id = ?
            GROUP BY ti.id
        ");
        $stmt->bind_param("ii", $item['transaction_item_id'], $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $itemData = $result->fetch_assoc();
        $stmt->close();
        
        if (!$itemData) {
            throw new Exception("Invalid item ID: {$item['transaction_item_id']}");
        }
        
        $maxRefundable = $itemData['quantity'] - $itemData['refunded_qty'];
        if ($item['quantity'] > $maxRefundable) {
            throw new Exception("Cannot refund more than {$maxRefundable} of item {$item['transaction_item_id']}");
        }
    }
    
    // Generate unique refund reference
    $refundRef = "RF-" . time() . "-" . mt_rand(1000, 9999);
    $totalRefund = 0;
    
    // Calculate total refund
    foreach ($items as $item) {
        $totalRefund += (float)$item['refund_amount'];
    }

    // Insert refund record
    $stmt = $conn->prepare("
        INSERT INTO pos_refunds (
            original_transaction_id,
            refund_amount,
            refund_reason,
            processed_by,
            refund_ref
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("idsss", 
        $transactionId,
        $totalRefund,
        $reason,
        $_SESSION['username'],
        $refundRef
    );
    $stmt->execute();
    $refundId = $conn->insert_id;
    $stmt->close();
    
    // Process each item
    foreach ($items as $item) {
        // Insert refund item
        $stmt = $conn->prepare("
            INSERT INTO pos_refund_items (
                refund_id,
                transaction_item_id,
                product_id,
                quantity,
                refund_amount
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiid",
            $refundId,
            $item['transaction_item_id'],
            $item['product_id'],
            $item['quantity'],
            $item['refund_amount']
        );
        $stmt->execute();
        $stmt->close();
        
        // Update inventory
        $stmt = $conn->prepare("
            UPDATE products 
            SET quantity = quantity + ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'refund_id' => $refundId,
        'refund_amount' => $totalRefund,
        'refund_ref' => $refundRef
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error processing refund: ' . $e->getMessage()
    ]);
}
?>
