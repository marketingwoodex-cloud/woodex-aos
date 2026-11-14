<?php
require_once "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();

wdx_ensure_product_categories($conn);

/* ---------------------- Helpers ---------------------- */
function abs_url($path) {
  global $domain;
  if (!$path) return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  if (!empty($domain)) return rtrim($domain, '/') . '/' . ltrim($path, '/');
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function db_name($conn) {
  $res = $conn->query("SELECT DATABASE() AS db");
  $row = $res ? $res->fetch_assoc() : null;
  return $row ? $row['db'] : '';
}
function table_exists($conn, $db, $table) {
  if (!$db || !$table) return false;
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1");
  $stmt->bind_param("ss", $db, $table);
  $stmt->execute(); $stmt->store_result();
  $ok = $stmt->num_rows > 0;
  $stmt->close();
  return $ok;
}
function column_exists($conn, $db, $t, $c) {
  if (!$db || !$t || !$c) return false;
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=? LIMIT 1");
  $stmt->bind_param("sss", $db, $t, $c);
  $stmt->execute(); $stmt->store_result();
  $ok = $stmt->num_rows > 0;
  $stmt->close();
  return $ok;
}

/* ---------------------- Input ---------------------- */
$query = '';
if (isset($_GET['query'])) {
  $query = trim((string)$_GET['query']);
}
if (!$query && isset($_GET['q'])) {
  $query = trim((string)$_GET['q']);
}
if (strpos($query, '-') !== false) $query = str_replace('-', ' ', $query);
$cleanTitle = htmlspecialchars($query, ENT_QUOTES);

if ($query === '') {
  $products = [];
  $categories = [];
} else {
  $like      = '%'.$query.'%';
  $likeStart = $query.'%';

  $DB = db_name($conn);
  $hasBrands       = table_exists($conn, $DB, 'brands')      && column_exists($conn, $DB, 'brands', 'brand_name');
  $hasCategories   = table_exists($conn, $DB, 'categories')  && column_exists($conn, $DB, 'categories', 'category_name');
  $hasProdCatId    = column_exists($conn, $DB, 'products', 'category_id');
  $hasProdCatMap   = table_exists($conn, $DB, 'product_categories');
  $hasVariations   = table_exists($conn, $DB, 'product_variations');

  $joins = '';
  $wheres = [
    'p.product_name LIKE ?',
    'p.product_sku LIKE ?',
    'p.short_description LIKE ?',
    'p.product_details LIKE ?'
  ];
  if ($hasBrands) {
    $joins  .= ' LEFT JOIN brands b ON b.id = p.brand_id ';
    $wheres[] = 'b.brand_name LIKE ?';
  }
  if ($hasCategories && $hasProdCatId) {
    $joins  .= ' LEFT JOIN categories c1 ON c1.id = p.category_id ';
    $wheres[] = 'c1.category_name LIKE ?';
  }
  if ($hasCategories && $hasProdCatMap) {
    $joins  .= ' LEFT JOIN product_categories pc ON pc.product_id = p.id
                 LEFT JOIN categories c2 ON c2.id = pc.category_id ';
    $wheres[] = 'c2.category_name LIKE ?';
  }

  $effPriceExpr = $hasVariations
    ? "COALESCE(NULLIF(p.current_price,0),
                (SELECT MIN(v.price) FROM product_variations v WHERE v.product_id = p.id))"
    : "p.current_price";

  $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $limit = 24;
  $offset = ($page - 1) * $limit;

  /* -------- COUNT QUERY -------- */
  $sqlCount = "SELECT COUNT(DISTINCT p.id) AS c 
             FROM products p 
             $joins 
             WHERE (" . implode(' OR ', $wheres) . ")";

  $typesC  = str_repeat('s', count($wheres));
  $paramsC = array_fill(0, count($wheres), $like);

  if ($stmt = $conn->prepare($sqlCount)) {
      $stmt->bind_param($typesC, ...$paramsC);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      $totalProducts = (int)($row['c'] ?? 0);
      $stmt->close();
  } else {
      $totalProducts = 0;
  }

  /* -------- PRODUCT QUERY -------- */
  $sqlProducts = "
    SELECT DISTINCT
      p.id, p.product_name, p.slug, p.images,
      $effPriceExpr AS eff_price
      ".($hasBrands ? ", b.brand_name" : "").",
      ".($hasCategories && $hasProdCatMap ? "(SELECT GROUP_CONCAT(c3.category_name ORDER BY c3.category_name SEPARATOR ', ') FROM product_categories pc3 JOIN categories c3 ON c3.id = pc3.category_id WHERE pc3.product_id = p.id) AS main_category" : ($hasCategories && $hasProdCatId ? "c1.category_name AS main_category" : "NULL AS main_category"))."
    FROM products p
    $joins
    WHERE (".implode(' OR ', $wheres).")
    ORDER BY
      (p.product_name LIKE ?) DESC,
      (p.product_name LIKE ?) DESC
      ".($hasBrands ? ", (b.brand_name LIKE ?) DESC" : "").",
      p.id DESC
    LIMIT ? OFFSET ?
  ";

  // total placeholders = count($wheres) + 2 or 3 (for ORDER BY) + 2 (limit/offset)
  $typesP = str_repeat('s', count($wheres)); 
  $paramsP = array_fill(0, count($wheres), $like);

  // add ORDER BY bindings
  $typesP .= $hasBrands ? 'sss' : 'ss';
  if ($hasBrands)
      $paramsP = array_merge($paramsP, [$likeStart, $like, $like]);
  else
      $paramsP = array_merge($paramsP, [$likeStart, $like]);

  // add LIMIT + OFFSET
  $typesP .= 'ii';
  $paramsP[] = (int)$limit;
  $paramsP[] = (int)$offset;

  /* -------- EXECUTE PRODUCT QUERY -------- */
  $products = [];
  if ($stmt = $conn->prepare($sqlProducts)) {
      // Reliable dynamic binding
      $bindArgs = [];
      $bindArgs[] = $typesP;
      foreach ($paramsP as $k => $v) {
          $bindArgs[] = &$paramsP[$k];
      }
      call_user_func_array([$stmt, 'bind_param'], $bindArgs);

      $stmt->execute();
      $res = $stmt->get_result();

      while ($row = $res->fetch_assoc()) {
          $images = json_decode($row['images'] ?? '[]', true);
          $img = is_array($images) && !empty($images)
              ? abs_url($images[0])
              : abs_url('assets/images/placeholder.png');
          $brand = (!empty($row['brand_name'])) ? $row['brand_name'] : '';
          $price = isset($row['eff_price']) ? (float)$row['eff_price'] : 0.0;

          $products[] = [
              'id'    => (int)$row['id'],
              'name'  => $row['product_name'],
              'slug'  => (string)$row['slug'],
              'brand' => $brand,
              'price' => $price,
              'image' => $img,
              'url'   => abs_url('shop/' . $row['slug']),
              'cat'   => $row['main_category'] ?? ''
          ];
      }

      $stmt->close();
  }

  /* ---------------------- Category matches (side section) ---------------------- */
$categories = [];
if ($hasCategories) {

  // Direct category link (products.category_id)
  if ($hasProdCatId) {
    $sql = "
      SELECT c.id, c.category_name, c.slug, c.main_image, COUNT(DISTINCT p.id) AS cnt
      FROM categories c
      JOIN products p ON p.category_id = c.id
      WHERE c.category_name LIKE ?
      GROUP BY c.id
      ORDER BY cnt DESC, c.category_name ASC
      LIMIT 12
    ";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param("s", $like);
      $st->execute();
      $r = $st->get_result();
      while ($row = $r->fetch_assoc()) {
        if (!empty($row['category_name']) && !empty($row['slug'])) {
          $categories[] = [
            'name'  => $row['category_name'],
            'slug'  => $row['slug'],
            'image' => $row['main_image'] ? abs_url($row['main_image']) : abs_url('assets/images/placeholder.png'),
            'count' => (int)$row['cnt'],
            'url'   => abs_url('categories/' . $row['slug'])
          ];
        }
      }
      $st->close();
    }
  }

  // Mapping table (product_categories)
  if ($hasProdCatMap) {
    $sql = "
      SELECT c.id, c.category_name, c.slug, c.main_image, COUNT(DISTINCT p.id) AS cnt
      FROM categories c
      JOIN product_categories pc ON pc.category_id = c.id
      JOIN products p ON p.id = pc.product_id
      WHERE c.category_name LIKE ?
      GROUP BY c.id
      ORDER BY cnt DESC, c.category_name ASC
      LIMIT 12
    ";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param("s", $like);
      $st->execute();
      $r = $st->get_result();
      while ($row = $r->fetch_assoc()) {
        if (!empty($row['category_name']) && !empty($row['slug'])) {
          $categories[] = [
            'name'  => $row['category_name'],
            'slug'  => $row['slug'],
            'image' => $row['main_image'] ? abs_url($row['main_image']) : abs_url('assets/images/placeholder.png'),
            'count' => (int)$row['cnt'],
            'url'   => abs_url('categories/' . $row['slug'])
          ];
        }
      }
      $st->close();
    }
  }

  // Remove empty or invalid category entries before deduplication
  $categories = array_filter($categories, function($c) {
    return !empty($c['name']) && !empty($c['slug']);
  });

  // Deduplicate by slug
  $seen = [];
  $categories = array_values(array_filter($categories, function($c) use (&$seen) {
    $k = strtolower($c['slug']);
    if (isset($seen[$k])) return false;
    $seen[$k] = true;
    return true;
  }));
}
}

$totalPages = isset($totalProducts) ? max(1, (int)ceil($totalProducts / $limit)) : 1;
$currentPage = isset($page) ? $page : 1;
function page_url($pageNum, $query) {
  return 'search_results.php?query=' . urlencode($query) . '&page=' . $pageNum;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Search Results<?= $cleanTitle ? ' - '.$cleanTitle : '' ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet" />
  <style>
    .srch-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px; }
    .srch-count { color:#6c757d; }
    .cat-chip { display:flex; gap:10px; align-items:center; padding:10px 12px; border:1px solid #eee; border-radius:10px; text-decoration:none; color:inherit; }
    .cat-chip:hover { background:#fafafa; }
    .cat-chip img { width:44px; height:44px; object-fit:cover; border-radius:8px; background:#f4f4f4; }
    .prod-card .brand { color:#6c757d; font-size:.92rem; }
    .prod-card .price { font-weight:700; color:#5c5c5c; }
  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<section class="py-4" style="background:#f8f8f8;">
  <div class="container">
    <div class="srch-head">
      <h1 class="h4 mb-0">Search results<?= $cleanTitle ? ' for: “'.$cleanTitle.'”' : '' ?></h1>
      <?php if (!empty($query)): ?>
      <div class="srch-count small">
        <?php if (isset($totalProducts)): ?>
          <?= number_format($totalProducts) ?> product<?= $totalProducts==1?'':'s' ?> found
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($query === ''): ?>
      <div class="alert alert-light border">
        Type something in the search box to get results.
      </div>
    <?php else: ?>

      <?php if (!empty($products)): ?>
      <div class="row g-4 product-row-v2">
        <?php foreach ($products as $p): ?>
        <div class="col-lg-3 col-md-4 col-sm-6 d-flex">
          <div class="product-card-v2">
            <div class="product-image-container-v2">
              <button type="button" class="wdx-quick-view-btn" data-slug="<?= htmlspecialchars((string)$p['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-eye"></i> Quick View
              </button>
              <a href="<?= htmlspecialchars($p['url']) ?>" class="text-decoration-none text-dark">
                <img src="<?= htmlspecialchars($p['image']) ?>" class="product-image-v2" alt="<?= htmlspecialchars($p['name']) ?>">
              </a>
            </div>
            <div class="product-details-v2">
              <?php if (!empty($p['cat'])): ?>
              <div class="product-cat-v2"><?= htmlspecialchars($p['cat']) ?></div>
              <?php endif; ?>
              <p class="product-title-v2">
                <a href="<?= htmlspecialchars($p['url']) ?>" class="text-decoration-none text-dark">
                  <?= htmlspecialchars($p['name']) ?>
                </a>
              </p>
              <p class="mb-0"><span class="product-price-v2">PKR <?= number_format((float)$p['price'], 0) ?></span></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $currentPage<=1?'disabled':'' ?>">
            <a class="page-link" href="<?= $currentPage<=1 ? '#' : htmlspecialchars(page_url($currentPage-1, $query)) ?>">Previous</a>
          </li>
          <?php
          $start = max(1, $currentPage - 2);
          $end   = min($totalPages, $currentPage + 2);
          if ($start > 1) echo '<li class="page-item"><a class="page-link" href="'.htmlspecialchars(page_url(1, $query)).'">1</a></li><li class="page-item disabled"><span class="page-link">…</span></li>';
          for ($i=$start; $i<=$end; $i++) {
            $active = $i===$currentPage ? ' active' : '';
            echo '<li class="page-item'.$active.'"><a class="page-link" href="'.htmlspecialchars(page_url($i, $query)).'">'.$i.'</a></li>';
          }
          if ($end < $totalPages) echo '<li class="page-item disabled"><span class="page-link">…</span></li><li class="page-item"><a class="page-link" href="'.htmlspecialchars(page_url($totalPages, $query)).'">'.$totalPages.'</a></li>';
          ?>
          <li class="page-item <?= $currentPage>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="<?= $currentPage>=$totalPages ? '#' : htmlspecialchars(page_url($currentPage+1, $query)) ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>

      <?php else: ?>
      <div class="alert alert-light border">
        No products matched “<?= $cleanTitle ?>”. Try different keywords (product, brand, or category).
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
