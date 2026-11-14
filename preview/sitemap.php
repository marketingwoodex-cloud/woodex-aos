<?php
// Preview-only sitemap. Never publishes or accesses the original database.
header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
    http_response_code(400);
    exit;
}
$forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
$scheme = $forwarded === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $host;
$paths = ['/', '/shop', '/sale', '/categories', '/services', '/projects', '/blog', '/about-us', '/contact', '/showrooms', '/e-quotation'];
require __DIR__ . '/runtime/app/lbpanel/config/connection.php';
foreach ([['categories', 'slug', 'categories/', ' WHERE is_active = 1'], ['products', 'slug', 'shop/', '']] as [$table, $column, $prefix, $where]) {
    $result = $conn->query("SELECT `$column` FROM `$table`" . $where);
    if (!$result) continue;
    while ($row = $result->fetch_assoc()) {
        $slug = (string)($row[$column] ?? '');
        if (preg_match('/^[A-Za-z0-9-]+$/', $slug)) $paths[] = '/' . $prefix . $slug;
    }
}
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach (array_unique($paths) as $path) {
    echo '<url><loc>' . htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
}
echo "</urlset>\n";
