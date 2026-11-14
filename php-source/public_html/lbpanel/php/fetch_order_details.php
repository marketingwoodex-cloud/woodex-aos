<?php
require_once __DIR__ . '/../config/auth_guard.php';
include '../config/connection.php';

if (isset($_GET['order_id'])) {
    $orderNumber = $_GET['order_id']; // This is the order_number passed from the frontend

    // Fetch customer details including new fields from the `orders` table
    $stmt = $conn->prepare("
        SELECT 
            customer_name, customer_email, customer_phone, shipping_address, 
            coupon_code, discount_amount, shipping_cost, total_amount 
        FROM orders 
        WHERE order_number = ?
    ");
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $customerResult = $stmt->get_result();

    // If no order found, return an error
    if ($customerResult->num_rows === 0) {
        echo json_encode(['error' => 'Order not found.']);
        exit();
    }

    $customerDetails = $customerResult->fetch_assoc();
    $stmt->close();

    // Fetch order items using the `order_number` directly
    // Note: order_id in order_items now refers to order_number (string)
    $stmt = $conn->prepare("
        SELECT product_name, price, quantity, subtotal 
        FROM order_items 
        WHERE order_id = ?
    ");
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    $orderItems = [];
    while ($row = $result->fetch_assoc()) {
        $orderItems[] = $row;
    }
    $stmt->close();

    // Combine customer details and order items in the response
    $response = [
        'customer_details' => $customerDetails,
        'order_items' => $orderItems
    ];

    echo json_encode($response);
    exit();
} else {
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}
?>
