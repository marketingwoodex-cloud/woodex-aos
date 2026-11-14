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

function project_excerpt($shortDescription, $detailContent, $max = 130) {
  $text = trim((string)$shortDescription);
  if ($text === '' || strpos($text, '![', 0) !== false) {
    $text = (string)$detailContent;
  }

  $text = preg_replace('/!\[[^\]]*\]\([^\)]*\)/', ' ', $text);
  $text = strip_tags($text);
  $text = preg_replace('/https?:\/\/\S+/i', ' ', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = trim($text);

  if ($text === '') {
    return '';
  }
  if (mb_strlen($text) <= $max) {
    return $text;
  }
  return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

ensure_projects_table($conn);

$activeCategory = trim((string)($_GET['category'] ?? 'all'));

$projectCategories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM projects WHERE is_active = 1 AND category <> '' ORDER BY category ASC");
if ($catRes) {
  while ($r = $catRes->fetch_assoc()) {
    $projectCategories[] = (string)$r['category'];
  }
}

if ($activeCategory !== 'all') {
  $stmt = $conn->prepare("SELECT id, title, slug, category, client_name, location_city, area_sqft, short_description, detail_content, cover_image FROM projects WHERE is_active = 1 AND category = ? ORDER BY sort_order ASC, id DESC");
  $stmt->bind_param('s', $activeCategory);
} else {
  $stmt = $conn->prepare("SELECT id, title, slug, category, client_name, location_city, area_sqft, short_description, detail_content, cover_image FROM projects WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
}
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$heroBg = project_abs_url('assets/images/herobg.webp', $domain);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Projects - Woodex Furniture</title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
  <meta name="description" content="Explore Woodex projects across corporate, healthcare, hospitality, education, and government sectors.">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    .wdx-projects-wrap{ background:#f3f4f6; padding:24px 0 54px; }
    .wdx-proj-filters{ display:flex; flex-wrap:wrap; gap:10px; margin: 0 0 16px; }
    .wdx-proj-chip{
      display:inline-flex; align-items:center; justify-content:center;
      padding:6px 14px; border-radius:5px; border:1px solid #d9dde3;
      text-decoration:none; color:#6b7280; background:#fff; font-weight:600; font-size:12px;
    }
    .wdx-proj-chip.active{ background:#78be20; border-color:#78be20; color:#fff; }
    .wdx-proj-count{ color:#6b7280; font-size:13px; margin:0 0 14px; }

    .wdx-proj-grid{ display:grid; grid-template-columns: repeat(12, minmax(0,1fr)); gap:16px; }
    .wdx-proj-card{
      grid-column: span 4;
      background:#fff; border:1px solid #e1e5ea; border-radius:0; overflow:hidden;
      text-decoration:none; color:inherit;
      transition: border-color .15s ease;
    }
    .wdx-proj-card:hover{ border-color:#d2d8df; }
    .wdx-proj-img-wrap{ display:block; position:relative; height:190px; background:#eef1f4; }
    .wdx-proj-img-wrap img{ width:100%; height:100%; object-fit:cover; display:block; }
    .wdx-proj-tag{
      position:absolute; left:10px; top:10px; background:#67b325; color:#fff;
      font-size:10px; font-weight:700; padding:3px 8px; border-radius:5px;
    }
    .wdx-proj-body{ display:block; background:#fff; padding:14px 14px 13px; }
    .wdx-proj-title{ font-size:1.05rem; line-height:1.3; margin:0 0 8px; font-weight:800; color:#111827; }
    .wdx-proj-client{ margin:0 0 2px; color:#67b325; font-weight:700; font-size:13px; line-height:1.3; }
    .wdx-proj-meta{ margin:0 0 10px; color:#6b7280; font-size:12px; line-height:1.3; }
    .wdx-proj-desc{ margin:0 0 12px; color:#4b5563; font-size:13px; line-height:1.6; min-height:64px; }
    .wdx-proj-link{ color:#67b325; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
    .wdx-proj-link i{ color:#67b325; font-size:12px; }

    @media (max-width: 991.98px){
      .wdx-proj-card{ grid-column: span 6; }
      .wdx-proj-img-wrap{ height:200px; }
    }
    @media (max-width: 575.98px){
      .wdx-proj-card{ grid-column: span 12; }
      .wdx-proj-title{ font-size:1.05rem; }
      .wdx-proj-img-wrap{ height:190px; }
    }
  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<section>
  <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo htmlspecialchars($heroBg, ENT_QUOTES); ?>');">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>
    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-7 col-xl-6">
          <div class="wdx-badge">Our Work</div>
          <h1 class="wdx-hero-title">Featured <span>Projects</span></h1>
          <p class="wdx-desc">Discover real case studies from corporate offices, hospitals, libraries, hotels, and institutions delivered by Woodex across Pakistan.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="wdx-projects-wrap">
  <div class="container">
    <div class="wdx-proj-filters">
      <a class="wdx-proj-chip <?= $activeCategory === 'all' ? 'active' : '' ?>" href="<?php echo $domain; ?>projects">All</a>
      <?php foreach ($projectCategories as $cat): ?>
        <a class="wdx-proj-chip <?= $activeCategory === $cat ? 'active' : '' ?>" href="<?php echo $domain; ?>projects?<?php echo htmlspecialchars(http_build_query(['category' => $cat]), ENT_QUOTES); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    </div>

    <p class="wdx-proj-count"><?php echo (int)count($projects); ?> projects found</p>

    <div class="wdx-proj-grid">
      <?php if (!$projects): ?>
        <div class="wdx-proj-card" style="grid-column: span 12;">
          <div class="wdx-proj-body">
            <h3 class="wdx-proj-title">No projects found</h3>
            <p class="wdx-proj-desc">Try another category filter.</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($projects as $p): ?>
          <?php
            $title = (string)($p['title'] ?? 'Project');
            $slug = (string)($p['slug'] ?? '');
            $url = $domain . 'projects/' . $slug;
            $img = project_abs_url((string)($p['cover_image'] ?? 'assets/images/herobg.webp'), $domain);
            $category = (string)($p['category'] ?? 'Category');
            $client = (string)($p['client_name'] ?? '');
            $city = (string)($p['location_city'] ?? '');
            $sqft = (string)($p['area_sqft'] ?? '');
            $desc = project_excerpt((string)($p['short_description'] ?? ''), (string)($p['detail_content'] ?? ''));
          ?>
          <article class="wdx-proj-card">
            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="wdx-proj-img-wrap">
              <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
              <span class="wdx-proj-tag"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <div class="wdx-proj-body">
              <h2 class="wdx-proj-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
              <p class="wdx-proj-client"><?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="wdx-proj-meta"><?php echo htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?><?php echo $sqft !== '' ? ' • ' . htmlspecialchars($sqft, ENT_QUOTES, 'UTF-8') : ''; ?></p>
              <p class="wdx-proj-desc"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>
              <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="wdx-proj-link">View Case Study <i class="bi bi-arrow-right"></i></a>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
