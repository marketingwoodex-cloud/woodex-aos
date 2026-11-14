<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Optional: Provide a success/error message for deletes
$message = "";

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success text-center'>FAQ Deleted Successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger text-center'>Error Deleting FAQ!</div>";
    }
    $stmt->close();
}

// Pagination
$faqPerPage = 10;
$page       = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$q          = trim((string)($_GET['q'] ?? ''));
$like       = '%' . $q . '%';
$offset     = ($page - 1) * $faqPerPage;

// Count total
$totalFaqs = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM faqs WHERE question LIKE ? OR faq_for LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('ss', $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalFaqs = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM faqs");
    $totalFaqs   = (int)($countResult->fetch_assoc()['total'] ?? 0);
}
$totalPages  = max(1, (int)ceil($totalFaqs / $faqPerPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $faqPerPage;

// Fetch FAQs
$stmt = $q !== ''
    ? $conn->prepare("SELECT id, faq_for, item_id, question, created_at FROM faqs WHERE question LIKE ? OR faq_for LIKE ? ORDER BY id DESC LIMIT ?, ?")
    : $conn->prepare("SELECT id, faq_for, item_id, question, created_at FROM faqs ORDER BY id DESC LIMIT ?, ?");
if ($q !== '') {
    $stmt->bind_param("ssii", $like, $like, $offset, $faqPerPage);
} else {
    $stmt->bind_param("ii", $offset, $faqPerPage);
}
$stmt->execute();
$result = $stmt->get_result();
$faqs   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View FAQs</title>
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" 
    rel="stylesheet"
  >
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="table-container">
  <div class="table-header">FAQs</div>

  <!-- Search Bar -->
  <div class="search-bar">
    <form method="get" action="view-faqs.php" class="d-flex gap-2 align-items-center">
      <input 
        type="text" 
        class="form-control" 
        id="searchInput" 
        name="q"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Search FAQ by question or target..."
      >
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-faqs.php">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if (!empty($message)): ?>
    <?= $message ?>
  <?php endif; ?>

  <table class="table table-borderless text-center">
    <thead>
      <tr>
        <th>ID</th>
        <th>FAQ For</th>
        <th>Item ID</th>
        <th>Question</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="faqTable">
      <?php foreach ($faqs as $faq): ?>
        <tr>
          <td><?= htmlspecialchars($faq['id']) ?></td>
          <td><?= htmlspecialchars($faq['faq_for']) ?></td>
          <td><?= htmlspecialchars($faq['item_id']) ?></td>
          <td><?= htmlspecialchars($faq['question']) ?></td>
          <td><?= htmlspecialchars($faq['created_at']) ?></td>
          <td>
            <button class="btn btn-action btn-edit">
              <a href="edit-faq.php?id=<?= $faq['id'] ?>">
                <i class="fas fa-edit"></i> Edit
              </a>
            </button>
            <button class="btn btn-action btn-delete">
              <a href="?delete_id=<?= $faq['id'] ?>">
                <i class="fas fa-trash"></i> Delete
              </a>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Pagination Controls -->
  <div class="pagination-container">
    <nav>
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
        </li>
      </ul>
    </nav>
  </div>
</div>

<script 
  src="https://code.jquery.com/jquery-3.5.1.min.js">
</script>
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
