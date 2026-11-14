<?php
include 'lbpanel/config/connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Canceled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container text-center py-5">
        <h1 class="text-danger">Payment Canceled</h1>
        <p>Your payment was canceled. You can return to the shop to try again or contact us if you need help.</p>
        <a href="<?php echo $domain;?>shop" class="btn btn-primary">Return to Shop</a>
    </div>
</body>
</html>
