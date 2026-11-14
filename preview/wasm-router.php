<?php
// Temporary preview router. Not a production security boundary.
// Arena terminates TLS at its proxy; PHP's built-in server sees plain HTTP.
// Normalize only the preview host, so browser-facing URLs don't use mixed content.
$previewHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
if (preg_match('/^[a-z0-9-]+\.e2b\.app$/', $previewHost)) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    $_SERVER['REQUEST_SCHEME'] = 'https';
}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$uri = rawurldecode($uri);
// Serve the homepage's pinned frontend libraries locally rather than depending
// on third-party CDN availability in the preview browser.
if (str_starts_with($uri, '/preview-vendor/')) {
    $vendorRoot = realpath(__DIR__ . '/vendor');
    $asset = realpath($vendorRoot . '/' . substr($uri, strlen('/preview-vendor/')));
    $types = ['css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'png' => 'image/png', 'svg' => 'image/svg+xml'];
    $ext = strtolower(pathinfo($asset ?: '', PATHINFO_EXTENSION));
    if (!$asset || !str_starts_with($asset, $vendorRoot . '/') || !is_file($asset) || !isset($types[$ext])) {
        http_response_code(404); exit('Not found');
    }
    header('Content-Type: ' . $types[$ext]);
    readfile($asset);
    return true;
}
// Keep local URLs relative to the preview's browser origin, including JSON
// responses. This avoids mixed content and proxy-host mismatches.
$host = $_SERVER['HTTP_HOST'] ?? '';
header('Cache-Control: no-store');
ob_start(static function ($body) use ($host, $previewHost) {
    foreach (headers_list() as $header) {
        if (preg_match('~^Content-Type:\s*(?:image/|audio/|video/|text/xml|application/(?:xml|pdf|zip|octet-stream))~i', $header)) return $body;
    }
    $replacements = [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css' => '/preview-vendor/bootstrap/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js' => '/preview-vendor/bootstrap/bootstrap.bundle.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css' => '/preview-vendor/icons/bootstrap-icons.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css' => '/preview-vendor/owl/owl.carousel.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css' => '/preview-vendor/owl/owl.theme.default.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js' => '/preview-vendor/owl/owl.carousel.min.js',
        'https://code.jquery.com/jquery-3.6.0.min.js' => '/preview-vendor/jquery/jquery.min.js',
        'https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg' => '/preview-vendor/icons/whatsapp.svg',
    ];
    if (preg_match('/^[a-z0-9-]+\.e2b\.app$/', $previewHost)) {
        foreach (['http', 'https'] as $scheme) {
            $origin = $scheme . '://' . $host . '/';
            $replacements[$origin] = '/';
            $replacements[str_replace('/', '\\/', $origin)] = '\\/';
        }
    }
    return strtr($body, $replacements);
});
if (str_contains($uri, "\0") || preg_match('~(^|/)\.{1,2}(/|$)|(^|/)\.|/(sessions|database)(/|$)|\.(sql|log|ini|bak|sh|config|md)$|(?:/uploads/|^/assets/).*\.(php\d*|phtml|phar)$~i', $uri)) {
    http_response_code(403); exit('Forbidden');
}
$root = __DIR__ . '/runtime/app';
$static = realpath($root . $uri);
// The archived admin has endpoints without their own authorization checks.
// Require the demo admin session for every admin PHP script except login.
$isAdminPhp = preg_match('~^/lbpanel/~i', $uri) && (preg_match('~\.php$~i', $uri) || is_dir($root . $uri));
$isAdminLogin = in_array(strtolower($uri), ['/lbpanel/', '/lbpanel/index.php'], true);
if ($isAdminPhp && !$isAdminLogin) {
    require_once $root . '/lbpanel/config/session.php';
    wdx_session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /lbpanel/');
        exit;
    }
}
if ($static && str_starts_with($static, $root . '/') && is_file($static)) return false;
if ($uri === '/') { require $root . '/index.php'; return true; }
if (is_dir($root . $uri)) {
    $index = realpath($root . rtrim($uri, '/') . '/index.php');
    if ($index && str_starts_with($index, $root . '/')) { require $index; return true; }
}
$routes = ['shop'=>'shop.php','sale'=>'sale.php','projects'=>'projects.php','e-quotation'=>'equotation.php','requested-quote'=>'quotation-basket.php','quotation-success'=>'quotation-success.php','material-and-color'=>'material.php','terms-and-conditions'=>'terms.php','blog'=>'all-blogs.php','categories'=>'servicelist.php','services'=>'servicelist.php','contact-us'=>'contact.php','contact'=>'contact.php','checkout'=>'checkout.php','about-us'=>'about.php','privacy-policy'=>'privacypolicy.php','faqs'=>'faqs.php','shipping-and-returns'=>'shipping.php','warranty'=>'warranty.php','showrooms'=>'showrooms.php','b2b'=>'b2b.php','factory-direct'=>'factorydirect.php','delivery-installation'=>'deliveryandinstallation.php','project-based'=>'projectbased.php','space-planning'=>'space_planing.php','cart'=>'shopppingcart.php','thanks-for-the-review'=>'review_thanks.php','search'=>'search_results.php'];
$key = trim($uri, '/');
// Legacy footer links reference slugs absent from the supplied catalog. Map
// them to the closest existing catalog/landing page, without changing live data.
$legacyRoutes = [
    'categories/ergonomic-chair' => '/categories/office-chairs',
    'categories/executive-desks' => '/categories/executive-table',
    'categories/workstations' => '/categories/workstation',
    'categories/reception' => '/categories/reception-desk',
    'categories/office-sofa-storage' => '/categories',
    'planning-ideas' => '/space-planning',
    'workspace-design' => '/project-based',
];
if (isset($legacyRoutes[$key])) { header('Location: ' . $legacyRoutes[$key], true, 302); return true; }
if ($key === 'sitemap.xml') { require __DIR__ . '/sitemap.php'; return true; }
if (isset($routes[$key])) { require $root . '/' . $routes[$key]; return true; }
if (preg_match('~^(categories|blog|projects|shop|thank-you|review)/([a-zA-Z0-9-]+)$~', $key, $m)) {
    $dynamic = ['categories'=>['servicedetail.php','cat_url'], 'blog'=>['blog-single.php','slug'], 'projects'=>['project-detail.php','slug'], 'shop'=>['singleproduct.php','proslug'], 'thank-you'=>['order_success.php','order_number'], 'review'=>['review.php','order']];
    [$script,$parameter] = $dynamic[$m[1]]; $_GET[$parameter] = $m[2]; require $root . '/' . $script; return true;
}
http_response_code(404); exit('Not found');
