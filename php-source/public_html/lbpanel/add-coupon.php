<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Fetch products
$productQuery = "SELECT id, product_name FROM products";
$productResult = $conn->query($productQuery);
$products = [];
if ($productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

// Fetch categories
$categoryQuery = "SELECT id, category_name FROM categories";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
if ($categoryResult->num_rows > 0) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Check for session messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Coupon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
<?php include 'includes/navbar.php'; ?>

<div class="form-container">
    <div class="form-header">Add New Coupon</div>
    
    <!-- Display Success or Error Message -->
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <form action="php/process_add_coupon.php" method="POST">
        <!-- Coupon Code -->
        <div class="form-group">
            <label for="couponCode">Coupon Code</label>
            <input type="text" class="form-control" id="couponCode" name="couponCode" placeholder="Enter Coupon Code" required>
        </div>

        <!-- Coupon Description -->
        <div class="form-group">
            <label for="description">Coupon Description</label>
            <input type="text" class="form-control" id="description" name="description" placeholder="Enter Coupon Description" required>
        </div>

        <!-- Discount Type -->
        <div class="form-group">
            <label for="discountType">Discount Type</label>
            <select class="form-control" id="discountType" name="discountType" required>
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed</option>
            </select>
        </div>

        <!-- Discount Amount -->
        <div class="form-group">
            <label for="discountAmount">Discount Amount</label>
            <input type="number" class="form-control" id="discountAmount" name="discountAmount" placeholder="Enter Discount Amount" required>
        </div>

        <!-- Validity Period -->
        <div class="form-group">
            <label for="validityPeriod">Validity Period</label>
            <input type="date" class="form-control" id="validityPeriod" name="validityPeriod" required>
        </div>

        <!-- Applies To -->
        <div class="form-group">
            <label for="appliesTo">Applies To</label>
            <select class="form-control" id="appliesTo" name="appliesTo" required>
                <option value="all">All Products</option>
                <option value="product">Specific Product</option>
                <option value="category">Specific category</option>
            </select>
        </div>

        <!-- Related Product or category -->
        <div class="form-group" id="relatedField" style="display: none;">
            <label for="relatedId" id="relatedLabel"></label>
            <select class="form-control" id="relatedId" name="relatedId">
                <!-- Dynamically populated -->
            </select>
        </div>

        <!-- Coupon Status -->
        <div class="form-group">
            <label for="status">Coupon Status</label>
            <select class="form-control" id="status" name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <!-- Submit Button -->
        <div class="form-group text-center">
            <button type="submit" class="btn btn-primary">Add Coupon</button>
            <button type="reset" class="btn btn-secondary">Cancel</button>
        </div>
    </form>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Populate Related Field Based on Applies To
    const products = <?= json_encode($products) ?>;
    const categories = <?= json_encode($categories) ?>;

    document.getElementById('appliesTo').addEventListener('change', function () {
        const appliesTo = this.value;
        const relatedField = document.getElementById('relatedField');
        const relatedLabel = document.getElementById('relatedLabel');
        const relatedId = document.getElementById('relatedId');

        if (appliesTo === 'product') {
            relatedField.style.display = 'block';
            relatedLabel.textContent = 'Select Product';
            relatedId.innerHTML = products.map(product => `<option value="${product.id}">${product.product_name}</option>`).join('');
        } else if (appliesTo === 'category') {
            relatedField.style.display = 'block';
            relatedLabel.textContent = 'Select category';
            relatedId.innerHTML = categories.map(category => `<option value="${category.id}">${category.category_name}</option>`).join('');
        } else {
            relatedField.style.display = 'none';
        }
    });
</script>

</body>
</html>
