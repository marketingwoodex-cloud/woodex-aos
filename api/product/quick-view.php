<?php
header('Content-Type: application/json; charset=UTF-8');
include __DIR__ . '/../../lbpanel/config/connection.php';
require_once __DIR__ . '/../../lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed']);
    exit;
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing product slug']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$root = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/\\') . '/';
$base = $scheme . '://' . $host . ($root === '/' ? '/' : $root);

$sql = "
SELECT
  p.id,
  p.product_name,
  p.slug,
  p.short_description,
  p.product_details,
  p.old_price,
  p.current_price,
  p.images,
  p.rating_count,
  p.rating_sum,
  COALESCE((
    SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ')
    FROM product_categories pc2
    JOIN categories c2 ON c2.id = pc2.category_id
    WHERE pc2.product_id = p.id
  ), c.category_name) AS category_name,
  COUNT(v.id) AS variation_count,
  MIN(v.price) AS min_price,
  MAX(v.price) AS max_price
FROM products p
LEFT JOIN categories c ON c.id = p.category
LEFT JOIN product_variations v ON v.product_id = p.id
WHERE p.slug = ?
GROUP BY p.id, p.product_name, p.slug, p.short_description, p.product_details, p.old_price, p.current_price, p.images, p.rating_count, p.rating_sum, c.category_name
LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'DB prepare failed']);
    exit;
}

$stmt->bind_param('s', $slug);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Product not found']);
    exit;
}

$images = json_decode((string)($product['images'] ?? '[]'), true);
$first = (is_array($images) && !empty($images)) ? (string)$images[0] : 'assets/images/placeholder.png';
if (!preg_match('~^https?://~i', $first)) {
    $first = rtrim($base, '/') . '/' . ltrim($first, '/');
}

$oldPrice = (float)($product['old_price'] ?? 0);
$current = (float)($product['current_price'] ?? 0);
$minVar = isset($product['min_price']) ? (float)$product['min_price'] : null;
$maxVar = isset($product['max_price']) ? (float)$product['max_price'] : null;
$hasVars = ((int)($product['variation_count'] ?? 0)) > 0;

$effective = $hasVars && $minVar !== null ? $minVar : $current;

if ($hasVars && $minVar !== null && $maxVar !== null && $maxVar > $minVar) {
    $priceText = 'PKR ' . number_format($minVar, 0) . ' – ' . number_format($maxVar, 0);
} else {
    $priceText = 'PKR ' . number_format($effective, 0);
}

$discount = ($oldPrice > 0 && $oldPrice > $effective) ? round((($oldPrice - $effective) / $oldPrice) * 100) : 0;
$desc = trim((string)($product['short_description'] ?? ''));
if ($desc === '') {
    $desc = trim(strip_tags((string)($product['product_details'] ?? '')));
}

echo json_encode([
    'status' => true,
    'product' => [
        'id' => (int)$product['id'],
        'name' => (string)$product['product_name'],
        'slug' => (string)$product['slug'],
        'category' => (string)($product['category_name'] ?? ''),
        'image' => $first,
        'description' => $desc,
        'price_text' => $priceText,
        'old_price' => $oldPrice,
        'effective_price' => $effective,
        'discount' => $discount,
        'url' => rtrim($base, '/') . '/shop/' . (string)$product['slug'],
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
