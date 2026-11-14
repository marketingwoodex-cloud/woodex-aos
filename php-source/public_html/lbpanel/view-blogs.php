<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Flash message
$message = '';
if (!empty($_SESSION['success'])) {
    $message = "<div class='alert alert-success text-center'>" . htmlspecialchars($_SESSION['success']) . "</div>";
    unset($_SESSION['success']);
}
if (!empty($_SESSION['error'])) {
    $message = "<div class='alert alert-danger text-center'>" . htmlspecialchars($_SESSION['error']) . "</div>";
    unset($_SESSION['error']);
}

// Handle deletion (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['error'] = 'Invalid request token.';
        header('Location: view-blogs.php');
        exit;
    }

    $delete_id = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Blog deleted successfully.';
    } else {
        $_SESSION['error'] = 'Error deleting blog.';
    }
    $stmt->close();
    header('Location: view-blogs.php');
    exit;
}

// Pagination
$blogsPerPage = 10;
$page         = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$q            = trim((string)($_GET['q'] ?? ''));
$like         = '%' . $q . '%';
$offset       = ($page - 1) * $blogsPerPage;

// Count total for pagination
$totalBlogs = 0;
if ($q !== '') {
  $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM blogs WHERE blog_title LIKE ? OR slug LIKE ? OR meta_description LIKE ? OR blog_content LIKE ?");
  if ($countStmt) {
    $countStmt->bind_param('ssss', $like, $like, $like, $like);
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $totalBlogs = (int)($countRes->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
  }
} else {
  $countResult = $conn->query("SELECT COUNT(*) AS total FROM blogs");
  $totalBlogs  = (int)($countResult->fetch_assoc()['total'] ?? 0);
}
$totalPages  = max(1, (int)ceil($totalBlogs / $blogsPerPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $blogsPerPage;

// Fetch blogs for the current page
$stmt = $q !== ''
  ? $conn->prepare("SELECT id, blog_title, slug, meta_description, blog_image, created_at FROM blogs WHERE blog_title LIKE ? OR slug LIKE ? OR meta_description LIKE ? OR blog_content LIKE ? ORDER BY id DESC LIMIT ?, ?")
  : $conn->prepare("SELECT id, blog_title, slug, meta_description, blog_image, created_at FROM blogs ORDER BY id DESC LIMIT ?, ?");
if ($q !== '') {
  $stmt->bind_param("ssssii", $like, $like, $like, $like, $offset, $blogsPerPage);
} else {
  $stmt->bind_param("ii", $offset, $blogsPerPage);
}
$stmt->execute();
$result = $stmt->get_result();
$blogs  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Blogs</title>
  <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="table-container">
  <div class="table-header">
    Blog Inventory
  </div>

  <div class="d-flex justify-content-end mb-3">
    <a href="add-blog.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Blog</a>
  </div>

  <!-- Search Bar -->
  <div class="search-bar">
    <form method="get" action="view-blogs.php" class="d-flex gap-2 align-items-center">
      <input 
        type="text" 
        class="form-control" 
        id="searchInput" 
        name="q"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Search blogs by title, slug, meta, content..."
      >
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-blogs.php">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if (!empty($message)): ?>
    <?php echo $message; ?>
  <?php endif; ?>

  <table class="table table-borderless text-center">
    <thead>
      <tr>
        <th>ID</th>
        <th>Image</th>
        <th>Blog Title</th>
        <th>Slug</th>
        <th>Meta Description</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="blogTable">
      <?php foreach ($blogs as $blog): ?>
        <?php
          $img = !empty($blog['blog_image']) ? '../' . ltrim($blog['blog_image'], '/') : '';
          $slug = $blog['slug'] ?? '';
        ?>
        <tr>
          <td><?= htmlspecialchars($blog['id']) ?></td>
          <td>
            <?php if ($img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="" style="width:70px;height:44px;object-fit:cover;border-radius:8px;">
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($blog['blog_title']) ?></td>
          <td><code><?= htmlspecialchars($slug) ?></code></td>
          <td><?= htmlspecialchars($blog['meta_description']) ?></td>
          <td><?= htmlspecialchars($blog['created_at']) ?></td>
          <td>
            <button class="btn btn-action btn-edit">
              <a href="edit-blog.php?id=<?= $blog['id'] ?>">
                <i class="fas fa-edit"></i> Edit
              </a>
            </button>
            <form method="post" action="view-blogs.php" style="display:inline;" onsubmit="return confirm('Delete this blog?');">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="delete_id" value="<?= (int)$blog['id'] ?>">
              <button type="submit" class="btn btn-action btn-delete"><i class="fas fa-trash"></i> Delete</button>
            </form>
            <?php if (!empty($slug)): ?>
              <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= htmlspecialchars($domain . 'blog/' . $slug) ?>">Preview</a>
            <?php endif; ?>
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

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
