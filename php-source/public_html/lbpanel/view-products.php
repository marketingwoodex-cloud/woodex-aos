<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';
require_once __DIR__ . '/./config/product_categories.php';

wdx_ensure_product_categories($conn);

/**
 * Delete product
 */
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    if ($mapStmt = $conn->prepare("DELETE FROM product_categories WHERE product_id = ?")) {
        $mapStmt->bind_param('i', $deleteId);
        $mapStmt->execute();
        $mapStmt->close();
    }
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Product deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting product!';
    }
    $stmt->close();
    header('Location: view-products.php');
    exit;
}

/**
 * Pagination
 */
$perPage = 5;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = trim((string)($_GET['q'] ?? ''));
$offset = ($page - 1) * $perPage;

$whereSql = '';
$searchLike = '';
if ($search !== '') {
    $whereSql = ' WHERE (p.product_name LIKE ? OR p.slug LIKE ? OR p.product_sku LIKE ? OR EXISTS (SELECT 1 FROM product_categories pcs JOIN categories cs ON cs.id = pcs.category_id WHERE pcs.product_id = p.id AND cs.category_name LIKE ?)) ';
    $searchLike = '%' . $search . '%';
}

/**
 * Count total products
 */
$countSql = "SELECT COUNT(*) AS total FROM products p" . $whereSql;
$rowCount = null;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($search !== '') {
        $countStmt->bind_param('ssss', $searchLike, $searchLike, $searchLike, $searchLike);
    }
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $rowCount = $countRes ? $countRes->fetch_assoc() : null;
    $countStmt->close();
}
$total      = (int)($rowCount['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/**
 * Fetch products with variation-aware price & stock
 *
 * - min/max price prefer in-stock variations (stock NULL or > 0). Fallback to any variation, then product price.
 * - stock:
 *    has_instock_flag: 1 if any variation is in stock (NULL or > 0)
 *    sum_stock_in: SUM of in-stock variation quantities (NULL counts as 0 for the sum).
 *    If has_instock_flag=1 and sum_stock_in=0 (because stocks are NULL), we will display “In stock”.
 *    If no variations exist, use product.quantity and product.current_price.
 */
$sql = "
  SELECT
    p.id,
    p.product_name,
    p.slug,
    COALESCE((
      SELECT GROUP_CONCAT(c.category_name ORDER BY c.category_name SEPARATOR ', ')
      FROM product_categories pc
      JOIN categories c ON c.id = pc.category_id
      WHERE pc.product_id = p.id
    ), 'Uncategorized') AS category_names,
    p.current_price,
    p.quantity,

    COUNT(v.id) AS variation_count,

    /* Prefer in-stock variation prices */
    MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END) AS min_price_stock,
    MAX(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END) AS max_price_stock,

    /* Fallback (any variation prices) */
    MIN(v.price) AS min_price_any,
    MAX(v.price) AS max_price_any,

    /* Stock signals */
    SUM(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN COALESCE(v.stock,0) END) AS sum_stock_in,
    MAX(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN 1 ELSE 0 END) AS has_instock_flag

  FROM products p
  LEFT JOIN product_variations v
    ON v.product_id = p.id
  {$whereSql}
  GROUP BY
    p.id, p.product_name, p.slug, p.current_price, p.quantity
  ORDER BY p.id ASC
  LIMIT ? OFFSET ?
";

$products = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($search !== '') {
        $stmt->bind_param('ssssii', $searchLike, $searchLike, $searchLike, $searchLike, $perPage, $offset);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
    $pid        = (int)$r['id'];
    $pname      = $r['product_name'];
    $pslug      = (string)($r['slug'] ?? '');
    $catName    = (string)($r['category_names'] ?? 'Uncategorized');
    $basePrice  = $r['current_price'] !== null ? (float)$r['current_price'] : 0.0;
    $baseQty    = $r['quantity'] !== null ? (int)$r['quantity'] : 0;

    $varCount   = (int)$r['variation_count'];
    $minStock   = $r['min_price_stock'] !== null ? (float)$r['min_price_stock'] : null;
    $maxStock   = $r['max_price_stock'] !== null ? (float)$r['max_price_stock'] : null;
    $minAny     = $r['min_price_any']   !== null ? (float)$r['min_price_any']   : null;
    $maxAny     = $r['max_price_any']   !== null ? (float)$r['max_price_any']   : null;

    $sumStockIn = $r['sum_stock_in'] !== null ? (int)$r['sum_stock_in'] : 0;
    $hasInFlag  = (int)$r['has_instock_flag'] === 1;

    // Determine display price range
    if ($varCount > 0) {
        $minPrice = $minStock !== null ? $minStock : ($minAny !== null ? $minAny : $basePrice);
        $maxPrice = $maxStock !== null ? $maxStock : ($maxAny !== null ? $maxAny : $basePrice);
    } else {
        $minPrice = $basePrice;
        $maxPrice = $basePrice;
    }

    // Determine stock display
    if ($varCount > 0) {
        if ($hasInFlag) {
            // At least one variation is in stock:
            // If sum is > 0, show the sum. If sum == 0 (NULL stocks) show generic "In stock".
            $stockDisplay = ($sumStockIn > 0) ? $sumStockIn : null; // null => "In stock"
        } else {
            // No in-stock variations
            $stockDisplay = 0;
        }
    } else {
        // No variations => fallback to product quantity
        $stockDisplay = $baseQty;
    }

    $products[] = [
        'id'           => $pid,
        'name'         => $pname,
        'slug'         => $pslug,
        'category'     => $catName !== '' ? $catName : 'Uncategorized',
        'min_price'    => (float)$minPrice,
        'max_price'    => (float)$maxPrice,
        'has_range'    => (float)$minPrice !== (float)$maxPrice,
        'stock'        => $stockDisplay, // int or null (null means generic “In stock”)
        'has_variants' => $varCount > 0,
        'base_price'   => $basePrice,
        'base_qty'     => $baseQty,
    ];
    }
    $stmt->close();
} else {
    // Avoid hard-failing the page on SQL errors.
    $_SESSION['error'] = 'Unable to load products right now.';
}

/**
 * Flash messages
 */
$successMessage = $_SESSION['success'] ?? '';
$errorMessage   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Products</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .status-indicator{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;vertical-align:middle}
    .status-in-stock{background:#28a745}
    .status-low-stock{background:#ffc107}
    .status-out-of-stock{background:#dc3545}
    .text-price{white-space:nowrap}
    .product-actions{white-space:nowrap}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="table-container">
  <div class="table-header">Product Inventory</div>

  <?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
      <?= htmlspecialchars($successMessage) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php elseif ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
      <?= htmlspecialchars($errorMessage) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="search-bar">
    <form method="get" action="view-products.php" class="d-flex gap-2 align-items-center">
      <input type="text" name="q" class="form-control" id="searchInput" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search products by name, slug, SKU...">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="view-products.php" class="btn btn-outline-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <table class="table table-borderless text-center align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th class="text-start">Product Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="productTable">
      <?php if (empty($products)): ?>
        <tr><td colspan="6">No products found.</td></tr>
      <?php else: ?>
        <?php foreach ($products as $p): ?>
          <?php
            $priceHtml = $p['has_range']
              ? 'PKR ' . number_format($p['min_price'], 2) . ' – ' . number_format($p['max_price'], 2)
              : 'PKR ' . number_format($p['min_price'], 2);

            // Stock badge logic
            $stockLabel = '';
            $stockIcon  = '';
            if ($p['stock'] === null) {
                // In stock (unknown / unlimited)
                $stockLabel = 'In stock';
                $stockIcon  = 'status-in-stock';
            } else {
                $qty = (int)$p['stock'];
                if ($qty > 10)      { $stockLabel = $qty; $stockIcon = 'status-in-stock'; }
                elseif ($qty > 0)   { $stockLabel = $qty; $stockIcon = 'status-low-stock'; }
                else                { $stockLabel = '0';  $stockIcon = 'status-out-of-stock'; }
            }
          ?>
          <tr>
            <td>#<?= (int)$p['id'] ?></td>
            <td class="text-start"><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-price"><?= $priceHtml ?></td>
            <td>
              <span class="status-indicator <?= $stockIcon ?>"></span>
              <?= htmlspecialchars((string)$stockLabel) ?>
            </td>
            <td class="product-actions">
              <?php if ($p['slug'] !== ''): ?>
                <a href="<?= htmlspecialchars(rtrim($domain, '/') . '/shop/' . rawurlencode($p['slug']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-action btn-secondary" target="_blank" rel="noopener">
                  <i class="fas fa-eye"></i> View
                </a>
              <?php endif; ?>
              <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn btn-action btn-edit">
                <i class="fas fa-edit"></i> Edit
              </a>
              <a
                href="view-products.php?delete_id=<?= (int)$p['id'] ?>"
                class="btn btn-action btn-delete"
                onclick="return confirm('Are you sure you want to delete this product?');"
              >
                <i class="fas fa-trash"></i> Delete
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
      <nav>
        <ul class="pagination">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="view-products.php?page=<?= max(1, $page-1) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Previous</a>
          </li>
          <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="view-products.php?page=<?= $i ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="view-products.php?page=<?= min($totalPages, $page+1) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $(function(){
    let timer = null;
    $('#searchInput').on('input', function(){
      clearTimeout(timer);
      const form = this.form;
      timer = setTimeout(function(){
        if (form) form.submit();
      }, 500);
    });
  });
</script>
</body>
</html>
