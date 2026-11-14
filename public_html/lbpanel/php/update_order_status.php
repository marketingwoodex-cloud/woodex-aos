<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

include '../config/connection.php';
require_once __DIR__ . '/../includes/email_config.php';

// Brand display name used in emails
$brandName = 'Woodex';
$logoUrl = rtrim((string)($domain ?? ''), '/') . '/assets/images/header-logo.png';
$emailSettings = get_email_settings($conn);
$emailEnabled = !empty($emailSettings['enabled'])
    && !empty($emailSettings['smtp_host'])
    && !empty($emailSettings['smtp_username'])
    && !empty($emailSettings['smtp_password'])
    && !empty($emailSettings['from_email']);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/SMTP.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['order_id'], $_POST['status'])
) {
    $orderId = $_POST['order_id'];
    $status  = $_POST['status'];

    // 1. Update the order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_number = ?");
    $stmt->bind_param('ss', $status, $orderId);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo 'Failed to update order status.';
        $stmt->close();
        exit;
    }
    $stmt->close();

    // 2. If the status is "Completed," do stock adjustments AND send "review" email
    if ($status === 'Completed') {
        // Fetch the ordered products and their quantities for this order
        $orderItemsStmt = $conn->prepare("
            SELECT product_id, quantity 
            FROM order_items 
            WHERE order_id = ?
        ");
        $orderItemsStmt->bind_param('s', $orderId);
        $orderItemsStmt->execute();
        $orderItemsResult = $orderItemsStmt->get_result();

        while ($orderItem = $orderItemsResult->fetch_assoc()) {
            $productId = $orderItem['product_id'];
            $quantity  = $orderItem['quantity'];

            // Increment sold_quantity and decrement quantity in products table
            $updateOrderQuantityStmt = $conn->prepare("
                UPDATE products 
                SET sold_quantity = sold_quantity + ?, 
                    quantity = quantity - ? 
                WHERE id = ? 
                  AND quantity >= ?
            ");
            $updateOrderQuantityStmt->bind_param(
                'iiii',
                $quantity,
                $quantity,
                $productId,
                $quantity
            );

            if ($updateOrderQuantityStmt->execute()) {
                if ($updateOrderQuantityStmt->affected_rows === 0) {
                    // Handle insufficient stock
                    echo "Error: Insufficient stock for product ID $productId.";
                    $updateOrderQuantityStmt->close();
                    $orderItemsStmt->close();
                    http_response_code(400);
                    exit;
                }
            } else {
                echo "Error updating product stock for product ID $productId.";
                $updateOrderQuantityStmt->close();
                $orderItemsStmt->close();
                http_response_code(500);
                exit;
            }
            $updateOrderQuantityStmt->close();
        }
        $orderItemsStmt->close();

        // 2.1 Fetch the order info to send a "review request" email
        $stmtFetch = $conn->prepare("
            SELECT customer_name, customer_email 
            FROM orders
            WHERE order_number = ?
            LIMIT 1
        ");
        $stmtFetch->bind_param('s', $orderId);
        $stmtFetch->execute();
        $res = $stmtFetch->get_result()->fetch_assoc();
        $stmtFetch->close();

        if ($res) {
            $custName  = $res['customer_name'];
            $custEmail = $res['customer_email'];

            // Build a "Please review" email
            $subject    = "We'd love your feedback on Order #{$orderId}";
            $reviewLink = rtrim((string)($domain ?? ''), '/') . "/review/{$orderId}";

            $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Review Your Order</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8f8f8; margin:0; padding:20px;">
  <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden;">
    <div style="background:#2B2B2B; text-align:center; padding:20px;">
      <img src="{$logoUrl}" alt="{$brandName} Logo" style="max-width:180px;">
    </div>
    <div style="padding:20px;">
      <h2>Hello, {$custName}!</h2>
      <p>We hope you're enjoying your purchase from {$brandName}. Your order <strong>#{$orderId}</strong> has been completed, and we'd love to hear your feedback!</p>
      <p>Please click the link below to rate the products in your order and leave a review:</p>
      <p>
        <a href="{$reviewLink}" 
           style="background:#007bff; color:#fff; padding:10px 15px; text-decoration:none; border-radius:4px;"
        >
          Review My Order
        </a>
      </p>
      <p style="margin-top:20px;">Thanks for choosing {$brandName}!</p>
    </div>
    <div style="background:#2B2B2B; text-align:center; padding:15px; color:#fff;">
      &copy; 2025 {$brandName} — All rights reserved.
    </div>
  </div>
</body>
</html>
HTML;

            if ($emailEnabled) {
                // Send email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // SMTP Configuration
                    $mail->isSMTP();
                    $mail->Host = $emailSettings['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $emailSettings['smtp_username'];
                    $mail->Password = $emailSettings['smtp_password'];
                    if (!empty($emailSettings['smtp_secure'])) {
                        $mail->SMTPSecure = $emailSettings['smtp_secure'];
                    }
                    $mail->Port = (int)($emailSettings['smtp_port'] ?? 465);

                    // Email Details
                    $fromName = !empty($emailSettings['from_name']) ? $emailSettings['from_name'] : $brandName;
                    $mail->setFrom($emailSettings['from_email'], $fromName);
                    $mail->addAddress($custEmail, $custName); // Recipient
                    if (!empty($emailSettings['reply_to_email'])) {
                        $rtName = !empty($emailSettings['reply_to_name']) ? $emailSettings['reply_to_name'] : $fromName;
                        $mail->addReplyTo($emailSettings['reply_to_email'], $rtName);
                    }

                    // Email Content
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body;

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Review email failed: " . $mail->ErrorInfo);
                }
            }
        }
        // else no order found, do nothing
    }

    // 3. If the status is "Delivered," store delivery info & send "dispatched" email
    if ($status === 'Delivered') {
        // Get the 3 new fields from POST
        $deliveryCompany = $_POST['delivery_company'] ?? '';
        $trackingURL     = $_POST['tracking_url']     ?? '';
        $trackingCode    = $_POST['tracking_code']    ?? '';

        // Update these columns in the `orders` table
        $stmtUpdate = $conn->prepare("
            UPDATE orders 
            SET delivery_company = ?, 
                tracking_url     = ?, 
                tracking_code    = ?
            WHERE order_number   = ?
        ");
        $stmtUpdate->bind_param(
            'ssss',
            $deliveryCompany,
            $trackingURL,
            $trackingCode,
            $orderId
        );

        if (!$stmtUpdate->execute()) {
            http_response_code(500);
            echo 'Failed to update delivery fields.';
            $stmtUpdate->close();
            exit;
        }
        $stmtUpdate->close();

        // Fetch the customer's email and relevant details
        $stmtFetch = $conn->prepare("
            SELECT customer_name, customer_email, total_amount, shipping_address
            FROM orders
            WHERE order_number = ?
            LIMIT 1
        ");
        $stmtFetch->bind_param('s', $orderId);
        $stmtFetch->execute();
        $resOrder = $stmtFetch->get_result()->fetch_assoc();
        $stmtFetch->close();

        if (!$resOrder) {
            // No order found - should not happen
            echo "Order not found for $orderId.";
            exit;
        }

        $customerName  = $resOrder['customer_name'];
        $customerEmail = $resOrder['customer_email'];
        $orderTotal    = number_format($resOrder['total_amount'], 2);
        $orderAddress  = $resOrder['shipping_address'];

        // Optionally fetch the order items
        $stmtItems = $conn->prepare("
            SELECT product_name, price, quantity, subtotal
            FROM order_items
            WHERE order_id = ?
        ");
        $stmtItems->bind_param('s', $orderId);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();

        $itemsHTML = '';
        while ($row = $itemsResult->fetch_assoc()) {
            $pName  = htmlspecialchars($row['product_name']);
            $pQty   = (int)$row['quantity'];
            $pPrice = number_format((float)$row['price'], 2);
            $pSub   = number_format((float)$row['subtotal'], 2);

            $itemsHTML .= "
              <tr>
                <td style='border:1px solid #e6e6e6; padding:8px;'>{$pName}</td>
                <td style='border:1px solid #e6e6e6; padding:8px; text-align:center;'>{$pQty}</td>
                <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR{$pPrice}</td>
                <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR{$pSub}</td>
              </tr>
            ";
        }
        $stmtItems->close();

        // Build a “Dispatched” email
        $subject = "Your Order #{$orderId} is on the Way!";
        $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Dispatched</title>
  <style>
    /* Minimal inline style for better email compatibility. */
    body, table, td { font-family: Arial, sans-serif; font-size:14px; color:#333; }
    .header { background:#2B2B2B; padding:20px; text-align:center; }
    .header img { max-width:180px; }
    .content { background:#fff; padding:20px; }
    .footer { background:#2B2B2B; color:#fff; text-align:center; padding:15px; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th, td { border:1px solid #e6e6e6; padding:8px; }
    th { background:#f7f7f7; }
  </style>
</head>
<body style="margin:0; padding:0; background:#f8f8f8;">
  <div style="max-width:600px; margin:20px auto; border-radius:6px; overflow:hidden;">
    <!-- Header -->
     <div class="header">
       <img src="{$logoUrl}" alt="{$brandName} Logo">
     </div>
    <!-- Content -->
    <div class="content">
      <h2 style="margin-top:0;">Hello, {$customerName}!</h2>
      <p style="font-size:14px;">
        We’re excited to let you know that your order <strong>#{$orderId}</strong> has been dispatched!<br>
        Here are your delivery details:
      </p>
      <ul style="list-style:none; padding-left:0;">
        <li><strong>Delivery Company:</strong> {$deliveryCompany}</li>
        <li><strong>Tracking Code:</strong> {$trackingCode}</li>
        <li><strong>Tracking URL:</strong> <a href="{$trackingURL}" target="_blank">Track Your Order</a></li>
      </ul>
      
      <h4>Your Order Items:</h4>
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {$itemsHTML}
        </tbody>
      </table>

      <p><strong>Order Total:</strong> PKR{$orderTotal}</p>
      <p><strong>Shipping Address:</strong> {$orderAddress}</p>
      
       <p style="margin-top:20px;">You can track your shipment using the link above. Thank you for shopping with {$brandName}!</p>
    </div>
    <!-- Footer -->
    <div class="footer">
      <p style="margin:0 0 10px 0;">Stay Connected!</p>
      <p style="margin:0;">
        <a href="https://www.instagram.com/streetwearofficial8558" 
           target="_blank" 
           style="color:#ffffff; text-decoration:none; margin:0 5px;"
        >
          Instagram
        </a> |
        <a href="https://www.facebook.com/profile.php?id=61578321626668" 
           target="_blank" 
           style="color:#ffffff; text-decoration:none; margin:0 5px;"
        >
          Facebook
        </a> |
        <a href="https://wa.me/923148880626" 
           target="_blank" 
           style="color:#ffffff; text-decoration:none; margin:0 5px;"
        >
          WhatsApp
        </a>
      </p>
       <p style="font-size:12px; color:#ccc; margin:10px 0 0 0;">&copy; 2025 {$brandName} — All rights reserved.</p>
    </div>
  </div>
</body>
</html>
HTML;

        if ($emailEnabled) {
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host = $emailSettings['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $emailSettings['smtp_username'];
                $mail->Password = $emailSettings['smtp_password'];
                if (!empty($emailSettings['smtp_secure'])) {
                    $mail->SMTPSecure = $emailSettings['smtp_secure'];
                }
                $mail->Port = (int)($emailSettings['smtp_port'] ?? 465);

                // Email Details
                $fromName = !empty($emailSettings['from_name']) ? $emailSettings['from_name'] : $brandName;
                $mail->setFrom($emailSettings['from_email'], $fromName);
                $mail->addAddress($customerEmail, $customerName); // Recipient
                if (!empty($emailSettings['reply_to_email'])) {
                    $rtName = !empty($emailSettings['reply_to_name']) ? $emailSettings['reply_to_name'] : $fromName;
                    $mail->addReplyTo($emailSettings['reply_to_email'], $rtName);
                }

                // Email Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;

                $mail->send();
            } catch (Exception $e) {
                error_log("Dispatched email failed: " . $mail->ErrorInfo);
            }
        }
    }

    echo 'Order status updated successfully.';
} else {
    http_response_code(400);
    echo 'Invalid request.';
}
?>
