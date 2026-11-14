<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Require login
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access reviews.';
    header('Location: index.php');
    exit;
}

// Delete (optional)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $rid = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param('i', $rid);
    if ($stmt->execute()) $_SESSION['success'] = 'Review deleted.';
    else $_SESSION['error'] = 'Failed to delete review.';
    $stmt->close();
    header('Location: manage_reviews.php');
    exit;
}

// Search & filter
$perPage = 10;
$page    = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$offset  = ($page - 1) * $perPage;

$search  = isset($_GET['s']) ? trim($_GET['s']) : '';
$statusF = isset($_GET['status']) ? $_GET['status'] : 'all'; // all|pending|approved|rejected

$where = [];
$types = '';
$args  = [];

if ($search !== '') {
    // match order_number, product name, review_text
    $where[] = "(r.order_number LIKE ? OR p.product_name LIKE ? OR r.review_text LIKE ?)";
    $like = "%{$search}%";
    $types .= 'sss';
    $args[]  = $like; $args[] = $like; $args[] = $like;
}
if (in_array($statusF, ['pending','approved','rejected'], true)) {
    $where[] = "r.status = ?";
    $types .= 's';
    $args[]  = $statusF;
}
$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$sqlCount = "
  SELECT COUNT(*) AS c
  FROM reviews r
  JOIN order_items oi ON oi.order_id = r.order_number AND oi.product_id = r.product_id
  LEFT JOIN products p ON p.id = r.product_id
  $whereSQL
";
$stmt = $conn->prepare($sqlCount);
if ($types) { $stmt->bind_param($types, ...$args); }
$stmt->execute();
$res = $stmt->get_result();
$totalRows = (int)($res->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Data
$sql = "
  SELECT
    r.id, r.order_number, r.product_id, r.rating, r.review_text, r.status, r.created_at,
    p.product_name, p.slug,
    oi.subtotal
  FROM reviews r
  JOIN order_items oi ON oi.order_id = r.order_number AND oi.product_id = r.product_id
  LEFT JOIN products p ON p.id = r.product_id
  $whereSQL
  ORDER BY
    CASE r.status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END,
    r.created_at DESC
  LIMIT ? OFFSET ?
";
$types2 = $types . 'ii';
$args2  = $args;
$args2[] = $perPage;
$args2[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$args2);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) { $rows[] = $row; }
$stmt->close();

// Flash
$successMessage = $_SESSION['success'] ?? '';
$errorMessage   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Reviews</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .table-container{max-width:1200px;margin:30px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
    .table-header{font-size:1.25rem;font-weight:700;margin-bottom:12px}
    .search-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
    .review-text{max-width:380px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .rating-stars{color:#ffc107}
    .badge-status{padding:.4rem .6rem;border-radius:999px}
    .badge-status.pending{background:#fff3cd;color:#856404;border:1px solid #ffe9a6}
    .badge-status.approved{background:#e9f9ee;color:#0f5132;border:1px solid #b7f0c7}
    .badge-status.rejected{background:#fde8e8;color:#842029;border:1px solid #f8c1c1}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="table-container">
  <div class="table-header">Manage Reviews</div>

  <?php if ($successMessage): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
  <?php endif; ?>
  <?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
  <?php endif; ?>

  <form method="get" class="search-row">
    <input type="text" name="s" value="<?= htmlspecialchars($search) ?>" placeholder="Search by order #, product, or text" class="form-control" style="max-width:340px">
    <select name="status" class="form-select" style="max-width:180px">
      <option value="all"      <?= $statusF==='all'?'selected':'' ?>>All statuses</option>
      <option value="pending"  <?= $statusF==='pending'?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $statusF==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $statusF==='rejected'?'selected':'' ?>>Rejected</option>
    </select>
    <button class="btn btn-primary">Filter</button>
    <?php if ($search!=='' || $statusF!=='all'): ?>
      <a href="manage_reviews.php" class="btn btn-outline-secondary">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr class="text-center">
          <th style="width:120px">Order #</th>
          <th>Product</th>
          <th style="width:120px">Rating</th>
          <th>Review</th>
          <th style="width:140px">Status</th>
          <th style="width:210px">Actions</th>
        </tr>
      </thead>
      <tbody id="reviewTable">
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No reviews found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']);
          $statusClass = 'pending';
          if ($r['status']==='approved') $statusClass='approved';
          elseif ($r['status']==='rejected') $statusClass='rejected';
        ?>
        <tr>
          <td class="text-center fw-semibold"><?= htmlspecialchars($r['order_number']) ?></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($r['product_name'] ?? ('#'.$r['product_id'])) ?></div>
            <div class="text-muted small">PID: <?= (int)$r['product_id'] ?></div>
          </td>
          <td class="text-center">
            <span class="rating-stars"><?= $stars ?></span><br>
            <span class="text-muted small"><?= (int)$r['rating'] ?>/5</span>
          </td>
          <td><div class="review-text"><?= htmlspecialchars($r['review_text'] ?? '') ?></div></td>
          <td class="text-center">
            <span class="badge-status <?= $statusClass ?> text-capitalize"><?= htmlspecialchars($r['status']) ?></span>
          </td>
          <td class="text-center">
            <div class="d-flex gap-2 justify-content-center">
              <?php if ($r['status']!=='approved'): ?>
                <button class="btn btn-success btn-sm js-review-status" data-id="<?= (int)$r['id'] ?>" data-status="approved"><i class="fas fa-check me-1"></i>Approve</button>
              <?php endif; ?>
              <?php if ($r['status']!=='rejected'): ?>
                <button class="btn btn-warning btn-sm js-review-status" data-id="<?= (int)$r['id'] ?>" data-status="rejected"><i class="fas fa-ban me-1"></i>Reject</button>
              <?php endif; ?>
              <a class="btn btn-outline-danger btn-sm" href="manage_reviews.php?delete_id=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this review?');"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="manage_reviews.php?<?= http_build_query(['s'=>$search,'status'=>$statusF,'page'=>$page-1]) ?>">Previous</a>
        </li>
        <?php for ($p=1;$p<=$totalPages;$p++): ?>
          <li class="page-item <?= $p==$page?'active':'' ?>">
            <a class="page-link" href="manage_reviews.php?<?= http_build_query(['s'=>$search,'status'=>$statusF,'page'=>$p]) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="manage_reviews.php?<?= http_build_query(['s'=>$search,'status'=>$statusF,'page'=>$page+1]) ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
  $('.js-review-status').on('click', function(){
    const id = $(this).data('id');
    const status = $(this).data('status');
    $(this).prop('disabled', true);

    $.ajax({
  url: 'php/update_review_status.php',
  type: 'POST',
  dataType: 'json',          // ✅ tell jQuery to expect JSON
  data: { id, status },
  success: function(r) {
    if (r.success) {
      location.reload();
    } else {
      alert(r.message || 'Failed to update status.');
    }
  },
  error: function(xhr, status, error) {
    console.error('AJAX Error:', status, error, xhr.responseText);
    alert('Network or parsing error.');
  }
});
  });
});
</script>
</body>
</html>
