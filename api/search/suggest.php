<?php
// /api/search/suggest.php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once "../../lbpanel/config/connection.php";
require_once __DIR__ . '/../../lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

/* helpers */
function abs_url($path){
  if(!$path) return '';
  if(preg_match('~^https?://~i',$path)) return $path;
  if (!empty($GLOBALS['domain'])) return rtrim($GLOBALS['domain'], '/') . '/' . ltrim($path, '/');
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme.'://'.$host.'/'.ltrim($path,'/');
}
function db_name($conn){
  $res=$conn->query("SELECT DATABASE() AS db"); $row=$res?$res->fetch_assoc():null; return $row?$row['db']:'';
}
function table_exists($conn,$db,$table){
  if(!$db||!$table) return false;
  $stmt=$conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1");
  $stmt->bind_param("ss",$db,$table); $stmt->execute(); $stmt->store_result();
  $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
}
function column_exists($conn,$db,$table,$col){
  if(!$db||!$table||!$col) return false;
  $stmt=$conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=? LIMIT 1");
  $stmt->bind_param("sss",$db,$table,$col); $stmt->execute(); $stmt->store_result();
  $ok=$stmt->num_rows>0; $stmt->close(); return $ok;
}

/* input */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '' || mb_strlen($q) < 2) {
  ob_end_clean(); echo json_encode(['terms'=>[], 'products'=>[]]); exit;
}
$like='%'.$q.'%'; $likeStart=$q.'%';

/* schema checks */
$DB = db_name($conn);
$hasBrands       = table_exists($conn,$DB,'brands')      && column_exists($conn,$DB,'brands','brand_name');
$hasCategories   = table_exists($conn,$DB,'categories')  && column_exists($conn,$DB,'categories','category_name');
$hasProdCatId    = column_exists($conn,$DB,'products','category_id');
$hasProdCatMap   = table_exists($conn,$DB,'product_categories');
$hasVariations   = table_exists($conn,$DB,'product_variations'); // for price fallback

/* PRODUCTS (right column) */
$joins  = '';
$wheres = [
  'p.product_name LIKE ?',
  'p.product_sku LIKE ?',
  'p.short_description LIKE ?',
  'p.product_details LIKE ?'
];
if ($hasBrands) { $joins .= ' LEFT JOIN brands b ON b.id = p.brand_id '; $wheres[]='b.brand_name LIKE ?'; }
if ($hasCategories && $hasProdCatId) { $joins .= ' LEFT JOIN categories c1 ON c1.id = p.category_id '; $wheres[]='c1.category_name LIKE ?'; }
if ($hasCategories && $hasProdCatMap) {
  $joins .= ' LEFT JOIN product_categories pc ON pc.product_id = p.id
              LEFT JOIN categories c2 ON c2.id = pc.category_id ';
  $wheres[]='c2.category_name LIKE ?';
}

/* Effective price: current_price if > 0, else MIN variation price */
$effPriceExpr = $hasVariations
  ? "COALESCE(NULLIF(p.current_price,0),
              (SELECT MIN(v.price) FROM product_variations v WHERE v.product_id = p.id))"
  : "p.current_price";

$sqlProducts = "
  SELECT DISTINCT
    p.id, p.product_name, p.slug, p.images,
    $effPriceExpr AS eff_price
    ".($hasBrands ? ", b.brand_name" : "")."
  FROM products p
  $joins
  WHERE (".implode(' OR ',$wheres).")
  ORDER BY
    (p.product_name LIKE ?) DESC,
    (p.product_name LIKE ?) DESC
    ".($hasBrands ? ", (b.brand_name LIKE ?) DESC" : "").",
    p.id DESC
  LIMIT 5
";

$types = 'ssss'; $params = [$like,$like,$like,$like];
if ($hasBrands){ $types.='s'; $params[]=$like; }
if ($hasCategories && $hasProdCatId){ $types.='s'; $params[]=$like; }
if ($hasCategories && $hasProdCatMap){ $types.='s'; $params[]=$like; }
$types.='ss'; $params[]=$likeStart; $params[]=$like;
if ($hasBrands){ $types.='s'; $params[]=$like; }

$products=[];
if ($stmt=$conn->prepare($sqlProducts)) {
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res=$stmt->get_result();
  while($row=$res->fetch_assoc()){
    $images=json_decode($row['images'] ?? '[]', true);
    $img=is_array($images)&&!empty($images) ? abs_url($images[0]) : abs_url('assets/images/placeholder.png');
    $brand = ($hasBrands && !empty($row['brand_name'])) ? $row['brand_name'] : '';
    $price = isset($row['eff_price']) ? (float)$row['eff_price'] : 0.0;

    $products[]=[
      'label'=>htmlspecialchars($row['product_name'],ENT_QUOTES),
      'brand'=>htmlspecialchars($brand,ENT_QUOTES),
      'price'=>$price,
      'image'=>$img,
      'url'=>abs_url('shop/'.$row['slug']),
    ];
  }
  $stmt->close();
}

/* TERM SUGGESTIONS (left column) — unchanged from previous version */
$terms=[]; $dedup=[];

/* Brands */
if ($hasBrands){
  $sql="SELECT b.brand_name AS label, COUNT(*) AS cnt
        FROM products p JOIN brands b ON b.id=p.brand_id
        WHERE b.brand_name LIKE ? GROUP BY b.id ORDER BY cnt DESC LIMIT 6";
  if($st=$conn->prepare($sql)){
    $st->bind_param("s",$like); $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()){
      $label=trim($row['label']);
      $terms[]=['label'=>htmlspecialchars($label,ENT_QUOTES),'count'=>(int)$row['cnt'],'url'=>abs_url('search?query='.rawurlencode($label))];
    }
    $st->close();
  }
}

/* Categories (direct id) */
if ($hasCategories && $hasProdCatId){
  $sql="SELECT c.category_name AS label, c.slug, COUNT(DISTINCT p.id) AS cnt
        FROM categories c JOIN products p ON p.category_id=c.id
        WHERE c.category_name LIKE ? GROUP BY c.id ORDER BY cnt DESC LIMIT 6";
  if($st=$conn->prepare($sql)){
    $st->bind_param("s",$like); $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()){
      $label=trim($row['label']);
      $terms[]=['label'=>htmlspecialchars($label,ENT_QUOTES),'count'=>(int)$row['cnt'],'url'=>abs_url('categories/'.$row['slug'])];
    }
    $st->close();
  }
}

/* Categories (mapping) */
if ($hasCategories && $hasProdCatMap){
  $sql="SELECT c.category_name AS label, c.slug, COUNT(DISTINCT p.id) AS cnt
        FROM categories c JOIN product_categories pc ON pc.category_id=c.id
        JOIN products p ON p.id=pc.product_id
        WHERE c.category_name LIKE ? GROUP BY c.id ORDER BY cnt DESC LIMIT 6";
  if($st=$conn->prepare($sql)){
    $st->bind_param("s",$like); $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()){
      $label=trim($row['label']);
      $terms[]=['label'=>htmlspecialchars($label,ENT_QUOTES),'count'=>(int)$row['cnt'],'url'=>abs_url('categories/'.$row['slug'])];
    }
    $st->close();
  }
}

/* de-dup + cap */
$finalTerms=[];
foreach($terms as $t){ $k=mb_strtolower($t['label']); if(!isset($dedup[$k])){ $dedup[$k]=1; $finalTerms[]=$t; } }
usort($finalTerms,function($a,$b){ return $b['count'] <=> $a['count']; });
$finalTerms=array_slice($finalTerms,0,9);

/* total count for “See all …” */
$totalCount=0;
if($st=$conn->prepare("SELECT COUNT(*) AS c FROM products p WHERE p.product_name LIKE ? OR p.product_sku LIKE ? OR p.short_description LIKE ? OR p.product_details LIKE ?")){
  $st->bind_param("ssss",$like,$like,$like,$like); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $totalCount=(int)($row['c']??0); $st->close();
}

ob_end_clean();
echo json_encode(['query'=>$q,'counts'=>['products'=>$totalCount],'terms'=>$finalTerms,'products'=>$products], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
exit;
