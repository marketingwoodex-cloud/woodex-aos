<?php

require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

/* =======================
   INLINE AJAX ENDPOINTS
   ======================= */
function json_out($arr){ header('Content-Type: application/json'); echo json_encode($arr); exit; }

/** Build list of category IDs (self + descendants) */
function collect_category_ids($conn, $category_id, $cat_path){
  $ids = [$category_id];
  if ($cat_path !== '') {
    if ($st = $conn->prepare("SELECT id FROM categories WHERE path LIKE CONCAT(?, '/%') AND is_active = 1")) {
      $st->bind_param('s', $cat_path);
      $st->execute();
      $r = $st->get_result();
      while ($row = $r->fetch_assoc()) $ids[] = (int)$row['id'];
      $st->close();
    }
  }
  return $ids;
}

/** Variation-aware price bounds (prefer in-stock variations) */
function get_price_bounds($conn, $catIds){
  $in = implode(',', array_fill(0, count($catIds), '?'));
  $types = str_repeat('i', count($catIds));

  $min = 0.0; $max = 0.0;

  $sql1 = "
    SELECT 
      MIN(COALESCE(vagg.min_price, p.current_price)) AS min_price,
      MAX(COALESCE(vagg.max_price, p.current_price)) AS max_price
    FROM products p
    LEFT JOIN (
      SELECT product_id, MIN(price) AS min_price, MAX(price) AS max_price
      FROM product_variations
      WHERE (stock IS NULL OR stock > 0)
      GROUP BY product_id
    ) vagg ON vagg.product_id = p.id
    WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in))
  ";
  if ($st = $conn->prepare($sql1)) {
    $st->bind_param($types, ...$catIds);
    $st->execute();
    $res = $st->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
      $min = $row['min_price'] !== null ? (float)$row['min_price'] : 0.0;
      $max = $row['max_price'] !== null ? (float)$row['max_price'] : 0.0;
    }
    $st->close();
  }

  // Fallback to any variations if in-stock produced zeros
  if ($min == 0.0 && $max == 0.0) {
    $sql2 = "
      SELECT 
        MIN(COALESCE(vagg.min_price, p.current_price)) AS min_price,
        MAX(COALESCE(vagg.max_price, p.current_price)) AS max_price
      FROM products p
      LEFT JOIN (
        SELECT product_id, MIN(price) AS min_price, MAX(price) AS max_price
        FROM product_variations
        GROUP BY product_id
      ) vagg ON vagg.product_id = p.id
      WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in))
    ";
    if ($st = $conn->prepare($sql2)) {
      $st->bind_param($types, ...$catIds);
      $st->execute();
      $res = $st->get_result();
      if ($res && ($row = $res->fetch_assoc())) {
        $min = $row['min_price'] !== null ? (float)$row['min_price'] : 0.0;
        $max = $row['max_price'] !== null ? (float)$row['max_price'] : 0.0;
      }
      $st->close();
    }
  }

  return [$min, $max];
}

/** Collect Sizes/Colors from variation_data within the category scope */
function get_meta_facets($conn, $catIds){
  $in = implode(',', array_fill(0, count($catIds), '?'));
  $types = str_repeat('i', count($catIds));

  $COLOR_KEYS = ['colour','color','colors'];
  $SIZE_KEYS  = ['size','sizes','waist','length'];

  $colors = []; $sizes = [];

  if ($st = $conn->prepare("
    SELECT v.variation_data, v.stock
    FROM product_variations v
    JOIN products p ON p.id = v.product_id
    WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in)) AND (v.stock IS NULL OR v.stock > 0)
  ")) {
    $st->bind_param($types, ...$catIds);
    $st->execute();
    $res = $st->get_result();
    if ($res && $res->num_rows === 0) {
      // fallback to any variations
      $st->close();
      $st = $conn->prepare("
        SELECT v.variation_data, v.stock
        FROM product_variations v
        JOIN products p ON p.id = v.product_id
        WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in))
      ");
      $st->bind_param($types, ...$catIds);
      $st->execute();
      $res = $st->get_result();
    }

    while ($row = $res->fetch_assoc()) {
      $vd = $row['variation_data'];
      if (!$vd) continue;
      $decoded = json_decode($vd, true);
      if (!is_array($decoded)) continue;

      foreach ($decoded as $key => $val) {
        $k = mb_strtolower((string)$key, 'UTF-8');

        // colors
        if (in_array($k, $COLOR_KEYS, true)) {
          if (is_array($val)) {
            if (isset($val['label']) || isset($val['value'])) {
              $label = trim((string)($val['label'] ?? ''));
              $value = trim((string)($val['value'] ?? ''));
              $colors[] = $label !== '' ? mb_convert_case(mb_strtolower($label,'UTF-8'), MB_CASE_TITLE, 'UTF-8') : $value;
            } else {
              foreach ($val as $v) {
                if (is_array($v)) {
                  $label = trim((string)($v['label'] ?? ''));
                  $value = trim((string)($v['value'] ?? ''));
                  $colors[] = $label !== '' ? mb_convert_case(mb_strtolower($label,'UTF-8'), MB_CASE_TITLE, 'UTF-8') : $value;
                } else $colors[] = (string)$v;
              }
            }
          } else $colors[] = (string)$val;
        }

        // sizes
        if (in_array($k, $SIZE_KEYS, true)) {
          if (is_array($val)) {
            if (isset($val['label']) || isset($val['value'])) {
              $cand = trim((string)($val['label'] ?? $val['value'] ?? ''));
              $sizes[] = $cand;
            } else {
              foreach ($val as $v) {
                if (is_array($v)) {
                  $cand = trim((string)($v['label'] ?? $v['value'] ?? ''));
                  $sizes[] = $cand;
                } else $sizes[] = (string)$v;
              }
            }
          } else $sizes[] = (string)$val;
        }
      }
    }
    $st->close();
  }

  // normalize sizes
  $map = [
    'xxs'=>'XXS','xs'=>'XS','s'=>'S','m'=>'M','l'=>'L','xl'=>'XL','xxl'=>'XXL','xxxl'=>'XXXL',
    'extra small'=>'XS','small'=>'S','medium'=>'M','large'=>'L','extra large'=>'XL'
  ];
  $sizes = array_values(array_unique(array_map(function($s) use($map){
    $s = trim((string)$s); if ($s==='') return '';
    $low = mb_strtolower($s,'UTF-8');
    return $map[$low] ?? preg_replace('/\s+/', ' ', $s);
  }, $sizes)));
  $sizes = array_filter($sizes);
  $order = ['XXS'=>0,'XS'=>1,'S'=>2,'M'=>3,'L'=>4,'XL'=>5,'XXL'=>6,'XXXL'=>7];
  usort($sizes, function($a,$b) use($order){
    $ia = $order[$a] ?? 999; $ib = $order[$b] ?? 999;
    return $ia === $ib ? strnatcasecmp($a,$b) : ($ia <=> $ib);
  });

  // normalize colors
  $colors = array_values(array_unique(array_map(function($c){
    $c = trim((string)$c); if ($c==='') return '';
    return mb_convert_case(mb_strtolower($c,'UTF-8'), MB_CASE_TITLE, 'UTF-8');
  }, $colors)));
  $colors = array_filter($colors, fn($v)=>$v!=='');
  natcasesort($colors); $colors = array_values($colors);

  return [$sizes, $colors];
}

/** Products fetch for this category scope */
function fetch_category_products($conn, $catIds, $args){
  $offset = (int)($args['offset'] ?? 0);
  $limit  = max(1, min(48, (int)($args['limit'] ?? 24)));
  $sort   = $args['sort'] ?? 'newest';
  $priceMin = isset($args['price_min']) ? (float)$args['price_min'] : null;
  $priceMax = isset($args['price_max']) ? (float)$args['price_max'] : null;
  $sizes  = is_array($args['sizes'] ?? null) ? $args['sizes'] : [];
  $colors = is_array($args['colors'] ?? null) ? $args['colors'] : [];

  $in = implode(',', array_fill(0, count($catIds), '?'));
  $bindTypes = str_repeat('i', count($catIds));
  $bindVals  = $catIds;

  $where = " WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in)) ";

  // price band (variation-aware)
  if ($priceMin !== null && $priceMax !== null && $priceMax >= $priceMin) {
    $where .= " AND COALESCE(
      (SELECT MIN(v2.price) FROM product_variations v2 WHERE v2.product_id=p.id AND (v2.stock IS NULL OR v2.stock>0)),
      (SELECT MIN(v3.price) FROM product_variations v3 WHERE v3.product_id=p.id),
      p.current_price
    ) BETWEEN ? AND ? ";
    $bindTypes .= 'dd';
    $bindVals[] = $priceMin; $bindVals[] = $priceMax;
  }

  // sizes/colors via EXISTS on variation_data (case-insensitive)
  $addInList = function($vals) {
    return implode(',', array_fill(0, count($vals), '?'));
  };
  $norm = function($arr){
    $o=[]; foreach($arr as $v){ $v=trim((string)$v); if($v!=='') $o[] = mb_strtolower($v,'UTF-8'); }
    return $o;
  };
  $sizesN  = $norm($sizes);
  $colorsN = $norm($colors);

  if (!empty($sizesN)) {
    $ph = $addInList($sizesN);
    $where .= "
      AND EXISTS (
        SELECT 1 FROM product_variations vs
        WHERE vs.product_id=p.id AND (vs.stock IS NULL OR vs.stock>0)
          AND (
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Size.label')))  IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Size.value')))  IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Sizes.label'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Sizes.value'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Waist.label'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vs.variation_data,'$.Waist.value'))) IN ($ph)
          )
      )
    ";
    $bindTypes .= str_repeat('s', count($sizesN)*6);
    for($i=0;$i<6;$i++){ foreach($sizesN as $v){ $bindVals[]=$v; } }
  }

  if (!empty($colorsN)) {
    $ph = $addInList($colorsN);
    $where .= "
      AND EXISTS (
        SELECT 1 FROM product_variations vc
        WHERE vc.product_id=p.id AND (vc.stock IS NULL OR vc.stock>0)
          AND (
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Colour.label'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Colour.value'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Color.label')))  IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Color.value')))  IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Colors.label'))) IN ($ph) OR
            LOWER(JSON_UNQUOTE(JSON_EXTRACT(vc.variation_data,'$.Colors.value'))) IN ($ph)
          )
      )
    ";
    $bindTypes .= str_repeat('s', count($colorsN)*6);
    for($i=0;$i<6;$i++){ foreach($colorsN as $v){ $bindVals[]=$v; } }
  }

  // count total
  $sqlCount = "SELECT COUNT(*) AS c FROM products p $where";
  $total = 0;
  if ($st = $conn->prepare($sqlCount)) {
    $st->bind_param($bindTypes, ...$bindVals);
    $st->execute();
    $r = $st->get_result();
    if ($r && ($row = $r->fetch_assoc())) $total = (int)$row['c'];
    $st->close();
  }

  // sorting
  switch ($sort) {
    case 'price-low-high': $order = "ORDER BY sort_price ASC"; break;
    case 'price-high-low': $order = "ORDER BY sort_price DESC"; break;
    case 'rating':         $order = "ORDER BY avg_rating DESC"; break;
    case 'popular':        $order = "ORDER BY p.sold_quantity DESC"; break;
    case 'newest':
    default:               $order = "ORDER BY p.id DESC"; break;
  }

  // main products
  $bindTypes2 = $bindTypes . 'ii';
  $bindVals2  = $bindVals; $bindVals2[] = $limit; $bindVals2[] = $offset;

  $sql = "
    SELECT
      p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug,
      CASE WHEN p.rating_count>0 THEN p.rating_sum/p.rating_count ELSE 0 END AS avg_rating,
      p.rating_count,
      COUNT(v.id) AS variation_count,
      COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock>0) THEN v.price END), MIN(v.price)) AS min_price,
      COALESCE(MAX(CASE WHEN (v.stock IS NULL OR v.stock>0) THEN v.price END), MAX(v.price)) AS max_price,
      COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock>0) THEN v.price END), MIN(v.price), p.current_price) AS sort_price
    FROM products p
    LEFT JOIN product_variations v ON v.product_id = p.id
    $where
    GROUP BY p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug, p.rating_count, p.rating_sum
    $order
    LIMIT ? OFFSET ?
  ";
  $out = [];
  if ($st = $conn->prepare($sql)) {
    $st->bind_param($bindTypes2, ...$bindVals2);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
      $imgs = json_decode($row['images'] ?? '[]', true);
      $row['first_image'] = abs_url(is_array($imgs)&&!empty($imgs)?$imgs[0]:'assets/images/placeholder.png');
      $row['avg_rating']  = round((float)$row['avg_rating'], 1);
      $row['variation_count'] = (int)$row['variation_count'];
      $row['min_price']   = $row['min_price'] !== null ? (float)$row['min_price'] : null;
      $row['max_price']   = $row['max_price'] !== null ? (float)$row['max_price'] : null;
      $row['old_price']   = (float)$row['old_price'];
      $row['current_price'] = (float)$row['current_price'];
      $row['sold_quantity'] = (int)$row['sold_quantity'];
      $out[] = $row;
    }
    $st->close();
  }

  return [$out, $total];
}

/* ===== AJAX router ===== */
if (isset($_POST['ajax'])) {
  // We need $slug -> $cat to compute scope. Load quickly:
  if (!isset($_GET['cat_url']) || $_GET['cat_url'] === '') { json_out(['error'=>'no category']); }
  $slugAjax = trim($_GET['cat_url']);
  $catRow = null;
  if ($st = $conn->prepare("SELECT id, path FROM categories WHERE slug=? AND is_active=1 LIMIT 1")) {
    $st->bind_param('s', $slugAjax);
    $st->execute();
    $r = $st->get_result();
    $catRow = $r ? $r->fetch_assoc() : null;
    $st->close();
  }
  if (!$catRow) json_out(['error'=>'category not found']);

  $catIds = collect_category_ids($conn, (int)$catRow['id'], (string)$catRow['path']);

  if ($_POST['ajax'] === 'meta') {
    [$min,$max] = get_price_bounds($conn, $catIds);
    [$sizes,$colors] = get_meta_facets($conn, $catIds);
    json_out(['min_price'=>$min, 'max_price'=>$max, 'sizes'=>$sizes, 'colors'=>$colors]);
  }

  if ($_POST['ajax'] === 'products') {
    [$rows, $total] = fetch_category_products($conn, $catIds, $_POST);
    json_out(['products'=>$rows, 'total_count'=>$total]);
  }

  json_out(['error'=>'unknown action']);
}


/* ---------------- Helpers ---------------- */
function abs_url($path) {
  global $domain;
  if (!$path) return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  $base = !empty($domain) ? rtrim($domain,'/') . '/' : ( ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' );
  return $base . ltrim($path, '/');
}
function render_stars($avg) {
  $full = floor($avg);
  $half = ($avg - $full) >= 0.5;
  $out = '';
  for ($i=1;$i<=5;$i++){
    if ($i <= $full) $out .= '<i class="fas fa-star"></i>';
    elseif ($i == $full + 1 && $half) $out .= '<i class="fas fa-star-half-alt"></i>';
    else $out .= '<i class="far fa-star"></i>';
  }
  return $out . ' <span>(' . number_format($avg,1) . ')</span>';
}

/* ---------------- Input: slug ---------------- */
if (!isset($_GET['cat_url']) || $_GET['cat_url'] === '') {
  http_response_code(404);
  echo "No Category URL specified.";
  exit;
}
$slug = trim($_GET['cat_url']);

$hasHeroLayoutColumn = false;
$heroColRes = $conn->query("SHOW COLUMNS FROM categories LIKE 'hero_layout'");
if ($heroColRes && $heroColRes->num_rows > 0) {
  $hasHeroLayoutColumn = true;
}

$hasSeriesHeroFields = false;
$seriesColRes = $conn->query("SHOW COLUMNS FROM categories LIKE 'hero_series_tag'");
if ($seriesColRes && $seriesColRes->num_rows > 0) {
  $hasSeriesHeroFields = true;
}

/* ---------------- Load current category ---------------- */
$cat = null;
$selectHeroLayout = $hasHeroLayoutColumn ? ", hero_layout" : ", 'default' AS hero_layout";
$selectSeriesFields = $hasSeriesHeroFields
  ? ", hero_series_tag, hero_series_subtitle, hero_series_description, hero_series_bullets, hero_primary_btn_label, hero_primary_btn_url, hero_secondary_btn_label, hero_secondary_btn_url"
  : ", '' AS hero_series_tag, '' AS hero_series_subtitle, '' AS hero_series_description, '' AS hero_series_bullets, '' AS hero_primary_btn_label, '' AS hero_primary_btn_url, '' AS hero_secondary_btn_label, '' AS hero_secondary_btn_url";
if ($st = $conn->prepare("SELECT id, parent_id, level, path, category_name, short_description, long_description, background_image, main_image{$selectHeroLayout}{$selectSeriesFields} FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1")) {
  $st->bind_param('s', $slug);
  $st->execute();
  $catRes = $st->get_result();
  $cat = $catRes ? $catRes->fetch_assoc() : null;
  $st->close();
}
if (!$cat) {
  http_response_code(404);
  echo "No Category found for the given URL.";
  exit;
}
$category_id   = (int)$cat['id'];
$category_name = $cat['category_name'];
$short         = $cat['short_description'] ?? '';
$long_desc     = $cat['long_description'] ?? '';
$cat_path      = $cat['path'] ?? '';
$cat_level     = (int)$cat['level'];
$cat_parent    = $cat['parent_id'] !== null ? (int)$cat['parent_id'] : null;
$heroLayout    = (($cat['hero_layout'] ?? 'default') === 'series') ? 'series' : 'default';

$seriesTag = trim((string)($cat['hero_series_tag'] ?? ''));
if ($seriesTag === '') {
  $seriesTag = $category_name;
}
$seriesSubtitle = trim((string)($cat['hero_series_subtitle'] ?? ''));
if ($seriesSubtitle === '') {
  $shortPlain = trim(strip_tags((string)$short));
  $seriesSubtitle = $shortPlain !== '' ? $shortPlain : 'Solutions tailored for modern workspaces';
}
$seriesDesc = trim((string)($cat['hero_series_description'] ?? ''));
if ($seriesDesc === '') {
  $seriesDesc = trim(strip_tags((string)$long_desc));
}
if ($seriesDesc === '') {
  $seriesDesc = 'Discover thoughtfully designed options built for productivity, comfort, and long-lasting performance.';
}

$seriesBullets = [];
$decodedBullets = json_decode((string)($cat['hero_series_bullets'] ?? ''), true);
if (is_array($decodedBullets)) {
  foreach ($decodedBullets as $bullet) {
    $bullet = trim((string)$bullet);
    if ($bullet !== '') {
      $seriesBullets[] = $bullet;
    }
  }
}
if (empty($seriesBullets)) {
  $seriesBullets = [
    'Acoustic privacy panels',
    'Height-adjustable options',
    'Personal storage integration',
    '5-year warranty'
  ];
}

$seriesPrimaryLabel = trim((string)($cat['hero_primary_btn_label'] ?? ''));
if ($seriesPrimaryLabel === '') {
  $seriesPrimaryLabel = 'View Full Series';
}
$seriesPrimaryUrl = trim((string)($cat['hero_primary_btn_url'] ?? ''));
if ($seriesPrimaryUrl === '') {
  $seriesPrimaryUrl = '#cat-products-grid';
}

$seriesSecondaryLabel = trim((string)($cat['hero_secondary_btn_label'] ?? ''));
if ($seriesSecondaryLabel === '') {
  $seriesSecondaryLabel = 'Request Quote';
}
$seriesSecondaryUrl = trim((string)($cat['hero_secondary_btn_url'] ?? ''));
if ($seriesSecondaryUrl === '') {
  $seriesSecondaryUrl = 'e-quotation';
}

/* ---------------- Category Navigator data ---------------- */
// children of current
$children = [];
if ($st = $conn->prepare("SELECT id, category_name, slug FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order ASC, category_name ASC")) {
  $st->bind_param('i', $category_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $children[] = $row;
  $st->close();
}
// siblings (if has parent)
$siblings = [];
$parent_name = '';
if ($cat_parent) {
  if ($st = $conn->prepare("SELECT id, category_name FROM categories WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $cat_parent);
    $st->execute();
    $r = $st->get_result();
    if ($r && ($pr = $r->fetch_assoc())) $parent_name = $pr['category_name'];
    $st->close();
  }
  if ($st = $conn->prepare("SELECT id, category_name, slug FROM categories WHERE parent_id = ? AND id <> ? AND is_active = 1 ORDER BY sort_order ASC, category_name ASC")) {
    $st->bind_param('ii', $cat_parent, $category_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $siblings[] = $row;
    $st->close();
  }
}
// top-level (fallback)
$topLevel = [];
if ($st = $conn->prepare("SELECT id, category_name, slug FROM categories WHERE level = 1 AND is_active = 1 ORDER BY sort_order ASC, category_name ASC")) {
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $topLevel[] = $row;
  $st->close();
}

/* ---------------- Collect self + descendants for product filtering ---------------- */
$categoryIds = [$category_id];
if ($cat_path !== '') {
  if ($st = $conn->prepare("SELECT id FROM categories WHERE path LIKE CONCAT(?, '/%') AND is_active = 1")) {
    $st->bind_param('s', $cat_path);
    $st->execute();
    $r = $st->get_result();
    while ($row = $r->fetch_assoc()) $categoryIds[] = (int)$row['id'];
    $st->close();
  }
}

/* ---------------- Filters: price + sort + pagination ---------------- */
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 24;
$offset = ($page - 1) * $limit;

$priceMin = null; $priceMax = null;
if (!empty($_GET['price']) && strpos($_GET['price'], '-') !== false) {
  [$pmin, $pmax] = explode('-', $_GET['price'], 2);
  $pmin = (float)$pmin; $pmax = (float)$pmax;
  if ($pmin >= 0 && $pmax > 0 && $pmax >= $pmin) { $priceMin = $pmin; $priceMax = $pmax; }
}

$sort = $_GET['sort'] ?? '';
switch ($sort) {
  case 'price_asc': $orderSQL = "ORDER BY sort_price ASC, p.id DESC"; break;
  case 'price_desc': $orderSQL = "ORDER BY sort_price DESC, p.id DESC"; break;
  case 'popular': $orderSQL = "ORDER BY p.sold_quantity DESC, p.id DESC"; break;
  case 'rating': $orderSQL = "ORDER BY avg_rating DESC, p.id DESC"; break;
  case 'newest': default: $orderSQL = "ORDER BY p.id DESC"; break;
}

/* ---------------- Count total products (self+descendants, variation-aware price filter) ---------------- */
$inPh = implode(',', array_fill(0, count($categoryIds), '?'));
$bindCountTypes = str_repeat('i', count($categoryIds));
$bindCountVals  = $categoryIds;

// price filter via correlated subquery
$priceWhere = '';
if ($priceMin !== null && $priceMax !== null) {
  $priceWhere = " AND COALESCE(
        (SELECT MIN(v2.price) FROM product_variations v2 WHERE v2.product_id = p.id AND (v2.stock IS NULL OR v2.stock > 0)),
        (SELECT MIN(v3.price) FROM product_variations v3 WHERE v3.product_id = p.id),
        p.current_price
      ) BETWEEN ? AND ?";
  $bindCountTypes .= 'dd';
  $bindCountVals[] = $priceMin;
  $bindCountVals[] = $priceMax;
}

$countSQL = "SELECT COUNT(*) AS c FROM products p WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($inPh)) $priceWhere";
$totProducts = 0;
if ($st = $conn->prepare($countSQL)) {
  $st->bind_param($bindCountTypes, ...$bindCountVals);
  $st->execute();
  $r = $st->get_result();
  if ($r && ($row = $r->fetch_assoc())) $totProducts = (int)$row['c'];
  $st->close();
}

/* ---------------- Products query (variation-aware) ---------------- */
$bindTypes = str_repeat('i', count($categoryIds));
$bindVals  = $categoryIds;

$priceWhere2 = '';
if ($priceMin !== null && $priceMax !== null) {
  $priceWhere2 = " AND COALESCE(
        (SELECT MIN(v2.price) FROM product_variations v2 WHERE v2.product_id = p.id AND (v2.stock IS NULL OR v2.stock > 0)),
        (SELECT MIN(v3.price) FROM product_variations v3 WHERE v3.product_id = p.id),
        p.current_price
      ) BETWEEN ? AND ?";
  $bindTypes .= 'dd';
  $bindVals[] = $priceMin;
  $bindVals[] = $priceMax;
}

$bindTypes .= 'ii';
$bindVals[]  = $limit;
$bindVals[]  = $offset;

$sql = "
SELECT
  p.id,
  p.product_name,
  p.old_price,
  p.sold_quantity,
  p.current_price,
  p.images,
  p.slug,
  CASE WHEN p.rating_count > 0 THEN p.rating_sum / p.rating_count ELSE 0 END AS avg_rating,
  p.rating_count,
  COUNT(v.id) AS variation_count,
  COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MIN(v.price)) AS min_price,
  COALESCE(MAX(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MAX(v.price)) AS max_price,
  COALESCE(MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END), MIN(v.price), p.current_price) AS sort_price
FROM products p
LEFT JOIN product_variations v ON v.product_id = p.id
WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($inPh)) $priceWhere2
GROUP BY p.id, p.product_name, p.old_price, p.sold_quantity, p.current_price, p.images, p.slug, p.rating_count, p.rating_sum
$orderSQL
LIMIT ? OFFSET ?";

$products = [];
if ($st = $conn->prepare($sql)) {
  $st->bind_param($bindTypes, ...$bindVals);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) {
    $images = json_decode($row['images'] ?? '[]', true);
    $first  = is_array($images) && !empty($images) ? abs_url($images[0]) : abs_url('assets/images/placeholder.png');
    $row['first_image']     = $first;
    $row['avg_rating']      = round((float)$row['avg_rating'], 1);
    $row['variation_count'] = (int)$row['variation_count'];
    $row['min_price']       = $row['min_price'] !== null ? (float)$row['min_price'] : null;
    $row['max_price']       = $row['max_price'] !== null ? (float)$row['max_price'] : null;
    $row['old_price']       = (float)$row['old_price'];
    $row['current_price']   = (float)$row['current_price'];
    $row['sold_quantity']   = (int)$row['sold_quantity'];
    $products[] = $row;
  }
  $st->close();
}

/* ---------------- FAQs ---------------- */
$faqs = [];
if ($st = $conn->prepare("SELECT question, answer FROM faqs WHERE faq_for = 'category' AND item_id = ? ORDER BY id ASC")) {
  $st->bind_param('i', $category_id);
  $st->execute();
  $faqs = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();
}

/* ---------------- Price Ranges (UI presets) ---------------- */
$priceRanges = [
  ['min' => 0, 'max' => 5000,   'label' => 'Under PKR 5,000'],
  ['min' => 5000, 'max' => 10000,'label' => 'PKR 5,000 – 10,000'],
  ['min' => 10000,'max' => 20000,'label' => 'PKR 10,000 – 20,000'],
  ['min' => 20000,'max' => 999999,'label' => 'Over PKR 20,000'],
];

/* ---------------- Pagination ---------------- */
$totalPages  = max(1, (int)ceil($totProducts / $limit));
$currentPage = $page;
function page_url_cat($pageNum) {
  $url = strtok($_SERVER['REQUEST_URI'], '?');
  $qs  = $_GET;
  $qs['page'] = $pageNum;
  return htmlspecialchars($url . '?' . http_build_query($qs));
}

$currentUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico" />
  <title><?php echo htmlspecialchars($category_name); ?> | <?php echo htmlspecialchars($siteName ?? ''); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($short ?: $long_desc),0,160)); ?>">
  <link rel="canonical" href="<?php echo htmlspecialchars($currentUrl); ?>">

  <!-- Fonts & Icons (match Shop page) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">

  <style>
    :root{--muted:#6c757d;--card-border:#edf0f2;--soft:#f4f5f7;--ink:#111;}
    body{font-family: var(--body, system-ui) !important;}

    /* Hero (matches reference layout) */
    .category-hero{
      padding: 44px 0 22px;
      background: #fff;
      border-bottom: 1px solid #eef1f4;
      margin-bottom: 10px;
    }
    .hero-grid{display:grid;grid-template-columns: 1.2fr .8fr;gap: 22px;align-items:center;}
    .hero-kicker{color: var(--accent-color);font-weight: 800;letter-spacing: -.01em;font-size: 1.05rem;margin:0 0 6px;}
    .hero-title{font-weight: 900;letter-spacing: -.03em;font-size: clamp(1.7rem, 3vw, 2.45rem);margin:0;line-height:1.1;color:var(--ink);}
    .hero-title .accent{color: var(--accent-color);}
    .hero-desc{color:#5b6470;max-width: 70ch;margin-top: 12px;}
    .hero-meta{margin-top: 14px;color: var(--muted);font-weight: 700;font-size: .95rem;}
    .hero-art{display:flex;justify-content:flex-end;}
    .hero-art .art-frame{background:#fff;border:1px solid var(--card-border);border-radius:14px;padding:12px;box-shadow:0 10px 24px rgba(0,0,0,.05);}
    .hero-art img{width: min(420px, 100%);height:auto;display:block;object-fit:contain;}
    @media (max-width: 991.98px){
      .hero-grid{grid-template-columns: 1fr;}
      .hero-art{justify-content:flex-start;}
    }

    /* Series Hero option */
    .series-hero-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:34px;align-items:center;}
    .series-hero-media{position:relative;border-radius:16px;overflow:hidden;border:1px solid var(--card-border);background:#f4f5f7;min-height:260px;}
    .series-hero-media img{width:100%;height:100%;object-fit:cover;display:block;}
    .series-pill{position:absolute;top:14px;left:14px;background:var(--accent-color);color:#fff;font-size:.78rem;font-weight:800;line-height:1;padding:7px 10px;border-radius:999px;}
    .series-count{position:absolute;left:12px;bottom:12px;background:rgba(17, 17, 17, 0.56);color:#fff;font-size:.78rem;font-weight:700;padding:6px 8px;border-radius:6px;}
    .series-copy{max-width:620px;padding-right:10px;}
    .series-copy .series-kicker{display:block;width:40px;height:4px;background:var(--accent-color);border-radius:999px;margin-bottom:16px;}
    .series-title{font-size:clamp(1.65rem, 2.8vw, 2.35rem);font-weight:900;line-height:1.08;margin:0 0 8px;color:var(--ink);}
    .series-subtitle{color:var(--accent-color);font-weight:700;margin-bottom:10px;font-size:1.02rem;}
    .series-desc{color:#5b6470;margin-bottom:14px;line-height:1.55;}
    .series-bullets{list-style:none;padding:0;margin:0 0 16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:26px;row-gap:10px;}
    .series-bullets li{position:relative;padding-left:16px;color:#4f5c68;}
    .series-bullets li::before{content:'';position:absolute;left:0;top:.58em;width:6px;height:6px;border-radius:50%;background:var(--accent-color);}
    .series-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}
    .series-actions .btn{font-weight:700;padding:.52rem .95rem;border-radius:2px;}
    .series-actions .btn-primary{background:var(--accent-color);border-color:var(--accent-color);}
    .series-actions .btn-primary:hover{filter:brightness(.95);}
    .series-actions .btn-outline-dark{border-color:#8fca54;color:#44702a;}
    @media (max-width: 991.98px){
      .series-hero-grid{grid-template-columns:1fr;gap:20px;}
      .series-copy{max-width:none;}
      .series-bullets{grid-template-columns:1fr;}
    }

    /* Category tabs row */
    .cat-tabs-row{border-bottom:1px solid #eef1f4;}
    .cat-tabs-carousel{display:flex;align-items:center;gap:8px;}
    .cat-tabs-nav{
      width:34px;height:34px;border:1px solid #dbe2ea;background:#fff;color:#1f2937;
      display:inline-flex;align-items:center;justify-content:center;border-radius:2px;
      font-weight:700;line-height:1;cursor:pointer;
    }
    .cat-tabs-nav:disabled{opacity:.35;cursor:not-allowed;}
    .cat-tabs{
      display:flex;
      gap:10px;
      overflow-x:auto;
      overflow-y:hidden;
      white-space:nowrap;
      flex-wrap:nowrap;
      padding: 12px 0;
      scrollbar-width:none;
      -ms-overflow-style:none;
      scroll-behavior:smooth;
      flex:1;
    }
    .cat-tabs::-webkit-scrollbar{display:none;}
    .cat-tabs a{
      color:#374151;
      text-decoration:none;
      font-weight:700;
      font-size:.86rem;
      padding:6px 10px;
      border:1px solid transparent;
      border-radius:0;
      background:transparent;
    }
    .cat-tabs a:hover{color: var(--ink);border-color:#b8c4d1;background:#fff;}
    .cat-tabs a.active{
      color:#fff;
      background:var(--accent-color);
      border-color:var(--accent-color);
    }
    .tab-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;padding: 12px 0;}
    .tab-actions a{font-weight:800;font-size:.85rem;color:var(--ink);text-decoration:none;}
    .tab-actions a:hover{color: var(--accent-color);}

    .subtle{color:var(--muted)}

    /* Layout */
    .cat-layout{margin-top:12px}
    .sidebar{position:sticky;top:90px}
    .filter-card{background:#fff;border:1px solid #eee;border-radius:12px;padding:18px}
    .filter-card h5{font-size:1rem;margin-bottom:12px}
    .filter-option{display:flex;align-items:center;gap:10px;margin-bottom:8px}
    .active-chips{display:flex;flex-wrap:wrap;gap:8px}
    .chip{display:inline-flex;align-items:center;gap:6px;background:#f4f6f8;border:1px solid #e6e6e6;border-radius:999px;padding:4px 10px;font-size:.875rem}
    .chip .x{cursor:pointer;color:#999}

    /* Product grid */
    .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap: 22px;}
    .grid > *{display:flex;}
    .grid .product-card-v2{
      width:100%;
      display:flex;
      flex-direction:column;
      border-radius:10px;
      border:1px solid #e8ecf1;
      box-shadow:0 4px 14px rgba(15,23,42,.06);
      padding:12px;
    }
    .grid .product-image-container-v2{
      width:100%;
      height:280px;
      margin:0;
      padding:0;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:8px;
      overflow:hidden;
      background:#fff;
    }
    .grid .product-image-v2{
      width:auto;
      height:auto;
      max-width:100%;
      max-height:100%;
      object-fit:contain;
      display:block;
    }
    .grid .product-details-v2{
      padding:12px 10px 8px;
      text-align:left;
      margin-top:auto;
    }
    @media (max-width: 1199.98px){.grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
    @media (max-width: 991.98px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width: 575.98px){
      .grid{grid-template-columns:repeat(1,minmax(0,1fr));}
      .grid .product-image-container-v2{height:240px;}
    }

    /* Sort row */
    .sort-row{display:flex;justify-content:space-between;align-items:center;margin:10px 0 18px}
    .count{color:#6c757d}

    /* FAQ / description */
    .long-desc{background:#f8f9fb;border-top:1px solid #eee;margin-top:42px;padding:40px 0}
    .filter-bar select {
  border-radius: 8px;
  font-size: 0.95rem;
}
.filter-bar .btn {
  font-size: 0.9rem;
}
@media (max-width: 768px) {
  .filter-bar {
    padding: 10px 12px;
  }
  .sort-tools-wrap{
    width: 100%;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .sort-tools-wrap .small{
    width: 100%;
    text-align: right;
  }
}

  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<!-- Category Hero -->
<section class="category-hero">
  <div class="container">
    <?php if ($heroLayout === 'series'): ?>
      <?php
        $heroMainImg = !empty($cat['main_image']) ? abs_url($cat['main_image']) : abs_url('assets/images/placeholder.png');
        $heroBgImg = !empty($cat['background_image']) ? abs_url($cat['background_image']) : $heroMainImg;
        $seriesDescPreview = trim(strip_tags((string)$seriesDesc));
        if (mb_strlen($seriesDescPreview, 'UTF-8') > 240) {
          $seriesDescPreview = mb_substr($seriesDescPreview, 0, 240, 'UTF-8') . '...';
        }

        $primaryHref = $seriesPrimaryUrl;
        if ($primaryHref !== '' && strpos($primaryHref, '#') !== 0 && !preg_match('~^https?://~i', $primaryHref)) {
          $primaryHref = abs_url($primaryHref);
        }

        $secondaryHref = $seriesSecondaryUrl;
        if ($secondaryHref !== '' && strpos($secondaryHref, '#') !== 0 && !preg_match('~^https?://~i', $secondaryHref)) {
          $secondaryHref = abs_url($secondaryHref);
        }
      ?>
      <div class="series-hero-grid">
        <div class="series-hero-media">
          <img src="<?php echo htmlspecialchars($heroBgImg); ?>" alt="<?php echo htmlspecialchars($category_name); ?>" loading="lazy">
          <span class="series-pill"><?php echo htmlspecialchars($seriesTag); ?></span>
          <span class="series-count"><?php echo number_format($totProducts); ?> product<?php echo $totProducts==1?'':'s'; ?> in this series</span>
        </div>

        <div class="series-copy">
          <span class="series-kicker" aria-hidden="true"></span>
          <h1 class="series-title"><?php echo htmlspecialchars($category_name); ?></h1>
          <p class="series-subtitle"><?php echo htmlspecialchars($seriesSubtitle); ?></p>
          <p class="series-desc"><?php echo htmlspecialchars($seriesDescPreview); ?></p>
          <ul class="series-bullets">
            <?php foreach ($seriesBullets as $bullet): ?>
              <li><?php echo htmlspecialchars($bullet); ?></li>
            <?php endforeach; ?>
          </ul>
          <div class="series-actions">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars($primaryHref); ?>"><?php echo htmlspecialchars($seriesPrimaryLabel); ?></a>
            <a class="btn btn-outline-dark" href="<?php echo htmlspecialchars($secondaryHref); ?>"><?php echo htmlspecialchars($seriesSecondaryLabel); ?></a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="hero-grid">
        <div>
          <p class="hero-kicker">Transform Your Workspace with <?php echo htmlspecialchars($siteName ?? 'Woodex'); ?></p>
          <h1 class="hero-title"><span class="accent"><?php echo htmlspecialchars($category_name); ?></span></h1>
          <div class="hero-desc">
            <?php if ($short): ?>
              <?php echo $short; ?>
            <?php else: ?>
              <p class="mb-0">Explore our collection curated for quality, comfort, and lasting performance.</p>
            <?php endif; ?>
          </div>
          <div class="hero-meta"><?php echo number_format($totProducts); ?> product<?php echo $totProducts==1?'':'s'; ?></div>
        </div>

        <div class="hero-art">
          <div class="art-frame">
            <?php
              $heroImg = !empty($cat['main_image']) ? abs_url($cat['main_image']) : abs_url('assets/images/placeholder.png');
            ?>
            <img src="<?php echo htmlspecialchars($heroImg); ?>" alt="<?php echo htmlspecialchars($category_name); ?>" loading="lazy">
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Category Navigator -->
<section class="cat-tabs-row">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-10">
        <div class="cat-tabs-carousel" aria-label="Category navigation carousel">
          <button type="button" class="cat-tabs-nav" id="catTabsPrev" aria-label="Previous categories">‹</button>
          <div class="cat-tabs" id="catTabsTrack" aria-label="Category navigation">
          <?php
            // Reference-style tabs: prefer siblings (including current) when in a sub-category; otherwise show children.
            $navItems = [];
            if ($cat_parent) {
              if ($st = $conn->prepare("SELECT category_name, slug FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order ASC, category_name ASC")) {
                $st->bind_param('i', $cat_parent);
                $st->execute();
                $res = $st->get_result();
                while ($row = $res->fetch_assoc()) $navItems[] = $row;
                $st->close();
              }
            } elseif (!empty($children)) {
              $navItems = $children;
            } else {
              $navItems = $topLevel;
            }
          ?>
          <?php foreach ($navItems as $n):
            $isActive = (isset($n['slug']) && $n['slug'] === $slug);
          ?>
            <a class="<?php echo $isActive ? 'active' : ''; ?>" href="<?php echo abs_url('categories/' . $n['slug']); ?>"><?php echo htmlspecialchars($n['category_name']); ?></a>
          <?php endforeach; ?>
          </div>
          <button type="button" class="cat-tabs-nav" id="catTabsNext" aria-label="Next categories">›</button>
        </div>
      </div>
      <div class="col-lg-2">
        <div class="tab-actions">
          <a href="<?php echo $domain; ?>shop">See All Products</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Main -->
<!-- Main -->
<section class="cat-layout">
  <div class="container">
    <div class="row g-4">

      <!-- Products -->
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="count subtle" id="count-read">Loading…</div>
          <div class="d-flex align-items-center gap-2 sort-tools-wrap">
            <span class="small text-muted">Sort by</span>
            <select id="sort-select" class="form-select form-select-sm cat-sort-select">
              <option value="popular">Best selling</option>
              <option value="price-low-high">Price: Low to High</option>
              <option value="price-high-low">Price: High to Low</option>
              <option value="newest">Newest</option>
              <option value="rating">Rating</option>
            </select>
          </div>
        </div>

        <div id="cat-products-grid" class="grid"></div>
        <div class="text-center mt-4">
          <button id="btn-more" class="btn btn-primary" style="display:none;">
            <i class="bi bi-plus-circle me-1"></i> Load more
          </button>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- Long Description -->
<?php if (!empty($long_desc)): ?>
<section class="long-desc">
  <div class="container">
    <div class="row"><div class="col-lg-10 mx-auto">
      <h2 class="mb-3 text-center">About <?php echo htmlspecialchars($category_name); ?></h2>
      <div><?php echo $long_desc; ?></div>
    </div></div>
  </div>
</section>
<?php endif; ?>

<!-- FAQs -->
<?php if (!empty($faqs)): ?>
<section class="py-5">
  <div class="container">
    <div class="row"><div class="col-lg-10 mx-auto">
      <h2 class="mb-4 text-center">Frequently Asked Questions</h2>
      <div class="accordion" id="faqAccordion">
        <?php foreach ($faqs as $i => $f): ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="h<?php echo $i; ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c<?php echo $i; ?>" aria-expanded="false" aria-controls="c<?php echo $i; ?>">
                <?php echo htmlspecialchars($f['question']); ?>
              </button>
            </h2>
            <div id="c<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
              <div class="accordion-body"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>
</section>
<?php endif; ?>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
<script>
$(function(){
  const ajaxUrl = window.location.href; // same page
  let offset=0, total=0, perPage=24;
  const catGrid = $('#cat-products-grid');

  function starHTML(avg){
    avg = Math.round((+avg||0)*2)/2;
    const full='<i class="bi bi-star-fill text-warning"></i>';
    const half='<i class="bi bi-star-half text-warning"></i>';
    const empty='<i class="bi bi-star text-warning"></i>';
    let out='';
    for(let i=1;i<=5;i++){
      if(i<=avg) out+=full;
      else if(i-0.5===avg) out+=half;
      else out+=empty;
    }
    return out+` <span class="small text-muted ms-1">(${(+avg||0).toFixed(1)})</span>`;
  }

  function renderProducts(rows){
    const catLabel = <?php echo json_encode((string)$category_name); ?>;
    rows.forEach(p=>{
      const hasVars = (parseInt(p.variation_count,10)||0) > 0;
      const minVar = p.min_price!==null ? +p.min_price : null;
      const maxVar = p.max_price!==null ? +p.max_price : null;
      const base   = +p.current_price || 0;
      let eff = base, priceText = `PKR ${Math.round(eff).toLocaleString()}`;
      if (hasVars && minVar!==null){
        eff = minVar;
        priceText = (maxVar!==null && maxVar!==minVar)
          ? `PKR ${Math.round(minVar).toLocaleString()} – ${Math.round(maxVar).toLocaleString()}`
          : `PKR ${Math.round(minVar).toLocaleString()}`;
      }
      let disc = 0; if ((+p.old_price||0)>0 && (+p.old_price||0) > eff){
        disc = Math.round(((+p.old_price - eff) / (+p.old_price)) * 100);
      }
      const oldH  = disc>0 ? `<span class="product-old-price-v2">PKR ${Math.round(+p.old_price||0).toLocaleString()}</span>` : '';
      const badge = disc>0 ? `<span class="discount-badge-v2">${hasVars ? `Up to ${disc}% OFF` : `${disc}% OFF`}</span>` : '';

      catGrid.append(`
        <div class="product-card-v2">
          <div class="product-image-container-v2">
            ${badge}
            <button type="button" class="wdx-quick-view-btn" data-slug="${p.slug}">
              <i class="bi bi-eye"></i> Quick View
            </button>
            <a href="${(window.SITE_BASE || '').toString()}shop/${p.slug}">
              <img class="product-image-v2" src="${p.first_image}" alt="${$('<div>').text(p.product_name).html()}" loading="lazy">
            </a>
          </div>
          <div class="product-details-v2">
            <div class="product-cat-v2">${$('<div>').text(catLabel || '').html()}</div>
            <p class="product-title-v2"><a href="${(window.SITE_BASE || '').toString()}shop/${p.slug}">${$('<div>').text(p.product_name).html()}</a></p>
            <p class="mb-0">
              ${oldH}
              <span class="product-price-v2">${priceText}</span>
            </p>
          </div>
        </div>
      `);
    });
  }

  function loadProducts(reset=false){
    const sortMap = {
      'popular':'popular','price-low-high':'price-low-high','price-high-low':'price-high-low',
      'newest':'newest','rating':'rating'
    };
    const sort = sortMap[$('#sort-select').val()] || 'popular';

    if (reset){ offset=0; catGrid.empty(); }

    $.post(ajaxUrl, {
      ajax:'products',
      sort, offset, limit:perPage
    }).done(res=>{
      const rows = res.products || [];
      total = res.total_count || 0;
      if (rows.length===0 && offset===0){
        $('#count-read').text('0 Products');
        $('#btn-more').hide();
        catGrid.html(`
          <div class="p-5 text-center bg-white border rounded-4">
            <h5 class="mb-1">No products found</h5>
            <div class="text-muted small">Try adjusting your filters</div>
          </div>
        `);
        return;
      }
      renderProducts(rows);
      offset += rows.length;
      $('#count-read').text(`${Math.min(offset,total)} of ${total} Products`);
      if (offset >= total) $('#btn-more').hide(); else $('#btn-more').show();
    }).fail(()=>{
      $('#btn-more').hide();
      if (offset===0) catGrid.html('<div class="p-5 text-center bg-white border rounded-4">Error loading products</div>');
    });
  }

  $('#sort-select').on('change', ()=>loadProducts(true));
  $('#btn-more').on('click', ()=>loadProducts(false));

  // Category tabs carousel controls
  const tabsTrack = document.getElementById('catTabsTrack');
  const btnPrev = document.getElementById('catTabsPrev');
  const btnNext = document.getElementById('catTabsNext');

  function updateTabNavState(){
    if (!tabsTrack || !btnPrev || !btnNext) return;
    const maxScroll = Math.max(0, tabsTrack.scrollWidth - tabsTrack.clientWidth);
    btnPrev.disabled = tabsTrack.scrollLeft <= 2;
    btnNext.disabled = tabsTrack.scrollLeft >= (maxScroll - 2);
    const overflowed = tabsTrack.scrollWidth > tabsTrack.clientWidth + 2;
    btnPrev.style.visibility = overflowed ? 'visible' : 'hidden';
    btnNext.style.visibility = overflowed ? 'visible' : 'hidden';
  }

  if (tabsTrack && btnPrev && btnNext) {
    btnPrev.addEventListener('click', function(){
      tabsTrack.scrollBy({ left: -220, behavior: 'smooth' });
    });
    btnNext.addEventListener('click', function(){
      tabsTrack.scrollBy({ left: 220, behavior: 'smooth' });
    });
    tabsTrack.addEventListener('scroll', updateTabNavState, { passive: true });
    window.addEventListener('resize', updateTabNavState);
    const activeTab = tabsTrack.querySelector('a.active');
    if (activeTab) {
      const left = activeTab.offsetLeft - Math.max(0, (tabsTrack.clientWidth - activeTab.offsetWidth) / 2);
      tabsTrack.scrollLeft = Math.max(0, left);
    }
    updateTabNavState();
  }

  // boot
  loadProducts(true);
});
</script>

</body>
</html>
