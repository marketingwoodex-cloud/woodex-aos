<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

// Calculate cart total
$total = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $subtotal = ((float)$item['current_price']) * ((int)$item['quantity']);
        $total += $subtotal;
    }
}

// Retrieve cart totals & coupon (if any)
$cartTotals = $_SESSION['cart_totals'] ?? [
    'subtotal'    => 0,
    'discount'    => 0,
    'grand_total' => 0,
];

$coupon      = $_SESSION['coupon'] ?? null;
$couponCode  = $coupon['code'] ?? 'None';
$discount    = (float)($coupon['discount'] ?? 0);

// If no coupon is applied, use $total
if ($couponCode === 'None') {
    $subtotalDisplay = $total;
    $grandTotal      = $total;
} else {
    $subtotalDisplay = $cartTotals['subtotal'] > 0 ? (float)$cartTotals['subtotal'] : $total;
    $grandTotal      = $cartTotals['grand_total'] > 0 ? (float)$cartTotals['grand_total'] : max(0, $subtotalDisplay - $discount);
}

// Determine shipping cost (free > 3000)
$shippingCost = ($total > 3000) ? 0 : 200;
$grandTotal  += $shippingCost;

// If nothing in cart, bounce to cart
if ($total <= 0) {
    header("Location: " . $domain . "cart?error=" . urlencode('Your cart is empty.'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Cash on Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
    <style>
      .checkout-wrap{ padding: 20px 0 34px; }
      .checkout-title{ font-weight: 800; color:#1f2a36; margin-bottom: 20px; }
      .checkout-card{
        background:#fff;
        border:1px solid #dfe5eb;
        border-radius:14px;
        box-shadow:0 8px 24px rgba(20,34,50,.06);
        padding:18px;
      }
      .checkout-sec-title{
        font-size:1.2rem;
        font-weight:800;
        color:#1f2a36;
        margin:0 0 14px;
      }
      .checkout-item{ border:1px solid #e6ecf2; border-radius:12px; }
      .checkout-item .card-body{ gap:12px; }
      .checkout-item img{ width:84px; height:84px; object-fit:contain; background:#f7f9fb; border:1px solid #e4e9ee; border-radius:10px; }
      .checkout-item h5{ font-size:1rem; margin-bottom:4px; font-weight:800; color:#1f2a36; }
      .checkout-summary-box{
        border:1px solid #e6ecf2;
        border-radius:12px;
        padding:14px;
        background:#fbfcfe;
      }
      .checkout-summary-box h4{ color:var(--accent-color); font-weight:900; }
      .checkout-form .form-label{ font-weight:700; color:#334155; }
      .checkout-form .form-control,
      .checkout-form .form-select{ border-color:#d8e1ea; min-height:44px; }
      .checkout-form .form-control:focus,
      .checkout-form .form-select:focus{ border-color:var(--accent-color); box-shadow:0 0 0 2px rgba(120,190,32,.15); }
      .checkout-btn-main{ background:var(--accent-color); border-color:var(--accent-color); font-weight:800; min-height:46px; }
      .checkout-btn-main:hover{ background:#69a81c; border-color:#69a81c; }

      @media (max-width: 767.98px){
        .checkout-wrap{ padding-top: 8px; }
        .checkout-card{ padding:14px; }
        .checkout-item .card-body{ flex-direction:column; align-items:flex-start !important; }
        .checkout-item img{ width:74px; height:74px; }
        .checkout-sec-title{ font-size:1.08rem; }
      }
    </style>
</head>
<body>

<?php include_once('header/header.php'); ?>

<div class="container checkout-wrap">
    <h2 class="text-center checkout-title">Checkout</h2>

    <div class="row">
        <div class="col-md-6">
            <div class="checkout-card mb-3 mb-md-0">
            <h3 class="checkout-sec-title">Your Order</h3>

            <?php foreach ($_SESSION['cart'] as $item): ?>
                <div class="card mb-3 checkout-item">
                    <div class="card-body d-flex align-items-center">
                        <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/placeholder.png'); ?>" class="img-thumbnail" width="80" alt="">
                        <div class="ms-3">
                            <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                            <?php if (!empty($item['attributes'])): ?>
                                <p class="small text-muted mb-1">
                                    <?php
                                    $variationParts = [];
                                    foreach ($item['attributes'] as $key => $attr) {
                                        $variationParts[] = htmlspecialchars($key) . ": " . htmlspecialchars($attr['label'] ?? $attr['value']);
                                    }
                                    echo implode(', ', $variationParts);
                                    ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-1">Quantity: <?php echo (int)$item['quantity']; ?></p>
                            <p class="mb-0">Price: PKR <?php echo number_format((float)$item['current_price'], 2); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="checkout-summary-box">
                <p class="mb-1"><strong>Subtotal:</strong> PKR <?php echo number_format($subtotalDisplay, 2); ?></p>
                <p class="mb-1"><strong>Shipping:</strong> <?php echo $shippingCost > 0 ? "PKR " . number_format($shippingCost, 2) : "Free"; ?></p>
                <p class="mb-1"><strong>Applied Coupon:</strong> <?php echo $couponCode !== 'None' ? htmlspecialchars($couponCode) : 'No coupon applied'; ?></p>
                <p class="mb-2"><strong>Discount:</strong> -PKR <?php echo number_format($discount, 2); ?></p>
                <h4 class="mb-0">Total: PKR <?php echo number_format($grandTotal, 2); ?></h4>
            </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="checkout-card checkout-form">
            <h3 class="checkout-sec-title">Shipping Details</h3>
            <form action="process_checkout.php" method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ZIP Code</label>
                        <input type="text" name="zip" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select" required>
                        <option value="" disabled selected>Select Country</option>
                        <option value="PK">Pakistan</option>
                    </select>
                </div>

                <input type="hidden" name="total_amount" value="<?php echo htmlspecialchars($grandTotal, ENT_QUOTES); ?>">
                <input type="hidden" name="shipping_cost" value="<?php echo (int)$shippingCost; ?>">

                <div class="mb-3">
                    <h4>Payment Method</h4>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" value="COD" checked>
                        <label class="form-check-label">Cash on Delivery</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary checkout-btn-main w-100">Place Order</button>
            </form>
            </div>
        </div>
    </div>
</div>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
