<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

// helper to make absolute base URL for images in responses if you need it later
function base_url() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  return $scheme . '://' . $host . ($dir === '/' ? '' : $dir . '/');
}

function sp_abs_url($path, $domain) {
  $path = str_replace('\\', '/', trim((string)$path));
  if ($path === '') {
    return '';
  }
  if (preg_match('~^https?://~i', $path)) {
    return $path;
  }
  return rtrim((string)$domain, '/') . '/' . ltrim($path, '/');
}

// Get product details
if (isset($_GET['proslug'])) {
    $slug = $_GET['proslug'];
    $stmt = $conn->prepare("SELECT p.*, b.brand_name, 
        (SELECT COUNT(*) FROM product_variations WHERE product_id = p.id) AS variation_count 
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $product_result = $stmt->get_result();

    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        $has_variations = ((int)$product['variation_count']) > 0;
        
        // Assign product details
        $id = (int)$product['id'];
        $product_name   = $product['product_name'];
        $old_price      = (float)$product['old_price'];
        $current_price  = (float)$product['current_price'];
        $sold_quantity  = (int)$product['sold_quantity'];
        $product_sku    = $product['product_sku'];
        $quantity       = (int)$product['quantity'];
        $video_embed    = $product['video_embed'];
        $short_des      = $product['short_description'];
        $product_details= $product['product_details'];
        $specifications = json_decode($product['specifications'] ?: "[]", true);
        $images         = json_decode($product['images'] ?: "[]", true);
        if (!is_array($images)) {
            $images = [];
        }
        $normalizedImages = [];
        foreach ($images as $imgPath) {
            $imgUrl = sp_abs_url((string)$imgPath, $domain ?? base_url());
            if ($imgUrl !== '') {
                $normalizedImages[] = $imgUrl;
            }
        }
        $images = $normalizedImages;
        if (count($images) === 0) {
            $images = [sp_abs_url('assets/images/placeholder.png', $domain ?? base_url())];
        }
        $brand_name     = $product['brand_name'];
    } else {
        die("Product not found.");
    }
} else {
    die("Invalid product.");
}

// Products can belong to multiple categories. Keep breadcrumb generic and show categories as tags.
$breadcrumbTrail = [];
$productCategories = [];
$productCategoryIds = wdx_get_product_category_ids($conn, $id, isset($product['category']) ? (int)$product['category'] : 0);
if (!empty($productCategoryIds)) {
    $ph = implode(',', array_fill(0, count($productCategoryIds), '?'));
    $types = str_repeat('i', count($productCategoryIds));
    if ($catStmt = $conn->prepare("SELECT id, category_name, slug FROM categories WHERE id IN ($ph) ORDER BY category_name ASC")) {
        $catStmt->bind_param($types, ...$productCategoryIds);
        $catStmt->execute();
        $catRes = $catStmt->get_result();
        while ($catRow = $catRes->fetch_assoc()) {
            $productCategories[] = [
                'id' => (int)$catRow['id'],
                'name' => (string)$catRow['category_name'],
                'slug' => (string)$catRow['slug'],
            ];
        }
        $catStmt->close();
    }
}

// Variations & attributes
$variations = [];
$availableCombinations = [];
$defaultVariation = null;
$attributes = [];

if (!empty($has_variations)) {
    // 1) variations
    $varStmt = $conn->prepare("
        SELECT id, sku, price, stock, image, variation_data, variation_key
        FROM product_variations
        WHERE product_id = ?
        ORDER BY id
    ");
    $varStmt->bind_param("i", $id);
    $varStmt->execute();
    $varResult = $varStmt->get_result();

    $variations = [];
    $usedAttributes = [];

    while ($row = $varResult->fetch_assoc()) {
        $variationData = json_decode($row['variation_data'], true) ?: [];
        foreach ($variationData as $attrName => $attrDetails) {
            $usedAttributes[$attrName][] = $attrDetails['value'];
        }
        $variations[] = [
            'id' => (int)$row['id'],
            'sku' => $row['sku'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock'],
            'image' => sp_abs_url((string)$row['image'], $domain ?? base_url()),
            'variation_key' => $row['variation_key'],
            'attributes' => $variationData
        ];
    }
    $varStmt->close();

    // 2) attributes used by variations
    if (!empty($usedAttributes)) {
        $placeholders = implode(',', array_fill(0, count($usedAttributes), '?'));
        $types = str_repeat('s', count($usedAttributes));

        $attrQuery = "
            SELECT a.id AS attribute_id, a.name AS attribute_name, a.value_type,
                   av.id AS value_id, av.value AS attribute_value, av.label
            FROM attributes a
            JOIN attribute_values av ON a.id = av.attribute_id
            WHERE a.name IN ($placeholders)
            ORDER BY a.id, av.id
        ";

        $attrStmt = $conn->prepare($attrQuery);
        $attrStmt->bind_param($types, ...array_keys($usedAttributes));
        $attrStmt->execute();
        $attrResult = $attrStmt->get_result();

        while ($row = $attrResult->fetch_assoc()) {
            if (in_array($row['attribute_value'], $usedAttributes[$row['attribute_name']])) {
                if (!isset($attributes[$row['attribute_name']])) {
                    $attributes[$row['attribute_name']] = [
                        'id' => (int)$row['attribute_id'],
                        'value_type' => $row['value_type'],
                        'values' => []
                    ];
                }
                $attributes[$row['attribute_name']]['values'][$row['attribute_value']] = [
                    'id' => (int)$row['value_id'],
                    'label' => $row['label'] ?: $row['attribute_value']
                ];
            }
        }
        $attrStmt->close();

        // Fallback: if attributes master data is missing, build selectors from variation JSON.
        if (empty($attributes)) {
            foreach ($usedAttributes as $attrName => $values) {
                $values = array_values(array_unique(array_map('strval', $values)));
                $inferredType = 'text';
                $colorLikeCount = 0;
                foreach ($values as $v) {
                    $v = trim($v);
                    if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $v) || preg_match('/^rgb\(/i', $v) || preg_match('/^[a-zA-Z\s-]{3,20}$/', $v)) {
                        $colorLikeCount++;
                    }
                }
                if ($colorLikeCount > 0 && $colorLikeCount === count($values)) {
                    $inferredType = 'color';
                }

                $attributes[$attrName] = [
                    'id' => 0,
                    'value_type' => $inferredType,
                    'values' => []
                ];

                foreach ($values as $v) {
                    $attributes[$attrName]['values'][$v] = [
                        'id' => 0,
                        'label' => $v,
                    ];
                }
            }
        }
    }

    // 3) available combos
    foreach ($variations as $variation) {
        foreach ($variation['attributes'] as $attrName => $attrData) {
            if (!isset($availableCombinations[$attrName])) $availableCombinations[$attrName] = [];
            if (!in_array($attrData['value'], $availableCombinations[$attrName], true)) {
                $availableCombinations[$attrName][] = $attrData['value'];
            }
        }
    }

    // 4) default variation (first with stock else first)
    foreach ($variations as $variation) { if ($variation['stock'] > 0) { $defaultVariation = $variation; break; } }
    if (!$defaultVariation && !empty($variations)) $defaultVariation = $variations[0];

    if ($defaultVariation) { $current_price = (float)$defaultVariation['price']; $quantity = (int)$defaultVariation['stock']; }
}

// ratings
$avgStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id = ? and status = 'approved'");
$avgStmt->bind_param("i", $id);
$avgStmt->execute();
$avgRes = $avgStmt->get_result()->fetch_assoc();
$avgStmt->close();

$avgRating    = $avgRes['avg_rating'] ? (float)$avgRes['avg_rating'] : 0.0;
$totalReviews = (int)($avgRes['total_reviews'] ?? 0);

// all reviews
$revStmt = $conn->prepare("
    SELECT r.`rating`, r.`review_text`, r.`created_at`, c.`customer_name`
    FROM `reviews` as r
    LEFT JOIN `orders` as c ON r.`order_number` = c.`order_number`  
    WHERE `product_id` = ? AND r.`status` = 'approved'
    ORDER BY r.`id` DESC
");
$revStmt->bind_param("i", $id);
$revStmt->execute();
$reviewsData = $revStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$revStmt->close();

// faqs
$faqStmt = $conn->prepare("SELECT question, answer FROM faqs WHERE faq_for='product' AND item_id=? ORDER BY id ASC");
$faqStmt->bind_param("i", $id);
$faqStmt->execute();
$faqs = $faqStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$faqStmt->close();

function renderStars($average) {
    $rounded = round($average);
    $output = '';
    for ($i = 1; $i <= 5; $i++) {
        $output .= ($i <= $rounded) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
    }
    return $output;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>
    <?php if (isset($old_price, $current_price) && $old_price > $current_price): ?>
     <?php echo round((($old_price - $current_price) / $old_price) * 100); ?>% OFF
    <?php endif; ?>
    <?php echo htmlspecialchars($product_name); ?> | <?php echo $site_name ?? "Shop"; ?>
  </title>
  <meta name="description" content="<?php echo htmlspecialchars($product_name); ?> - <?php echo strip_tags($product_details); ?>">
  <link rel="icon" type="image/x-icon" href="<?php echo $domain ?? '';?>assets/images/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="<?php echo $domain ?? '';?>assets/style.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>

  <style>
    body{ background:#fffffff; }
    .product-breadcrumb{
      margin: 18px 0 12px;
      font-size: .95rem;
      color:#728091;
    }
    .product-breadcrumb ol{ display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0; padding:0; list-style:none; }
    .product-breadcrumb li{ display:inline-flex; align-items:center; gap:10px; }
    .product-breadcrumb li+li::before{ content:'›'; color:#a1adba; font-size:1.05rem; }
    .product-breadcrumb a{ color:#728091; text-decoration:none; font-weight:600; }
    .product-breadcrumb a:hover{ color:#3c4a59; }
    .product-breadcrumb .current{ color:#2f3944; font-weight:700; }

    .product-main {
      display: flex;
      gap: 40px;
      background: #fff;
      border-radius: 5px;
      border:1px solid #e2e7ec;
      box-shadow: 0 8px 30px rgba(25, 35, 46, .05);
      padding: 24px;
      align-items: flex-start;
    }
    .product-main.row{
      --bs-gutter-x: 0;
      --bs-gutter-y: 0;
      margin-left: 0;
      margin-right: 0;
      flex-wrap: nowrap;
    }
    .product-main > .gallery-section,
    .product-main > .info-section{
      padding-left: 0;
      padding-right: 0;
      min-width: 0;
    }
    .product-main > .gallery-section{
      flex: 0 0 calc(52% - 20px);
      max-width: calc(52% - 20px);
    }
    .product-main > .info-section{
      flex: 0 0 calc(48% - 20px);
      max-width: calc(48% - 20px);
    }
    .gallery-section {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .main-img-wrap {
      background: #f2f4f7;
      border-radius: 14px;
      border:1px solid #e2e7ec;
      width: 100%;
      aspect-ratio: 1/1;
      overflow: hidden;
      box-shadow: none;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: box-shadow .3s;
    }
    .main-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      transition: transform .25s;
      display: block;
      margin: 0 auto;
    }
    .gallery-thumbs {
      display: flex;
      gap: 10px;
      margin: 0 auto;
    }
    .gallery-thumb {
      width: 110px;
      height: 110px;
      background: #fff;
      border-radius: 5px;
      box-shadow: none;
      overflow: hidden;
      border: 1.8px solid #dbe2ea;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color .18s;
    }
    .gallery-thumb.selected, .gallery-thumb:hover {
      border-color: #f3f5f7;
    }
    .gallery-thumb img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }
    .info-section {
      max-width: none;
      position: sticky;
      top: 32px;
    }
    .brand-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      color: #78be20;
      font-size: 13px;
      letter-spacing: 1px;
      text-transform: uppercase;
      font-weight: 800;
    }
    .brand-row .brand-label{ color:#78be20; }
    .product-title {
      font-size: clamp(2rem, 3.3vw, 2.5rem);
      font-weight: 600;
      margin-bottom: 10px;
      line-height: 1.08;
      letter-spacing: .01em;
      color:#121a24;
    }
    .sku-row{
      color:#000;
      font-size:.78rem;
      font-weight:600;
      letter-spacing:.06em;
      text-transform:uppercase;
      margin-bottom:8px;
    }
    .product-price {
      font-size: 2rem;
      color: #121a24;
      font-weight: 700;
      margin-bottom: 6px;
      margin-top: 6px;
      letter-spacing: .01em;
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .old-price {
      text-decoration: line-through;
      color: var(--gray);
      font-size: 1.2rem;
      margin-right: 12px;
    }
    .save-badge{
      background:#e11d2e;
      color:#fff;
      border-radius:4px;
      padding:4px 8px;
      font-size:.86rem;
      font-weight:700;
    }
    .variation-row {
      display: flex;
      gap: 18px;
      flex-wrap: wrap;
      margin: 16px 0 10px;
      align-items: center;
    }
    .variation-block {
      display: flex;
      flex-direction: column;
      gap: 7px;
      min-width: 100%;
    }
    .variation-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      width:100%;
    }
    .variation-value-display{ color:#7a8694; font-weight:700; font-size:.95rem; }
    .variation-label {
      font-size: 1.01rem;
      color: #2e3742;
      font-weight: 700;
      margin-bottom: 1px;
    }
    /* Color swatches */
    .swatch-row {
      display: flex;
      gap: 9px;
      margin-top: 2px;
    }
    .color-swatch {
      width: 38px; height: 38px;
      border-radius: 50%;
      border: 2px solid #d9e0e8;
      cursor: pointer;
      display: inline-block;
      transition: border-color .19s, transform .19s;
      position: relative;
      box-sizing: border-box;
      overflow: hidden;
    }
    .color-swatch:hover{ transform: translateY(-1px); }
    .color-swatch.selected {
      border: 3px solid #78be20;
      box-shadow: 0 0 0 3px rgba(120, 190, 32, .2);
    }
    .color-swatch.selected::before{
      content:'✓';
      position:absolute;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#243019;
      font-weight:900;
      font-size:.95rem;
      text-shadow: 0 1px 1px rgba(255,255,255,.5);
    }
    .color-swatch.textual{
      border-radius: 10px;
      width: auto;
      min-width: 42px;
      padding: 0 10px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:#f4f7fa;
      color:#2e3742;
      font-weight:700;
      font-size:.78rem;
      text-transform: capitalize;
    }
    .color-swatch.textual.selected::before{ content:''; }
    .color-swatch.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      position: relative;
    }
    .color-swatch.disabled::after {
      content: "";
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: red;
      transform: rotate(-45deg);
      transform-origin: center;
    }
    /* Size dropdown */
    .size-dropdown, .brand-dropdown {
      padding: 8px 18px 8px 9px;
      border-radius: 6px;
      border: 1.8px solid #e4e4e7;
      font-size: 1.06rem;
      background: #fafbfc;
      color: #222;
      min-width: 80px;
      cursor: pointer;
      outline: none;
      font-weight: 500;
      transition: border-color .14s;
    }
    .size-dropdown:focus, .brand-dropdown:focus { border-color: var(--primary-dark);}
    .reviews-row {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--gray);
      font-size: 1.02rem;
    }
    .reviews-row .stars {
      color: #fbbf24;
      font-size: 1.13rem;
      margin-right: 2px;
    }
    #youMayAlsoLikeCarousel .carousel-control-prev,
    #youMayAlsoLikeCarousel .carousel-control-next{
      width: 42px;
      height: 42px;
      top: -56px;
      background:#121a24;
      border-radius: 50%;
      opacity: 1;
    }
    #youMayAlsoLikeCarousel .carousel-control-prev{ left: auto; right: 52px; }
    #youMayAlsoLikeCarousel .carousel-control-next{ right: 0; }
    #youMayAlsoLikeCarousel .carousel-control-prev-icon,
    #youMayAlsoLikeCarousel .carousel-control-next-icon{ width: 1rem; height: 1rem; }
    .desc-short {
      color: #5b6773;
      font-size: 1.12rem;
      margin-bottom: 14px;
      margin-top: 8px;
      line-height: 1.65;
    }
    .product-actions {
      display: flex;
      gap: 18px;
      margin: 22px 0 7px 0;
      align-items: center;
    }
    .btn-main {
      padding: 16px 44px;
      border: none;
      background: #78be20;
      color: #fff;
      font-weight: 700;
      font-size: 1.05rem;
      border-radius: 4px;
      cursor: pointer;
      box-shadow: 0 2px 12px rgba(248,197,55,.07);
      transition: background .18s, color .2s;
      letter-spacing: .02em;
    }
    .btn-ghost {
      padding: 16px 22px;
      border: 1.5px solid #d9e0e8;
      background: #fff;
      color: #2d3844;
      border-radius: 4px;
      font-weight: 600;
      font-size: 1.11rem;
      cursor: pointer;
      transition: background .18s, color .2s;
    }
    .btn-ghost:hover {
      border-color:#78be20;
      color:#4f8f1a;
    }
    .features-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: 16px 22px;
        margin-top: 30px;
        padding-top: 6px;
    }
    .features-row .meta-item {
      display:flex;
      align-items:center;
      gap:12px;
      background:transparent;
      border:0;
      padding:0;
    }
    .features-row .meta-item i{
      width:46px;
      height:46px;
      border-radius:50%;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      color:#6aa728;
      background:#edf2e8;
      font-size:1.25rem;
      flex:0 0 46px;
    }
    .meta-copy{
      display:flex;
      flex-direction:column;
      gap:2px;
      line-height:1.25;
    }
    .meta-copy strong{
      font-size:1 rem;
      color:#202830;
      font-weight:600;
    }
    .meta-copy span{
      font-size:.95rem;
      color:#66727f;
      font-weight:500;
    }
    /* Tabs */
    .tabs-section {
      margin-top: 40px;
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 0;
      overflow: hidden;
    }
    .tabs {
      display: flex;
      border-bottom: 1.5px solid var(--border);
      background: #fcfcfd;
    }
    .tab-btn {
      padding: 22px 44px;
      font-size: 1.13rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      color: #888;
      border-bottom: 3.5px solid transparent;
      transition: color .18s, border-color .18s;
      outline: none;
      letter-spacing: .01em;
    }
    .tab-btn.active {
      color: var(--primary-dark);
      border-color: var(--primary-color);
      background: #fff;
    }
    .tab-content {
      padding: 34px 42px 38px 42px;
      animation: fadein .34s;
      min-height: 180px;
    }
    @keyframes fadein {
      from {opacity: 0;transform: translateY(30px);}
      to {opacity: 1;transform: none;}
    }
    /* Description content */
    .desc-long {
      color: #444;
      font-size: 1.11rem;
      line-height: 1.8;
      margin-bottom: 4px;
    }
    /* Specs Table */
    .specs-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 6px 0;
    }
    .specs-table th, .specs-table td {
      text-align: left;
      padding: 12px 14px;
      font-size: 1rem;
    }
    .specs-table th {
      background: #f8f8f8;
      color: #444;
      font-weight: 600;
      border-bottom: 2px solid #ececec;
    }
    .specs-table tr:not(:last-child) td {
      border-bottom: 1px solid #f1f1f1;
    }
    .specs-table td {
      color: #555;
    }
    /* Reviews */
    .reviews-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .review-item {
      padding: 24px 0;
      border-bottom: 1px solid #f2f2f2;
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }
    .review-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      background: #ececec;
      flex-shrink: 0;
      display: block;
    }
    .review-content {
      flex: 1;
    }
    .review-stars {
      color: #fbbf24;
      font-size: 1.06rem;
      margin-bottom: 3px;
    }
    .review-name {
      font-weight: 600;
      font-size: 1rem;
      margin-right: 6px;
    }
    .review-date {
      color: var(--gray);
      font-size: 0.93rem;
    }
    .review-text {
      margin-top: 8px;
      font-size: 1.06rem;
      color: #444;
      line-height: 1.7;
    }
    /* Quantity selector */
    .quantity-selector {
      display: inline-flex;
      align-items: center;
      gap: 14px;
      padding: 6px 10px;
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      background: #f7f8fa;
      height: 52px;
    }
    .quantity-selector button {
      border: 0;
      width: 34px;
      height: 34px;
      font-size: 1.25rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      background: transparent;
      color: #111;
      font-weight: 700;
      line-height: 1;
      padding: 0;
    }
    .quantity-selector .qty-sign{
      display:block;
      font-size:1.35rem;
      font-weight:800;
      line-height:1;
      color:inherit;
      transform: translateY(-1px);
    }
    .quantity-selector button.is-active {
      background: #000;
      color: #fff !important;
    }
    .quantity-selector button.is-active .qty-sign{
      color:#fff !important;
    }
    .quantity-selector button:not(.is-active) {
      background: transparent;
      color: #111;
    }
    .quantity-selector input {
      width: 32px;
      text-align: center;
      height: 34px;
      border: 0;
      border-radius: 0;
      background: transparent;
      font-weight: 700;
      font-size: 1.05rem;
      padding: 0;
      -moz-appearance: textfield;
    }
    .quantity-selector input:focus {
      outline: none;
      box-shadow: none;
    }
    .quantity-selector input::-webkit-outer-spin-button,
    .quantity-selector input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Purchase row: qty + buttons */
    .purchase-row{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
      align-items: stretch;
      margin-top: 16px;
    }
    .purchase-actions{
      display:grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      grid-column: 2 / span 2;
      min-width: 0;
    }
    .quantity-selector{
      width:100%;
      height:46px;
      justify-content:space-between;
    }
    .purchase-actions .btn-main,
    .purchase-actions .btn-ghost{
      flex: 1 1 auto;
      padding: 0 16px;
      height: 46px;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
      font-size: 0.85rem;
    }

    @media (max-width: 576px){
      .purchase-row{ grid-template-columns: 1fr; }
      .quantity-selector{ width: 100%; justify-content: center; }
      .purchase-actions{ grid-template-columns: 1fr; grid-column: auto; min-width: 0; }
      .purchase-actions .btn-main,
      .purchase-actions .btn-ghost{ width: 100%; }
    }
    /* Responsive */
    @media (max-width: 900px) {
      .product-main {
        flex-direction: column;
        gap: 28px;
        padding: 26px 3vw;
      }
      .product-main.row{ flex-wrap: wrap; }
      .product-main > .gallery-section,
      .product-main > .info-section{
        flex: 0 0 100%;
        max-width: 100%;
      }
      .info-section {position: static; max-width: 100%;}
      .section {padding: 20px 3vw;}
    }
    @media (max-width: 700px) {
      .main-img-wrap, .gallery-section {max-width: 98vw;}
      .tabs .tab-btn {padding: 13px 13px;}
      .tab-content {padding: 18px 9px;}
      .product-main {padding: 16px 1vw;}
      .features-row{ grid-template-columns: 1fr; gap: 14px; }
    }
    @media (max-width: 520px) {
      .product-title {font-size: 1.9rem;}
      .main-img-wrap {border-radius: 10px;}
      .gallery-thumb {width: 43px;height: 43px;}
    }
    .main-img-wrap {
  --zoom-size: 230%;                 /* how much to zoom in */
  background-color: #f4f4f6;
  background-repeat: no-repeat;
  background-position: center;
  transition: background-size .15s ease;
}

.main-img-wrap.zooming {
  background-size: var(--zoom-size);
  cursor: zoom-in;
}

.main-img-wrap.zooming img {
  opacity: 0;                        /* hide the <img> while zooming */
  transition: opacity .05s linear;
}

/* (optional) disable zoom on small screens */
@media (max-width: 768px) {
  .main-img-wrap.zooming {
    background-size: 100%;
    cursor: default;
  }
  .main-img-wrap.zooming img { opacity: 1; }
}
.cart-panel{position:fixed;top:0;right:-400px;width:400px;max-width:100%;height:100%;background:#fff;box-shadow:-2px 0 14px rgba(0,0,0,.12);z-index:9999;transition:right .25s;}
.cart-panel .cart-panel-header{padding:14px 16px;border-bottom:1px solid #eee;}
.cart-panel .cart-panel-body{max-height:calc(100vh - 180px);overflow:auto;padding:10px 16px;}
.cart-panel .cart-panel-footer{padding:14px 16px;border-top:1px solid #eee;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:saturate(90%) blur(2px);opacity:0;visibility:hidden;transition:opacity .25s;z-index:9998;}
.overlay.show{opacity:1;visibility:visible;}
.badge-notification{position:absolute;top:-6px;right:-8px;background:#dc3545;color:#fff;border-radius:999px;font-size:12px;padding:2px 6px;}

/* Toast */
.cart-toast{position:fixed;left:20px;bottom:-200px;min-width:280px;max-width:360px;background:#28a745;color:#fff;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,.2);z-index:10000;transition:bottom .25s ease;}
.cart-toast.show{bottom:20px;}
.cart-toast-inner{display:flex;gap:10px;align-items:center;padding:12px 14px;}
.cart-toast img{width:48px;height:48px;border-radius:8px;object-fit:cover;display:block;}
  </style>
</head>
<body>
  <?php include_once('header/header.php'); ?>

  <div class="container">

    <nav class="product-breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?php echo $domain; ?>">Home</a></li>
        <li><a href="<?php echo $domain; ?>shop">Shop</a></li>
        <li><span class="current"><?php echo htmlspecialchars($product_name); ?></span></li>
      </ol>
    </nav>

    <!-- Main Product Section -->
    <div class="product-main row">
      <!-- Gallery -->
      <div class="gallery-section col-md-6">
        <div class="main-img-wrap" id="mainImgWrap">
          <img id="mainImg" src="<?php echo htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product_name); ?>">
        </div>
        <div class="gallery-thumbs" id="thumbs">
          <?php foreach ($images as $index => $image): ?>
            <div class="gallery-thumb <?php echo $index === 0 ? 'selected' : ''; ?>">
              <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product_name) . ' - Image ' . ($index + 1); ?>">
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Product Info with Variations -->
      <div class="info-section col-md-6">
        <div class="sku-row">SKU: <?php echo htmlspecialchars($product_sku); ?></div>
        <div class="product-title"><?php echo htmlspecialchars($product_name); ?></div>
        <?php if (!empty($productCategories)): ?>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach ($productCategories as $cat): ?>
              <a class="badge bg-light text-dark border text-decoration-none" href="<?php echo $domain . 'categories/' . htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
                <div class="reviews-row">
          <?php if ((int)$totalReviews > 0): ?>
            <span><?php echo (int)$totalReviews; ?> reviews</span>
          <?php endif; ?>
        </div>
        <div class="product-price">
          <span>PKR <?php echo number_format($current_price, 2); ?></span>
          <?php if ($old_price > $current_price): ?>
            <span class="old-price">PKR <?php echo number_format($old_price, 2); ?></span>
            <span class="save-badge">Save <?php echo round((($old_price - $current_price) / $old_price) * 100); ?>%</span>
          <?php endif; ?>
        </div>
        <div class="badge bg-light text-success" style="
    font-size: 15px;
">In Stock</div>
                <div class="desc-short">
          <?php echo $short_des; ?>
        </div>
        
        <?php if (!empty($has_variations) && !empty($attributes)): ?>
        <div class="variation-row" id="attributeSelectors">
            <?php foreach ($attributes as $attrName => $attrData): ?>
                <div class="variation-block">
                    <?php
                      $safeAttrId = preg_replace('/[^a-zA-Z0-9_]/', '_', $attrName);
                      $currentVal = $defaultVariation && isset($defaultVariation['attributes'][$attrName]['value'])
                        ? (string)$defaultVariation['attributes'][$attrName]['value']
                        : '';
                    ?>
                    <div class="variation-top">
                      <span class="variation-label"><?= htmlspecialchars($attrName) ?>:</span>
                      <span class="variation-value-display" id="display_<?= htmlspecialchars($safeAttrId) ?>"><?= htmlspecialchars($currentVal) ?></span>
                    </div>
                    <?php if ($attrData['value_type'] === 'color'): ?>
                        <div class="swatch-row">
                            <?php foreach ($attrData['values'] as $value => $valueData): 
                                $isAvailable = in_array($value, $availableCombinations[$attrName] ?? []);
                                $isDefault = $defaultVariation && 
                                            isset($defaultVariation['attributes'][$attrName]) && 
                                            $defaultVariation['attributes'][$attrName]['value'] === $value;
                                $isCssColor = preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string)$value)
                                  || preg_match('/^rgb\(/i', (string)$value)
                                  || preg_match('/^hsl\(/i', (string)$value)
                                  || preg_match('/^[a-zA-Z]+$/', (string)$value);
                                $swatchStyle = $isCssColor ? 'background-color: ' . htmlspecialchars((string)$value) . ';' : '';
                                $swatchText = !$isCssColor ? htmlspecialchars((string)$valueData['label']) : '';
                            ?>
                                <span class="color-swatch <?= $isDefault ? 'selected' : '' ?> <?= !$isAvailable ? 'disabled' : '' ?> <?= !$isCssColor ? 'textual' : '' ?>"
                                      data-attr="<?= htmlspecialchars($attrName) ?>"
                                      data-display-id="display_<?= htmlspecialchars($safeAttrId) ?>"
                                      data-label="<?= htmlspecialchars($valueData['label']) ?>"
                                      data-value="<?= htmlspecialchars($value) ?>"
                                      style="<?= $swatchStyle ?>"
                                      title="<?= htmlspecialchars($valueData['label']) ?>"
                                      <?= !$isAvailable ? 'disabled' : '' ?>>
                                      <?= $swatchText ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="selected_<?= htmlspecialchars($attrName) ?>" 
                               value="<?= $defaultVariation ? htmlspecialchars($defaultVariation['attributes'][$attrName]['value']) : '' ?>">
                    <?php else: ?>
                        <select class="size-dropdown attribute-select" 
                                id="select_<?= htmlspecialchars($attrName) ?>"
                                data-display-id="display_<?= htmlspecialchars($safeAttrId) ?>"
                                data-attr="<?= htmlspecialchars($attrName) ?>">
                            <?php foreach ($attrData['values'] as $value => $valueData): 
                                $isAvailable = in_array($value, $availableCombinations[$attrName] ?? []);
                                $isDefault = $defaultVariation && 
                                            isset($defaultVariation['attributes'][$attrName]) && 
                                            $defaultVariation['attributes'][$attrName]['value'] === $value;
                            ?>
                                <option value="<?= htmlspecialchars($value) ?>" 
                                        <?= $isDefault ? 'selected' : '' ?>
                                        <?= !$isAvailable ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($valueData['label']) ?>
                                    <?= !$isAvailable ? ' (Out of Stock)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" id="selected_<?= htmlspecialchars($attrName) ?>" 
                               value="<?= $defaultVariation ? htmlspecialchars($defaultVariation['attributes'][$attrName]['value']) : '' ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="purchase-row">
          <div class="quantity-selector" aria-label="Quantity selector">
            <button type="button" id="decreaseQty" class="is-active" aria-label="Decrease quantity"><span class="qty-sign">&minus;</span></button>
            <input type="number" id="pro-quantity" value="1" min="1" max="<?= (int)$quantity ?>" inputmode="numeric" aria-label="Quantity">
            <button type="button" id="increaseQty" aria-label="Increase quantity"><span class="qty-sign">+</span></button>
          </div>

          <div class="purchase-actions">
            <button type="button" class="btn-main add-to-cart-btn" 
              data-id="<?php echo (int)$product['id']; ?>"
              data-name="<?php echo htmlspecialchars($product_name); ?>"
              data-price="<?php echo number_format($current_price, 2); ?>"
              data-image="<?php echo htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8'); ?>"
              data-quantity="1">
              <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>

            <a id="btnEQuotation" class="btn-ghost add-to-quote-btn" href="#"
               data-id="<?php echo (int)$product['id']; ?>" data-quantity="1">
              <i class="bi bi-file-earmark-text"></i> E-Quotation
            </a>
          </div>
        </div>
        <div class="features-row">
          <div class="meta-item">
            <i class="bi bi-truck"></i>
            <div class="meta-copy">
              <strong>Free Delivery</strong>
              <span>Nationwide across Pakistan</span>
            </div>
          </div>
          <div class="meta-item">
            <i class="bi bi-shield-check"></i>
            <div class="meta-copy">
              <strong>3 years</strong>
              <span>Manufacturer warranty</span>
            </div>
          </div>
          <div class="meta-item">
            <i class="bi bi-box-seam"></i>
            <div class="meta-copy">
              <strong>Free Assembly</strong>
              <span>Professional installation</span>
            </div>
          </div>
          <div class="meta-item">
            <i class="bi bi-telephone"></i>
            <div class="meta-copy">
              <strong>Expert Support</strong>
              <span>24/7 assistance</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs (keep as-is) -->
    <div class="tabs-section" id="tabsSection">
      <div class="tabs">
        <button class="tab-btn active" data-tab="desc">Description</button>
        <button class="tab-btn" data-tab="specs">Specifications</button>
        <button class="tab-btn" data-tab="reviews">Reviews</button>
      </div>
      <div class="tab-content" id="descTab">
        <div class="desc-long"><?php echo $product_details; ?></div>
      </div>
      <div class="tab-content" id="specsTab" style="display:none;">
        <table class="specs-table">
          <?php foreach ($specifications as $sp): ?>
            <tr>
              <td><?php echo htmlspecialchars($sp['heading']); ?></td>
              <td><?php echo htmlspecialchars($sp['description']); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="tab-content" id="reviewsTab" style="display:none;">
        <?php if ($totalReviews > 0): ?>
          <ul class="reviews-list">
            <?php foreach ($reviewsData as $review): ?>
              <li class="review-item">
                <img class="review-avatar" src="https://randomuser.me/api/portraits/<?php echo rand(0, 1) ? 'men' : 'women'; ?>/<?php echo rand(1, 99); ?>.jpg" alt="Reviewer">
                <div class="review-content">
                  <span class="review-name"><?php echo $review['customer_name']; ?></span>
                  <span class="review-date"><?php echo date("F Y", strtotime($review['created_at'])); ?></span>
                  <div class="review-text"><?php echo $review['review_text']; ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- FAQ (kept) -->
  <div class="container">
    <div class="section">
      <h2 class="section-title"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
      <?php if (!empty($faqs)): ?>
        <div class="accordion" id="faqAccordion">
          <?php foreach ($faqs as $index => $faq): ?>
            <div class="accordion-item">
              <h3 class="accordion-header" id="faqHeading<?php echo $index; ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?php echo $index; ?>" aria-expanded="false" aria-controls="faqCollapse<?php echo $index; ?>">
                  <?php echo htmlspecialchars($faq['question']); ?>
                </button>
              </h3>
              <div id="faqCollapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="faqHeading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                <div class="accordion-body"><?php echo htmlspecialchars($faq['answer']); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No FAQs available for this product.</p>
      <?php endif; ?>
    </div>
  </div>

<?php
// Related products
$query = "SELECT
    p.id,
    p.product_name,
    p.old_price,
    p.current_price,
    p.images,
    p.slug,
    COALESCE((
      SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ')
      FROM product_categories pc2
      JOIN categories c2 ON c2.id = pc2.category_id
      WHERE pc2.product_id = p.id
    ), c.category_name) AS category_name,
    MIN(v.price) AS min_price,
    MAX(v.price) AS max_price,
    COUNT(v.id) AS variation_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category
    LEFT JOIN product_variations v ON v.product_id = p.id
    WHERE p.id != ?
      AND (
        ? = 0 OR EXISTS (
          SELECT 1
          FROM product_categories pc_rel
          WHERE pc_rel.product_id = p.id
            AND pc_rel.category_id IN (SELECT category_id FROM product_categories WHERE product_id = ?)
        )
      )
    GROUP BY p.id, p.product_name, p.old_price, p.current_price, p.images, p.slug, c.category_name
    ORDER BY p.id DESC
    LIMIT 12";
$stmt = $conn->prepare($query);
$hasRelatedCategoryScope = !empty($productCategoryIds) ? 1 : 0;
$stmt->bind_param("iii", $id, $hasRelatedCategoryScope, $id);
$stmt->execute();
$relatedProducts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$relatedSlides = array_chunk($relatedProducts, 4);
?>
<div class="container my-5">
  <div class="section">
    <h2 class="section-title mb-4">You May Also Like</h2>
    <?php if (!empty($relatedSlides)): ?>
    <div id="youMayAlsoLikeCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <?php foreach ($relatedSlides as $slideIndex => $slideRows): ?>
          <div class="carousel-item <?php echo $slideIndex === 0 ? 'active' : ''; ?>">
            <div class="row g-4">
      <?php foreach ($slideRows as $row):
        $productId = (int)$row['id'];
        $productName = htmlspecialchars($row['product_name']);
        $oldPrice = (float)$row['old_price'];
        $price = (float)$row['current_price'];
        $proslug = htmlspecialchars($row['slug']);
        $catLabel = !empty($row['category_name']) ? strtoupper((string)$row['category_name']) : '';
        $imgs = json_decode($row['images'] ?: "[]", true);
        $firstRel = is_array($imgs) && count($imgs) > 0 ? (string)$imgs[0] : 'assets/images/placeholder.svg';
        $imgSrc = function_exists('wdx_join_url') ? wdx_join_url($domain, $firstRel) : ($domain . ltrim($firstRel, '/'));
        $hasVars = ((int)($row['variation_count'] ?? 0)) > 0;
        $minVar = $row['min_price'] !== null ? (float)$row['min_price'] : null;
        $maxVar = $row['max_price'] !== null ? (float)$row['max_price'] : null;
        $displayPrice = ($hasVars && $minVar !== null) ? $minVar : $price;
        $priceText = ($hasVars && $minVar !== null && $maxVar !== null && $maxVar > $minVar)
          ? 'PKR ' . number_format($minVar, 0) . ' - ' . number_format($maxVar, 0)
          : 'PKR ' . number_format($displayPrice, 0);
        $discount = $oldPrice > $displayPrice ? round((($oldPrice - $displayPrice) / $oldPrice) * 100) : 0;
      ?>
      <div class="col-lg-3 col-md-4 col-sm-6 d-flex">
        <div class="product-card-v2">
          <div class="product-image-container-v2">
            <?php if ($discount > 0): ?><span class="discount-badge-v2"><?= $discount ?>% OFF</span><?php endif; ?>
            <button type="button" class="wdx-quick-view-btn" data-slug="<?= $proslug ?>">
              <i class="bi bi-eye"></i> Quick View
            </button>
            <a href="<?= $domain . 'shop/' . $proslug ?>" class="text-decoration-none text-dark">
              <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" class="product-image-v2" alt="<?= $productName ?>">
            </a>
          </div>
          <div class="product-details-v2">
            <?php if ($catLabel !== ''): ?><div class="product-cat-v2"><?= htmlspecialchars($catLabel) ?></div><?php endif; ?>
            <p class="product-title-v2"><a href="<?= $domain . 'shop/' . $proslug ?>" class="text-decoration-none text-dark"><?= $productName ?></a></p>
            <p class="mb-0">
              <?php if ($oldPrice > $displayPrice): ?><span class="product-old-price-v2">PKR <?= number_format($oldPrice, 0) ?></span><?php endif; ?>
              <span class="product-price-v2"><?= htmlspecialchars($priceText) ?></span>
            </p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($relatedSlides) > 1): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#youMayAlsoLikeCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#youMayAlsoLikeCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

  <?php include_once('footer/footer.php'); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

 <script>
    // ===== Gallery switching + Zoom on hover =====
(function () {
  const wrap   = document.getElementById('mainImgWrap');
  const mainEl = document.getElementById('mainImg');
  const thumbs = document.querySelectorAll('.gallery-thumb');

  // keep background-image in sync with <img> src
  function updateZoomBackground(src) {
    // guard for quotes in URLs
    const safe = src.replace(/"/g, '%22').replace(/'/g, '%27');
    wrap.style.backgroundImage = `url("${safe}")`;
  }

  // initialize background image once main image is ready
  if (mainEl.complete) {
    updateZoomBackground(mainEl.src);
  } else {
    mainEl.addEventListener('load', () => updateZoomBackground(mainEl.src));
  }

  // thumbs click -> switch main image + update zoom bg
  thumbs.forEach(thumb => {
    thumb.addEventListener('click', function () {
      const selected = document.querySelector('.gallery-thumb.selected');
      if (selected) selected.classList.remove('selected');
      thumb.classList.add('selected');

      const src = thumb.querySelector('img').src;
      if (mainEl.src !== src) {
        mainEl.src = src;
        // update background immediately and also after load (for high-DPI swaps)
        updateZoomBackground(src);
        mainEl.addEventListener('load', function onLoad() {
          updateZoomBackground(mainEl.src);
          mainEl.removeEventListener('load', onLoad);
        });
      } else {
        updateZoomBackground(src);
      }
    });
  });

  // inner-zoom: enlarge background under cursor
  function beginZoom(e) {
    // disable on small screens
    if (window.matchMedia('(max-width: 768px)').matches) return;
    wrap.classList.add('zooming');
    moveZoom(e);
  }
  function endZoom() {
    wrap.classList.remove('zooming');
  }
  function moveZoom(e) {
    if (!wrap.classList.contains('zooming')) return;
    const rect = wrap.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top)  / rect.height) * 100;
    wrap.style.backgroundPosition = `${x}% ${y}%`;
  }

  wrap.addEventListener('mouseenter', beginZoom);
  wrap.addEventListener('mousemove',  moveZoom);
  wrap.addEventListener('mouseleave', endZoom);

  // expose for variation changes
  window.__updateZoomBG = updateZoomBackground;
})();

    // Quantity selector (keeps add-to-cart + eQuotation in sync)
    function clampQty(v, min, max){
      v = parseInt(v, 10);
      if (isNaN(v)) v = min;
      if (v < min) v = min;
      if (max > 0 && v > max) v = max;
      return v;
    }
    function syncQtyToButtons(){
      const $qty = $('#pro-quantity');
      const max = parseInt($qty.attr('max') || '0', 10) || 0;
      const val = clampQty($qty.val(), 1, max);
      $qty.val(val);

      // Update add-to-cart payload
      $('.add-to-cart-btn').attr('data-quantity', val).data('quantity', val);

      // Update eQuotation href
      const eq = document.getElementById('btnEQuotation');
      if (eq) {
        eq.setAttribute('data-quantity', String(val));
      }
    }

    function setActiveQtyButton(buttonId){
      $('#decreaseQty, #increaseQty').removeClass('is-active');
      if (buttonId) {
        $(buttonId).addClass('is-active');
      }
    }

    $('#increaseQty').on('click', function(e){
      if (e) { e.preventDefault(); e.stopPropagation(); }
      setActiveQtyButton('#increaseQty');
      const $qty = $('#pro-quantity');
      const max = parseInt($qty.attr('max') || '0', 10) || 0;
      const next = clampQty((parseInt($qty.val(),10)||1) + 1, 1, max);
      $qty.val(next);
      syncQtyToButtons();
    });
    $('#decreaseQty').on('click', function(e){
      if (e) { e.preventDefault(); e.stopPropagation(); }
      setActiveQtyButton('#decreaseQty');
      const $qty = $('#pro-quantity');
      const max = parseInt($qty.attr('max') || '0', 10) || 0;
      const next = clampQty((parseInt($qty.val(),10)||1) - 1, 1, max);
      $qty.val(next);
      syncQtyToButtons();
    });
    $('#pro-quantity').on('input change', syncQtyToButtons);

    
    // Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    const descTab = document.getElementById('descTab');
    const specsTab = document.getElementById('specsTab');
    const reviewsTab = document.getElementById('reviewsTab');
    tabBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        descTab.style.display = btn.dataset.tab === "desc" ? "block" : "none";
        specsTab.style.display = btn.dataset.tab === "specs" ? "block" : "none";
        reviewsTab.style.display = btn.dataset.tab === "reviews" ? "block" : "none";
      });
    });

    // Quantity selector


    // Store variations data
    const variations = <?= $has_variations ? json_encode($variations) : '[]' ?>;
    const attributes = <?= $has_variations ? json_encode($attributes) : '{}' ?>;
    let selectedAttributes = {};
    const initialMainImage = $('#mainImg').attr('src') || '';

    function safeSetMainImage(nextSrc) {
        const targetSrc = String(nextSrc || '').trim();
        if (!targetSrc) return;
        const probe = new Image();
        probe.onload = function() {
            $('#mainImg').attr('src', targetSrc);
            if (window.__updateZoomBG) window.__updateZoomBG(targetSrc);
        };
        probe.onerror = function() {
            // keep current image if candidate is invalid
        };
        probe.src = targetSrc;
    }

    <?php if ($has_variations && $defaultVariation): ?>
        <?php foreach ($defaultVariation['attributes'] as $attrName => $attrData): ?>
            selectedAttributes['<?= $attrName ?>'] = '<?= $attrData['value'] ?>';
        <?php endforeach; ?>
    <?php endif; ?>

    // Function to find matching variation
    function findMatchingVariation() {
        if (variations.length === 0) return null;
        
        return variations.find(variation => {
            return Object.keys(selectedAttributes).every(attr => {
                return variation.attributes[attr]['value'] === selectedAttributes[attr];
            });
        });
    }

    // Update product details based on selected variation
    function updateProductDetails() {
        const matchedVariation = findMatchingVariation();
        
        if (matchedVariation) {
            // Update price
            $('.product-price').html(`
                <span>PKR ${parseFloat(matchedVariation.price).toFixed(2)}</span>
                <?php if ($old_price > $current_price): ?>
                    <span class="old-price">PKR <?= number_format($old_price, 2) ?></span>
                    <span class="save-badge">Save <?= round((($old_price - $current_price) / $old_price) * 100) ?>%</span>
                <?php endif; ?>
            `);
            
            // Update quantity available
            const qtyInput = $('#pro-quantity');
            const maxStock = matchedVariation.stock;
            qtyInput.attr('max', maxStock);
            
            // Reset quantity if current is more than available
            if (parseInt(qtyInput.val()) > maxStock) {
                qtyInput.val(maxStock > 0 ? 1 : 0);
            }

            // Re-sync quantity to buttons after variation updates
            syncQtyToButtons();
            
            // Disable add to cart if out of stock
            if (maxStock <= 0) {
                $('.add-to-cart-btn').prop('disabled', true).text('Out of Stock');
            } else {
                $('.add-to-cart-btn').prop('disabled', false).html('<i class="fas fa-shopping-cart"></i> Add to Cart');
            }
            
            // Update image if variation has specific image
            if (matchedVariation.image) {
                safeSetMainImage(matchedVariation.image);
                // Also update the selected thumbnail if it exists in the gallery
                const thumbIndex = $('.gallery-thumb img').index($(`.gallery-thumb img[src="${matchedVariation.image}"]`));
                if (thumbIndex >= 0) {
                    $('.gallery-thumb.selected').removeClass('selected');
                    $('.gallery-thumb').eq(thumbIndex).addClass('selected');
                }
            }
            
            // Update add to cart button data
            $('.add-to-cart-btn').data('price', matchedVariation.price);
            $('.add-to-cart-btn').data('variation-key', matchedVariation.variation_key);
        }
    }

    // Only set up variation handlers if this is a variable product
    <?php if ($has_variations): ?>
        // Handle attribute changes
        $(document).on('change', '.attribute-select', function() {
            const attr = $(this).data('attr');
            selectedAttributes[attr] = $(this).val();
            $('#selected_' + attr).val(selectedAttributes[attr]);
            const displayId = $(this).data('display-id');
            if (displayId) {
              const selectedText = $(this).find('option:selected').text().replace(' (Out of Stock)', '').trim();
              $('#' + displayId).text(selectedText);
            }
            updateProductDetails();
        });

        $(document).on('click', '.color-swatch:not(.disabled)', function() {
            const attr = $(this).data('attr');
            selectedAttributes[attr] = $(this).data('value');
            $('#selected_' + attr).val(selectedAttributes[attr]);
            $('.color-swatch[data-attr="' + attr + '"]').removeClass('selected');
            $(this).addClass('selected');
            const displayId = $(this).data('display-id');
            const label = $(this).data('label') || $(this).data('value');
            if (displayId) {
              $('#' + displayId).text(label);
            }
            updateProductDetails();
        });
        
        // Initialize attribute selection
        $(document).ready(function() {
            <?php if ($defaultVariation): ?>
                <?php foreach ($defaultVariation['attributes'] as $attrName => $attrData): ?>
                    $('#selected_<?= $attrName ?>').val('<?= $attrData['value'] ?>');
                    $('#display_<?= preg_replace('/[^a-zA-Z0-9_]/', '_', $attrName) ?>').text('<?= htmlspecialchars($attrData['value'], ENT_QUOTES) ?>');
                    <?php if ($attributes[$attrName]['value_type'] === 'color'): ?>
                        $('.color-swatch[data-attr="<?= $attrName ?>"][data-value="<?= $attrData['value'] ?>"]').addClass('selected');
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        });
    <?php endif; ?>

    // Add to Cart button with proper quantity validation


    // Initialize
    $('#mainImg').on('error', function() {
        const firstThumb = $('#thumbs .gallery-thumb img').first().attr('src') || '';
        const fallbackSrc = firstThumb || initialMainImage;
        if (fallbackSrc && this.src !== fallbackSrc) {
            this.src = fallbackSrc;
            if (window.__updateZoomBG) window.__updateZoomBG(fallbackSrc);
        }
    });

    updateProductDetails();
    syncQtyToButtons();
</script>

  <!-- Cart JS -->
  <script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
