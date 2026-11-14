<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

if (!function_exists('wdx_join_url')) {
function wdx_join_url($base, $path) {
  $path = (string)$path;
  if ($path === '') return '';
  $path = str_replace('\\', '/', $path);
  if (preg_match('~^https?://~i', $path)) return $path;

  $base = rtrim((string)$base, '/') . '/';
  $basePath = '/';
  $p = parse_url($base, PHP_URL_PATH);
  if (is_string($p) && $p !== '') {
    $basePath = '/' . trim($p, '/') . '/';
  }
  $bpTrim = trim($basePath, '/');
  $origin = rtrim(preg_replace('~' . preg_quote($basePath, '~') . '$~', '/', $base), '/');

  if ($bpTrim !== '') {
    if (strpos($path, '/' . $bpTrim . '/') === 0) return $origin . $path;
    if (strpos($path, $bpTrim . '/') === 0) return $origin . '/' . $path;
  }
  if (strpos($path, '/assets/') === 0) return $origin . rtrim($basePath, '/') . $path;
  if (strpos($path, 'assets/') === 0) return $base . $path;
  if (strpos($path, '/') === 0) return $origin . rtrim($basePath, '/') . $path;
  return $base . $path;
}
}

/* ------------------ Data bootstrapping ------------------ */
$categoryQuery  = "
  SELECT c.id, c.category_name, c.parent_id, c.level, COUNT(DISTINCT pc.product_id) AS product_count
  FROM categories c
  LEFT JOIN product_categories pc ON pc.category_id = c.id
  GROUP BY c.id, c.category_name, c.parent_id, c.level
  ORDER BY c.level ASC, c.category_name ASC
";
$categoryRows = [];
$categoryTree = [];
$categoryResult = $conn->query($categoryQuery);
if ($categoryResult && $categoryResult->num_rows > 0) {
  while ($row = $categoryResult->fetch_assoc()) {
    $row['id'] = (int)($row['id'] ?? 0);
    $row['parent_id'] = (int)($row['parent_id'] ?? 0);
    $row['product_count'] = (int)($row['product_count'] ?? 0);
    if ($row['parent_id'] < 1) {
      $row['parent_id'] = 0;
    }
    $categoryRows[] = $row;
  }
}

foreach ($categoryRows as $row) {
  $parentId = (int)$row['parent_id'];
  if (!isset($categoryTree[$parentId])) {
    $categoryTree[$parentId] = [];
  }
  $categoryTree[$parentId][] = $row;
}

$categoryIds = [];
foreach ($categoryRows as $row) {
  $categoryIds[(int)$row['id']] = true;
}
foreach ($categoryRows as $row) {
  $parentId = (int)$row['parent_id'];
  if ($parentId > 0 && !isset($categoryIds[$parentId])) {
    $categoryTree[0][] = $row;
  }
}

if (!function_exists('renderShopCategoryItems')) {
  function renderShopCategoryItems($tree, $parentId = 0, $depth = 0) {
    if (empty($tree[$parentId])) {
      return;
    }
    foreach ($tree[$parentId] as $row) {
      $id = (int)($row['id'] ?? 0);
      $name = (string)($row['category_name'] ?? 'Category');
      $count = (int)($row['product_count'] ?? 0);
      $isChild = $depth > 0;
      $extraClass = $isChild ? ('subcat-item depth-' . min($depth, 4)) : 'maincat-item';
      echo '<a class="list-group-item ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" href="#" data-category="' . $id . '">';
      echo '<span>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
      echo '<span class="cat-count">(' . $count . ')</span>';
      echo '</a>';

      renderShopCategoryItems($tree, $id, $depth + 1);
    }
  }
}

$totalProducts = 0;
if ($tr = $conn->query("SELECT COUNT(*) AS c FROM products")) {
  $totalProducts = (int)($tr->fetch_assoc()['c'] ?? 0);
}

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

/* Recommended (variation-aware) */
$recommendedQuery = "
  SELECT
    p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug,
    COALESCE((
      SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ')
      FROM product_categories pc2
      JOIN categories c2 ON c2.id = pc2.category_id
      WHERE pc2.product_id = p.id
    ), c.category_name) AS category_name,
    CASE WHEN p.rating_count > 0 THEN p.rating_sum / p.rating_count ELSE 0 END AS avg_rating,
    p.rating_count,
    COUNT(v.id)  AS variation_count,
    MIN(v.price) AS min_price,
    MAX(v.price) AS max_price
  FROM products p
  LEFT JOIN categories c ON c.id = p.category
  LEFT JOIN product_variations v ON v.product_id = p.id
  GROUP BY p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug, c.category_name, p.rating_count, p.rating_sum
  ORDER BY RAND()
  LIMIT 8";
$recommendedResult   = $conn->query($recommendedQuery);
$recommendedProducts = [];
if ($recommendedResult && $recommendedResult->num_rows > 0) {
  while ($row = $recommendedResult->fetch_assoc()) {
    $recommendedProducts[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico" />
<title>Shop Premium Wardrobe Essentials | <?php echo $siteName ?? 'Store'; ?></title>
<meta name="description" content="Explore our premium Wardrobe Essentials collection. From denim jeans to quality shirts, find everything you need to enhance your style.">
<link rel="canonical" href="<?php echo htmlspecialchars($currentUrl, ENT_QUOTES); ?>">

<!-- Fonts & CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

<style>
:root{
  --shop-green: var(--accent-color);
  --shop-gray: #f6f7f8;
  --shop-border: #e9ecef;
  --text-muted-2: #6c757d;
}

body{ background:#fff; }
.page-wrap{ padding: 28px 0 56px; }

/* Sidebar */
.shop-sidebar{
  position: sticky;
  top: 24px;
  width: 100%;
  padding: 20px;
  background-color: #ffffff;
}

@media (min-width: 992px){
  .page-wrap .row.g-1 > aside{ padding-right: .35rem; }
  .page-wrap .row.g-1 > main{  padding-left: .35rem; }
}
.sidebar-title{
  letter-spacing: .04em;
  font-weight: 700;
  font-size: .9rem;
  display:flex;
  align-items:center;
  gap:.5rem;
  margin-bottom: .75rem;
}
.cat-list .list-group-item{
  border: 0;
  border-radius: 0;
  padding: .85rem 0;
  border-bottom: 1px solid var(--shop-border);
  display:flex;
  align-items:center;
  justify-content:space-between;
  color:#111;
  font-weight: 300;
}
.cat-list .list-group-item.maincat-item{
  font-weight: 700;
  font-size: .88rem;
  color:#0f172a;
}
.cat-list .list-group-item.subcat-item{
  font-weight: 400;
  color:#1f2937;
  padding-top:.2rem;
  font-size:13px;
  padding-bottom:.2rem;
}
.cat-list .list-group-item.subcat-item.depth-1{ padding-left: 1rem; }
.cat-list .list-group-item.subcat-item.depth-2{ padding-left: 1.7rem; }
.cat-list .list-group-item.subcat-item.depth-3,
.cat-list .list-group-item.subcat-item.depth-4{ padding-left: 2.3rem; }
.cat-list .list-group-item.active{
  background:#78be20;
  color:#000000;
  border-bottom-color:#78be20;
  padding-left: 1rem;
  padding-right: 1rem;
  border-radius: .25rem;
  margin-bottom: .25rem;
}
.cat-count{ color: var(--text-muted-2); font-weight: 500; }
.cat-list .active .cat-count{ color:#fff; opacity:.9; }

.price-box{
  border: 1px solid var(--shop-border);
  border-radius: .25rem;
  padding: 1.25rem;
  margin-top: 1.5rem;
  background:#fff;
}
.price-box h6{
  font-weight: 800;
  letter-spacing: .04em;
  font-size: .85rem;
  margin-bottom: .75rem;
}
.price-box .range{ font-weight: 600; margin-bottom: .25rem; }
.price-box small{ color: var(--text-muted-2); }

.filter-box{
  border: 1px solid var(--shop-border);
  border-radius: .25rem;
  padding: 1.0rem;
  margin-top: 1.0rem;
  background:#fff;
}
.filter-box h6{
  font-weight: 800;
  letter-spacing: .04em;
  font-size: .85rem;
  margin-bottom: .75rem;
}

.chip-wrap{ display:flex; flex-wrap:wrap; gap:10px; }
.chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: .55rem .8rem;
  border: 1px solid var(--shop-border);
  border-radius: .25rem;
  background:#fff;
  font-weight: 600;
  font-size: .86rem;
}
.chip input{ margin:0; }

.sidebar-actions .btn{ border-radius: .25rem; padding: .8rem 1rem; font-weight: 600; }
.btn-outline-dark{ border-color:#111; color:#111; }
.btn-outline-dark:hover{ background:#111; color:#fff; }

/* Header row */
.shop-header h2{ font-weight: 800; margin: 0; }
.shop-sub{ color: var(--text-muted-2); margin-top: .25rem; font-size: .95rem; }

.toolbar{ display:flex; gap:.75rem; justify-content:end; align-items:center; flex-wrap: wrap; }
.toolbar .form-select{ width: 180px; border-radius: .25rem; }
.view-toggle .btn{
  border: 1px solid var(--shop-border);
  border-radius: .25rem;
  width: 44px;
  height: 44px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#fff;
}
.view-toggle .btn.active{ background:#111; border-color:#111; color:#fff; }

.divider-soft{ border-top: 1px solid var(--shop-border); margin: 18px 0 22px; }

/* Tighten product grid spacing */
#products-grid{
  --bs-gutter-x: .9rem;
  --bs-gutter-y: .9rem;
}

/* Product cards */
.product-card{
  border: 1px solid var(--shop-border);
  border-radius: .25rem;
  overflow:hidden;
  background:#fff;
  transition: transform .12s ease, box-shadow .12s ease;
  height: 100%;
}
.product-card:hover{ transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0,0,0,.08); }
.product-media{
  position: relative;
  background: #fff;
  width: 300px;
  height: 300px;
  min-width: 300px;
  min-height: 300px;
  max-width: 300px;
  max-height: 300px;
  display:flex;
  align-items:center;
  justify-content:center;
  margin: 0 auto;
  padding: 0;
  overflow: hidden;
}
.product-media img{
  width: 100%;
  height: 100%;
  max-width: 100%;
  max-height: 100%;
  object-fit: cover;
  object-position: center;
  background: #fff;
  display:block;
  margin: 0 auto;
  transition: transform .24s ease;
}
.product-card:hover .product-media img{
  transform: scale(1);
}
.badge-pill{
  position:absolute;
  top: 12px;
  left: 12px;
  right: auto;
  font-weight: 700;
  font-size: .72rem;
  padding: .45rem .6rem;
  border-radius: .2rem;
  z-index: 4;
  white-space: nowrap;
}
.badge-green{ background: var(--shop-green); color:#0b0f14; }
.badge-red{ background:#e11d2e; color:#fff; }

.quick-view{
  position:absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: var(--shop-green);
  border: 1px solid var(--shop-green);
  color:#fff;
  border-radius: .25rem;
  padding: .6rem .9rem;
  font-weight: 700;
  display:none;
  gap:.5rem;
  align-items:center;
  white-space: nowrap;
  z-index: 6;
}
.product-card:hover .quick-view{ display:inline-flex; }
.quick-view:hover{ background:#67a61b; border-color:#67a61b; color:#fff; }

.product-body{ padding: 1rem 1rem 1.1rem; }
.product-cat{
  color: #000;
  font-weight: 800;
  letter-spacing: .08em;
  font-size: .72rem;
  margin-bottom: .25rem;
}
.product-name{
  font-weight: 300;
  margin: 0 0 .85rem;
  line-height: 1.35;
  font-size: 1.03rem;
  letter-spacing: .01em;
  color:#000;
  min-height: 2.8em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.product-price{ font-weight: 900; color:#111; margin:0; font-size: 1.05rem; }
.product-old{ color:#8a93a2; text-decoration: line-through; margin-right: 8px; font-weight: 700; }

#products-grid:not(.view-list) > [class*="col-"]{
  display:flex;
  justify-content:stretch;
}
#products-grid:not(.view-list) .product-card,
#products-grid:not(.view-list) .product-card-v2{
  width: 100%;
  max-width: none;
}

#products-grid .product-image-container-v2{
  height: 300px;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}

#products-grid .product-image-v2{
  width:100% !important;
  height:100% !important;
  object-fit:contain !important;
  object-position:center center !important;
}

#products-grid .product-card-v2:hover .product-image-v2{
  transform:none !important;
}

/* List view */
#products-grid.view-list .product-card-v2{ display:flex; width:100%; }
#products-grid.view-list .product-image-container-v2{ aspect-ratio: auto; width: 240px; min-height: 180px; flex:0 0 240px; }
#products-grid.view-list .product-image-container-v2 img{ max-width: 78%; max-height: 78%; margin: 0 auto; }
#products-grid.view-list .product-details-v2{ display:flex; flex-direction:column; justify-content:center; flex:1; }
@media (max-width: 575.98px){
  #products-grid .product-image-container-v2{ height:260px; }
  #products-grid.view-list .product-card-v2{ display:block; }
  #products-grid.view-list .product-image-container-v2{ width: 100%; aspect-ratio: 4/3; }
}

/* Responsive: sidebar on top on small screens */
@media (max-width: 991.98px){
  .shop-sidebar{ position: static; }
  .toolbar{ justify-content: start; margin-top: 12px; }
}

.small-note{font-size:.85rem; color:var(--text-muted-2);width:100%;}
</style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<div class="container page-wrap">
  <div class="row g-1 align-items-start">
    <!-- Sidebar -->
    <aside class="col-lg-3 pe-lg-1">
      <div class="shop-sidebar">
        <div class="sidebar-title">
          <i class="bi bi-sliders"></i>
          <span>CATEGORIES</span>
        </div>

        <div class="list-group cat-list" id="filter-categories">
          <a class="list-group-item active" href="#" data-category="">
            <span>All Products</span>
            <span class="cat-count" id="cat-count-all">(<?php echo (int)$totalProducts; ?>)</span>
          </a>
          <?php renderShopCategoryItems($categoryTree, 0, 0); ?>
        </div>

        <div class="price-box">
          <h6>PRICE RANGE</h6>
          <div class="range"><span id="price-min-label">0</span> – <span id="price-max-label">0</span></div>
          <small>Factory-direct pricing</small>
          <div class="mt-3">
            <input type="range" class="form-range" id="price-min" min="0" max="100" value="0">
            <input type="range" class="form-range" id="price-max" min="0" max="100" value="100">
          </div>
        </div>

        <div class="filter-box" id="wrap-sizes" style="display:none;">
          <h6>SIZE</h6>
          <div id="size-filters" class="chip-wrap"></div>
        </div>

        <div class="filter-box" id="wrap-colors" style="display:none;">
          <h6>COLOR</h6>
          <div id="color-filters" class="chip-wrap"></div>
        </div>

        <div class="sidebar-actions mt-4 d-grid gap-2">
          <a class="btn btn-outline-dark" href="<?php echo $domain; ?>requested-quote" data-quote-label>
            <i class="bi bi-file-earmark-text me-2"></i>Requested Quote (0)
          </a>
          <a class="btn btn-light border" href="<?php echo $domain; ?>contact-us">
            Custom Design <i class="bi bi-arrow-right ms-1"></i>
          </a>
          <button id="filters-clear" class="btn btn-light border" type="button">
            Clear Filters
          </button>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <main class="col-lg-9 ps-lg-1">
      <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-2 shop-header">
        <div>
          <h2 id="shop-title">All Products</h2>
          <div class="shop-sub" id="product-count-sub">Showing <?php echo (int)$totalProducts; ?> products</div>
        </div>

        <div class="toolbar">
          <select class="form-select" id="sort-select" aria-label="Sort products">
            <option value="best-selling" selected>Default</option>
            <option value="price-low-high">Price: Low to High</option>
            <option value="price-high-low">Price: High to Low</option>
            <option value="newest">Newest</option>
            <option value="rating">Rating</option>
          </select>
          <div class="view-toggle btn-group" role="group" aria-label="View toggle">
            <button type="button" class="btn active" id="viewGrid" title="Grid view">
              <i class="bi bi-grid-3x3-gap-fill"></i>
            </button>
            <button type="button" class="btn" id="viewList" title="List view">
              <i class="bi bi-list"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="divider-soft"></div>

      <!-- Products grid -->
      <div id="products-grid" class="row g-2"></div>
      <div class="text-center mt-4">
        <button id="load-more" class="btn btn-outline-dark" style="display:none;" type="button">
          <i class="bi bi-plus-circle me-1"></i> Load more
        </button>
      </div>
    </main>
  </div>
</div>

<!-- Recommended -->
<section class="py-5" style="background: var(--shop-gray);">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h2 class="mb-1" style="font-weight:800;">Recommended For You</h2>
        <div class="small-note">Based on your browsing</div>
      </div>
      <a class="btn btn-outline-dark" href="<?php echo $domain; ?>shop">View all</a>
    </div>

    <div class="row g-4">
      <?php if (!empty($recommendedProducts)): foreach ($recommendedProducts as $product):
        $oldPrice  = (float)$product['old_price'];
        $basePrice = (float)$product['current_price'];
        $minVar    = $product['min_price'] !== null ? (float)$product['min_price'] : null;
        $maxVar    = $product['max_price'] !== null ? (float)$product['max_price'] : null;
        $hasVars   = ((int)$product['variation_count']) > 0;

        $images = json_decode($product['images'], true);
        $firstRel = is_array($images) && count($images) ? (string)$images[0] : 'assets/images/placeholder.svg';
        $first = wdx_join_url($domain, $firstRel);

        $effective = $hasVars && $minVar !== null ? $minVar : $basePrice;
        $discount   = ($oldPrice > 0 && $oldPrice > $effective) ? round((($oldPrice - $effective)/$oldPrice) * 100) : 0;
        $badgeText  = $discount>0 ? ($hasVars ? 'Up to '.$discount.'% OFF' : $discount.'% OFF') : '';
        $catLabel = !empty($product['category_name']) ? strtoupper($product['category_name']) : '';
      ?>
      <div class="col-12 col-sm-6 col-xl-3 d-flex">
        <div class="product-card-v2">
          <div class="product-image-container-v2">
            <?php if ($badgeText): ?><span class="discount-badge-v2"><?php echo htmlspecialchars($badgeText); ?></span><?php endif; ?>
            <button type="button" class="wdx-quick-view-btn" data-slug="<?php echo htmlspecialchars($product['slug']); ?>">
              <i class="bi bi-eye"></i> Quick View
            </button>
            <a href="<?php echo $domain; ?>shop/<?php echo htmlspecialchars($product['slug']); ?>">
              <img class="product-image-v2" src="<?php echo htmlspecialchars($first); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" loading="lazy">
            </a>
          </div>
          <div class="product-details-v2">
            <?php if ($catLabel): ?><div class="product-cat-v2"><?php echo htmlspecialchars($catLabel); ?></div><?php endif; ?>
            <p class="product-title-v2"><a href="<?php echo $domain; ?>shop/<?php echo htmlspecialchars($product['slug']); ?>"><?php echo htmlspecialchars($product['product_name']); ?></a></p>
            <p class="mb-0">
              <?php if ($discount>0): ?><span class="product-old-price-v2">Rs.<?php echo number_format($oldPrice, 0); ?></span><?php endif; ?>
              <span class="product-price-v2">Rs.<?php echo number_format($effective, 0); ?></span>
            </p>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<!-- Features -->
<section class="py-5 bg-white border-top">
  <div class="container">
    <div class="row g-3">
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-truck fs-2 text-dark"></i>
        <div class="fw-bold mt-2">Free Shipping</div>
        <div class="small-note">On orders over PKR 3,000</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-arrow-counterclockwise fs-2 text-dark"></i>
        <div class="fw-bold mt-2">Easy Returns</div>
        <div class="small-note">15-day policy</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-shield-lock fs-2 text-dark"></i>
        <div class="fw-bold mt-2">Secure Payment</div>
        <div class="small-note">100% secure checkout</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-headset fs-2 text-dark"></i>
        <div class="fw-bold mt-2">24/7 Support</div>
        <div class="small-note">We’re here to help</div>
      </div>
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(function(){
  /* State */
  let offset=0, totalCount=0, currentCategory="";
  let currentView = 'grid';
  let priceBounds={min:0,max:0}, priceFilter={min:0,max:0};
  let selectedSizes=[], selectedColors=[];
  const perPage=24;

  /* Helpers */
  function stars(avg){
    const f='<i class="bi bi-star-fill text-warning"></i>',
          h='<i class="bi bi-star-half text-warning"></i>',
          e='<i class="bi bi-star text-warning"></i>';
    const r=Math.round((+avg||0)*2)/2, out=[];
    for(let i=1;i<=5;i++){ if(i<=r) out.push(f); else if(i-0.5===r) out.push(h); else out.push(e); }
    return out.join('');
  }
  function alertMini(msg, type='success'){
    const el = $(`<div class="alert alert-${type} position-fixed bottom-0 end-0 m-3 shadow" role="alert" style="z-index:2000">${msg}</div>`);
    $('body').append(el); setTimeout(()=>el.fadeOut(200,()=>el.remove()),2000);
  }
  function syncRangeLabels(){
    $('#price-min-label').text(`Rs. ${Number($('#price-min').val()).toLocaleString()}`);
    $('#price-max-label').text(`Rs. ${Number($('#price-max').val()).toLocaleString()}`);
  }

  /* Meta (sizes, colors, price bounds) */
  function loadFiltersMeta(){
    return $.ajax({
      url:'filters_meta.php', method:'POST', dataType:'json',
      data:{category: currentCategory, search:''}
    }).done(meta=>{
      priceBounds.min = Math.floor(parseFloat(meta.min_price || 0));
      priceBounds.max = Math.ceil(parseFloat(meta.max_price || 0));
      if (!isFinite(priceBounds.min) || !isFinite(priceBounds.max) || priceBounds.max < priceBounds.min) {
        priceBounds={min:0,max:100};
      }
      priceFilter.min = priceBounds.min; priceFilter.max = priceBounds.max;

      $('#price-min').attr({min:priceBounds.min, max:priceBounds.max}).val(priceFilter.min);
      $('#price-max').attr({min:priceBounds.min, max:priceBounds.max}).val(priceFilter.max);
      syncRangeLabels();

      const sizes = Array.isArray(meta.sizes) ? meta.sizes : [];
      const colors = Array.isArray(meta.colors) ? meta.colors : [];
      const $sz = $('#size-filters').empty(), $cl = $('#color-filters').empty();

      if (sizes.length){
        sizes.forEach(s => $sz.append(
          `<label class="chip"><input type="checkbox" class="size-chip" value="${s}"> ${s}</label>`
        ));
        $('#wrap-sizes').show();
      } else $('#wrap-sizes').hide();

      if (colors.length){
        colors.forEach(c => $cl.append(
          `<label class="chip"><input type="checkbox" class="color-chip" value="${c}"> ${c}</label>`
        ));
        $('#wrap-colors').show();
      } else $('#wrap-colors').hide();
    });
  }

  /* Fetch & render products */
  function loadProducts(reset=false){
    const sort = $('#sort-select').val();
    if (reset){ offset=0; $('#products-grid').empty(); }

    $.ajax({
      url:'fetch_products.php', method:'POST',
      data:{ search:'', sort, offset, limit:perPage, category:currentCategory,
             price_min:priceFilter.min, price_max:priceFilter.max,
             sizes:selectedSizes, colors:selectedColors }
    }).done(raw=>{
      let response;
      try{ response = typeof raw==='object' ? raw : JSON.parse(raw); }catch(e){ response={products:[], total_count:0}; }
      const products = response.products || []; totalCount = response.total_count || 0;

      if (products.length===0 && offset===0){
        $('#products-grid').html(`
          <div class="p-5 text-center bg-white border rounded-4">
            <h5 class="mb-1">No products found</h5>
            <div class="small-note">Try adjusting your filters</div>
          </div>`);
        $('#product-count-sub').text('Showing 0 products');
        $('#load-more').hide(); return;
      }

       $('#products-grid').toggleClass('view-list', currentView === 'list');

      products.forEach(p=>{
        const avg = +p.avg_rating || 0;
        const oldPrice = +p.old_price || 0;
        const basePrice= +p.current_price || 0;
        const hasVars = (parseInt(p.variation_count,10)||0) > 0;
        const minVar = p.min_price!==null ? +p.min_price : null;
        const maxVar = p.max_price!==null ? +p.max_price : null;
        const firstImage = p.first_image || '<?php echo $domain; ?>assets/images/default-product.jpg';
        const catName = (p.category_name || '').toString();

        let eff = basePrice, priceText = `PKR ${eff.toFixed(2)}`;
        if (hasVars && minVar!==null){
          eff = minVar;
          priceText = (maxVar!==null && maxVar!==minVar) ? `PKR ${minVar.toFixed(2)} – ${maxVar.toFixed(2)}` : `PKR ${minVar.toFixed(2)}`;
        }
        let disc = 0; if (oldPrice>0 && oldPrice>eff) disc = Math.round(((oldPrice-eff)/oldPrice)*100);

        const fmt = (n) => 'Rs.' + (Math.round(Number(n)||0)).toLocaleString();
        const badge = disc>0 ? `<span class="discount-badge-v2">${hasVars ? `Up to ${disc}% OFF` : `${disc}% OFF`}</span>` : '';
        const oldH  = disc>0 ? `<span class="product-old-price-v2">${fmt(oldPrice)}</span>` : '';
        const colClass = (currentView === 'list') ? 'col-12' : 'col-12 col-sm-6 col-lg-4';

        $('#products-grid').append(`
          <div class="${colClass} d-flex">
            <div class="product-card-v2">
              <div class="product-image-container-v2">
                ${badge}
                <a class="quick-view wdx-quick-view-btn text-decoration-none" href="#"
                  data-id="${p.id}"
                  data-name="${p.product_name}"
                  data-price="${eff}"
                  data-oldprice="${oldPrice}"
                  data-minprice="${minVar!==null ? minVar : ''}"
                  data-maxprice="${maxVar!==null ? maxVar : ''}"
                  data-hasvariations="${hasVars ? 1 : 0}"
                  data-image="${firstImage}"
                  data-slug="${p.slug}"
                  data-description="${p.description || 'Premium quality product'}"
                  data-slug="${p.slug}">
                  <i class="bi bi-eye"></i> Quick View
                </a>
                <a href="${(window.SITE_BASE || '').toString()}shop/${p.slug}">
                  <img class="product-image-v2" src="${firstImage}" alt="${$('<div>').text(p.product_name).html()}">
                </a>
              </div>
              <div class="product-details-v2">
                ${catName ? `<div class="product-cat-v2">${$('<div>').text(catName).html()}</div>` : ''}
                <p class="product-title-v2"><a href="${(window.SITE_BASE || '').toString()}shop/${p.slug}">${$('<div>').text(p.product_name).html()}</a></p>
                <p class="mb-0">${oldH}<span class="product-price-v2">${fmt(eff)}</span></p>
              </div>
            </div>
          </div>
        `);
      });

      offset += products.length;
      const showing = Math.min(offset,totalCount);
      $('#product-count-sub').text(`Showing ${showing} products`);
      if (offset >= totalCount) $('#load-more').hide(); else $('#load-more').show();
    }).fail(()=>{
      $('#products-grid').html('<div class="p-5 bg-white border rounded-4 text-center">Error loading products</div>');
      $('#load-more').hide();
    });
  }

  /* Events: category */
  $(document).on('click', '#filter-categories .list-group-item', function(e){
    e.preventDefault();
    $('#filter-categories .list-group-item').removeClass('active');
    $(this).addClass('active');
    currentCategory = $(this).data('category') || "";
    loadFiltersMeta().then(()=>loadProducts(true));
  });

  /* Price sliders */
  $('#price-min, #price-max').on('input', syncRangeLabels);
  $('#price-min, #price-max').on('change', function(){
    let min = +$('#price-min').val(), max = +$('#price-max').val();
    if (min > max){ if (this.id==='price-min') $('#price-max').val(min); else $('#price-min').val(max); }
    priceFilter.min = +$('#price-min').val(); priceFilter.max = +$('#price-max').val();
    syncRangeLabels(); loadProducts(true);
  });

  /* Size/Color chips */
  $(document).on('change', '.size-chip', function(){
    const v=$(this).val();
    if (this.checked){ if (!selectedSizes.includes(v)) selectedSizes.push(v); }
    else { selectedSizes = selectedSizes.filter(x=>x!==v); }
    loadProducts(true);
  });
  $(document).on('change', '.color-chip', function(){
    const v=$(this).val();
    if (this.checked){ if (!selectedColors.includes(v)) selectedColors.push(v); }
    else { selectedColors = selectedColors.filter(x=>x!==v); }
    loadProducts(true);
  });

  /* Clear */
  $('#filters-clear').on('click', function(){
    // category
    $('#filter-categories .list-group-item').removeClass('active');
    $('#filter-categories .list-group-item').first().addClass('active');
    currentCategory = "";
    // chips
    selectedSizes=[]; selectedColors=[];
    $('#size-filters input, #color-filters input').prop('checked', false);
    // price reset
    $('#price-min').val(priceBounds.min); $('#price-max').val(priceBounds.max);
    priceFilter.min = priceBounds.min; priceFilter.max = priceBounds.max; syncRangeLabels();
    loadProducts(true);
  });

  /* Sort */
  $('#sort-select').on('change', ()=>loadProducts(true));

  /* View toggle */
  $('#viewGrid').on('click', function(){
    currentView = 'grid';
    $('#viewGrid').addClass('active');
    $('#viewList').removeClass('active');
    loadProducts(true);
  });
  $('#viewList').on('click', function(){
    currentView = 'list';
    $('#viewList').addClass('active');
    $('#viewGrid').removeClass('active');
    loadProducts(true);
  });

  /* Load more */
  $('#load-more').on('click', ()=>loadProducts());

  /* Boot */
  loadFiltersMeta().then(()=>loadProducts(true));
});
</script>

<script src="<?php echo $domain; ?>assets/script/cart.js"></script>
</body>
</html>
