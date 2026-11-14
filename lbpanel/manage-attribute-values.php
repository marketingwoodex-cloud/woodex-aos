<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch attribute
$stmt = $conn->prepare("SELECT * FROM attributes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$attr = $stmt->get_result()->fetch_assoc();

if (!$attr) {
    echo "Attribute not found!";
    exit;
}

// Handle deletion
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM attribute_values WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Value deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete value.";
    }
    header("Location: manage-attribute-values.php?id=$id");
    exit;
}

// Handle adding value
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $value = trim($_POST['value']);
    $label = trim($_POST['label']) ?: null;
    if ($value) {
        $stmt = $conn->prepare("INSERT INTO attribute_values (attribute_id, value, label) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $id, $value, $label);
        $stmt->execute();
        $_SESSION['success'] = "Value added!";
    } else {
        $_SESSION['error'] = "Please provide a value.";
    }
    header("Location: manage-attribute-values.php?id=$id");
    exit;
}

// Fetch values
$values = $conn->query("SELECT * FROM attribute_values WHERE attribute_id = $id");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manage Attribute Values</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<h2>Manage Values for <?= htmlspecialchars($attr['name']) ?> (<?= htmlspecialchars($attr['value_type']) ?>)</h2>
<a href="manage-attributes.php" class="btn btn-secondary mb-3">Back</a>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
<?php unset($_SESSION['error']); endif; ?>

<form method="POST" class="mb-4">
  <div class="row g-2">
    <div class="col-md-4">
      <input type="<?= $attr['value_type'] === 'color' ? 'color' : 'text' ?>" name="value" class="form-control" required>
    </div>
    <?php if ($attr['value_type'] === 'color'): ?>
    <div class="col-md-4">
      <input type="text" name="label" class="form-control" placeholder="Color Name (e.g. Red)">
    </div>
    <?php endif; ?>
    <div class="col-md-4">
      <button class="btn btn-primary w-100">Add Value</button>
    </div>
  </div>
</form>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Value</th>
      <?php if ($attr['value_type'] === 'color') echo '<th>Label</th>'; ?>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $values->fetch_assoc()): ?>
      <tr>
        <td>
          <?php if ($attr['value_type'] === 'color'): ?>
            <div style="width:30px;height:30px;background:<?= htmlspecialchars($row['value']) ?>;border:1px solid #ccc;"></div>
          <?php else: ?>
            <?= htmlspecialchars($row['value']) ?>
          <?php endif; ?>
        </td>
        <?php if ($attr['value_type'] === 'color'): ?>
        <td><?= htmlspecialchars($row['label'] ?? '-') ?></td>
        <?php endif; ?>
        <td>
          <a href="manage-attribute-values.php?id=<?= $id ?>&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this value?');">Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</body>
</html>
