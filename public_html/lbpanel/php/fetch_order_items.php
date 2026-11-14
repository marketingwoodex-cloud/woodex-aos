<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

include '../config/connection.php';

if (isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    $stmt = $conn->prepare("SELECT product_name, price, quantity, subtotal FROM order_items WHERE order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".htmlspecialchars($row['product_name'])."</td>
                    <td>₨ ".htmlspecialchars($row['price'])."</td>
                    <td>".htmlspecialchars($row['quantity'])."</td>
                    <td>₨ ".htmlspecialchars($row['subtotal'])."</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center'>No items found for this order.</td></tr>";
    }
    $stmt->close();
}
?>
