<?php
include "lbpanel/config/connection.php";

if (!isset($_GET['order_number'])) {
    header("Location: $domain");
    exit();
}

$order_number = $_GET['order_number'];

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->bind_param("s", $order_number);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Order!</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet" />
</head>
<body>
    <?php include_once('header/header.php'); ?>
    <div class="container cart-success">
        <img src="<?php echo $domain;?>assets/images/cart_success.gif">
        <h1>Order Successful!</h1>
        <p class="mt-5">Thank you for your purchase. Your order number is <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>. You can get in touch with us in case you need any help.</p>
        <p>We will send you a confirmation email shortly. Please check your inbox or spam folder. Thank you again for shopping with us!</p>
        <a href="<?php echo $domain;?>" class="btn btn-primary bg-primary-color mb-5 mt-5">Continue Shopping</a>
    </div>
    <?php include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
