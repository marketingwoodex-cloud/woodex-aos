<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include 'lbpanel/config/connection.php';
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

function ensureColumnIfMissing($conn, $table, $column, $definition) {
  $safeTable = str_replace('`', '', (string)$table);
  $safeColumn = str_replace('`', '', (string)$column);
  $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
  }
}

function renderPriceRange($row) {
  $min = (float)($row['min_price'] ?? 0);
  $max = (float)($row['max_price'] ?? 0);
  if ($min > 0 && $max > 0 && $min != $max) {
    return 'PKR ' . number_format($min, 0) . ' – ' . number_format($max, 0);
  }
  return 'PKR ' . number_format($min, 0);
}

function renderDiscountPercent($row) {
  $old = (float)($row['old_price'] ?? 0);
  $min = (float)($row['min_price'] ?? 0);
  return ($old > 0 && $old > $min) ? round((($old - $min) / $old) * 100) : 0;
}

if (!function_exists('ensureSaleSettingsTable')) {
  function ensureSaleSettingsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS sale_page_settings (
      id TINYINT UNSIGNED PRIMARY KEY,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      hero_background VARCHAR(2048) NOT NULL DEFAULT 'assets/images/herobg.webp',
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $exists = $conn->query("SELECT id FROM sale_page_settings WHERE id = 1 LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
      $conn->query("INSERT INTO sale_page_settings (id, is_active, hero_background) VALUES (1, 1, 'assets/images/herobg.webp')");
    }
  }
}

ensureColumnIfMissing($conn, 'products', 'is_sale_item', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_best_seller");
ensureSaleSettingsTable($conn);

$salePageActive = true;
$saleHeroBg = 'assets/images/herobg.webp';
$settingsRes = $conn->query("SELECT is_active, hero_background FROM sale_page_settings WHERE id = 1 LIMIT 1");
if ($settingsRes && ($settings = $settingsRes->fetch_assoc())) {
  $salePageActive = ((int)($settings['is_active'] ?? 1) === 1);
  $hero = trim((string)($settings['hero_background'] ?? ''));
  if ($hero !== '') {
    $saleHeroBg = $hero;
  }
}

if (!$salePageActive) {
  http_response_code(404);
  include_once('header/header.php');
  echo '<section class="py-5"><div class="container"><div class="text-center bg-white border rounded-3 p-5"><h2 class="mb-2">Sale Page Unavailable</h2><p class="text-muted mb-0">Sale page is currently disabled by admin.</p></div></div></section>';
  include_once('footer/footer.php');
  exit;
}

$saleQuery = "
  SELECT
    p.id,
    p.product_name,
    p.old_price,
    p.sold_quantity,
    p.images,
    p.slug,
    p.rating_count,
    p.rating_sum,
    p.quantity,
    p.is_sale_item,
    COALESCE((
      SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ')
      FROM product_categories pc2
      JOIN categories c2 ON c2.id = pc2.category_id
      WHERE pc2.product_id = p.id
    ), c.category_name) AS category_name,
    MIN(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price ELSE p.current_price END) AS min_price,
    MAX(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price ELSE p.current_price END) AS max_price,
    CASE
      WHEN COALESCE(p.rating_count,0) > 0 THEN ROUND(COALESCE(p.rating_sum,0) / p.rating_count, 1)
      ELSE 0
    END AS avg_rating,
    COALESCE(
      SUM(CASE WHEN v.stock IS NOT NULL THEN v.stock ELSE 0 END),
      p.quantity,
      0
    ) AS total_stock
  FROM products p
  LEFT JOIN categories c ON c.id = p.category
  LEFT JOIN product_variations v ON v.product_id = p.id
  WHERE p.is_sale_item = 1
  GROUP BY p.id, p.product_name, p.old_price, p.sold_quantity, p.images, p.slug, p.rating_count, p.rating_sum, p.quantity, p.is_sale_item, c.category_name
  ORDER BY p.id DESC
";

$saleProducts = $conn->query($saleQuery);

$heroBg = preg_match('~^https?://~i', $saleHeroBg)
  ? $saleHeroBg
  : rtrim((string)$domain, '/') . '/' . ltrim($saleHeroBg, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sale Items - Woodex Furniture</title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
  <meta name="description" content="Browse exclusive Woodex sale items and limited-time discounted office and home furniture deals.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">
</head>
<body>
<?php include_once('header/header.php'); ?>

<section>
  <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo htmlspecialchars($heroBg, ENT_QUOTES, 'UTF-8'); ?>');">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>
    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-7 col-xl-6">
          <div class="wdx-badge">Limited Time</div>
          <h1 class="wdx-hero-title">Sale <span>Items</span></h1>
          <p class="wdx-desc">Handpicked products marked for sale. Explore special pricing and secure your favorites before they are gone.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="home-products py-4" style="background:#f7f8fa;">
  <div class="product-section-v2">
    <div class="container">
      <?php if ($saleProducts && $saleProducts->num_rows > 0): ?>
        <div class="product-row-v2 justify-content-start row">
          <?php while ($row = $saleProducts->fetch_assoc()): ?>
            <?php include 'product-card.php'; ?>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-5 bg-white border rounded-3">
          <h3 class="mb-2">No Sale Items Yet</h3>
          <p class="text-muted mb-0">Please check back soon for new sale products.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
