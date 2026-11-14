<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

// Retrieve filter data
$fromDate = isset($_POST['from_date']) ? $_POST['from_date'] : '';
$toDate = isset($_POST['to_date']) ? $_POST['to_date'] : '';

// Query to fetch sales data
$query = "
    SELECT 
        o.order_number AS 'Order ID', 
        o.customer_name AS 'Client Name', 
        oi.product_name AS 'Product Name', 
        oi.price AS 'Price', 
        oi.quantity AS 'Quantity', 
        (oi.price * oi.quantity) AS 'Subtotal', 
        o.discount_amount AS 'Discount', 
        o.total_amount AS 'Total Amount', 
        o.status AS 'Status', 
        o.order_date AS 'Order Date'
    FROM 
        order_items AS oi
    INNER JOIN 
        orders AS o ON oi.order_id = o.order_number
    WHERE 1=1
";

// Apply date filters if provided
if ($fromDate && $toDate) {
    $query .= " AND o.order_date BETWEEN '$fromDate' AND '$toDate'";
}

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    $_SESSION['error'] = "No sales data found for the selected date range.";
    header('Location: sales_report.php');
    exit;
}

// Generate CSV file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales_report.csv');
$output = fopen('php://output', 'w');

// Add the column headers
fputcsv($output, [
    'Order ID',
    'Client Name',
    'Product Name',
    'Price',
    'Quantity',
    'Subtotal',
    'Discount',
    'Total Amount',
    'Status',
    'Order Date',
]);

// Add the data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['Order ID'],
        $row['Client Name'],
        $row['Product Name'],
        number_format($row['Price'], 2),
        $row['Quantity'],
        number_format($row['Subtotal'], 2),
        number_format($row['Discount'], 2),
        number_format($row['Total Amount'], 2),
        $row['Status'],
        $row['Order Date'],
    ]);
}

// Close the output stream
fclose($output);
exit;
?>
