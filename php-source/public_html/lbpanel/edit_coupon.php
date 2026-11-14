<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Validate `id`
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid coupon ID.';
    header('Location: view-coupons.php');
    exit;
}

$couponId = (int)$_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = $_POST['couponCode'] ?? '';
    $description = $_POST['description'] ?? '';
    $discountType = $_POST['discountType'] ?? '';
    $discountAmount = $_POST['discountAmount'] ?? '';
    $validityPeriod = $_POST['validityPeriod'] ?? '';
    $appliesTo = $_POST['appliesTo'] ?? 'all';
    $relatedId = ($_POST['relatedId'] ?? null) ?: null; // Null if empty
    $status = $_POST['status'] ?? '';

    // Validate required fields
    if (empty($couponCode) || empty($description) || empty($discountType) || empty($discountAmount) || empty($validityPeriod) || empty($status)) {
        $_SESSION['error'] = 'Please fill out all required fields.';
    } else {
        $updateStmt = $conn->prepare("
            UPDATE coupons 
            SET coupon_code = ?, description = ?, discount_type = ?, discount_amount = ?, validity_period = ?, applies_to = ?, related_id = ?, status = ? 
            WHERE id = ?
        ");
        $updateStmt->bind_param(
            'ssssssssi', 
            $couponCode, $description, $discountType, $discountAmount, $validityPeriod, $appliesTo, $relatedId, $status, $couponId
        );

        if ($updateStmt->execute()) {
            $_SESSION['success'] = 'Coupon updated successfully!';
        } else {
            $_SESSION['error'] = 'Error updating coupon: ' . $conn->error;
        }
        $updateStmt->close();
    }

    // Redirect to the same page to show messages and refetch data
    header("Location: edit_coupon.php?id=$couponId");
    exit;
}

// Fetch updated data after any operations
$stmt = $conn->prepare("SELECT id, coupon_code, description, discount_type, discount_amount, validity_period, applies_to, related_id, status FROM coupons WHERE id = ?");
$stmt->bind_param('i', $couponId);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) {
    $_SESSION['error'] = 'Coupon not found.';
    header('Location: view-coupons.php');
    exit;
}

// Fetch products and categories
$productQuery = "SELECT id, product_name FROM products";
$productResult = $conn->query($productQuery);
$products = [];
if ($productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

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
    <title>Edit Coupon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="form-container">
    <div class="form-header">Edit Coupon</div>

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

    <form action="" method="POST">
        <!-- Coupon Code -->
        <div class="form-group">
            <label for="couponCode">Coupon Code</label>
            <input type="text" class="form-control" id="couponCode" name="couponCode" value="<?= htmlspecialchars($coupon['coupon_code']) ?>" required>
        </div>

        <!-- Coupon Description -->
        <div class="form-group">
            <label for="description">Coupon Description</label>
            <input type="text" class="form-control" id="description" name="description" value="<?= htmlspecialchars($coupon['description']) ?>" required>
        </div>

        <!-- Discount Type -->
        <div class="form-group">
            <label for="discountType">Discount Type</label>
            <select class="form-control" id="discountType" name="discountType" required>
                <option value="percentage" <?= $coupon['discount_type'] == 'percentage' ? 'selected' : '' ?>>Percentage</option>
                <option value="fixed" <?= $coupon['discount_type'] == 'fixed' ? 'selected' : '' ?>>Fixed</option>
            </select>
        </div>

        <!-- Discount Amount -->
        <div class="form-group">
            <label for="discountAmount">Discount Amount</label>
            <input type="number" step="0.01" class="form-control" id="discountAmount" name="discountAmount" value="<?= htmlspecialchars($coupon['discount_amount']) ?>" required>
        </div>

        <!-- Validity Period -->
        <div class="form-group">
            <label for="validityPeriod">Validity Period</label>
            <input type="date" class="form-control" id="validityPeriod" name="validityPeriod" value="<?= htmlspecialchars($coupon['validity_period']) ?>" required>
        </div>

        <!-- Applies To -->
        <div class="form-group">
            <label for="appliesTo">Applies To</label>
            <select class="form-control" id="appliesTo" name="appliesTo" required>
                <option value="all" <?= $coupon['applies_to'] == 'all' ? 'selected' : '' ?>>All Products</option>
                <option value="product" <?= $coupon['applies_to'] == 'product' ? 'selected' : '' ?>>Specific Product</option>
                <option value="category" <?= $coupon['applies_to'] == 'category' ? 'selected' : '' ?>>Specific category</option>
            </select>
        </div>

        <!-- Related Field -->
        <div class="form-group" id="relatedField" style="display: <?= ($coupon['applies_to'] != 'all') ? 'block' : 'none'; ?>;">
            <label for="relatedId" id="relatedLabel"><?= $coupon['applies_to'] == 'product' ? 'Select Product' : ($coupon['applies_to'] == 'category' ? 'Select category' : '') ?></label>
            <select class="form-control" id="relatedId" name="relatedId">
                <?php if ($coupon['applies_to'] == 'product'): ?>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= $product['id'] == $coupon['related_id'] ? 'selected' : '' ?>><?= $product['product_name'] ?></option>
                    <?php endforeach; ?>
                <?php elseif ($coupon['applies_to'] == 'category'): ?>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $category['id'] == $coupon['related_id'] ? 'selected' : '' ?>><?= $category['category_name'] ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <!-- Coupon Status -->
        <div class="form-group">
            <label for="status">Coupon Status</label>
            <select class="form-control" id="status" name="status" required>
                <option value="active" <?= $coupon['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $coupon['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <!-- Submit Button -->
        <div class="form-group text-center">
            <button type="submit" class="btn-submit">Update Coupon</button>
            <a href="view-coupons.php" class="btn-cancel">Cancel</a>
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
