<?php
require_once __DIR__ . '/../config/session.php';
wdx_session_start();

// Compute site base (works on local subfolder + live root)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $p = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
  if ($p === 'https') $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/';
$pos = strpos($script, '/lbpanel/');
$rootPath = ($pos !== false) ? substr($script, 0, $pos) : rtrim(str_replace('\\', '/', dirname($script)), '/');
$rootPath = '/' . trim($rootPath, '/');
if ($rootPath === '/') $rootPath = '';
$SITE_BASE = $scheme . '://' . $host . $rootPath . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxe POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #78be20;
            --secondary-color: #67a61b;
            --accent-color: #8ccc3b;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #f8961e;
            --gray-color: #adb5bd;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Receipt Printing Styles */
        .receipt {
            width: 300px;
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: white;
            color: #000;
            margin: 0 auto;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }

        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-subtitle {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .receipt-address {
            font-size: 10px;
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .receipt-details {
            margin-bottom: 15px;
            font-size: 12px;
        }

        .receipt-items {
            width: 100%;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .receipt-item-name {
            flex: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .receipt-item-qty {
            flex: 0 0 30px;
            text-align: center;
        }

        .receipt-item-price {
            flex: 0 0 70px;
            text-align: right;
        }

        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .receipt-totals {
            font-size: 12px;
            margin-bottom: 15px;
        }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .receipt-total-label {
            font-weight: bold;
        }

        .receipt-total-value {
            font-weight: bold;
        }

        .receipt-payment {
            font-size: 12px;
            margin-bottom: 15px;
        }

        .receipt-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }

        .receipt-barcode {
            text-align: center;
            margin-top: 10px;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 24px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-print, .receipt-print * {
                visibility: visible;
            }
            .receipt-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
        }

        /* Header Styles */
        .pos-header {
            background-color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 100;
            position: relative;
        }

        .logo {
            height: 40px;
        }

        .user-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .pos-container {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            min-height: calc(100vh - 72px);
            height: calc(100vh - 72px);
            overflow: hidden;
        }

        /* Products Section */
        .products-section {
            padding: 16px;
            background-color: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
        }

        .category-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
        }

        .category-tab {
            padding: 8px 16px;
            border-radius: 20px;
            background-color: var(--light-color);
            color: var(--dark-color);
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .category-tab.active {
            background-color: var(--primary-color);
            color: white;
        }

        .category-tab:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .search-bar {
            margin-bottom: 20px;
            position: relative;
        }

        .search-bar input {
            width: 100%;
            min-height: 42px;
            padding: 10px 16px;
            padding-left: 45px;
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(120, 190, 32, 0.14);
        }

        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .product-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #eee;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            height: 120px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--danger-color);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .product-info {
            padding: 15px;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-price {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 18px;
        }

        .product-old-price {
            text-decoration: line-through;
            color: var(--gray-color);
            font-size: 14px;
            margin-right: 5px;
        }

        .product-stock {
            font-size: 12px;
            color: var(--gray-color);
            margin-top: 5px;
        }

        /* Cart Section */
        .cart-section {
            display: flex;
            flex-direction: column;
            background-color: white;
            height: calc(100vh - 72px);
            border-left: 1px solid #e0e0e0;
        }

        .cart-header {
            padding: 16px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-size: 20px;
            font-weight: 600;
        }

        .cart-clear {
            color: var(--danger-color);
            cursor: pointer;
            font-size: 14px;
        }

        .cart-clear:hover {
            text-decoration: underline;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0 14px;
        }

        .cart-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 50px;
            height: 50px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .cart-item-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .cart-item-price {
            color: var(--primary-color);
            font-weight: bold;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }

        .quantity-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
        }

        .quantity-value {
            margin: 0 10px;
            min-width: 20px;
            text-align: center;
        }

        .cart-item-remove {
            color: var(--danger-color);
            cursor: pointer;
        }

        .cart-summary {
            padding: 16px;
            border-top: 1px solid #eee;
            background-color: #f8f9fa;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-total {
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            background-color: var(--secondary-color);
        }

        .checkout-btn:disabled {
            background-color: var(--gray-color);
            cursor: not-allowed;
        }

        /* Payment Modal */
        .payment-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .payment-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .payment-container {
            background-color: white;
            width: 500px;
            max-width: 90%;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: all 0.3s;
        }

        .payment-modal.active .payment-container {
            transform: translateY(0);
        }

        .payment-header {
            padding: 20px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-title {
            font-size: 20px;
            font-weight: 600;
        }

        .payment-close {
            cursor: pointer;
            font-size: 20px;
        }

        .payment-body {
            padding: 20px;
        }

        .payment-methods {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            flex: 1;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method.active {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1);
        }

        .payment-method i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .payment-method.active i {
            color: var(--secondary-color);
        }

        .payment-form-group {
            margin-bottom: 15px;
        }

        .payment-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .payment-form-group input,
        .payment-form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .payment-form-group input:focus,
        .payment-form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .bank-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .bank-info.active {
            display: block;
        }

        .bank-info p {
            margin-bottom: 5px;
        }

        .bank-info-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .payment-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .payment-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-btn-cancel {
            background-color: var(--light-color);
            color: var(--dark-color);
        }

        .payment-btn-cancel:hover {
            background-color: #e9ecef;
        }

        .payment-btn-confirm {
            background-color: var(--primary-color);
            color: white;
        }

        .payment-btn-confirm:hover {
            background-color: var(--secondary-color);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .pos-container {
                grid-template-columns: minmax(0, 1.2fr) minmax(280px, 1fr);
            }
        }

        @media (max-width: 768px) {
            .pos-container {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                height: auto;
                min-height: auto;
            }

            .products-section {
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                height: 55vh;
                padding: 12px;
            }

            .cart-section {
                height: auto;
                min-height: 45vh;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: var(--success-color);
            color: white;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            z-index: 1001;
            transform: translateX(150%);
            transition: all 0.3s;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification i {
            margin-right: 10px;
            font-size: 20px;
        }

        /* Empty Cart */
        .empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray-color);
            text-align: center;
            padding: 20px;
        }

        .empty-cart i {
            font-size: 50px;
            margin-bottom: 20px;
        }

        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="pos-header">
        <div class="logo">
            <img src="<?php echo htmlspecialchars($SITE_BASE . 'assets/images/header-logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Woodex POS" height="40">
        </div>
        <div class="user-controls">
            <button class="btn btn-outline" onclick="window.location.href='../dashboard.php'">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
            <button class="btn btn-outline" onclick="window.location.href='../pos/manage_pos.php'">
                <i class="fas fa-tasks"></i> Manage POS Orders
            </button>
            <div class="user-info">
                <?php
                  $username = isset($_SESSION['username']) && is_string($_SESSION['username']) && $_SESSION['username'] !== ''
                    ? $_SESSION['username']
                    : 'Admin';
                  $initial = strtoupper(substr($username, 0, 1));
                ?>
                <div class="user-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <span><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <!-- POS Container -->
    <div class="pos-container">
        <!-- Products Section -->
        <div class="products-section">
            <div class="category-tabs" id="categoryTabs">
                <div class="category-tab active" data-category="0">All Items</div>
                <!-- Categories will be loaded here dynamically -->
            </div>

            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search products..." id="searchInput">
            </div>

            <div class="products-grid" id="productsGrid">
                <!-- Products will be loaded here dynamically -->
                <div class="empty-cart">
                    <i class="fas fa-spinner fa-spin"></i>
                    <h3>Loading products...</h3>
                </div>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="cart-section">
            <div class="cart-header">
                <div class="cart-title">Current Order</div>
                <div class="cart-clear" id="clearCart">Clear All</div>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p>Add items to get started</p>
                </div>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">₨0.00</span>
                </div>
                <div class="summary-row">
                    <span>Tax:</span>
                    <span id="tax">₨0.00</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total:</span>
                    <span id="total">₨0.00</span>
                </div>
                <button class="checkout-btn" id="checkoutBtn" disabled>Checkout</button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="payment-modal" id="paymentModal">
        <div class="payment-container">
            <div class="payment-header">
                <div class="payment-title">Complete Payment</div>
                <div class="payment-close" id="closePaymentModal">&times;</div>
            </div>
            <div class="payment-body">
                <div class="payment-methods">
                    <div class="payment-method active" data-method="cash">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>Cash</div>
                    </div>
                    <div class="payment-method" data-method="mobile">
                        <i class="fas fa-mobile-alt"></i>
                        <div>Mobile Pay</div>
                    </div>
                </div>

                <div class="bank-info" id="bankInfo">
                    <div class="bank-info-title">Bank Account Information</div>
                    <p><strong>Bank Name:</strong> Meezan Bank</p>
                    <p><strong>Account Title:</strong> WOODEX FURNITURE</p>
                    <p><strong>Account Number:</strong> 02810112256050</p>
                    <p><strong>IBAN:</strong> PK51MEZN0002810112256050</p>
                </div>
                <div class="row">
                    <div class="payment-form-group col-md-6">
                        <label for="customerName">Customer Name *</label>
                        <input type="text" id="customerName" placeholder="Enter customer name" required>
                    </div>
    
                    <div class="payment-form-group col-md-6">
                        <label for="customerPhone">Phone Number (Optional)</label>
                        <input type="tel" id="customerPhone" placeholder="Enter phone number">
                    </div>
    
                    <div class="payment-form-group col-md-6" id="mobilePayFields" style="display: none;">
                        <label for="paymentApp">Payment App *</label>
                        <select id="paymentApp" required>
                            <option value="">Select payment app</option>
                            <option value="JazzCash">JazzCash</option>
                            <option value="EasyPaisa">EasyPaisa</option>
                            <option value="UBL Omni">UBL Omni</option>
                            <option value="HBL Konnect">HBL Konnect</option>
                        </select>
                    </div>
    
                    <div class="payment-form-group col-md-6" id="transactionIdField" style="display: none;">
                        <label for="transactionId">Transaction ID *</label>
                        <input type="text" id="transactionId" placeholder="Enter transaction ID">
                    </div>
    
                    <div class="payment-form-group col-md-6">
                        <label for="amountTendered">Amount Tendered *</label>
                        <input type="number" id="amountTendered" placeholder="0.00" step="0.01" min="0" required>
                    </div>
    
                    <div class="payment-form-group col-md-6">
                        <label for="changeDue">Change Due</label>
                        <input type="text" id="changeDue" placeholder="0.00" readonly>
                    </div>
                </div>
                <div class="payment-actions">
                    <button class="payment-btn payment-btn-cancel" id="cancelPayment">Cancel</button>
                    <button class="payment-btn payment-btn-confirm" id="confirmPayment">
                        <span id="confirmText">Complete Payment</span>
                        <span id="loadingSpinner" class="loading-spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification">
        <i class="fas fa-check-circle"></i>
        <span id="notificationMessage">Item added to cart</span>
    </div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const domain = "<?php echo htmlspecialchars($SITE_BASE, ENT_QUOTES, 'UTF-8'); ?>";
        const basePath = (function(){
            try {
                const u = new URL(domain);
                return (u.pathname || '/').replace(/\/+$/, '/') || '/';
            } catch (e) {
                return '/';
            }
        })();
        const baseOrigin = (function(){
            try {
                const u = new URL(domain);
                return u.origin;
            } catch (e) {
                return '';
            }
        })();

        function joinUrl(base, path){
            if (!path) return '';
            path = String(path).replace(/\\/g, '/');
            if (/^https?:\/\//i.test(path)) return path;

            const bpTrim = (basePath || '/').replace(/^\/+|\/+$/g, '');
            if (bpTrim) {
                if (path.indexOf('/' + bpTrim + '/') === 0) {
                    return baseOrigin + path;
                }
                if (path.indexOf(bpTrim + '/') === 0) {
                    return baseOrigin + '/' + path;
                }
            }

            if (path.indexOf('/assets/') === 0) {
                return baseOrigin + (basePath === '/' ? '' : basePath.replace(/\/+$/, '')) + path;
            }
            if (path.indexOf('assets/') === 0) {
                return (base || '').toString().replace(/\/+$/, '/') + path;
            }
            if (path.indexOf('/') === 0) {
                return baseOrigin + (basePath === '/' ? '' : basePath.replace(/\/+$/, '')) + path;
            }
            return (base || '').toString().replace(/\/+$/, '/') + path;
        }
        
        // DOM Elements
        const productsGrid = document.getElementById('productsGrid');
        const cartItems = document.getElementById('cartItems');
        const subtotalEl = document.getElementById('subtotal');
        const taxEl = document.getElementById('tax');
        const totalEl = document.getElementById('total');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const clearCartBtn = document.getElementById('clearCart');
        const categoryTabsContainer = document.getElementById('categoryTabs');
        const searchInput = document.getElementById('searchInput');
        const paymentModal = document.getElementById('paymentModal');
        const closePaymentModal = document.getElementById('closePaymentModal');
        const cancelPayment = document.getElementById('cancelPayment');
        const confirmPayment = document.getElementById('confirmPayment');
        const amountTendered = document.getElementById('amountTendered');
        const changeDue = document.getElementById('changeDue');
        const paymentMethods = document.querySelectorAll('.payment-method');
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notificationMessage');
        const confirmText = document.getElementById('confirmText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const bankInfo = document.getElementById('bankInfo');
        const mobilePayFields = document.getElementById('mobilePayFields');
        const transactionIdField = document.getElementById('transactionIdField');
        const paymentApp = document.getElementById('paymentApp');
        const transactionId = document.getElementById('transactionId');
        const customerName = document.getElementById('customerName');
        const customerPhone = document.getElementById('customerPhone');

        // State
        let cart = [];
        let currentCategory = 0;
        let currentSearch = '';
        let currentPaymentMethod = 'cash';

        // Initialize
        async function init() {
    await loadCategories();
    await loadProducts(); // This will populate the products variable
    loadCartFromStorage();
    renderCart();
    setupEventListeners();
}

        // Load categories from server
        async function loadCategories() {
            try {
                const response = await fetch('api/get_categories.php');
                const data = await response.json();
                
                if (data.success) {
                    renderCategoryTabs(data.categories);
                } else {
                    console.error('Error loading categories:', data.message);
                    showNotification('Error loading categories');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showNotification('Error loading categories');
            }
        }

        // Load products from server

async function loadProducts() {
    try {
        const response = await fetch('api/get_products.php');
        const data = await response.json();
        
        if (data.success) {
            products = data.products;
            renderProducts(); // Now uses the global products
        } else {
            console.error('Error loading products:', data.message);
            showErrorState();
        }
    } catch (error) {
        console.error('Error loading products:', error);
        showErrorState();
    }
}

        // Render category tabs
        function renderCategoryTabs(categories) {
            // Clear existing tabs except "All Items"
            const allItemsTab = categoryTabsContainer.querySelector('.category-tab[data-category="0"]');
            categoryTabsContainer.innerHTML = '';
            categoryTabsContainer.appendChild(allItemsTab);

            // Add other categories
            categories.forEach(category => {
                const categoryTab = document.createElement('div');
                categoryTab.className = 'category-tab';
                categoryTab.dataset.category = category.id;
                categoryTab.textContent = category.category_name;
                categoryTabsContainer.appendChild(categoryTab);
            });

            // Set up event listeners for the new tabs
            setupCategoryTabListeners();
        }

        // Set up event listeners for category tabs
        function setupCategoryTabListeners() {
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentCategory = parseInt(tab.dataset.category);
                    renderProducts();
                });
            });
        }

        // Set up event listeners
        function setupEventListeners() {
    // Search input - remove any existing listener first
    searchInput.removeEventListener('input', handleSearch);
    searchInput.addEventListener('input', handleSearch);
    
    // Category tabs - remove existing listeners first
    const tabs = document.querySelectorAll('.category-tab');
    tabs.forEach(tab => {
        tab.removeEventListener('click', handleCategoryChange);
        tab.addEventListener('click', handleCategoryChange);
    });

            // Checkout button
            checkoutBtn.addEventListener('click', openPaymentModal);

            // Clear cart button
            clearCartBtn.addEventListener('click', clearCart);

            // Payment modal
            closePaymentModal.addEventListener('click', closePaymentModalFunc);
            cancelPayment.addEventListener('click', closePaymentModalFunc);
            confirmPayment.addEventListener('click', completePayment);

            // Payment methods
            paymentMethods.forEach(method => {
                method.addEventListener('click', () => {
                    paymentMethods.forEach(m => m.classList.remove('active'));
                    method.classList.add('active');
                    currentPaymentMethod = method.dataset.method;
                    
                    // Show/hide mobile payment fields
                    if (currentPaymentMethod === 'mobile') {
                        bankInfo.classList.add('active');
                        mobilePayFields.style.display = 'block';
                        transactionIdField.style.display = 'block';
                        paymentApp.required = true;
                        transactionId.required = true;
                    } else {
                        bankInfo.classList.remove('active');
                        mobilePayFields.style.display = 'none';
                        transactionIdField.style.display = 'none';
                        paymentApp.required = false;
                        transactionId.required = false;
                    }
                });
            });

            // Amount tendered input
            amountTendered.addEventListener('input', calculateChange);
        }
function handleSearch(e) {
    currentSearch = e.target.value.toLowerCase();
    renderProducts();
}

function handleCategoryChange() {
    const tabs = document.querySelectorAll('.category-tab');
    tabs.forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    currentCategory = parseInt(this.dataset.category);
    renderProducts();
}
        // Render products based on category and search
        function renderProducts() {
    // Clear the products grid completely first
    productsGrid.innerHTML = '';
    
    if (!products || products.length === 0) {
        productsGrid.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>No products available</h3>
            </div>
        `;
        return;
    }

    const filteredProducts = products.filter(product => {
        const matchesCategory = currentCategory === 0 || product.category == currentCategory;
        const matchesSearch = product.product_name.toLowerCase().includes(currentSearch);
        return matchesCategory && matchesSearch;
    });

    if (filteredProducts.length === 0) {
        productsGrid.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-search"></i>
                <h3>No products found</h3>
                <p>Try different filters</p>
            </div>
        `;
        return;
    }

    // Create a document fragment for better performance
    const fragment = document.createDocumentFragment();
    
    filteredProducts.forEach(product => {
        const productCard = createProductCard(product);
        fragment.appendChild(productCard);
    });
    
    productsGrid.appendChild(fragment);
}

function createProductCard(product) {
    const productCard = document.createElement('div');
    productCard.className = 'product-card';
    
     // Get first image if available
     let productImage = '<span style="font-size:22px">&#128230;</span>';
     try {
         const images = JSON.parse(product.images);
         if (images && images.length > 0) {
             productImage = `<img src="${joinUrl(domain, images[0])}" alt="${product.product_name}">`;
         }
     } catch (e) {
         console.error('Error parsing product images:', e);
     }

    productCard.innerHTML = `
        <div class="product-image">
            ${productImage}
            ${product.quantity < 5 ? `<div class="product-badge">Low Stock</div>` : ''}
        </div>
        <div class="product-info">
            <div class="product-name">${product.product_name}</div>
            <div>
                ${product.old_price > product.current_price ? 
                  `<span class="product-old-price">₨${parseFloat(product.old_price).toFixed(2)}</span>` : ''}
                <span class="product-price">₨${parseFloat(product.current_price).toFixed(2)}</span>
            </div>
            <div class="product-stock">${product.quantity} in stock | SKU: ${product.product_sku}</div>
        </div>
    `;
    
    // Add click event directly (no duplicate event listeners)
    productCard.addEventListener('click', () => addToCart(product));
    
    return productCard;
}

        // Add item to cart
        function addToCart(product) {
            const existingItem = cart.find(item => item.id === product.id);

            if (existingItem) {
                if (existingItem.quantity >= product.quantity) {
                    showNotification(`Only ${product.quantity} ${product.product_name} available in stock`);
                    return;
                }
                existingItem.quantity += 1;
            } else {
                if (product.quantity < 1) {
                    showNotification(`${product.product_name} is out of stock`);
                    return;
                }
                cart.push({
                    id: product.id,
                    name: product.product_name,
                    price: parseFloat(product.current_price),
                     image: (() => {
                         try {
                             const imgs = product.images ? JSON.parse(product.images) : [];
                             return (Array.isArray(imgs) && imgs.length > 0) ? imgs[0] : '';
                         } catch (e) {
                             return '';
                         }
                     })(),
                    quantity: 1,
                    sku: product.product_sku
                });
            }

            saveCartToStorage();
            renderCart();
            showNotification(`${product.product_name} added to cart`);
        }

        // Remove item from cart
        function removeFromCart(productId) {
            cart = cart.filter(item => item.id !== productId);
            saveCartToStorage();
            renderCart();
        }

        // Update item quantity
        function updateQuantity(productId, newQuantity) {
    const item = cart.find(item => item.id === productId);
    if (item) {
        // Access the global products variable
        const product = window.products.find(p => p.id === productId);
        if (product && newQuantity > product.quantity) {
            showNotification(`Only ${product.quantity} ${product.product_name} available in stock`);
            return;
        }
        item.quantity = Math.max(1, newQuantity);
        saveCartToStorage();
        renderCart();
    }
}

        // Clear cart
        function clearCart() {
            if (cart.length === 0) return;
            
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                saveCartToStorage();
                renderCart();
                showNotification('Cart cleared');
            }
        }

        // Render cart
        function renderCart() {
            if (cart.length === 0) {
                cartItems.innerHTML = '<div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Your cart is empty</h3><p>Add items to get started</p></div>';
                subtotalEl.textContent = '₨0.00';
                taxEl.textContent = '₨0.00';
                totalEl.textContent = '₨0.00';
                checkoutBtn.disabled = true;
                return;
            }

            cartItems.innerHTML = '';
            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;

                const cartItem = document.createElement('div');
                cartItem.className = 'cart-item';
                cartItem.innerHTML = `
                    <div class="cart-item-image">
                        <img src="${joinUrl(domain, item.image)}" alt="${item.name}">
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">₨${item.price.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-controls">
                        <div class="quantity-control">
                            <div class="quantity-btn minus" data-id="${item.id}">-</div>
                            <div class="quantity-value">${item.quantity}</div>
                            <div class="quantity-btn plus" data-id="${item.id}">+</div>
                        </div>
                        <div class="cart-item-remove" data-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </div>
                    </div>
                `;

                // Add event listeners to quantity buttons
                const minusBtn = cartItem.querySelector('.minus');
                const plusBtn = cartItem.querySelector('.plus');
                const removeBtn = cartItem.querySelector('.cart-item-remove');

                minusBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    updateQuantity(item.id, item.quantity - 1);
                });

                plusBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    updateQuantity(item.id, item.quantity + 1);
                });

                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeFromCart(item.id);
                });

                cartItems.appendChild(cartItem);
            });

            const tax = subtotal * 0; // 10% tax
            const total = subtotal + tax;

            subtotalEl.textContent = `₨${subtotal.toFixed(2)}`;
            taxEl.textContent = `₨${tax.toFixed(2)}`;
            totalEl.textContent = `₨${total.toFixed(2)}`;
            checkoutBtn.disabled = false;
        }

        // Open payment modal
        function openPaymentModal() {
            paymentModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            amountTendered.value = '';
            changeDue.value = '';
            customerName.value = '';
            customerPhone.value = '';
            paymentApp.value = '';
            transactionId.value = '';
            customerName.focus();
        }

        // Close payment modal
        function closePaymentModalFunc() {
            paymentModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Calculate change
        function calculateChange() {
            const total = parseFloat(totalEl.textContent.replace('₨', ''));
            const tendered = parseFloat(amountTendered.value) || 0;
            const change = tendered - total;

            if (change >= 0) {
                changeDue.value = `₨${change.toFixed(2)}`;
            } else {
                changeDue.value = 'Insufficient';
            }
        }

        // Complete payment
        // Complete payment
async function completePayment() {
    // Validate required fields
    if (!customerName.value.trim()) {
        alert('Customer name is required');
        customerName.focus();
        return;
    }

    if (currentPaymentMethod === 'mobile') {
        if (!paymentApp.value) {
            alert('Please select the payment app');
            paymentApp.focus();
            return;
        }
        if (!transactionId.value.trim()) {
            alert('Transaction ID is required for mobile payments');
            transactionId.focus();
            return;
        }
    }

    const total = parseFloat(totalEl.textContent.replace('₨', ''));
    const tendered = parseFloat(amountTendered.value) || 0;
    const change = tendered - total;

    if (tendered < total) {
        alert('Amount tendered is less than the total amount due.');
        return;
    }

    // Show loading state
    confirmText.style.display = 'none';
    loadingSpinner.style.display = 'inline-block';
    confirmPayment.disabled = true;

    try {
        // Prepare transaction data
        const transactionData = {
            subtotal: (total / 1).toFixed(2),
            tax: (total - (total / 1)).toFixed(2),
            total: total.toFixed(2),
            payment_method: currentPaymentMethod,
            amount_tendered: tendered.toFixed(2),
            change_given: change.toFixed(2),
            customer_name: customerName.value.trim(),
            customer_phone: customerPhone.value.trim(),
            payment_app: currentPaymentMethod === 'mobile' ? paymentApp.value : null,
            transaction_id: currentPaymentMethod === 'mobile' ? transactionId.value.trim() : null,
            items: cart.map(item => ({
                product_id: item.id,
                product_name: item.name,
                quantity: item.quantity,
                unit_price: item.price,
                total_price: (item.price * item.quantity).toFixed(2)
            }))
        };

        // Send transaction to server
        const response = await fetch('api/save_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(transactionData)
        });

        const data = await response.json();

        if (data.success) {
            // Prepare receipt data
            const receiptData = {
                total: total,
                tendered: tendered,
                change: change,
                customerName: customerName.value.trim(),
                customerPhone: customerPhone.value.trim(),
                currentPaymentMethod: currentPaymentMethod,
                paymentApp: currentPaymentMethod === 'mobile' ? paymentApp.value : null,
                transactionId: currentPaymentMethod === 'mobile' ? transactionId.value.trim() : null,
                cart: cart
            };
            
            // Print receipt
            printReceipt(receiptData);
            
            // Clear cart and close modal
            cart = [];
            saveCartToStorage();
            renderCart();
            closePaymentModalFunc();
            showNotification('Payment completed successfully');
        } else {
            alert('Error processing payment: ' + data.message);
        }
    } catch (error) {
        console.error('Error processing payment:', error);
        alert('Error processing payment. Please try again.');
    } finally {
        // Reset button state
        confirmText.style.display = 'inline';
        loadingSpinner.style.display = 'none';
        confirmPayment.disabled = false;
    }
}

// Print receipt with attractive design
function printReceipt(data) {
    const now = new Date();
    const dateStr = now.toLocaleDateString();
    const timeStr = now.toLocaleTimeString();
    const transactionId = 'TXN-' + now.getTime().toString().slice(-6);
    
    const receiptContent = `
        <div class="receipt receipt-print">
            <div class="receipt-header">
                <div class="receipt-title">WOODEX FURNITURE</div>
                <div class="receipt-subtitle">POS RECEIPT</div>
                <div class="receipt-address">
                www.woodexfurniture.pk<br>
                    Shop # G-66, Zainab Tower<br>
                Model Town Link Road, Lahore<br>
                    Phone: (123) 456-7890
                </div>
            </div>
            
            <div class="receipt-details">
                <div><strong>Date:</strong> ${dateStr}</div>
                <div><strong>Time:</strong> ${timeStr}</div>
                <div><strong>Transaction #:</strong> ${transactionId}</div>
                <div><strong>Cashier:</strong> <?php echo $_SESSION['username']; ?></div>
                <div><strong>Customer:</strong> ${data.customerName}</div>
                ${data.customerPhone ? `<div><strong>Phone:</strong> ${data.customerPhone}</div>` : ''}
            </div>
            
            <div class="receipt-items">
                ${data.cart.map(item => `
                    <div class="receipt-item">
                        <div class="receipt-item-name">${item.name}</div>
                        <div class="receipt-item-qty">${item.quantity}</div>
                        <div class="receipt-item-price">₨${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                    <div class="receipt-item">
                        <div class="receipt-item-name" style="padding-left: 10px;">@ ₨${item.price.toFixed(2)} each</div>
                        <div class="receipt-item-qty"></div>
                        <div class="receipt-item-price"></div>
                    </div>
                `).join('')}
            </div>
            
            <div class="receipt-divider"></div>
            
            <div class="receipt-totals">
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Subtotal:</span>
                    <span class="receipt-total-value">₨${(data.total / 1).toFixed(2)}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Tax (10%):</span>
                    <span class="receipt-total-value">₨${(data.total - (data.total / 1)).toFixed(2)}</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Total:</span>
                    <span class="receipt-total-value">₨${data.total.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="receipt-divider"></div>
            
            <div class="receipt-payment">
                <div><strong>Payment Method:</strong> ${data.currentPaymentMethod.charAt(0).toUpperCase() + data.currentPaymentMethod.slice(1)}</div>
                ${data.currentPaymentMethod === 'mobile' ? `
                    <div><strong>Payment App:</strong> ${data.paymentApp}</div>
                    <div><strong>Transaction ID:</strong> ${data.transactionId}</div>
                ` : ''}
                <div><strong>Amount Tendered:</strong> ₨${data.tendered.toFixed(2)}</div>
                <div><strong>Change:</strong> ₨${data.change.toFixed(2)}</div>
            </div>
            
            <div class="receipt-barcode">${transactionId}</div>
            
            <div class="receipt-footer">
                Thank you for shopping with us!<br>
                Please retain this receipt for your records<br>
                Returns accepted within 14 days with receipt<br>
                <strong>POS Developed By BAKSORBIT</strong>
            </div>
        </div>
    `;

    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Receipt #${transactionId}</title>
                <style>
                    body { 
                        font-family: 'Courier New', monospace;
                        margin: 0;
                        padding: 0;
                        background: white;
                        color: black;
                    }
                    @page {
                        size: auto;
                        margin: 0;
                    }
                    ${document.querySelector('style').textContent}
                </style>
                <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
            </head>
            <body onload="window.print(); window.close();">
                ${receiptContent}
            </body>
        </html>
    `);
    printWindow.document.close();
}

        // Show notification
        function showNotification(message) {
            notificationMessage.textContent = message;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        // Save cart to localStorage
        function saveCartToStorage() {
            localStorage.setItem('posCart', JSON.stringify(cart));
        }

        // Load cart from localStorage
        function loadCartFromStorage() {
            const savedCart = localStorage.getItem('posCart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
            }
        }

        // Initialize the app
        init();
    </script>
</body>
</html>
