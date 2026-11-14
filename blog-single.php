<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
require_once "lbpanel/config/connection.php";

function abs_url($path) {
  if (!$path) return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  return rtrim($GLOBALS['domain'] ?? '', '/') . '/' . ltrim($path, '/');
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
  http_response_code(404);
  $singleBlog = null;
} else {
  $stmt = $conn->prepare("SELECT id, blog_title, slug, meta_description, blog_image, blog_content, created_at FROM blogs WHERE slug = ? LIMIT 1");
  $stmt->bind_param("s", $slug);
  $stmt->execute();
  $singleBlog = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$singleBlog) http_response_code(404);
}

$canonical = $domain . 'blog/' . $slug;

$related = [];
if ($singleBlog) {
  $stmt = $conn->prepare("SELECT blog_title, slug, meta_description, blog_image, created_at FROM blogs WHERE id <> ? ORDER BY created_at DESC, id DESC LIMIT 3");
  $id = (int)$singleBlog['id'];
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $singleBlog ? htmlspecialchars($singleBlog['blog_title']) : 'Blog - Woodex Furniture'; ?></title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
  <link rel="canonical" href="<?php echo htmlspecialchars($canonical, ENT_QUOTES); ?>">
  <meta name="description" content="<?php echo htmlspecialchars($singleBlog['meta_description'] ?? 'Read the latest insights from Woodex Furniture.', ENT_QUOTES); ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">
  <style>
    .wdx-article{ padding: 34px 0 60px; background:#fff; }
    .wdx-article-grid{ display:grid; grid-template-columns: 320px 1fr; gap: 22px; }
    .wdx-toc{ background:#fff; border:1px solid rgba(0,0,0,.08); border-radius: 18px; padding: 16px; position: sticky; top: 18px; height: fit-content; box-shadow: 0 12px 34px rgba(0,0,0,.06); }
    .wdx-toc h3{ font-weight: 900; font-size: 1rem; margin:0 0 12px; }
    .wdx-toc ul{ list-style:none; padding:0; margin:0; }
    .wdx-toc a{ display:block; padding: 6px 0; text-decoration:none; color:#374151; font-size: .92rem; }
    .wdx-toc a:hover{ color: var(--accent-color); }
    .wdx-body{ background:#fff; border:1px solid rgba(0,0,0,.08); border-radius: 18px; padding: 18px; box-shadow: 0 12px 34px rgba(0,0,0,.06); }
    .wdx-body img{ max-width: 100%; height:auto; border-radius: 14px; }
    .wdx-body h2, .wdx-body h3{ margin-top: 1.75rem; }
    .wdx-body p{ line-height: 1.75; }
    .wdx-related{ padding-top: 28px; }
    .wdx-related-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 18px; }
    .wdx-rel-card{ grid-column: span 4; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius: 18px; overflow:hidden; box-shadow: 0 10px 28px rgba(0,0,0,.06); transition: transform .18s ease, box-shadow .18s ease; }
    .wdx-rel-card:hover{ transform: translateY(-3px); box-shadow: 0 18px 48px rgba(0,0,0,.10); }
    .wdx-rel-thumb{ aspect-ratio: 16/9; background:#f2f4f7; }
    .wdx-rel-thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .wdx-rel-body{ padding: 14px; }
    .wdx-rel-title{ font-weight: 900; letter-spacing: -.01em; margin: 0 0 8px; color:#111827; }
    .wdx-rel-meta{ color:#6b7280; font-size: 12px; margin-bottom: 10px; }
    @media (max-width: 991.98px){
      .wdx-article-grid{ grid-template-columns: 1fr; }
      .wdx-toc{ position: static; }
      .wdx-rel-card{ grid-column: span 6; }
    }
    @media (max-width: 575.98px){ .wdx-rel-card{ grid-column: span 12; } }
  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<?php if (!$singleBlog): ?>
  <section class="py-5">
    <div class="container">
      <div class="p-5 bg-white border rounded-4 text-center">
        <h1 class="h4 mb-2">Article not found</h1>
        <div class="text-muted mb-3">The link may be incorrect or the article was removed.</div>
        <a class="btn wdx-btn" href="<?php echo $domain; ?>blog">Back to Blog</a>
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
  $heroImg = !empty($singleBlog['blog_image']) ? abs_url($singleBlog['blog_image']) : abs_url('assets/images/herobg.webp');
  $title = htmlspecialchars($singleBlog['blog_title'] ?? '', ENT_QUOTES);
  $dateStr = !empty($singleBlog['created_at']) ? date('M j, Y', strtotime($singleBlog['created_at'])) : '';
  $content = (string)($singleBlog['blog_content'] ?? '');
  $content = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $content);
  $content = preg_replace('~\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')~i', '', $content);
  // Make root-relative links work in subfolder installs (e.g. /woodex)
  $content = preg_replace('~\b(src|href)="/(?!/)~', '$1="' . $domain, $content);
  // Make plain relative links work from /blog/{slug}
  $content = preg_replace('~\b(src|href)="(?!https?://|//|/|#|data:|mailto:|tel:|javascript:)([^"]+)"~i', '$1="' . $domain . '$2"', $content);
  $content = preg_replace("~\b(src|href)='(?!https?://|//|/|#|data:|mailto:|tel:|javascript:)([^']+)'~i", "$1='" . $domain . "$2'", $content);
?>

<section>
  <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo htmlspecialchars($heroImg, ENT_QUOTES); ?>');">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>
    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-8">
          <div class="wdx-badge">Article</div>
          <h1 class="wdx-hero-title"><?php echo $title; ?></h1>
          <p class="wdx-desc mb-0">
            <?php if ($dateStr): ?><span class="me-3"><i class="bi bi-calendar3 me-1"></i><?php echo htmlspecialchars($dateStr, ENT_QUOTES); ?></span><?php endif; ?>
            <a class="text-white-50 text-decoration-none" href="<?php echo $domain; ?>blog"><i class="bi bi-arrow-left me-1"></i>Back to Blog</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="wdx-article">
  <div class="container">
    <div class="wdx-article-grid">
      <aside class="wdx-toc" id="tocBox" style="display:none;">
        <h3>On this page</h3>
        <ul id="tocList"></ul>
      </aside>

      <article class="wdx-body" id="blogContent">
        <?php echo $content; ?>
      </article>
    </div>

    <?php if ($related): ?>
      <div class="wdx-related">
        <div class="d-flex align-items-end justify-content-between gap-3 flex-wrap">
          <div>
            <div class="wdx-kicker">MORE READING</div>
            <h2 class="wdx-title" style="margin:0;">Related Articles</h2>
          </div>
          <a class="btn btn-outline-secondary" href="<?php echo $domain; ?>blog">View all</a>
        </div>

        <div class="wdx-related-grid mt-3">
          <?php foreach ($related as $r): ?>
            <?php
              $rTitle = htmlspecialchars($r['blog_title'] ?? '', ENT_QUOTES);
              $rSlug  = htmlspecialchars($r['slug'] ?? '', ENT_QUOTES);
              $rImg   = !empty($r['blog_image']) ? abs_url($r['blog_image']) : abs_url('assets/images/herobg.webp');
              $rDate  = !empty($r['created_at']) ? date('M j, Y', strtotime($r['created_at'])) : '';
              $rUrl   = $domain . 'blog/' . ($r['slug'] ?? '');
            ?>
            <article class="wdx-rel-card">
              <a href="<?php echo htmlspecialchars($rUrl, ENT_QUOTES); ?>" class="text-decoration-none">
                <div class="wdx-rel-thumb"><img src="<?php echo htmlspecialchars($rImg, ENT_QUOTES); ?>" alt="<?php echo $rTitle; ?>" loading="lazy"></div>
              </a>
              <div class="wdx-rel-body">
                <div class="wdx-rel-meta"><?php echo htmlspecialchars($rDate, ENT_QUOTES); ?></div>
                <div class="wdx-rel-title"><?php echo $rTitle; ?></div>
                <a class="btn btn-sm wdx-btn wdx-blog-btn" href="<?php echo htmlspecialchars($rUrl, ENT_QUOTES); ?>">Read</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Build a Table of Contents from the H2/H3 inside #blogContent
 */
document.addEventListener('DOMContentLoaded', function() {
  const blogContent = document.getElementById('blogContent');
  const tocList     = document.getElementById('tocList');
  const tocBox      = document.getElementById('tocBox');
  if (!blogContent || !tocList) return;

  const h2s = blogContent.querySelectorAll('h2, h3');
  let counter = 1;

  const items = [];
  h2s.forEach(h => {
    const tag = (h.tagName || '').toUpperCase();
    if (tag !== 'H2' && tag !== 'H3') return;
    if (!h.id) h.id = 'heading-' + (counter++);
    items.push({ id: h.id, text: h.textContent || '', level: tag });
  });

  if (items.length === 0) return;
  if (tocBox) tocBox.style.display = '';

  items.forEach(it => {
    const li = document.createElement('li');
    if (it.level === 'H3') li.style.marginLeft = '12px';
    const a = document.createElement('a');
    a.href = '#' + it.id;
    a.textContent = it.text;
    li.appendChild(a);
    tocList.appendChild(li);
  });
});
</script>
</body>
</html>
