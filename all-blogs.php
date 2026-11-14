<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
require_once "lbpanel/config/connection.php";

function abs_url($path) {
  if (!$path) return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  return rtrim($GLOBALS['domain'] ?? '', '/') . '/' . ltrim($path, '/');
}
function excerpt_text($html, $max = 160) {
  $t = trim(preg_replace('/\s+/', ' ', strip_tags((string)$html)));
  if ($t === '') return '';
  if (mb_strlen($t) <= $max) return $t;
  return rtrim(mb_substr($t, 0, $max - 1)) . '…';
}
function reading_time_min($html) {
  $words = str_word_count(strip_tags((string)$html));
  return max(1, (int)ceil($words / 200));
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Count
if ($q !== '') {
  $like = '%' . $q . '%';
  $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM blogs WHERE blog_title LIKE ? OR meta_description LIKE ? OR blog_content LIKE ?");
  $stmt->bind_param('sss', $like, $like, $like);
  $stmt->execute();
  $total = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
  $stmt->close();
} else {
  $res = $conn->query("SELECT COUNT(*) AS total FROM blogs");
  $total = (int)($res ? ($res->fetch_assoc()['total'] ?? 0) : 0);
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch
if ($q !== '') {
  $like = '%' . $q . '%';
  $stmt = $conn->prepare("SELECT id, blog_title, slug, meta_description, blog_image, blog_content, created_at FROM blogs WHERE blog_title LIKE ? OR meta_description LIKE ? OR blog_content LIKE ? ORDER BY created_at DESC, id DESC LIMIT ?, ?");
  $stmt->bind_param('sssii', $like, $like, $like, $offset, $perPage);
} else {
  $stmt = $conn->prepare("SELECT id, blog_title, slug, meta_description, blog_image, blog_content, created_at FROM blogs ORDER BY created_at DESC, id DESC LIMIT ?, ?");
  $stmt->bind_param('ii', $offset, $perPage);
}
$stmt->execute();
$blogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$heroBg = abs_url('assets/images/herobg.webp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog - Woodex Furniture</title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">
  <link rel="canonical" href="<?php echo htmlspecialchars($domain . 'blog', ENT_QUOTES); ?>">
  <meta name="description" content="Insights, guides, and workspace ideas from Woodex Furniture.">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">

  <style>
    .wdx-blog-wrap{ padding: 34px 0 60px; background:#fff; }
    .wdx-featured{
      border:1px solid rgba(0,0,0,.08);
      border-radius:18px;
      overflow:hidden;
      background:#fff;
      box-shadow:0 10px 28px rgba(0,0,0,.06);
      margin-bottom:20px;
    }
    .wdx-featured-media{ background:#eef2f6; height:100%; min-height:320px; }
    .wdx-featured-media img{ width:100%; height:100%; object-fit:cover; display:block; }
    .wdx-featured-body{ padding:26px 26px 22px; }
    .wdx-chip{
      display:inline-block;
      background:#5f9d2f;
      color:#fff;
      border-radius:999px;
      font-size:12px;
      font-weight:800;
      padding:4px 10px;
      margin-bottom:10px;
    }
    .wdx-featured-title{
      font-size:30px;
      line-height:1.15;
      letter-spacing:-.01em;
      font-weight:600;
      color:#121826;
      margin:0 0 10px;
    }
    .wdx-featured-excerpt{
      color:#5f6b75;
      font-size:1.05rem;
      line-height:1.7;
      margin:0 0 12px;
    }
    .wdx-featured-meta{ display:flex; flex-wrap:wrap; gap:14px; color:#6b7280; font-size:14px; margin-bottom:14px; }
    .wdx-featured-meta i{ margin-right:5px; }
    .wdx-featured-btn{
      border-radius:0;
      font-weight:800;
      padding:.65rem 1rem;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }

    .wdx-blog-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 18px; }
    .wdx-list-head{
      font-size:38px;
      font-weight:700;
      color:#101828;
      margin:8px 0 16px;
      letter-spacing:-.01em;
    }
    .wdx-blog-card{
      grid-column: span 4;
      background:#fff;
      border:1px solid rgba(0,0,0,.08);
      border-radius: 0;
      overflow:hidden;
      box-shadow:none;
      transition: transform .18s ease;
    }
    .wdx-blog-card:hover{ transform: translateY(-2px); }
    .wdx-blog-thumb{ aspect-ratio: 16/9; width:100%; background:#f2f4f7; }
    .wdx-blog-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .wdx-blog-body{ padding: 14px 14px 16px; }
    .wdx-blog-tag{
      display:inline-block;
      border:1px solid #97c66c;
      color:#5f9d2f;
      border-radius:999px;
      font-size:12px;
      line-height:1;
      font-weight:700;
      padding:4px 10px;
      margin-bottom:10px;
    }
    .wdx-blog-title{ font-weight: 600; letter-spacing: -.01em; margin: 0 0 8px; font-size: 1.1rem; line-height:1.18; color:#111827; }
    .wdx-blog-excerpt{ color:#4b5563; margin:0 0 12px; font-size: 1.03rem; line-height: 1.6; min-height: 66px; }
    .wdx-blog-foot{ display:flex; align-items:center; justify-content:space-between; gap:10px; color:#6b7280; font-size: 13px; }
    .wdx-blog-foot i{ margin-right:5px; }
    @media (max-width: 991.98px){
      .wdx-featured-media{ min-height:240px; }
      .wdx-featured-body{ padding:18px; }
      .wdx-blog-card{ grid-column: span 6; }
    }
    @media (max-width: 575.98px){
      .wdx-list-head{ font-size:30px; }
      .wdx-blog-card{ grid-column: span 12; }
      .wdx-blog-title{ font-size:1.5rem; }
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
          <div class="wdx-badge">Insights & Guides</div>
          <h1 class="wdx-hero-title">Woodex <span>Blog</span></h1>
          <p class="wdx-desc">Workspace ideas, buying guides, and practical tips for planning modern offices in Pakistan.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="wdx-blog-wrap">
  <div class="container">
    <div class="mt-4">
      <?php if (!$blogs): ?>
        <div class="p-5 bg-white border rounded-4 text-center">
          <h5 class="mb-1">No articles found</h5>
          <div class="text-muted">Try a different search.</div>
        </div>
      <?php else: ?>
        <?php
          $featured = $blogs[0];
          $fTitle = htmlspecialchars($featured['blog_title'] ?? '', ENT_QUOTES);
          $fImgPath = $featured['blog_image'] ?? '';
          $fImg = $fImgPath ? abs_url($fImgPath) : abs_url('assets/images/herobg.webp');
          $fDesc = $featured['meta_description'] ?? '';
          if (!$fDesc) $fDesc = excerpt_text($featured['blog_content'] ?? '', 220);
          $fDateStr = !empty($featured['created_at']) ? date('M j, Y', strtotime($featured['created_at'])) : '';
          $fMins = reading_time_min($featured['blog_content'] ?? '');
          $fUrl = $domain . 'blog/' . ($featured['slug'] ?? '');
        ?>

        <article class="wdx-featured">
          <div class="row g-0 align-items-stretch">
            <div class="col-lg-6">
              <a href="<?php echo htmlspecialchars($fUrl, ENT_QUOTES); ?>" class="d-block h-100 wdx-featured-media">
                <img src="<?php echo htmlspecialchars($fImg, ENT_QUOTES); ?>" alt="<?php echo $fTitle; ?>" loading="lazy">
              </a>
            </div>
            <div class="col-lg-6">
              <div class="wdx-featured-body">
                <span class="wdx-chip">Latest</span>
                <h2 class="wdx-featured-title"><?php echo $fTitle; ?></h2>
                <p class="wdx-featured-excerpt"><?php echo htmlspecialchars(excerpt_text($fDesc, 240), ENT_QUOTES); ?></p>
                <div class="wdx-featured-meta">
                  <span><i class="bi bi-person"></i>Woodex Editorial</span>
                  <?php if ($fDateStr): ?><span><i class="bi bi-calendar3"></i><?php echo htmlspecialchars($fDateStr, ENT_QUOTES); ?></span><?php endif; ?>
                  <span><i class="bi bi-clock"></i><?php echo (int)$fMins; ?> min read</span>
                </div>
                <a class="btn wdx-btn wdx-featured-btn" href="<?php echo htmlspecialchars($fUrl, ENT_QUOTES); ?>">Read Article <i class="bi bi-arrow-right"></i></a>
              </div>
            </div>
          </div>
        </article>

        <h3 class="wdx-list-head">Latest Articles</h3>
        <div class="wdx-blog-grid">
          <?php foreach (array_slice($blogs, 1) as $b): ?>
            <?php
              $title = htmlspecialchars($b['blog_title'] ?? '', ENT_QUOTES);
              $slug  = htmlspecialchars($b['slug'] ?? '', ENT_QUOTES);
              $imgPath = $b['blog_image'] ?? '';
              $img = $imgPath ? abs_url($imgPath) : abs_url('assets/images/herobg.webp');
              $desc = $b['meta_description'] ?? '';
              if (!$desc) $desc = excerpt_text($b['blog_content'] ?? '', 180);
              $dateStr = !empty($b['created_at']) ? date('M j, Y', strtotime($b['created_at'])) : '';
              $mins = reading_time_min($b['blog_content'] ?? '');
              $url = $domain . 'blog/' . ($b['slug'] ?? '');
            ?>
            <article class="wdx-blog-card">
              <a href="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" class="text-decoration-none">
                <div class="wdx-blog-thumb"><img src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>" alt="<?php echo $title; ?>" loading="lazy"></div>
              </a>
              <div class="wdx-blog-body">
                <span class="wdx-blog-tag">Woodex Blog</span>
                <div class="wdx-blog-title"><a class="text-decoration-none text-dark" href="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>"><?php echo $title; ?></a></div>
                <p class="wdx-blog-excerpt"><?php echo htmlspecialchars(excerpt_text($desc, 170), ENT_QUOTES); ?></p>
                <div class="wdx-blog-foot">
                  <span><i class="bi bi-person"></i>Woodex Editorial</span>
                  <?php if ($dateStr): ?><span><i class="bi bi-calendar3"></i><?php echo htmlspecialchars($dateStr, ENT_QUOTES); ?></span><?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-4" aria-label="Blog pagination">
            <ul class="pagination justify-content-center">
              <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $domain; ?>blog?<?php echo htmlspecialchars(http_build_query(['q'=>$q,'page'=>max(1,$page-1)]), ENT_QUOTES); ?>">Previous</a>
              </li>
              <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
              ?>
              <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                  <a class="page-link" href="<?php echo $domain; ?>blog?<?php echo htmlspecialchars(http_build_query(['q'=>$q,'page'=>$p]), ENT_QUOTES); ?>"><?php echo (int)$p; ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $domain; ?>blog?<?php echo htmlspecialchars(http_build_query(['q'=>$q,'page'=>min($totalPages,$page+1)]), ENT_QUOTES); ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
