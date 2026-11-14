<?php
// filters_meta.php
header('Content-Type: application/json');
require_once "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

$category = isset($_POST['category']) ? $_POST['category'] : '';
$search   = isset($_POST['search'])   ? $_POST['search']   : '';

$whereClauses = [];
$bindTypes = '';
$bindValues = [];

// Restrict by category
/* --- CATEGORY (self + descendants via categories.path) --- */
$categoryIds = [];
if (!empty($category) && (int)$category > 0) {
    if ($st = $conn->prepare("SELECT path FROM categories WHERE id = ? LIMIT 1")) {
        $catId = (int)$category;
        $st->bind_param('i', $catId);
        $st->execute();
        $st->bind_result($catPath);
        $st->fetch();
        $st->close();

        if ($catPath !== null && $catPath !== '') {
            $categoryIds[] = $catId;
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


// Restrict by product name search
if ($search !== '') {
    $whereClauses[] = "p.product_name LIKE ?";
    $bindTypes .= 's';
    $bindValues[] = '%'.$search.'%';
}

$whereSQL = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// ---------- MIN/MAX PRICE (variation-aware; uses v.price) ----------
$min_price = 0.0;
$max_price = 0.0;

try {
    // Prefer in-stock variations for bounds
    $priceSQL = "
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
        $whereSQL
    ";
    $stmt = $conn->prepare($priceSQL);
    if ($bindTypes) { $stmt->bind_param($bindTypes, ...$bindValues); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $min_price = $row['min_price'] !== null ? (float)$row['min_price'] : 0.0;
        $max_price = $row['max_price'] !== null ? (float)$row['max_price'] : 0.0;
    }
    $stmt->close();

    // If no in-stock variations were found (both 0) but products exist, fallback to any variations
    if (($min_price == 0.0 && $max_price == 0.0)) {
        $priceSQL2 = "
            SELECT 
              MIN(COALESCE(vagg.min_price, p.current_price)) AS min_price,
              MAX(COALESCE(vagg.max_price, p.current_price)) AS max_price
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(price) AS min_price, MAX(price) AS max_price
                FROM product_variations
                GROUP BY product_id
            ) vagg ON vagg.product_id = p.id
            $whereSQL
        ";
        $stmt2 = $conn->prepare($priceSQL2);
        if ($bindTypes) { $stmt2->bind_param($bindTypes, ...$bindValues); }
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2 && ($row2 = $res2->fetch_assoc())) {
            $min_price = $row2['min_price'] !== null ? (float)$row2['min_price'] : 0.0;
            $max_price = $row2['max_price'] !== null ? (float)$row2['max_price'] : 0.0;
        }
        $stmt2->close();
    }
} catch (Throwable $e) {
    // Leave defaults if anything goes wrong
    $min_price = $min_price ?: 0.0;
    $max_price = $max_price ?: 0.0;
}

// ---------- COLORS & SIZES from variation_data ----------
$COLOR_KEYS = ['colour','color','colors']; // case-insensitive
$SIZE_KEYS  = ['size','sizes','waist','length']; // extend if needed

$colors = [];
$sizes  = [];

function add_unique(&$arr, $val) {
    $val = trim((string)$val);
    if ($val === '') return;
    if (!in_array($val, $arr, true)) $arr[] = $val;
}

function norm_color_label($label, $value) {
    // Prefer human label (e.g., 'Red'); fallback to hex value (e.g., #FF0000)
    $label = trim((string)$label);
    $value = trim((string)$value);
    if ($label !== '') {
        // Title Case the label
        $lc = mb_strtolower($label, 'UTF-8');
        return mb_convert_case($lc, MB_CASE_TITLE, 'UTF-8');
    }
    if ($value !== '') {
        $hex = strtoupper(ltrim($value, '#'));
        if (preg_match('/^[0-9A-F]{3,8}$/', $hex)) return '#'.$hex;
    }
    return '';
}

function norm_size_label($label, $value) {
    $candidate = trim((string)($label !== '' ? $label : $value));
    if ($candidate === '') return '';
    // Canonicalize common apparel sizes
    $map = [
        'xxs'=>'XXS','xs'=>'XS','s'=>'S','m'=>'M','l'=>'L','xl'=>'XL','xxl'=>'XXL','xxxl'=>'XXXL',
        'extra small'=>'XS','small'=>'S','medium'=>'M','large'=>'L','extra large'=>'XL'
    ];
    $lower = mb_strtolower($candidate, 'UTF-8');
    if (isset($map[$lower])) return $map[$lower];
    // Otherwise, keep as-is with single spaces
    return preg_replace('/\s+/', ' ', $candidate);
}

try {
    // Pull variations for matching products (prefer in-stock)
    $varSQL = "
        SELECT v.variation_data, v.stock
        FROM product_variations v
        JOIN products p ON p.id = v.product_id
        $whereSQL
        AND (v.stock IS NULL OR v.stock > 0)
    ";
    $ps = $conn->prepare($varSQL);
    if ($bindTypes) { $ps->bind_param($bindTypes, ...$bindValues); }
    $ps->execute();
    $vres = $ps->get_result();

    // If none in-stock, fallback to all
    if (!$vres || $vres->num_rows === 0) {
        $ps->close();
        $varSQL = "
            SELECT v.variation_data, v.stock
            FROM product_variations v
            JOIN products p ON p.id = v.product_id
            $whereSQL
        ";
        $ps = $conn->prepare($varSQL);
        if ($bindTypes) { $ps->bind_param($bindTypes, ...$bindValues); }
        $ps->execute();
        $vres = $ps->get_result();
    }

    while ($row = $vres->fetch_assoc()) {
        $vd = $row['variation_data'];
        if (!$vd) continue;

        $decoded = json_decode($vd, true);
        if (!is_array($decoded)) continue;

        // The data looks like: {"Colour":{"value":"#ff0000","label":"Red","type":...}, ...}
        foreach ($decoded as $key => $value) {
            $k = mb_strtolower((string)$key, 'UTF-8');

            // Value might be object/array with fields like label/value
            if (in_array($k, $COLOR_KEYS, true)) {
                if (is_array($value)) {
                    // single color object or list of objects
                    if (isset($value['label']) || isset($value['value'])) {
                        $c = norm_color_label($value['label'] ?? '', $value['value'] ?? '');
                        add_unique($colors, $c);
                    } else {
                        // array of objects/values
                        foreach ($value as $v) {
                            if (is_array($v)) {
                                $c = norm_color_label($v['label'] ?? '', $v['value'] ?? '');
                                add_unique($colors, $c);
                            } else {
                                add_unique($colors, (string)$v);
                            }
                        }
                    }
                } else {
                    add_unique($colors, (string)$value);
                }
            }

            if (in_array($k, $SIZE_KEYS, true)) {
                if (is_array($value)) {
                    if (isset($value['label']) || isset($value['value'])) {
                        $s = norm_size_label($value['label'] ?? '', $value['value'] ?? '');
                        add_unique($sizes, $s);
                    } else {
                        foreach ($value as $v) {
                            if (is_array($v)) {
                                $s = norm_size_label($v['label'] ?? '', $v['value'] ?? '');
                                add_unique($sizes, $s);
                            } else {
                                add_unique($sizes, (string)$v);
                            }
                        }
                    }
                } else {
                    add_unique($sizes, (string)$value);
                }
            }
        }
    }
    $ps->close();
} catch (Throwable $e) {
    // ignore parsing errors; arrays will remain as collected so far
}

// Sort nicely
// Size ordering
$sizeOrder = ['XXS'=>0,'XS'=>1,'S'=>2,'M'=>3,'L'=>4,'XL'=>5,'XXL'=>6,'XXXL'=>7];
usort($sizes, function($a,$b) use($sizeOrder){
    $ia = $sizeOrder[$a] ?? 999;
    $ib = $sizeOrder[$b] ?? 999;
    if ($ia === $ib) return strnatcasecmp($a,$b);
    return $ia <=> $ib;
});
natcasesort($colors);
$colors = array_values($colors);
$sizes  = array_values($sizes);

// Output
echo json_encode([
    'min_price' => $min_price,
    'max_price' => $max_price,
    'sizes'     => $sizes,
    'colors'    => $colors
]);
