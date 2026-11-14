<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

// Ensure $domain always ends with a single trailing slash (works on live + local subfolder installs).
if (!isset($domain) || !is_string($domain) || $domain === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = !empty($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $dir    = '/' . trim($dir, '/');
    $domain = $scheme . '://' . $host . ($dir === '/' ? '' : $dir) . '/';
}
$domain = rtrim($domain, "/") . "/";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/SMTP.php';

require_once __DIR__ . '/lbpanel/includes/email_config.php';

function abs_url($path) {
    if (!$path) return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (!empty($GLOBALS['domain'])) return rtrim($GLOBALS['domain'], '/') . '/' . ltrim($path, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $domain . "checkout");
    exit();
}

// ====== Form data ======
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$address  = trim($_POST['address'] ?? '');
$city     = trim($_POST['city'] ?? '');
$state    = trim($_POST['state'] ?? '');
$zip      = trim($_POST['zip'] ?? '');
$country  = trim($_POST['country'] ?? '');
$payment_method = "Cash on Delivery";

// Validate cart
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: " . $domain . "checkout?error=" . urlencode('Cart is empty or invalid.'));
    exit();
}
$cartItems = $_SESSION['cart'];

// Recalculate from the server-side cart. Never trust submitted hidden totals.
require_once __DIR__ . '/config/order_totals.php';
try {
    $totals = wdx_checkout_totals($cartItems, $_SESSION['coupon'] ?? null);
} catch (InvalidArgumentException $e) {
    header("Location: " . $domain . "cart?error=" . urlencode('Invalid cart.'));
    exit();
}
if (!$totals['coupon_valid']) unset($_SESSION['coupon']);
$coupon = $_SESSION['coupon'] ?? null;
$couponCode = $coupon['code'] ?? null;
$discountAmount = $totals['discount'];
$total_amount = $totals['total'];
$shippingCost = $totals['shipping'];

// Generate order number
$order_number = 'ORDER-' . strtoupper(uniqid());

// Insert into orders
$full_address   = "$address, $city, $state, $zip, $country";
$payment_status = 'Pending';

$stmt = $conn->prepare("
    INSERT INTO orders (
        order_number, customer_name, customer_email, customer_phone, shipping_address,
        total_amount, discount_amount, coupon_code, payment_status, shipping_cost, payment_method
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    error_log("Prepare failed for orders: " . $conn->error);
    header("Location: " . $domain . "checkout?error=" . urlencode('Failed to create order.'));
    exit();
}

$stmt->bind_param(
    "sssssddssds",
    $order_number,
    $name,
    $email,
    $phone,
    $full_address,
    $total_amount,
    $discountAmount,
    $couponCode,
    $payment_status,
    $shippingCost,
    $payment_method
);

if (!$stmt->execute()) {
    error_log("Order insertion failed: " . $stmt->error);
    header("Location: " . $domain . "checkout?error=" . urlencode('Failed to create order.'));
    exit();
}
$stmt->close();

// ===== IMPORTANT CHANGE =====
// Insert into order_items using the STRING order_number as order_id (FK expects order_number)
$stmt = $conn->prepare("
    INSERT INTO order_items
        (order_id, product_id, variation_id, variation_details, product_name, price, quantity, subtotal)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    error_log("Prepare failed for order_items: " . $conn->error);
    header("Location: " . $domain . "checkout?error=" . urlencode('Failed to add items.'));
    exit();
}
// Types: s (order_number) i (product_id) i (variation_id) s (variation_details) s (product_name) d (price) i (quantity) d (subtotal)
foreach ($cartItems as $cart_item_id => $item) {
    if (!isset($item['product_id'], $item['name'], $item['current_price'], $item['quantity'])) {
        error_log("Invalid cart item structure: " . print_r($item, true));
        continue;
    }

    $product_id        = (int)$item['product_id'];
    $variation_id      = isset($item['variation_id']) ? (int)$item['variation_id'] : null; // nullable
    $variation_details = !empty($item['attributes']) ? json_encode($item['attributes']) : null; // nullable
    $product_name      = (string)$item['name'];
    $price             = (float)$item['current_price'];
    $quantity          = (int)$item['quantity'];
    $subtotal          = $price * $quantity;

    $stmt->bind_param(
        "siissdid",
        $order_number,     // <-- string FK
        $product_id,
        $variation_id,
        $variation_details,
        $product_name,
        $price,
        $quantity,
        $subtotal
    );

    if (!$stmt->execute()) {
        error_log("Order item insertion failed: " . $stmt->error);
    }
}
$stmt->close();

// Calculate original subtotal for emails
$original_total = 0;
foreach ($cartItems as $item) {
    if (isset($item['current_price'], $item['quantity'])) {
        $original_total += ((float)$item['current_price']) * ((int)$item['quantity']);
    }
}

// Clear cart & coupon after saving order/items
unset($_SESSION['cart'], $_SESSION['coupon']);

// ===== Emails =====
$brandName = 'Woodex';
$emailSettings = get_email_settings($conn);

// Build items table for customer email
$itemsHTML = '';
foreach ($cartItems as $item) {
    $productName = htmlspecialchars($item['name']);
    $imgSrc      = abs_url($item['image'] ?? 'assets/images/placeholder.png');
    $qty         = (int)$item['quantity'];
    $price       = (float)$item['current_price'];
    $rowTotal    = number_format($price * $qty, 2);

    $variationInfo = '';
    if (!empty($item['attributes'])) {
        $variationParts = [];
        foreach ($item['attributes'] as $key => $attr) {
            $variationParts[] = htmlspecialchars($key) . ": " . htmlspecialchars($attr['label'] ?? $attr['value']);
        }
        $variationInfo = "<br><small>" . implode(', ', $variationParts) . "</small>";
    }

    $itemsHTML .= "
      <tr>
        <td style='border:1px solid #e6e6e6; padding:8px; text-align:center;'>
          <img src='{$imgSrc}' alt='{$productName}' style='max-width:60px;'>
        </td>
        <td style='border:1px solid #e6e6e6; padding:8px;'>{$productName}{$variationInfo}</td>
        <td style='border:1px solid #e6e6e6; padding:8px; text-align:center;'>{$qty}</td>
        <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR {$rowTotal}</td>
      </tr>
    ";
}

// Totals rows
$itemsHTML .= "
  <tr>
    <td colspan='2' style='border:1px solid #e6e6e6;'></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'><strong>Subtotal:</strong></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR ".number_format($original_total, 2)."</td>
  </tr>
  <tr>
    <td colspan='2' style='border:1px solid #e6e6e6;'></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'><strong>Discount:</strong></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR ".number_format($discountAmount, 2)."</td>
  </tr>
  <tr>
    <td colspan='2' style='border:1px solid #e6e6e6;'></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'><strong>Shipping Cost:</strong></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR ".number_format($shippingCost, 2)."</td>
  </tr>
  <tr>
    <td colspan='2' style='border:1px solid #e6e6e6;'></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'><strong>Order Total:</strong></td>
    <td style='border:1px solid #e6e6e6; padding:8px; text-align:right;'>PKR ".number_format($total_amount, 2)."</td>
  </tr>
";

// Customer email
$userSubject = "Your Order Confirmation - {$order_number}";
$userBody = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>{$userSubject}</title>
<style>
body, table, td { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
.order-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 6px; overflow: hidden; }
.order-header { background: #2B2B2B; text-align: center; padding: 20px; }
.order-body { padding: 20px; }
.order-items table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.order-items th { background: #f7f7f7; border:1px solid #e6e6e6; padding: 8px; text-align: left; }
.footer { background: #2B2B2B; padding: 15px; text-align:center; color:#fff; }
.footer a { color:#fff; text-decoration:none; }
</style>
</head>
<body style="margin:0; padding:0; background:#f8f8f8;">
  <div class="order-container">
    <div class="order-header">
      <img src="{$domain}assets/images/header-logo.png" alt="{$brandName}" style="max-width:180px;">
    </div>
    <div class="order-body">
      <h2 style="margin-top:0;">Hello, {$name}!</h2>
      <p>Thanks for your order. Here are your details:</p>
      <p><strong>Order Number:</strong> {$order_number}</p>
      <p><strong>Shipping Address:</strong> {$full_address}</p>
      <p><strong>Phone:</strong> {$phone}</p>
      <div class="order-items">
        <table>
          <thead>
            <tr>
              <th style="border:1px solid #e6e6e6;">Image</th>
              <th style="border:1px solid #e6e6e6;">Product</th>
              <th style="border:1px solid #e6e6e6;">Qty</th>
              <th style="border:1px solid #e6e6e6;">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {$itemsHTML}
          </tbody>
        </table>
      </div>
      <p>We’ll notify you when your order ships. Thank you for choosing {$brandName}!</p>
    </div>
    <div class="footer">
      <p style="margin:0;">Stay Connected!</p>
      <p>
        <a href="https://www.instagram.com/streetwearofficial8558" target="_blank">Instagram</a> |
        <a href="https://www.facebook.com/profile.php?id=61578321626668" target="_blank">Facebook</a> |
        <a href="https://wa.me/+923148880626" target="_blank">WhatsApp</a>
      </p>
      <p style="font-size:12px; color:#ccc; margin:5px 0 0 0;">&copy; 2025 {$brandName}</p>
    </div>
  </div>
</body></html>
HTML;

// Simple mail helper
function send_mail($toEmail, $toName, $subject, $htmlBody) {
    global $brandName;
    global $emailSettings;

    if (empty($emailSettings) || empty($emailSettings['enabled'])) return false;
    if (empty($emailSettings['smtp_host']) || empty($emailSettings['smtp_username']) || empty($emailSettings['smtp_password'])) return false;
    if (empty($emailSettings['from_email'])) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $emailSettings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailSettings['smtp_username'];
        $mail->Password   = $emailSettings['smtp_password'];
        if (!empty($emailSettings['smtp_secure'])) {
            $mail->SMTPSecure = $emailSettings['smtp_secure'];
        }
        $mail->Port       = (int)($emailSettings['smtp_port'] ?? 465);

        $fromName = !empty($emailSettings['from_name']) ? $emailSettings['from_name'] : $brandName;
        $mail->setFrom($emailSettings['from_email'], $fromName);
        if (!empty($emailSettings['reply_to_email'])) {
            $rtName = !empty($emailSettings['reply_to_name']) ? $emailSettings['reply_to_name'] : $fromName;
            $mail->addReplyTo($emailSettings['reply_to_email'], $rtName);
        }
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail to {$toEmail} failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Send emails (do not block checkout)
send_mail($email, $name, $userSubject, $userBody);

// Admin email
$adminSubject = "New Order Placed - {$order_number}";
$adminItemsHTML = '';
foreach ($cartItems as $item) {
    $productName = htmlspecialchars($item['name']);
    $qty         = (int)$item['quantity'];
    $price       = (float)$item['current_price'];
    $subtotal    = number_format($price * $qty, 2);
    $adminItemsHTML .= "
      <tr>
        <td style='border:1px solid #ddd; padding:6px;'>{$productName}</td>
        <td style='border:1px solid #ddd; padding:6px;'>{$qty}</td>
        <td style='border:1px solid #ddd; padding:6px;'>PKR {$subtotal}</td>
      </tr>
    ";
}
$adminBody = <<<ADMINHTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>{$adminSubject}</title>
<style>
body, table, td { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
table { border-collapse: collapse; width: 100%; }
th, td { padding:8px; border:1px solid #ddd; }
</style>
</head>
<body>
  <table style="max-width:600px; margin:20px auto; background:#fff;">
    <tr style="background:#2B2B2B; color:#fff;"><td colspan="3" style="padding:15px;">{$adminSubject}</td></tr>
    <tr><td colspan="3" style="padding:12px;">
      <p style="margin:0;">Customer: <strong>{$name}</strong></p>
      <p style="margin:0;">Email: <strong>{$email}</strong></p>
      <p style="margin:0;">Phone: <strong>{$phone}</strong></p>
      <p style="margin:0;">Address: <strong>{$full_address}</strong></p>
    </td></tr>
    <tr><th>Product</th><th>Qty</th><th>Subtotal</th></tr>
    {$adminItemsHTML}
    <tr><td colspan="2" style="text-align:right;"><strong>Subtotal:</strong></td><td>PKR {$original_total}</td></tr>
    <tr><td colspan="2" style="text-align:right;"><strong>Discount:</strong></td><td>PKR {$discountAmount}</td></tr>
    <tr><td colspan="2" style="text-align:right;"><strong>Shipping:</strong></td><td>PKR {$shippingCost}</td></tr>
    <tr><td colspan="2" style="text-align:right;"><strong>Total:</strong></td><td>PKR {$total_amount}</td></tr>
  </table>
</body></html>
ADMINHTML;

$adminTo = !empty($emailSettings['order_notify_email']) ? $emailSettings['order_notify_email'] : '';
if ($adminTo) {
    send_mail($adminTo, 'Admin', $adminSubject, $adminBody);
}

// Done
header("Location: " . $domain . "thank-you/" . urlencode($order_number));
exit();
