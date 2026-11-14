<?php
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

  // If already includes base path (e.g. /woodex/... or woodex/...), don't duplicate it.
  if ($bpTrim !== '') {
    if (strpos($path, '/' . $bpTrim . '/') === 0) {
      return rtrim(preg_replace('~' . preg_quote($basePath, '~') . '$~', '/', $base), '/') . $path;
    }
    if (strpos($path, $bpTrim . '/') === 0) {
      return rtrim(preg_replace('~' . preg_quote($basePath, '~') . '$~', '/', $base), '/') . '/' . $path;
    }
  }

  // If stored as /assets/... treat as site-relative within basePath
  if (strpos($path, '/assets/') === 0) {
    return $base . ltrim($path, '/');
  }
  if (strpos($path, 'assets/') === 0) {
    return $base . $path;
  }

  if (strpos($path, '/') === 0) {
    return $base . ltrim($path, '/');
  }
  return $base . $path;
}
}

$category = isset($_POST['category']) ? $_POST['category'] : '';
$search   = isset($_POST['search']) ? $_POST['search'] : '';
$sort     = isset($_POST['sort']) ? $_POST['sort'] : 'best-selling';
$offset   = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit    = isset($_POST['limit']) ? (int)$_POST['limit'] : 24;

$priceMin = isset($_POST['price_min']) ? (float)$_POST['price_min'] : 0;
$priceMax = isset($_POST['price_max']) ? (float)$_POST['price_max'] : 0;

$sizes  = isset($_POST['sizes'])  ? (array)$_POST['sizes']  : [];
$colors = isset($_POST['colors']) ? (array)$_POST['colors'] : [];

// Normalize color/size values for case-insensitive matching
$norm = function($arr){
    $out = [];
    foreach ($arr as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        $out[] = mb_strtolower($v, 'UTF-8');
    }
    return $out;
};
$sizesNorm  = $norm($sizes);
$colorsNorm = $norm($colors);

// WHERE
$whereClauses = [];
$bindTypes = '';
$bindValues = [];

// Category
/* --- CATEGORY (self + descendants via categories.path) --- */
$categoryIds = [];
if (!empty($category) && (int)$category > 0) {
    // 1) Get the selected category's path
    if ($st = $conn->prepare("SELECT path FROM categories WHERE id = ? LIMIT 1")) {
        $catId = (int)$category;
        $st->bind_param('i', $catId);
        $st->execute();
        $st->bind_result($catPath);
        $st->fetch();
        $st->close();

        // 2) Collect this category + all children (path starts with selected path + '/')
        if ($catPath !== null && $catPath !== '') {
            $categoryIds[] = $catId; // include self
            if ($st2 = $conn->prepare("SELECT id FROM categories WHERE path LIKE CONCAT(?, '/%')")) {
                $st2->bind_param('s', $catPath);
                $st2->execute();
                $res2 = $st2->get_result();
                while ($row = $res2->fetch_assoc()) {
                    $categoryIds[] = (int)$row['id'];
                }
                $st2->close();
            }
        } else {
            // fallback: if no path, just filter by the selected id
            $categoryIds[] = $catId;
        }
    }
}

if (!empty($categoryIds)) {
    $ph = implode(',', array_fill(0, count($categoryIds), '?'));
    $whereClauses[] = "EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($ph))";
    $bindTypes     .= str_repeat('i', count($categoryIds));
    foreach ($categoryIds as $cid) { $bindValues[] = $cid; }
}


// Search
if ($search !== '') {
    $whereClauses[] = "p.product_name LIKE ?";
    $bindTypes .= 's';
    $bindValues[] = '%'.$search.'%';
}

// Price overlap (variation-aware, prefer in-stock variations)
if ($priceMin > 0 || $priceMax > 0) {
    if ($priceMax > 0) {
        $whereClauses[] =
            "COALESCE((" .
            "  SELECT MIN(v2.price) FROM product_variations v2 " .
            "  WHERE v2.product_id = p.id AND (v2.stock IS NULL OR v2.stock > 0)" .
            "), (" .
            "  SELECT MIN(v2a.price) FROM product_variations v2a WHERE v2a.product_id = p.id" .
            "), p.current_price) <= ?";
        $bindTypes .= 'd';
        $bindValues[] = $priceMax;
    }
    if ($priceMin > 0) {
        $whereClauses[] =
            "COALESCE((" .
            "  SELECT MAX(v3.price) FROM product_variations v3 " .
            "  WHERE v3.product_id = p.id AND (v3.stock IS NULL OR v3.stock > 0)" .
            "), (" .
            "  SELECT MAX(v3a.price) FROM product_variations v3a WHERE v3a.product_id = p.id" .
            "), p.current_price) >= ?";
        $bindTypes .= 'd';
        $bindValues[] = $priceMin;
    }
}

// ---- Dynamic SIZE filter (from variation_data JSON) ----
// We’ll check JSON paths (label/value) across likely keys, case-insensitively.
if (!empty($sizesNorm)) {
    $placeholders = implode(',', array_fill(0, count($sizesNorm), '?'));
    // bind lowercased size values for both comparisons
    $bindTypes .= str_repeat('s', count($sizesNorm) * 6);
    // We’ll add values after building the clause.

    $sizeClause =
        "EXISTS (" .
        "  SELECT 1 FROM product_variations vs " .
        "  WHERE vs.product_id = p.id " .
        "    AND (vs.stock IS NULL OR vs.stock > 0) " .
        "    AND (" .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Size.label')))   IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Size.value')))   IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Sizes.label')))  IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Sizes.value')))  IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Waist.label')))  IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data, '$.Waist.value')))  IN ($placeholders) " .
        "    )" .
        ")";

    $whereClauses[] = $sizeClause;

    // Bind values for each IN list (6 times)
    for ($i = 0; $i < 6; $i++) {
        foreach ($sizesNorm as $v) { $bindValues[] = $v; }
    }
}

// ---- Dynamic COLOR filter (from variation_data JSON) ----
if (!empty($colorsNorm)) {
    $placeholders = implode(',', array_fill(0, count($colorsNorm), '?'));
    $bindTypes .= str_repeat('s', count($colorsNorm) * 6);

    $colorClause =
        "EXISTS (" .
        "  SELECT 1 FROM product_variations vc " .
        "  WHERE vc.product_id = p.id " .
        "    AND (vc.stock IS NULL OR vc.stock > 0) " .
        "    AND (" .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Colour.label'))) IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Colour.value'))) IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Color.label')))  IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Color.value')))  IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Colors.label'))) IN ($placeholders) OR " .
        "      LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data, '$.Colors.value'))) IN ($placeholders) " .
        "    )" .
        ")";

    $whereClauses[] = $colorClause;

    // Bind values for each IN list (6 times)
    for ($i = 0; $i < 6; $i++) {
        foreach ($colorsNorm as $v) { $bindValues[] = $v; }
    }
}

$whereSQL = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// Count total (with same WHERE)
$countQuery = "SELECT COUNT(*) AS total FROM products p $whereSQL";
$countStmt = $conn->prepare($countQuery);
if ($bindTypes) $countStmt->bind_param($bindTypes, ...$bindValues);
$countStmt->execute();
$countRes = $countStmt->get_result();
$totalCount = (int)($countRes->fetch_assoc()['total'] ?? 0);
$countStmt->close();

// Sorting
switch ($sort) {
  case 'price-low-high': $sortSQL = "ORDER BY sort_price ASC"; break;
  case 'price-high-low': $sortSQL = "ORDER BY sort_price DESC"; break;
  case 'newest':         $sortSQL = "ORDER BY p.id DESC"; break;
  case 'rating':         $sortSQL = "ORDER BY avg_rating DESC"; break;
  default:               $sortSQL = "ORDER BY p.sold_quantity DESC"; break; // best-selling
}

// Main query (ONLY_FULL_GROUP_BY safe)
// - min/max use in-stock variations when present, else any variation, else p.current_price
$productQuery = "
 SELECT
   p.id,
   p.product_name,
   p.old_price,
   p.sold_quantity,
   p.current_price,
   p.images,
   p.slug,
   COALESCE((
     SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ')
     FROM product_categories pc2
     JOIN categories c2 ON c2.id = pc2.category_id
     WHERE pc2.product_id = p.id
   ), c.category_name) AS category_name,
   CASE WHEN p.rating_count > 0 THEN p.rating_sum / p.rating_count ELSE 0 END AS avg_rating,
   p.rating_count,
   COUNT(v.id) AS variation_count,
  /* Display min/max (prefer in-stock variation prices) */
  COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MIN(v.price)) AS min_price,
  COALESCE(MAX(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MAX(v.price)) AS max_price,
  /* Sorting price: in-stock min -> any variation min -> product price */
  COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MIN(v.price), p.current_price) AS sort_price
 FROM products p
 LEFT JOIN categories c
   ON c.id = p.category
 LEFT JOIN product_variations v
   ON v.product_id = p.id
 $whereSQL
 GROUP BY
   p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug, c.category_name, p.rating_count, p.rating_sum
 $sortSQL
 LIMIT ?, ?";

$productBindTypes = $bindTypes . 'ii';
$productBindValues = $bindValues;
$productBindValues[] = $offset;
$productBindValues[] = $limit;

$stmt = $conn->prepare($productQuery);
$stmt->bind_param($productBindTypes, ...$productBindValues);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
  $images = json_decode($row['images'], true);
  $row['images'] = $images ?: ['assets/images/placeholder.svg'];

  $firstRel = (is_array($row['images']) && !empty($row['images'][0])) ? (string)$row['images'][0] : 'assets/images/placeholder.svg';
  $row['first_image'] = wdx_join_url($domain, $firstRel);
  $row['avg_rating'] = round((float)$row['avg_rating'], 1);
  $row['variation_count'] = (int)$row['variation_count'];
  $row['min_price'] = $row['min_price'] !== null ? (float)$row['min_price'] : null;
  $row['max_price'] = $row['max_price'] !== null ? (float)$row['max_price'] : null;
  $row['old_price'] = (float)$row['old_price'];
  $row['current_price'] = (float)$row['current_price'];
  $row['sold_quantity'] = (int)$row['sold_quantity'];
  $products[] = $row;
}
$stmt->close();

echo json_encode([
  'products'    => $products,
  'total_count' => $totalCount
]);
