<?php
require_once __DIR__ . '/../../config/auth_guard.php';
header('Content-Type: application/json');
require_once '../../config/connection.php';

try {
    $query = "SELECT * FROM products WHERE quantity > 0 ORDER BY product_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>