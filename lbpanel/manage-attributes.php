<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Handle deletion
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM attributes WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Attribute deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete attribute.";
    }
    header("Location: manage-attributes.php");
    exit;
}

// Handle adding attribute
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $value_type = $_POST['value_type'];
    if ($name && $value_type) {
        $stmt = $conn->prepare("INSERT INTO attributes (name, value_type) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $value_type);
        $stmt->execute();
        $_SESSION['success'] = "Attribute added!";
    } else {
        $_SESSION['error'] = "Please fill all fields.";
    }
    header("Location: manage-attributes.php");
    exit;
}

// Fetch attributes
$attributes = $conn->query("SELECT * FROM attributes ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Attributes</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
       <div class="menu">
        <?php include 'includes/navbar.php';?>
    </div>
<h2>Manage Attributes</h2>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
<?php unset($_SESSION['error']); endif; ?>

<form method="POST" class="mb-4">
  <div class="row g-2">
    <div class="col-md-5">
      <input type="text" name="name" class="form-control" placeholder="Attribute Name" required>
    </div>
    <div class="col-md-5">
      <select name="value_type" class="form-select" required>
        <option value="">Select Type</option>
        <option value="text">Text</option>
        <option value="number">Number</option>
        <option value="color">Color</option>
        <option value="select">Select/Dropdown</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Add</button>
    </div>
  </div>
</form>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Name</th>
      <th>Type</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $attributes->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['value_type']) ?></td>
        <td>
          <a href="manage-attribute-values.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">Manage Values</a>
          <a href="manage-attributes.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this attribute and all its values?');">Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
</body>
</html>
