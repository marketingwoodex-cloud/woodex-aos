<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">
    <title>Shopping Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
</head>
<body>
<?php include_once('header/header.php'); ?>
<section>
<div class="cart-container">

    <!-- Main Cart Section -->
    <div class="cart-main">
        <!-- Shopping Bag Section -->
        <div class="cart-items">
            <h2 class="cart-heading">Shopping Bag</h2>
            <?php
            if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0):
                $total = 0;
                foreach ($_SESSION['cart'] as $product_id => $item):
                    $subtotal = $item['current_price'] * $item['quantity'];
                    $total += $subtotal;
            ?>
            <div class="cart-item">
    <img src="<?php echo $item['image'] ?? 'images/default-product.png'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
    <div class="cart-item-details">
        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
        <?php if (!empty($item['attributes'])): ?>
            <p class="small text-muted">
                <?php
                    $attrParts = [];
                    foreach ($item['attributes'] as $attrName => $attrData) {
                        $attrParts[] = htmlspecialchars($attrName) . ': ' . htmlspecialchars($attrData['label'] ?? $attrData['value']);
                    }
                    echo implode(', ', $attrParts);
                ?>
            </p>
        <?php endif; ?>
        <p class="cart-item-price">PKR <?php echo number_format($item['current_price'], 2); ?></p>
    </div>
    <div class="cart-item-quantity">
        <div class="bg-white">
            <button class="quantity-btn decrease-btn" data-cart-key="<?php echo $item['cart_item_id']; ?>">-</button>
            <input type="number" value="<?php echo $item['quantity']; ?>" class="quantity-input" data-cart-key="<?php echo $item['cart_item_id']; ?>" readonly>
            <button class="quantity-btn increase-btn" data-cart-key="<?php echo $item['cart_item_id']; ?>">+</button>
        </div>
        <button class="delete-btn remove-item btn btn-danger" data-cart-key="<?php echo $item['cart_item_id']; ?>">Delete</button>
    </div>
</div>
            <?php endforeach; ?>
            <div class="mt-3 text-center">
                <a href="<?php echo $domain;?>shop"><button class="hero-button-v1">Continue Shopping</button></a>
            </div>
        </div>

        <!-- Checkout Summary Section -->
        <div class="cart-summary">
            <h2 class="cart-heading">Total</h2>
            <div class="cart-totals">
    <p>Subtotal: <span class="total-amount">PKR<?php echo number_format($total, 2); ?></span></p>
    <p>Discount: <span class="discount-amount">PKR0.00</span></p>
    <p>Grand Total: <span class="grand-total">PKR<?php echo number_format($total, 2); ?></span></p>
</div>
<div class="cart-coupon">
    <label for="coupon-code">Coupon Code:</label>
    <input type="text" id="coupon-code" placeholder="Enter coupon code">
    <button class="apply-btn">Apply</button>
</div>

            <a href="<?php echo $domain;?>checkout"><button class="checkout-btn">Proceed to Checkout</button></a>
        </div>
        <?php else: ?>
        <p class="text-muted">Your cart is empty.</p>
        <?php endif; ?>
    </div>
</div>
</section>
<?php include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    function refreshCartView(){
        $(document).trigger('cartUpdated');
        window.location.reload();
    }

    // Remove item from cart
    $(document).on('click', '.remove-item', function () {
        const cartKey = $(this).data('cart-key');
        if (!cartKey) return;

        $.ajax({
            url: 'api/cart/remove.php',
            method: 'POST',
            dataType: 'json',
            data: { cart_key: cartKey },
            success: function (response) {
                if (response && response.status) {
                    refreshCartView();
                }
            }
        });
    });

    // Increment and decrement quantity via cart API
    $(document).on('click', '.increase-btn, .decrease-btn', function () {
        const cartKey = $(this).data('cart-key');
        if (!cartKey) return;

        const action = $(this).hasClass('increase-btn') ? 'increase' : 'decrease';
        $.ajax({
            url: 'api/cart/update.php',
            method: 'POST',
            dataType: 'json',
            data: { cart_key: cartKey, action: action },
            success: function (response) {
                if (response && response.status) {
                    refreshCartView();
                }
            }
        });
    });
});
</script>
<script>
    $(document).ready(function () {
        // Apply coupon
        $('.apply-btn').on('click', function () {
            const couponCode = $('#coupon-code').val().trim();
            if (!couponCode) {
                displayMessage('danger', 'Please enter a coupon code.');
                return;
            }

            // Update the coupon application AJAX handler
            $.ajax({
                url: 'apply_coupon.php',
                method: 'POST',
                data: { coupon_code: couponCode },
                dataType: 'json',
                success: function (response) {
                    if (response.status) {
                        // Update totals
                        $('.discount-amount').text(`PKR${response.discount}`);
                        $('.grand-total').text(`PKR${response.grand_total}`);

                        // Save values in session
                        $.post('save_cart_totals.php', {
                            subtotal: $('.total-amount').text().replace('PKR', '').trim(),
                            discount: response.discount,
                            grand_total: response.grand_total
                        });

                        // Show success message
                        displayMessage('success', response.message);
                    } else {
                        displayMessage('danger', response.message);
                    }
                },
                error: function () {
                    displayMessage('danger', 'An error occurred while applying the coupon.');
                }
            });
        });

        // Function to display Bootstrap messages
        function displayMessage(type, message) {
            const messageBox = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
            $('.cart-container').prepend(messageBox);
        }
    }); // Close $(document).ready()
</script>

<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
