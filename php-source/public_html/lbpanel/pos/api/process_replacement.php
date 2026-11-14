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
$required = ['original_transaction_id', 'reason', 'replacements'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$transactionId = (int)$_POST['original_transaction_id'];
$reason = trim($_POST['reason']);
$replacements = json_decode($_POST['replacements'], true);

if (!is_array($replacements)) {
    echo json_encode(['success' => false, 'message' => 'Invalid replacements data']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Validate each replacement item
    foreach ($replacements as $replacement) {
        // Check if item is available for replacement (not fully refunded)
        $stmt = $conn->prepare("
            SELECT 
                ti.quantity as original_qty,
                COALESCE(SUM(ri.quantity), 0) as refunded_qty
            FROM pos_transaction_items ti
            LEFT JOIN pos_refund_items ri ON ri.transaction_item_id = ti.id
            WHERE ti.id = ? AND ti.transaction_id = ?
            GROUP BY ti.id
        ");
        $stmt->bind_param("ii", $replacement['original_item_id'], $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $itemData = $result->fetch_assoc();
        $stmt->close();
        
        if (!$itemData) {
            throw new Exception("Invalid original item ID: {$replacement['original_item_id']}");
        }
        
        $availableQty = $itemData['original_qty'] - $itemData['refunded_qty'];
        if ($availableQty <= 0) {
            throw new Exception("Item {$replacement['original_item_id']} is not available for replacement (fully refunded)");
        }
        
        if ($replacement['quantity'] > $availableQty) {
            throw new Exception("Cannot replace more than {$availableQty} of item {$replacement['original_item_id']}");
        }
        
        // Verify new product exists and has stock
        $stmt = $conn->prepare("
            SELECT quantity, product_name 
            FROM products 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $replacement['new_product_id']);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$product) {
            throw new Exception("Invalid replacement product ID: {$replacement['new_product_id']}");
        }
        
        if ($product['quantity'] < $replacement['quantity']) {
            throw new Exception("Insufficient stock for {$product['product_name']}");
        }
    }
    
    // Create replacement record
    $stmt = $conn->prepare("
        INSERT INTO pos_replacements (
            original_transaction_id,
            replacement_reason,
            processed_by
        ) VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $transactionId, $reason, $_SESSION['username']);
    $stmt->execute();
    $replacementId = $conn->insert_id;
    $stmt->close();
    
    // Process each replacement
    foreach ($replacements as $replacement) {
        // Add replacement item
        $stmt = $conn->prepare("
            INSERT INTO pos_replacement_items (
                replacement_id,
                original_item_id,
                new_product_id,
                quantity
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiii", 
            $replacementId,
            $replacement['original_item_id'],
            $replacement['new_product_id'],
            $replacement['quantity']
        );
        $stmt->execute();
        $stmt->close();
        
        // Update inventory (remove new product, restore original)
        $stmt = $conn->prepare("
            UPDATE products 
            SET quantity = quantity - ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $replacement['quantity'], $replacement['new_product_id']);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("
            UPDATE products 
            SET quantity = quantity + ? 
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $replacement['original_quantity'], $replacement['original_product_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'replacement_id' => $replacementId
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error processing replacement: ' . $e->getMessage()
    ]);
}
?>
