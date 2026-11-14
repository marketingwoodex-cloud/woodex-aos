<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include 'lbpanel/config/connection.php';

function ensure_projects_table($conn) {
  $conn->query("CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    category VARCHAR(120) NOT NULL,
    client_name VARCHAR(180) NOT NULL,
    location_city VARCHAR(120) NOT NULL,
    area_sqft VARCHAR(80) NOT NULL,
    short_description TEXT NOT NULL,
    detail_content LONGTEXT NOT NULL,
    cover_image VARCHAR(2048) NOT NULL,
    gallery_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_projects_slug (slug),
    KEY idx_projects_active_sort (is_active, sort_order, id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function project_abs_url($path, $domain) {
  $path = trim((string)$path);
  if ($path === '') return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  return rtrim((string)$domain, '/') . '/' . ltrim($path, '/');
}

function project_plain_text($value, $max = 220) {
  $text = (string)$value;
  $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/', ' ', $text);
  $text = preg_replace('/\[[^\]]+\]\((https?:\/\/[^\)]+)\)/i', ' ', $text);
  $text = strip_tags($text);
  $text = preg_replace('/https?:\/\/\S+/i', ' ', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = trim($text);
  if ($text === '') return '';
  if (mb_strlen($text) <= $max) return $text;
  return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

ensure_projects_table($conn);

$slug = trim((string)($_GET['slug'] ?? ''));
$project = null;
if ($slug !== '') {
  $stmt = $conn->prepare("SELECT * FROM projects WHERE slug = ? AND is_active = 1 LIMIT 1");
  $stmt->bind_param('s', $slug);
  $stmt->execute();
  $project = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if (!$project) {
  http_response_code(404);
}

$related = [];
if ($project) {
  $cat = (string)($project['category'] ?? '');
  $id = (int)($project['id'] ?? 0);
  $stmt = $conn->prepare("SELECT id, title, slug, category, cover_image FROM projects WHERE is_active = 1 AND id <> ? AND category = ? ORDER BY sort_order ASC, id DESC LIMIT 3");
  $stmt->bind_param('is', $id, $cat);
  $stmt->execute();
  $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

$heroFallback = project_abs_url('assets/images/herobg.webp', $domain);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $project ? htmlspecialchars((string)$project['title'], ENT_QUOTES, 'UTF-8') . ' - Project Case Study' : 'Project Not Found'; ?></title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
  <meta name="description" content="<?php echo htmlspecialchars($project ? (string)($project['short_description'] ?? '') : 'Project case study not found.', ENT_QUOTES, 'UTF-8'); ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    .wdx-proj-detail{ background:#fff; padding: 32px 0 56px; }
    .wdx-proj-shell{ display:grid; grid-template-columns: 1fr 320px; gap: 22px; }
    .wdx-proj-content{ border:1px solid #e5e7eb; padding:20px; border-radius:12px; }
    .wdx-proj-content h2, .wdx-proj-content h3{ margin-top: 1.4rem; }
    .wdx-proj-content p{ color:#374151; line-height:1.8; }
    .wdx-proj-content img{ max-width:100%; height:auto; border-radius:10px; }

    .wdx-proj-side{ border:1px solid #e5e7eb; padding:16px; border-radius:12px; height:fit-content; }
    .wdx-proj-side h3{ margin:0 0 10px; font-size:1rem; font-weight:800; }
    .wdx-proj-kv{ margin:0 0 8px; font-size:14px; color:#4b5563; }
    .wdx-proj-kv strong{ color:#111827; }

    .wdx-gallery{ margin-top:20px; display:grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap:10px; }
    .wdx-gallery-item{ grid-column: span 4; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; }
    .wdx-gallery-item img{ width:100%; height:180px; object-fit:cover; display:block; }

    .wdx-related{ margin-top:28px; }
    .wdx-related-grid{ display:grid; grid-template-columns: repeat(12, minmax(0,1fr)); gap:14px; }
    .wdx-related-card{ grid-column: span 4; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; text-decoration:none; color:inherit; background:#fff; }
    .wdx-related-card img{ width:100%; height:160px; object-fit:cover; display:block; }
    .wdx-related-card .meta{ padding:12px; }
    .wdx-related-card h4{ margin:0 0 6px; font-size:1.05rem; font-weight:800; }
    .wdx-related-card p{ margin:0; color:#6b7280; font-size:13px; }

    @media (max-width: 991.98px){
      .wdx-proj-shell{ grid-template-columns: 1fr; }
      .wdx-gallery-item{ grid-column: span 6; }
      .wdx-related-card{ grid-column: span 6; }
    }
    @media (max-width: 575.98px){
      .wdx-gallery-item, .wdx-related-card{ grid-column: span 12; }
    }
  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<?php if (!$project): ?>
  <section class="py-5">
    <div class="container">
      <div class="p-5 bg-white border rounded-4 text-center">
        <h1 class="h4 mb-2">Project not found</h1>
        <p class="text-muted mb-3">The project link may be incorrect or no longer available.</p>
        <a class="btn wdx-btn" href="<?php echo $domain; ?>projects">Back to Projects</a>
      </div>
    </div>
  </section>
  <?php include_once('footer/footer.php'); ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
  </html>
  <?php exit; ?>
<?php endif; ?>

<?php
  $title = (string)($project['title'] ?? 'Project');
  $category = (string)($project['category'] ?? '');
  $client = (string)($project['client_name'] ?? '');
  $city = (string)($project['location_city'] ?? '');
  $sqft = (string)($project['area_sqft'] ?? '');
  $short = project_plain_text((string)($project['short_description'] ?? ''), 220);
  $cover = project_abs_url((string)($project['cover_image'] ?? ''), $domain);
  if ($cover === '') $cover = $heroFallback;
  $detail = (string)($project['detail_content'] ?? '');
  if ($short === '') {
    $short = project_plain_text($detail, 220);
  }
  $detail = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $detail);
  $detail = preg_replace('~\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')~i', '', $detail);
  $detail = preg_replace('~\b(src|href)="/(?!/)~', '$1="' . $domain, $detail);
  $gallery = json_decode((string)($project['gallery_json'] ?? '[]'), true);
  if (!is_array($gallery)) $gallery = [];
?>

<section>
  <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>');">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>
    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-8">
          <div class="wdx-badge"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></div>
          <h1 class="wdx-hero-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="wdx-desc"><?php echo htmlspecialchars($short, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="wdx-proj-detail">
  <div class="container">
    <div class="wdx-proj-shell">
      <article class="wdx-proj-content">
        <?php echo $detail; ?>

        <?php if ($gallery): ?>
          <div class="wdx-gallery">
            <?php foreach ($gallery as $img): ?>
              <?php $g = project_abs_url((string)$img, $domain); ?>
              <div class="wdx-gallery-item"><img src="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <aside class="wdx-proj-side">
        <h3>Project Info</h3>
        <p class="wdx-proj-kv"><strong>Category:</strong> <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="wdx-proj-kv"><strong>Client:</strong> <?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="wdx-proj-kv"><strong>Location:</strong> <?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="wdx-proj-kv"><strong>Area:</strong> <?php echo htmlspecialchars($sqft, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="btn wdx-btn w-100 mt-2" href="<?php echo $domain; ?>projects">All Projects</a>
      </aside>
    </div>

    <?php if ($related): ?>
      <div class="wdx-related">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <h2 class="mb-0">Related Projects</h2>
          <a href="<?php echo $domain; ?>projects?<?php echo htmlspecialchars(http_build_query(['category' => $category]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-sm">View <?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <div class="wdx-related-grid">
          <?php foreach ($related as $r): ?>
            <?php
              $rUrl = $domain . 'projects/' . (string)($r['slug'] ?? '');
              $rImg = project_abs_url((string)($r['cover_image'] ?? ''), $domain);
              if ($rImg === '') $rImg = $heroFallback;
            ?>
            <a class="wdx-related-card" href="<?php echo htmlspecialchars($rUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <img src="<?php echo htmlspecialchars($rImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)($r['title'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?>">
              <div class="meta">
                <h4><?php echo htmlspecialchars((string)($r['title'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars((string)($r['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
