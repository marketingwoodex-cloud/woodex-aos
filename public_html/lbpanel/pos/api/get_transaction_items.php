<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../../config/auth_guard.php';
}

require_once '../../config/connection.php';
header('Content-Type: application/json');

// Check if transaction_id is provided
if (!isset($_GET['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
    exit;
}

$transactionId = (int)$_GET['transaction_id'];
$withRefunds = isset($_GET['with_refunds']) ? true : false;

try {
    // Get transaction items with product details
    $query = "
        SELECT 
            ti.id,
            ti.transaction_id,
            ti.product_id,
            ti.quantity,
            ti.unit_price,
            p.product_name,
            p.product_sku
        FROM pos_transaction_items ti
        JOIN products p ON ti.product_id = p.id
        WHERE ti.transaction_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get refund data if requested
    if ($withRefunds) {
        foreach ($items as &$item) {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantity), 0) as refunded_quantity
                FROM pos_refund_items
                WHERE transaction_item_id = ?
            ");
            $stmt->bind_param("i", $item['id']);
            $stmt->execute();
            $refundResult = $stmt->get_result();
            $refundData = $refundResult->fetch_assoc();
            $stmt->close();
            
            $item['refunded_quantity'] = $refundData['refunded_quantity'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>