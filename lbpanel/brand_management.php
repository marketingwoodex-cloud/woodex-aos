<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Add Brand
if (isset($_POST['add_brand'])) {
    $brandName = trim($_POST['brand_name']);
    
    // Check if brand exists
    $checkStmt = $conn->prepare("SELECT id FROM brands WHERE brand_name = ?");
    $checkStmt->bind_param("s", $brandName);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        $_SESSION['error'] = "Brand already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO brands (brand_name) VALUES (?)");
        $stmt->bind_param("s", $brandName);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Brand added successfully!";
        } else {
            $_SESSION['error'] = "Error adding brand: " . $conn->error;
        }
    }
    header("Location: brand_management.php");
    exit();
}

// Delete Brand
if (isset($_GET['delete_brand'])) {
    $brandId = (int)$_GET['delete_brand'];
    
    $stmt = $conn->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->bind_param("i", $brandId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Brand deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting brand: " . $conn->error;
    }
    header("Location: brand_management.php");
    exit();
}

// Fetch all brands
$brands = [];
$brandQuery = "SELECT * FROM brands ORDER BY brand_name ASC";
$brandResult = $conn->query($brandQuery);
if ($brandResult && $brandResult->num_rows > 0) {
    while ($row = $brandResult->fetch_assoc()) {
        $brands[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands</title>
    <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Brand</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="brand_name" class="form-label">Brand Name</label>
                                <input type="text" class="form-control" id="brand_name" name="brand_name" required>
                            </div>
                            <button type="submit" name="add_brand" class="btn btn-primary">Add Brand</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Brand List</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($brands)): ?>
                            <p>No brands found.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($brands as $brand): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($brand['brand_name']) ?>
                                        <a href="brand_management.php?delete_brand=<?= $brand['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
</body>
</html>