<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/lbpanel/config/product_categories.php';

wdx_ensure_product_categories($conn);

if (!function_exists('wdx_join_url')) {
function wdx_join_url($base, $path) {
    $path = (string)$path;
    if ($path === '') return '';
    $path = str_replace('\\', '/', $path);
    if (preg_match('~^https?://~i', $path)) return $path;

    $base = rtrim((string)$base, '/') . '/';
    $basePath = '/';
    $p = parse_url($base, PHP_URL_PATH);
    if (is_string($p) && $p !== '') {
        $basePath = '/' . trim($p, '/') . '/';
    }
    $bpTrim = trim($basePath, '/');
    $origin = rtrim(preg_replace('~' . preg_quote($basePath, '~') . '$~', '/', $base), '/');

    if ($bpTrim !== '') {
        if (strpos($path, '/' . $bpTrim . '/') === 0) return $origin . $path;
        if (strpos($path, $bpTrim . '/') === 0) return $origin . '/' . $path;
    }
    if (strpos($path, '/assets/') === 0) return $origin . rtrim($basePath, '/') . $path;
    if (strpos($path, 'assets/') === 0) return $base . $path;
    if (strpos($path, '/') === 0) return $origin . rtrim($basePath, '/') . $path;
    return $base . $path;
}
}

if (!function_exists('wdx_hero_plain_text')) {
function wdx_hero_plain_text($value) {
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $value);
    $value = preg_replace('~</\s*p\s*>~i', "\n", $value);
    $value = strip_tags($value);
    $value = preg_replace('/[ \t]+/', ' ', $value);
    $value = preg_replace('/\s*\R\s*/', "\n", $value);
    return trim($value);
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
    <title>Best Office Furniture in Pakistan - Woodex Furniture</title>
    <meta name="description" content="Discover Our Store, your one-stop destination for premium beauty products. Enhance your natural beauty with our personalized skincare, wellness, and cosmetic solutions.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
</head>
<body>
    <?php include_once('header/header.php'); ?>
<?php
// ====== fetch slides for active slider design ======
$activeDesign = 'classic';
$slider = null;
$hasActiveDesignCol = false;
$activeDesignColCheck = $conn->query("SHOW COLUMNS FROM hero_sliders LIKE 'active_design'");
if ($activeDesignColCheck && $activeDesignColCheck->num_rows > 0) {
  $hasActiveDesignCol = true;
}
$selectActiveDesign = $hasActiveDesignCol ? ', active_design' : ", 'classic' AS active_design";
$sliderRes = $conn->query("SELECT id{$selectActiveDesign} FROM hero_sliders WHERE is_active=1 ORDER BY id LIMIT 1");
if ($sliderRes) {
  $slider = $sliderRes->fetch_assoc();
}

$slides = [];
$altSlides = [];
if ($slider) {
  $sid = (int)$slider['id'];
  $activeDesign = (($slider['active_design'] ?? 'classic') === 'modern') ? 'modern' : 'classic';
  $now = date('Y-m-d H:i:s');

  $hasHeroDescription = false;
  $hasHeroBtnColor = false;
  $hasHeroHeadingColor = false;
  $hasHeroDescriptionColor = false;
  $heroDescCheck = $conn->query("SHOW COLUMNS FROM hero_slides LIKE 'description'");
  if ($heroDescCheck && $heroDescCheck->num_rows > 0) {
    $hasHeroDescription = true;
  }
  $heroBtnCheck = $conn->query("SHOW COLUMNS FROM hero_slides LIKE 'button_color'");
  if ($heroBtnCheck && $heroBtnCheck->num_rows > 0) {
    $hasHeroBtnColor = true;
  }
  $heroHeadingColorCheck = $conn->query("SHOW COLUMNS FROM hero_slides LIKE 'heading_color'");
  if ($heroHeadingColorCheck && $heroHeadingColorCheck->num_rows > 0) {
    $hasHeroHeadingColor = true;
  }
  $heroDescriptionColorCheck = $conn->query("SHOW COLUMNS FROM hero_slides LIKE 'description_color'");
  if ($heroDescriptionColorCheck && $heroDescriptionColorCheck->num_rows > 0) {
    $hasHeroDescriptionColor = true;
  }

  $heroDescSelect = $hasHeroDescription ? ', description' : ", '' AS description";
  $heroBtnSelect = $hasHeroBtnColor ? ', button_color' : ", '#78be20' AS button_color";
  $heroHeadingColorSelect = $hasHeroHeadingColor ? ', heading_color' : ", '#111111' AS heading_color";
  $heroDescriptionColorSelect = $hasHeroDescriptionColor ? ', description_color' : ", '#4f5d6b' AS description_color";

  $stmt = $conn->prepare("
    SELECT id, kicker, title{$heroDescSelect}, cta_label, cta_url{$heroBtnSelect}{$heroHeadingColorSelect}{$heroDescriptionColorSelect}, image_path, image_alt, text_col
    FROM hero_slides
    WHERE slider_id=? AND is_published=1
      AND (start_at IS NULL OR start_at <= ?)
      AND (end_at   IS NULL OR end_at   >= ?)
    ORDER BY sort_order ASC, id ASC
  ");
  if ($stmt) {
    $stmt->bind_param("iss", $sid, $now, $now);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $slides[] = $row;
    $stmt->close();
  }

  $tableCheck = $conn->query("SHOW TABLES LIKE 'hero_slides_alt'");
  if ($tableCheck && $tableCheck->num_rows > 0) {
    $hasAltBtnColor = false;
    $hasAltHeadingColor = false;
    $hasAltDescriptionColor = false;
    $altBtnCheck = $conn->query("SHOW COLUMNS FROM hero_slides_alt LIKE 'button_color'");
    if ($altBtnCheck && $altBtnCheck->num_rows > 0) {
      $hasAltBtnColor = true;
    }
    $altHeadingColorCheck = $conn->query("SHOW COLUMNS FROM hero_slides_alt LIKE 'heading_color'");
    if ($altHeadingColorCheck && $altHeadingColorCheck->num_rows > 0) {
      $hasAltHeadingColor = true;
    }
    $altDescriptionColorCheck = $conn->query("SHOW COLUMNS FROM hero_slides_alt LIKE 'description_color'");
    if ($altDescriptionColorCheck && $altDescriptionColorCheck->num_rows > 0) {
      $hasAltDescriptionColor = true;
    }
    $altBtnSelect = $hasAltBtnColor ? ', button_color' : ", '#78be20' AS button_color";
    $altHeadingColorSelect = $hasAltHeadingColor ? ', heading_color' : ", '#ffffff' AS heading_color";
    $altDescriptionColorSelect = $hasAltDescriptionColor ? ', description_color' : ", '#deebf7' AS description_color";

    $stmtAlt = $conn->prepare("
      SELECT id, title, description, cta_label, cta_url{$altBtnSelect}{$altHeadingColorSelect}{$altDescriptionColorSelect}, background_image_path, background_image_alt
      FROM hero_slides_alt
      WHERE slider_id=? AND is_published=1
        AND (start_at IS NULL OR start_at <= ?)
        AND (end_at   IS NULL OR end_at   >= ?)
      ORDER BY sort_order ASC, id ASC
    ");
    if ($stmtAlt) {
      $stmtAlt->bind_param("iss", $sid, $now, $now);
      $stmtAlt->execute();
      $resAlt = $stmtAlt->get_result();
      while($row = $resAlt->fetch_assoc()) $altSlides[] = $row;
      $stmtAlt->close();
    }
  }
}
?>

<?php if ($activeDesign === 'modern'): ?>
<section class="hero2-slider">
  <div class="hero2-viewport" <?= empty($altSlides)?'aria-hidden="true"':'';?> >
    <ul class="hero2-track list-unstyled m-0 p-0">
      <?php if (!empty($altSlides)): foreach($altSlides as $s): ?>
        <?php
          $bg = wdx_join_url($domain, $s['background_image_path'] ?? 'assets/images/heroes/placeholder.png');
          $desc = wdx_hero_plain_text($s['description'] ?? '');
          $ctaLabel = trim((string)($s['cta_label'] ?? ''));
          $ctaUrl = trim((string)($s['cta_url'] ?? ''));
          $btnColor = trim((string)($s['button_color'] ?? '#78be20'));
          $headingColor = trim((string)($s['heading_color'] ?? '#ffffff'));
          $descriptionColor = trim((string)($s['description_color'] ?? '#deebf7'));
          if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $btnColor)) {
            $btnColor = '#78be20';
          }
          if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $headingColor)) {
            $headingColor = '#ffffff';
          }
          if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $descriptionColor)) {
            $descriptionColor = '#deebf7';
          }
        ?>
        <li class="hero2-slide" style="background-image:url('<?php echo htmlspecialchars($bg, ENT_QUOTES); ?>')">
          <div class="container">
            <div class="hero2-overlay">
              <h1 class="hero2-title" style="color:<?php echo htmlspecialchars($headingColor, ENT_QUOTES); ?>;"><?php echo nl2br(htmlspecialchars((string)$s['title'])); ?></h1>
              <?php if ($desc !== ''): ?><p class="hero2-desc" style="color:<?php echo htmlspecialchars($descriptionColor, ENT_QUOTES); ?>;"><?php echo htmlspecialchars($desc); ?></p><?php endif; ?>
              <?php if ($ctaLabel !== '' && $ctaUrl !== ''): ?>
                <a class="hero2-btn" style="background:<?php echo htmlspecialchars($btnColor, ENT_QUOTES); ?>;border-color:<?php echo htmlspecialchars($btnColor, ENT_QUOTES); ?>;" href="<?php echo htmlspecialchars($ctaUrl); ?>"><?php echo htmlspecialchars($ctaLabel); ?></a>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; else: ?>
        <li class="hero2-slide" style="background-image:url('<?php echo htmlspecialchars(wdx_join_url($domain, 'assets/images/heroes/placeholder.png'), ENT_QUOTES); ?>')">
          <div class="container">
            <div class="hero2-overlay">
              <h1 class="hero2-title">Welcome to Woodex</h1>
              <p class="hero2-desc">Premium office furniture solutions for modern workspaces.</p>
              <a class="hero2-btn" href="<?php echo $domain; ?>shop">Shop Now</a>
            </div>
          </div>
        </li>
      <?php endif; ?>
    </ul>

    <button class="hero2-arrow hero2-prev" aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
    <button class="hero2-arrow hero2-next" aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>
    <div class="hero2-pagination" role="tablist" aria-label="Slider pagination"></div>
  </div>
</section>
<?php else: ?>
<section class="hero-slider position-relative">
  <div class="container">
    <div class="hero-viewport" <?= empty($slides)?'aria-hidden="true"':'';?>>
      <ul class="hero-track list-unstyled m-0 p-0">
        <?php if($slides): foreach($slides as $s): ?>
          <li class="hero-slide">
            <div class="row align-items-center gy-4">
              <?php
                $textLeft = ($s['text_col'] ?? 'left') === 'left';
                $descText = wdx_hero_plain_text($s['description'] ?? '');
                $btnColor = trim((string)($s['button_color'] ?? '#78be20'));
                $headingColor = trim((string)($s['heading_color'] ?? '#111111'));
                $descriptionColor = trim((string)($s['description_color'] ?? '#4f5d6b'));
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $btnColor)) {
                  $btnColor = '#78be20';
                }
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $headingColor)) {
                  $headingColor = '#111111';
                }
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $descriptionColor)) {
                  $descriptionColor = '#4f5d6b';
                }
                $textCol  = '<div class="col-12 col-lg-5">'.
                              ($s['kicker'] ? '<p class="hero-kicker mb-3">'.htmlspecialchars($s['kicker']).'</p>' : '').
                              '<h1 class="hero-title mb-4" style="color:'.htmlspecialchars($headingColor).'">'. nl2br(htmlspecialchars($s['title'])) .'</h1>'.
                              ($descText !== '' ? '<p class="hero-description mb-4" style="color:'.htmlspecialchars($descriptionColor).'">'. nl2br(htmlspecialchars($descText)) .'</p>' : '').
                              ($s['cta_label'] && $s['cta_url'] ? '<a class="btn btn-lg rounded-3 px-4 py-2 fw-bold hero-dyn-btn" style="background:'.htmlspecialchars($btnColor).';border-color:'.htmlspecialchars($btnColor).';color:#fff" href="'.htmlspecialchars($s['cta_url']).'">'.htmlspecialchars($s['cta_label']).'</a>' : '').
                             '</div>';
                $imgCol   = '<div class="col-12 col-lg-7 text-center">'.
                              '<img class="hero-image img-fluid" src="'.htmlspecialchars(wdx_join_url($domain, $s['image_path'] ?? 'assets/images/placeholder.svg')).'" alt="'.htmlspecialchars($s['image_alt'] ?? '') .'">'.
                            '</div>';
                echo $textLeft ? $textCol.$imgCol : $imgCol.$textCol;
              ?>
            </div>
          </li>
        <?php endforeach; else: ?>
          <!-- Safe fallback if no slides -->
          <li class="hero-slide">
            <div class="row align-items-center gy-4">
              <div class="col-12 col-lg-5">
                <p class="hero-kicker mb-3">Woodex</p>
                <h1 class="hero-title mb-4">Welcome to Woodex</h1>
                <a class="btn btn-outline-dark btn-lg rounded-3 px-4 py-2 fw-bold" href="<?php echo $domain; ?>shop">SHOP NOW</a>
              </div>
              <div class="col-12 col-lg-7 text-center">
                <img class="hero-image img-fluid" src="<?php echo $domain; ?>assets/images/heroes/placeholder.png" alt="Hero">
              </div>
            </div>
          </li>
        <?php endif; ?>
      </ul>

      <button class="hero-arrow hero-prev" aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
      <button class="hero-arrow hero-next" aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>
    </div>

    <div class="hero-pagination" role="tablist" aria-label="Slider pagination"></div>
  </div>
</section>
<?php endif; ?>

<!-- ===== Design → Delivery Services (5-up, like screenshot) ===== -->
<section class="services-band">
  <div class="container">

    <!-- Headline -->
    <div class="services-intro">
      <h2>Market We Served</h2>
      <p>
        Design to delivery manufacturing for startups, SMEs, and corporate clients. Transform your workspace with furniture built exactly to your specifications.
      </p>
    </div>

    <!-- Cards -->
    <div class="services-grid">
      <!-- 1 -->
      <article class="svc-card">
        <div class="svc-icon"><i class="bi bi-palette"></i></div>
        <h3 class="svc-title">Space Planning</h3>
        <p class="svc-text">
          Ask for consultation for space management, project management, personalized planning,
        </p>
        <a href="<?php echo $domain; ?>space_planing.php" class="svc-link"><span>&rsaquo;</span> Planning</a>
      </article>

      <!-- 2 -->
      <article class="svc-card">
        <div class="svc-icon"><i class="bi bi-palette"></i></div>
        <h3 class="svc-title">Custom Design</h3>
        <p class="svc-text">
          Every workspace is unique. We design furniture tailored to your exact specifications and.
        </p>
        <a href="<?php echo $domain; ?>projectbased.php" class="svc-link"><span>&rsaquo;</span> Custom</a>
      </article>

      <!-- 3 -->
      <article class="svc-card">
        <div class="svc-icon"><i class="bi bi-receipt"></i></div>
        <h3 class="svc-title">E- Quoting</h3>
        <p class="svc-text">
          Every workspace is unique. We design furniture tailored to your exact specifications and.
        </p>
        <a href="<?php echo $domain; ?>e-quotation" class="svc-link"><span>&rsaquo;</span> E- Quotation</a>
      </article>

      <!-- 4 -->
      <article class="svc-card">
        <div class="svc-icon"><i class="bi bi-flag"></i></div>
        <h3 class="svc-title">Factory Direct</h3>
        <p class="svc-text">
          Eliminate middleman costs. Premium quality furniture at manufacturer prices quotes.
        </p>
        <a href="<?php echo $domain; ?>factorydirect.php" class="svc-link"><span>&rsaquo;</span> Factory</a>
      </article>

      <!-- 5 -->
      <article class="svc-card">
        <div class="svc-icon"><i class="bi bi-receipt"></i></div>
        <h3 class="svc-title">Delivery &amp; Installation</h3>
        <p class="svc-text">
          Professional delivery and installation ensure your furniture is set up ready to use.
        </p>
        <a href="<?php echo $domain; ?>deliveryandinstallation.php" class="svc-link"><span>&rsaquo;</span> Delivery</a>
      </article>
    </div>

  </div>
</section>

<style>
/* Band background + spacing (light grey like screenshot) */
.services-band{
  background:#f6f7f8;
  padding: 36px 0 44px;
}

/* Headline */
.services-intro{
  text-align:center;
  max-width: 980px;
  margin: 0 auto 22px;
}
.services-intro h2{
  font-weight:800;
  font-size: clamp(1.1rem, 2.2vw, 1.6rem);
  line-height:1.5;
  margin:0;
  color:#111;
}

/* Grid — 5 columns on large, down to 1 on mobile */
.services-grid{
  display:grid;
  gap:22px;
  grid-template-columns: 1fr;
}
@media (min-width: 576px){ .services-grid{ grid-template-columns: repeat(2,1fr); } }
@media (min-width: 992px){ .services-grid{ grid-template-columns: repeat(3,1fr); } }
@media (min-width: 1200px){ .services-grid{ grid-template-columns: repeat(5,1fr); } }

/* Card look (white tile with faint border & large padding) */
.svc-card{
  background:#fff;
  border:1px solid #ececec;
  border-radius:4px;
  padding: 28px 24px;
  height:100%;
  display:flex; flex-direction:column; gap:10px;
  transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
}
.svc-card:hover{
  border-color:#e4e4e4;
  box-shadow: 0 10px 28px rgba(0,0,0,.06);
  transform: translateY(-2px);
}

/* Icon (grey, left-aligned like reference) */
.svc-icon .bi{
  font-size:28px;
  color:#5f6368;
  transition: color .28s ease, transform .28s ease;
}

/* Title + text */
.svc-title{
  font-weight:600;
  color:#111;
  margin: 6px 0 4px;
  font-size: 1.05rem;
  position: relative;
  display: inline-block;
  transition: color .28s ease;
}
.svc-title::after{
  content: '';
  position: absolute;
  left: 0;
  bottom: -4px;
  width: 100%;
  height: 2px;
  background: #78be20;
  transform: scaleX(0);
  transform-origin: left center;
  transition: transform .28s ease;
}
.svc-text{
  color:#666e75;
  margin:0 0 6px 0;
}

.svc-card:hover .svc-title,
.svc-title:hover{
  color:#78be20;
}

.svc-card:hover .svc-title::after,
.svc-title:hover::after{
  transform: scaleX(1);
}

.svc-card:hover .svc-icon .bi{
  color:#78be20;
  transform: translateY(-2px) scale(1.05);
}

/* E-Quote link with leading chevron */
.svc-link{
  margin-top:auto; /* stick to bottom for equal-height feel */
  display:inline-flex; align-items:center; gap:10px;
  color:#6b7280; text-decoration:none;
  font-weight:600;
}
.svc-link span{ color:#8a8f96; font-size:18px; line-height:1; }
.svc-link:hover{ color:#111; }
</style>

<section class="popular-cats">
  <div class="container">
    <div class="cat-strip owl-carousel owl-theme" role="list">
      <?php
      $homeCarouselCol = $conn->query("SHOW COLUMNS FROM categories LIKE 'show_in_home_carousel'");
      if ($homeCarouselCol && $homeCarouselCol->num_rows === 0) {
          $conn->query("ALTER TABLE categories ADD COLUMN show_in_home_carousel TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
      }

      $q = "SELECT id, category_name, main_image, slug
            FROM categories
            WHERE is_active = 1 AND show_in_home_carousel = 1
            ORDER BY path ASC, id ASC";
      $res = $conn->query($q);
      if ($res && $res->num_rows):
        while ($row = $res->fetch_assoc()):
          $name = htmlspecialchars($row['category_name']);
          $img  = trim($row['main_image'] ?? '');
          $src  = $img ? wdx_join_url($domain, $img) : wdx_join_url($domain, 'assets/images/placeholder.svg');
          $url  = wdx_join_url($domain, 'categories/' . ltrim((string)$row['slug'], '/'));
      ?>
      <a class="cat-card" role="listitem" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
        <div class="cat-thumb">
          <img loading="lazy" src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>">
          <span class="cat-hover" aria-hidden="true">
            <i class="bi bi-arrow-right"></i>
          </span>
        </div>
        <div class="cat-title"><?= $name ?></div>
      </a>
      <?php endwhile; endif; ?>
    </div>
  </div>
</section>

<style>
/* ===== Section spacing (white background as in the ref) ===== */
.popular-cats{ padding: 18px 0 30px; }

/* ===== Contained carousel row ===== */
.popular-cats{
  overflow:hidden;
}
.popular-cats .container{
  position:relative;
}
.cat-strip{
  width:100%;
  max-width:100%;
  overflow:hidden;
}
.popular-cats .owl-stage-outer{
  overflow:hidden;
}
.popular-cats .owl-stage{
  display:flex;
  align-items:stretch;
}
.popular-cats .owl-item{
  display:flex;
  justify-content:center;
}

/* ===== Card ===== */
.cat-card{
  display:flex; flex-direction:column; align-items:center; gap:10px;
  text-decoration:none;
  width:100%;
  max-width:160px;
  margin:0 auto;
}

/* Thumbnail box (not a circle by default; becomes circular on hover) */
.cat-thumb{
  position:relative;
  width: 140px; height: 140px;
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
  transition: transform .18s ease;
}
.cat-thumb img{
  max-width:100%; max-height:100%; object-fit:contain; display:block;
  transition: transform .18s ease, filter .18s ease, border-radius .18s ease, box-shadow .18s ease;
}

/* Hover overlay circle (appears on hover) */
.cat-hover{
  position:absolute; inset:8px;              /* margin inside circle */
  background:rgba(0,0,0,.22);
  border-radius:999px;
  display:flex; align-items:center; justify-content:center;
  opacity:0; transition:opacity .18s ease;
}
.cat-hover .bi{ color:#fff; font-size:22px; transform: translateX(2px); }

/* Title below image */
.cat-title{
  font-weight:700; color:#222; text-align:center;
  font-size: 14px;
}

/* Hover states to match the reference */
.cat-card:hover .cat-thumb img{
  border-radius:999px;                   /* becomes a circle */
  box-shadow: 0 4px 18px rgba(0,0,0,.08);
  transform: scale(1.02);
}
.cat-card:hover .cat-hover{ opacity:1; }
.cat-card:hover .cat-title{ color:#e23b34; } /* red title on hover */

.popular-cats .owl-nav{
  margin:0;
  pointer-events:none;
}
.popular-cats .owl-nav button.owl-prev,
.popular-cats .owl-nav button.owl-next{
  position:absolute;
  top:52px;
  width:34px;
  height:34px;
  border-radius:999px;
  background:#fff !important;
  border:1px solid #e5e7eb !important;
  box-shadow:0 8px 20px rgba(0,0,0,.08);
  display:flex !important;
  align-items:center;
  justify-content:center;
  pointer-events:auto;
}
.popular-cats .owl-nav button.owl-prev{ left:0; }
.popular-cats .owl-nav button.owl-next{ right:0; }
.popular-cats .owl-nav button i{
  color:#222;
  font-size:18px;
}
.popular-cats .owl-nav button:hover{
  background:#78be20 !important;
  border-color:#78be20 !important;
}
.popular-cats .owl-nav button:hover i{
  color:#fff;
}
.popular-cats .owl-dots{
  display:none;
}

/* Responsive sizes */
@media (max-width: 575.98px){
  .cat-thumb{ width:120px; height:120px; }
  .popular-cats .owl-nav button.owl-prev,
  .popular-cats .owl-nav button.owl-next{
    top:43px;
    width:30px;
    height:30px;
  }
}
</style>


<style>
  .hero2-slider{ position:relative; }
  .hero2-viewport{ position:relative; overflow:hidden; }
  .hero2-track{ display:flex; transition: transform .45s cubic-bezier(.22,.61,.36,1); will-change: transform; }
  .hero2-slide{
    flex:0 0 100%;
    min-height: min(72vh, 620px);
    background-size: cover;
    background-position: center center;
    display:flex;
    align-items:center;
    position: relative;
  }
  .hero2-slide::before{
    content:'';
    position:absolute;
    inset:0;
    background: linear-gradient(90deg, rgba(0,0,0,.68) 0%, rgba(0,0,0,.46) 34%, rgba(0,0,0,.18) 58%, rgba(0,0,0,0) 78%);
    z-index:0;
    pointer-events:none;
  }
  .hero2-overlay{
    position:relative;
    z-index:1;
    max-width: 640px;
    color:#fff;
    padding: 48px 0;
  }
  .hero2-title{
    font-size: clamp(2rem, 5vw, 3.8rem);
    line-height:1.04;
    letter-spacing:-.02em;
    font-weight:900;
    margin:0 0 14px;
  }
  .hero2-desc{
    font-size: clamp(1rem, 1.5vw, 1.2rem);
    color:#deebf7;
    max-width: 58ch;
    margin:0 0 20px;
  }
  .hero2-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:46px;
    padding:0 20px;
    background:#78be20;
    border:1px solid #78be20;
    color:#fff;
    font-weight:800;
    border-radius:4px;
    text-decoration:none;
  }
  .hero2-btn:hover{ background:#67a61b; border-color:#67a61b; color:#fff; }
  .hero2-arrow{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    width:46px;
    height:46px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.45);
    background: rgba(14, 20, 28, .4);
    color:#fff;
    z-index:2;
  }
  .hero2-prev{ left:20px; }
  .hero2-next{ right:20px; }
  .hero2-pagination{
    position:absolute;
    left:0;
    right:0;
    bottom:18px;
    display:flex;
    gap:10px;
    justify-content:center;
    z-index:2;
  }
  .hero2-dot{ width:10px;height:10px;border-radius:50%;border:0;background:rgba(255,255,255,.45); }
  .hero2-dot[aria-selected="true"]{ background:#fff; transform:scale(1.2); }

  .hero-slider{ background:#fff; padding:48px 0 32px; }
  .hero-viewport{ position:relative; overflow:hidden; }
  .hero-track{ display:flex; transition: transform .45s cubic-bezier(.22,.61,.36,1); will-change: transform; }
  .hero-slide{ flex:0 0 100%; padding:24px 0 8px; }
  .hero-kicker{ letter-spacing:.06em; color:#6c757d; font-weight:600; }
  .hero-title{ font-weight:800; line-height:1.05; letter-spacing:.01em; font-size:clamp(2rem, 4.4vw, 3.5rem); color:#111; }
  .hero-description{ color:#4f5d6b; font-size:1.04rem; line-height:1.6; max-width:58ch; }
  .hero-image{ max-height:360px; object-fit:contain; width:auto; }
  .hero-arrow{ position:absolute; top:50%; transform:translateY(-50%); width:44px;height:44px;border-radius:999px;border:1px solid #dcdcdc;background:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:#000;transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease; z-index:5; }
  .hero-prev{ left:-8px; } .hero-next{ right:-8px; }
  .hero-arrow:hover{ box-shadow:0 6px 18px rgba(0,0,0,.08); border-color:#cfcfcf; transform:translateY(-50%) scale(1.04); }
  .hero-pagination{ display:flex; gap:10px; align-items:center; justify-content:center; padding:18px 0 2px; }
  .hero-dot{ width:10px;height:10px;border-radius:999px;background:#d8d8d8;border:0; }
  .hero-dot[aria-selected="true"]{ background:#000; transform:scale(1.2); }
  @media (max-width: 991.98px){ .hero-image{ max-height:280px; } .hero-prev{ left:2px; } .hero-next{ right:2px; } }
  @media (max-width: 575.98px){ .hero-slider{ padding-top:28px; } .hero-title{ line-height:1.08; } .hero-image{ max-height:220px; } }
  @media (max-width: 767.98px){
    .hero2-slide{ min-height: 420px; }
    .hero2-overlay{ padding: 24px 0 34px; }
    .hero2-prev{ left:10px; }
    .hero2-next{ right:10px; }
  }
</style>


<?php
// ===== Best Sellers (product flags first, fallback to old category-based logic) =====
$rows = false;
$hasBestSellerFlag = false;
$flagColRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_best_seller'");
if ($flagColRes && $flagColRes->num_rows > 0) {
  $hasBestSellerFlag = true;
}

if ($hasBestSellerFlag) {
  $flaggedSql = "
    SELECT
      (SELECT c2.id FROM product_categories pc2 JOIN categories c2 ON c2.id = pc2.category_id WHERE pc2.product_id = p.id ORDER BY c2.category_name ASC LIMIT 1) AS cat_id,
      (SELECT GROUP_CONCAT(c2.category_name ORDER BY c2.category_name SEPARATOR ', ') FROM product_categories pc2 JOIN categories c2 ON c2.id = pc2.category_id WHERE pc2.product_id = p.id) AS cat_name,
      (SELECT c2.slug FROM product_categories pc2 JOIN categories c2 ON c2.id = pc2.category_id WHERE pc2.product_id = p.id ORDER BY c2.category_name ASC LIMIT 1) AS cat_slug,
      (SELECT c2.main_image FROM product_categories pc2 JOIN categories c2 ON c2.id = pc2.category_id WHERE pc2.product_id = p.id ORDER BY c2.category_name ASC LIMIT 1) AS cat_image,
      p.id            AS product_id,
      p.product_name  AS product_name,
      p.slug          AS product_slug,
      p.images        AS product_images,
      p.old_price     AS old_price,
      p.current_price AS current_price,
      COUNT(v.id) AS variation_count,
      MIN(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price END) AS min_price,
      MAX(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price END) AS max_price
    FROM products p
    LEFT JOIN product_variations v ON v.product_id = p.id
    WHERE p.is_best_seller = 1
    GROUP BY p.id, p.product_name, p.slug, p.images, p.old_price, p.current_price
    ORDER BY p.id DESC
    LIMIT 18
  ";
  $rows = $conn->query($flaggedSql);
}

if (!$rows || $rows->num_rows === 0) {
  $fallbackSql = "
    SELECT
      c.id                    AS cat_id,
      c.category_name         AS cat_name,
      c.slug                  AS cat_slug,
      c.main_image            AS cat_image,
      p.id                    AS product_id,
      p.product_name          AS product_name,
      p.slug                  AS product_slug,
      p.images                AS product_images,
      p.old_price             AS old_price,
      p.current_price         AS current_price,
      COUNT(v.id) AS variation_count,
      MIN(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price END) AS min_price,
      MAX(CASE WHEN v.price IS NOT NULL AND v.price > 0 THEN v.price END) AS max_price
    FROM categories c
    LEFT JOIN (
      SELECT p1.*, x.category_id AS picked_category_id
      FROM products p1
      INNER JOIN (
        SELECT category_id, MAX(product_id) AS pick_id
        FROM product_categories
        GROUP BY category_id
      ) x ON x.pick_id = p1.id
    ) p ON p.picked_category_id = c.id
    LEFT JOIN product_variations v ON v.product_id = p.id
    GROUP BY c.id, c.category_name, c.slug, c.main_image, p.id, p.product_name, p.slug, p.images, p.old_price, p.current_price
    ORDER BY c.category_name ASC
  ";
  $rows = $conn->query($fallbackSql);
}
?>

<section class="shop-by-category">
  <div class="container">
    <div class="sbc-head d-flex align-items-center justify-content-between">
      <h2 class="sbc-title mb-0">Latest Collection</h2>
      <div class="sbc-arrows">
        <button class="sbc-prev" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
        <button class="sbc-next" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
      </div>
    </div>

    <div class="owl-carousel owl-theme sbc-carousel mt-3">
      <?php if ($rows && $rows->num_rows): ?>
        <?php while ($r = $rows->fetch_assoc()):
          $catNameRaw = trim((string)($r['cat_name'] ?? ''));
          $catName   = htmlspecialchars($catNameRaw !== '' ? $catNameRaw : 'Category');
          $catSlugRaw = trim((string)($r['cat_slug'] ?? ''));
          $catSlug   = htmlspecialchars($catSlugRaw);
          $catUrl    = $catSlugRaw !== '' ? ($domain.'categories/'.$catSlug) : 'javascript:void(0)';

          // product image (first from JSON), else category image, else placeholder
          $prodName  = $r['product_name'] ? htmlspecialchars($r['product_name']) : '';
          $prodSlug  = $r['product_slug'] ? htmlspecialchars($r['product_slug']) : '';
          $prodUrl   = $prodSlug ? ($domain.'shop/'.$prodSlug) : $catUrl;

          $imgSrc    = '';
          if (!empty($r['product_images'])) {
            $imgs = json_decode($r['product_images'], true);
            if (is_array($imgs) && count($imgs)) $imgSrc = $imgs[0];
          }
          if (!$imgSrc && !empty($r['cat_image']))  $imgSrc = $r['cat_image'];
          if (!$imgSrc) $imgSrc = 'assets/images/placeholder.png';
          $imgUrl = wdx_join_url($domain, $imgSrc);

          $oldPrice = (float)($r['old_price'] ?? 0);
          $currentPrice = (float)($r['current_price'] ?? 0);
          $hasVars = ((int)($r['variation_count'] ?? 0)) > 0;
          $minVar = $r['min_price'] !== null ? (float)$r['min_price'] : null;
          $maxVar = $r['max_price'] !== null ? (float)$r['max_price'] : null;
          $effective = ($hasVars && $minVar !== null) ? $minVar : $currentPrice;
          $priceText = ($hasVars && $minVar !== null && $maxVar !== null && $maxVar > $minVar)
            ? 'PKR ' . number_format($minVar, 0) . ' - ' . number_format($maxVar, 0)
            : 'PKR ' . number_format($effective, 0);
          $discount = ($oldPrice > 0 && $oldPrice > $effective) ? round((($oldPrice - $effective) / $oldPrice) * 100) : 0;
        ?>
        <div class="item">
          <article class="product-card-v2">
            <div class="product-image-container-v2">
              <?php if ($discount > 0): ?><span class="discount-badge-v2"><?= $discount ?>% OFF</span><?php endif; ?>
              <?php if (!empty($r['product_slug'])): ?>
              <button type="button" class="wdx-quick-view-btn" data-slug="<?= htmlspecialchars((string)$r['product_slug']) ?>">
                <i class="bi bi-eye"></i> Quick View
              </button>
              <?php endif; ?>
              <a href="<?= $prodUrl ?>">
                <img class="product-image-v2" loading="lazy" src="<?= $imgUrl ?>" alt="<?= $prodName ?: $catName ?>">
              </a>
            </div>
            <div class="product-details-v2">
              <a class="product-cat-v2" href="<?= $catUrl ?>"><?= $catName ?></a>
              <p class="product-title-v2"><a href="<?= $prodUrl ?>"><?= $prodName ?: $catName ?></a></p>
              <p class="mb-0">
                <?php if ($discount > 0): ?><span class="product-old-price-v2">PKR <?= number_format($oldPrice, 0) ?></span><?php endif; ?>
                <span class="product-price-v2"><?= htmlspecialchars($priceText) ?></span>
              </p>
            </div>
          </article>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="item"><p class="text-muted">No categories found.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ===== Styles (match the screenshot) ===== -->
<style>
.shop-by-category{ padding: 24px 0; background:#f3f4f6; }
.sbc-head{ position:relative; min-height:34px; }
.sbc-title{
  font-family: inherit;
  font-weight:800;
  color:#111;
  line-height:1.5;
  font-size: clamp(1.1rem, 2.2vw, 1.6rem);
  letter-spacing:0;
  margin:0;
  position:absolute;
  left:50%;
  transform:translateX(-50%);
  text-align:center;
  white-space:nowrap;
}
.sbc-arrows{ display:flex; gap:10px; }
.sbc-arrows .sbc-prev,
.sbc-arrows .sbc-next{
  width:34px; height:34px; border-radius:999px; border:1px solid #ddd; background:#fff;
  display:inline-flex; align-items:center; justify-content:center; color:#222;
  transition: box-shadow .15s ease, border-color .15s ease;
}
.sbc-arrows button:hover{ border-color:#cfcfcf; box-shadow:0 4px 14px rgba(0,0,0,.08); }

.sbc-carousel .item{ height:100%; }
.sbc-carousel .item > .product-card-v2{ height:100%; }
.sbc-carousel .product-card-v2{ padding:01px; }
.sbc-carousel .product-image-container-v2{ height:300px; background:#fff !important; }
.sbc-carousel .product-image-v2{ background:#fff !important; }

@media (max-width: 1199.98px){
  .sbc-carousel .product-image-container-v2{ height:260px; }
}
@media (max-width: 767.98px){
  .sbc-title{ position:static; transform:none; width:100%; }
  .sbc-head{ justify-content:center !important; }
  .sbc-arrows{ display:none; }
  .sbc-carousel .product-image-container-v2{ height:220px; }
}

/* Owl spacing */
.sbc-carousel .owl-stage{ display:flex; }
.sbc-carousel .owl-item{ padding: 0 12px 10px; } /* card gutters */
</style>

<!-- ===== Complete Office Solutions ===== -->
<section class="office-solutions">
  <div class="container">
    <div class="row align-items-center g-4">
      <!-- Left: Copy -->
      <div class="col-12 col-lg-6">
        <h2 class="os-title">Make Your Space Work</h2>

        <p class="os-intro">From smart interiors to full-scale fitouts, our services cover everything you need to build a standout commercial space. Discover how our high-quality furniture can transform your space into a haven of comfort and happiness.</p>

        <ul class="os-list">
          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">01</div>
              <div>
                <h3 class="os-item-title">3D Rendered Floor Plan</h3>
              </div>
            </a>
          </li>

          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">02</div>
              <div>
                <h3 class="os-item-title">Custom Office Furniture</h3>
              </div>
            </a>
          </li>

          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">03</div>
              <div>
                <h3 class="os-item-title">2D Spatial Planning</h3>
              </div>
            </a>
          </li>

          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">04</div>
              <div>
                <h3 class="os-item-title">Project Management</h3>
              </div>
            </a>
          </li>

          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">05</div>
              <div>
                <h3 class="os-item-title">Interior Designing</h3>
              </div>
            </a>
          </li>

          <li>
            <a class="os-list-link" href="<?php echo $domain; ?>services">
              <div class="os-bullet" aria-hidden="true">06</div>
              <div>
                <h3 class="os-item-title">Turnkey Solutions</h3>
              </div>
            </a>
          </li>
        </ul>

      </div>

      <!-- Right: Visual -->
      <div class="col-12 col-lg-6">
        <div class="os-visual">
          <!-- Replace with your image path -->
          <img src="assets/images/custom-design.png" alt="Modular office plan with reception, workstations and meeting areas">
        </div>
      </div>
    </div>
  </div>
</section>

<style>
/* Section shell */
.office-solutions{
  background:#ffffff;
  padding: clamp(40px, 6vw, 78px) 0;
}

/* Title */
.os-title{
  font-family: inherit;
  font-weight:800;
  color:#111;
  line-height:1.5;
  font-size: clamp(1.1rem, 2.2vw, 1.6rem);
  margin: 0 0 28px 0;
}

.os-intro{
  margin: 0 0 12px 0;
  color:#6b7784;
  line-height:1.62;
  font-size: 1.02rem;
  max-width: 600px;
}

/* Feature list */
.os-list{
  list-style:none;
  padding:0; margin: 10px 0 18px 0;
  display:flex; flex-direction:column; gap:0;
}
.os-list li{
  display:flex; gap:12px; align-items:center;
  padding: 12px 0;
  border-bottom:1px solid #d9dde2;
}
.os-list-link{
  display:flex;
  align-items:center;
  gap:12px;
  width:100%;
  color:inherit;
  text-decoration:none;
}
.os-list-link:hover .os-item-title{
  color:#78be20;
}
.os-bullet{
  flex:0 0 18px;
  width:18px;
  height:18px;
  line-height:18px;
  border-radius:50%;
  text-align:center;
  background:#78be20;
  color:#fff;
  font-size:9px;
  font-weight:700;
}
.os-item-title{
  font-family: inherit;
  margin:0;
  color:#111;
  font-weight:600;
  line-height:1.35;
  font-size: 1rem;
}

/* Button */
.os-btn{
  display:inline-block;
  margin-top: 14px;
  padding:.72rem 1.85rem;
  border:1.5px solid #78be20;
  border-radius:8px;
  color:#78be20; text-decoration:none; font-weight:700;
  transition: transform .15s ease, background .15s ease, color .15s ease;
}
.os-btn:hover{ background:#78be20; border-color:#78be20; color:#fff; transform: translateY(-1px); }

/* Visual */
.os-visual{
  display:flex; align-items:center; justify-content:center;
}
.os-visual img{
  width:100%; height:auto; object-fit:contain; max-width: 760px;
}

/* Spacing tweaks on small screens */
@media (max-width: 991.98px){
  .os-title{ margin-bottom: 18px; }
  .os-intro{ font-size: 1rem; }
  .os-visual{ margin-top: 6px; }
}

@media (min-width: 992px){
  .office-solutions .row{
    position:relative;
    padding-top: 72px;
  }
  .office-solutions .col-lg-6:first-child{
    padding-right: 44px;
  }
  .office-solutions .col-lg-6:last-child{
    padding-left: 22px;
  }
  .os-title{
    position:absolute;
    top:0;
    left:50%;
    transform:translateX(-50%);
    margin:0;
    text-align:center;
    white-space:nowrap;
  }
}
</style>


<?php
// --- Hot Products categories + products ---
function categoryFlagColumnExists($conn, $columnName) {
  $safeColumn = str_replace('`', '', (string)$columnName);
  $res = $conn->query("SHOW COLUMNS FROM categories LIKE '{$safeColumn}'");
  return $res && $res->num_rows > 0;
}

function ensureHotProductsCategoryColumn($conn, $columnName, $definition) {
  if (!categoryFlagColumnExists($conn, $columnName)) {
    $safeColumn = str_replace('`', '', (string)$columnName);
    $conn->query("ALTER TABLE categories ADD COLUMN `{$safeColumn}` {$definition}");
  }
}

$hotCategories = [];
$hotProductsByCategory = [];

ensureHotProductsCategoryColumn($conn, 'show_in_hot_products', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
ensureHotProductsCategoryColumn($conn, 'hot_products_sort_order', "INT NOT NULL DEFAULT 0 AFTER show_in_hot_products");

$hasHotCategoryFlag = categoryFlagColumnExists($conn, 'show_in_hot_products');
$hasHotCategoryOrder = categoryFlagColumnExists($conn, 'hot_products_sort_order');

if ($hasHotCategoryFlag && $hasHotCategoryOrder) {
  $hotCatsResult = $conn->query("SELECT id, category_name FROM categories WHERE is_active = 1 AND show_in_hot_products = 1 ORDER BY CASE WHEN hot_products_sort_order > 0 THEN hot_products_sort_order ELSE 999999 END ASC, id DESC");
  if ($hotCatsResult) {
    while ($cat = $hotCatsResult->fetch_assoc()) {
      $hotCategories[] = $cat;
    }
  }
}

$baseAggSQLByCategory = "
  SELECT
    p.id,
    p.product_name,
    p.old_price,
    p.sold_quantity,
    p.images,
    p.slug,
    CASE WHEN p.rating_count > 0 THEN p.rating_sum / p.rating_count ELSE 0 END AS avg_rating,
    p.rating_count,
    /* price range from variations */
    COALESCE(
      MIN(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END),
      MIN(v.price),
      p.current_price
    ) AS min_price,
    COALESCE(
      MAX(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.price END),
      MAX(v.price),
      p.current_price
    ) AS max_price,
    COALESCE(
      SUM(CASE WHEN (v.stock IS NULL OR v.stock > 0) THEN v.stock END),
      p.quantity,
      0
    ) AS total_stock
  FROM products p
  LEFT JOIN product_variations v ON v.product_id = p.id
  WHERE EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?)
  GROUP BY p.id, p.product_name, p.old_price, p.sold_quantity, p.images, p.slug, p.rating_count, p.rating_sum, p.quantity
  ORDER BY p.id DESC
  LIMIT 8
";

if (!empty($hotCategories)) {
  $hotProductStmt = $conn->prepare($baseAggSQLByCategory);
  if ($hotProductStmt) {
    foreach ($hotCategories as $cat) {
      $catId = (int)($cat['id'] ?? 0);
      if ($catId <= 0) {
        $hotProductsByCategory[$catId] = [];
        continue;
      }
      $hotProductStmt->bind_param('i', $catId);
      $hotProductStmt->execute();
      $result = $hotProductStmt->get_result();
      $hotProductsByCategory[$catId] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    $hotProductStmt->close();
  }
}

// --- Helper functions for price & badges ---
function renderPriceRange($row) {
  $min = (float)($row['min_price'] ?? 0);
  $max = (float)($row['max_price'] ?? 0);

  if ($min > 0 && $max > 0 && $min != $max)
      return 'PKR ' . number_format($min, 0) . ' – ' . number_format($max, 0);
  return 'PKR ' . number_format($min, 0);
}

function renderDiscountPercent($row) {
  $old = (float)($row['old_price'] ?? 0);
  $min = (float)($row['min_price'] ?? 0);
  return ($old > 0 && $old > $min) ? round((($old - $min) / $old) * 100) : 0;
}
?>


<!-- Product Section with Tabs -->
<section class="home-products">
    <div class="product-section-v2">
        <div class="container">
            <h2 class="product-section-title-v2 text-center pb-3">Hot Products</h2>

            <?php if (!empty($hotCategories)): ?>
            <?php if (count($hotCategories) > 1): ?>
            <ul class="nav nav-tabs justify-content-center mb-4" id="productTabs" role="tablist">
                <?php foreach ($hotCategories as $tabIndex => $hotCategory): ?>
                    <?php
                        $catId = (int)($hotCategory['id'] ?? 0);
                        $tabId = 'hot-cat-' . $catId;
                        $isActiveTab = ($tabIndex === 0);
                    ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $isActiveTab ? 'active' : '' ?>" id="<?= htmlspecialchars($tabId) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= htmlspecialchars($tabId) ?>" type="button" role="tab" aria-controls="<?= htmlspecialchars($tabId) ?>" aria-selected="<?= $isActiveTab ? 'true' : 'false' ?>"><?= htmlspecialchars($hotCategory['category_name'] ?? 'Category') ?></button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <div class="text-center mb-4">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= htmlspecialchars((string)($hotCategories[0]['category_name'] ?? 'Category')) ?></span>
                </div>
            <?php endif; ?>

            <div class="tab-content" id="productTabsContent">
                <?php foreach ($hotCategories as $tabIndex => $hotCategory): ?>
                    <?php
                        $catId = (int)($hotCategory['id'] ?? 0);
                        $catName = (string)($hotCategory['category_name'] ?? 'Category');
                        $tabId = 'hot-cat-' . $catId;
                        $isActiveTab = ($tabIndex === 0);
                        $tabProducts = $hotProductsByCategory[$catId] ?? [];
                    ?>
                    <div class="tab-pane fade <?= $isActiveTab ? 'show active' : '' ?>" id="<?= htmlspecialchars($tabId) ?>" role="tabpanel" aria-labelledby="<?= htmlspecialchars($tabId) ?>-tab">
                        <div class="product-row-v2 row g-4 justify-content-center">
                            <?php if (!empty($tabProducts)): ?>
                                <?php foreach ($tabProducts as $row): ?>
                                    <?php include 'product-card.php'; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No products available in <?= htmlspecialchars($catName) ?> at the moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="text-center text-muted mb-0">No categories are marked as Hot Products.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* Hot Products – section-only modern grid polish */
.home-products .product-section-v2{
  padding: 56px 0;
  background:#fff;
}

.home-products .product-row-v2{
  justify-content:center;
}

.home-products .product-row-v2 > [class*="col-"]{
  display:flex;
}

.home-products .product-card-v2{
  width:100%;
  height:100%;
  display:flex;
  flex-direction:column;
  border-radius: 1px;
  border:1px solid #e8ecf1;
  box-shadow:0 4px 14px rgba(15, 23, 42, .06);
  padding:12px;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}

.home-products .product-card-v2:hover{
  transform: translateY(-3px);
  border-color:#dde4eb;
  box-shadow:0 12px 24px rgba(15, 23, 42, .10);
}

.home-products .product-image-container-v2{
  height:280px;
  margin:0;
  padding:0;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#fff;
  border-radius:5px;
  overflow:hidden;
}

.home-products .product-image-v2{
  width:auto;
  height:auto;
  max-width:100%;
  max-height:100%;
  object-fit:contain;
  display:block;
}

.home-products .product-details-v2{
  padding:12px 10px 8px;
  text-align:left;
  margin-top:auto;
}

.home-products .product-title-v2{
  min-height:0;
  margin:0;
  font-size:1rem;
  line-height:1;
  font-weight:700;
}

.home-products .product-title-v2 a{
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
  color:#0f1f35;
  text-decoration:none;
}

.home-products .product-title-v2 a:hover{
  color:#0b1a2f;
}

.home-products .product-rating-sold-v2{
  justify-content:flex-start;
  margin:2px 0;
}

.home-products .product-rating-v2{
  margin:0;
  color:#7a8594;
  font-size:.95rem;
  font-weight:500;
}

.home-products .product-old-price-v2{
  color:#9aa3af;
  font-size:.88rem;
  margin-right:6px;
}

.home-products .product-price-v2{
  color:#111827;
  font-size:1.02rem;
  font-weight:800;
}

.home-products #productTabs{
  border-bottom:0px solid #d7dee8;
}

.home-products #productTabs .nav-link{
  border:1px solid transparent;
  border-top-left-radius:5px;
  border-top-right-radius:5px;
  border-bottom-left-radius:5px;
  border-bottom-right-radius:5px;
  background:#fff;
  color:#111827;
  font-weight:500;
}

.home-products #productTabs .nav-link.active,
.home-products #productTabs .nav-link[aria-selected="true"]{
  background:#78be20;
  border-color:#78be20;
  color:#fff;
}

@media (max-width: 991.98px){
  .home-products .product-image-container-v2{ height:260px; }
}

@media (max-width: 575.98px){
  .home-products .product-row-v2 > [class*="col-"]{
    width:100%;
    max-width:100%;
    flex:0 0 100%;
  }
  .home-products .product-image-container-v2{ height:240px; }
  .home-products .product-title-v2{ font-size:.9rem; min-height:0; }
  .home-products .product-rating-v2{ font-size:.9rem; }
}
</style>

<!-- CTA Section -->
<?php /*
<section class="cta-section">
    <!-- CTA Image with Text and Button -->
    <div class="cta-banner">
        <img src="<?php echo $domain;?>assets/images/9.webp" alt="Promotional Banner" class="cta-image">
        <div class="cta-content">
            <h2 class="cta-title">Big Sale! Up to 50% Off</h2>
            <p class="cta-text">Shop the latest trends and save big on your favorite items. Limited time only!</p>
            <a href="<?php echo $domain; ?>shop" class="cta-button">Shop Now</a>
        </div>
    </div>

    <!-- Revolving Ticker for Promotional Coupons -->
    <div class="promo-ticker">
        <div class="ticker-content">
            <div class="ticker-item">
                <span class="ticker-text">Use code <strong>SAVE20</strong> for 20% off on all orders!</span>
                <span class="ticker-separator">|</span>
            </div>
            <div class="ticker-item">
                <span class="ticker-text">Free shipping on orders over <strong>PKR 5000</strong>!</span>
                <span class="ticker-separator">|</span>
            </div>
            <div class="ticker-item">
                <span class="ticker-text">Flash Sale: <strong>50% off</strong> on selected items!</span>
                <span class="ticker-separator">|</span>
            </div>
            <div class="ticker-item">
                <span class="ticker-text">New Arrivals: Shop now and get <strong>10% off</strong>!</span>
                <span class="ticker-separator">|</span>
            </div>
        </div>
    </div>
</section>

<section class="sale-section">
    <div class="container text-center">
        <p class="sale-subtitle">SEASON'S END SALE</p>
        <h2 class="sale-title">Huge discounts on last season’s styles</h2>
        <p class="sale-description">  Refresh your wardrobe for less! Enjoy exclusive discounts on last season’s best looks — limited time only.</p>

        <div class="row justify-content-center">
            <?php
            // Fetch products for the sale
            $saleProducts = false;
            $saleFlagCol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_sale_item'");
            if ($saleFlagCol && $saleFlagCol->num_rows > 0) {
                $saleQuery = "SELECT id, product_name, old_price, current_price, images, slug 
                              FROM products 
                              WHERE is_sale_item = 1
                              ORDER BY id DESC 
                              LIMIT 9"; // Displaying 9 products
                $saleProducts = $conn->query($saleQuery);
            }

            if ($saleProducts && $saleProducts->num_rows > 0) {
                while ($row = $saleProducts->fetch_assoc()) {
                    $productName = htmlspecialchars($row['product_name']);
                    $oldPrice = (float)$row['old_price'];
                    $price = (float)$row['current_price'];
                    $proslug = htmlspecialchars($row['slug']);
                    $images = json_decode($row['images'], true);
                    $imageUrl = is_array($images) && count($images) > 0 ? htmlspecialchars($images[0]) : 'assets/images/placeholder.png';
                    ?>

                    <div class="col-md-4 col-sm-6 mb-4">
                        <a href="<?php echo $domain;?>shop/<?= $proslug ?>" class="sale-product d-flex align-items-center text-decoration-none">
                            <img src="<?= $imageUrl ?>" alt="<?= $productName ?>" class="product-img">
                            <div class="ms-3 text-start">
                                <h3 class="product-name"><?= $productName ?></h3>
                                <p class="product-price">PKR <?= number_format($price, 2) ?></p>
                            </div>
                        </a>
                    </div>

                    <?php
                }
            } else {
                echo "<p>No sale products available.</p>";
            }
            ?>
        </div>
    </div>
</section>
*/?>
<?php
function ensure_customer_reviews_table($conn) {
  $conn->query("CREATE TABLE IF NOT EXISTS customer_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    review_text TEXT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    review_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    profile_image VARCHAR(2048) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customer_reviews_active_date (is_active, status, review_date, id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $statusCol = $conn->query("SHOW COLUMNS FROM customer_reviews LIKE 'status'");
  if ($statusCol && $statusCol->num_rows === 0) {
    $conn->query("ALTER TABLE customer_reviews ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER rating");
  }
}

function wdx_time_ago($datetime) {
  $ts = strtotime((string)$datetime);
  if (!$ts) return 'Recently';
  $diff = time() - $ts;
  if ($diff < 60) return 'Just now';
  if ($diff < 3600) {
    $m = (int)floor($diff / 60);
    return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
  }
  if ($diff < 86400) {
    $h = (int)floor($diff / 3600);
    return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
  }
  if ($diff < 2592000) {
    $d = (int)floor($diff / 86400);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
  }
  if ($diff < 31536000) {
    $mo = (int)floor($diff / 2592000);
    return $mo . ' month' . ($mo === 1 ? '' : 's') . ' ago';
  }
  $y = (int)floor($diff / 31536000);
  return $y . ' year' . ($y === 1 ? '' : 's') . ' ago';
}

ensure_customer_reviews_table($conn);

$reviewCount = 0;
$avgRating = 0.0;
$customerReviews = [];

$statsRes = $conn->query("SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS avg_rating FROM customer_reviews WHERE is_active = 1 AND status = 'approved'");
if ($statsRes) {
  $stats = $statsRes->fetch_assoc();
  $reviewCount = (int)($stats['total'] ?? 0);
  $avgRating = (float)($stats['avg_rating'] ?? 0);
}

$reviewsRes = $conn->query("SELECT id, customer_name, review_text, rating, review_date, profile_image FROM customer_reviews WHERE is_active = 1 AND status = 'approved' ORDER BY review_date DESC, id DESC LIMIT 6");
if ($reviewsRes) {
  $customerReviews = $reviewsRes->fetch_all(MYSQLI_ASSOC);
}

$avgRounded = max(0, min(5, (int)round($avgRating)));
?>

<section class="wdx-customer-reviews" id="customer-reviews">
  <div class="container">
    <h2 class="wdx-cr-main-title">What Woodex Customers Are Saying</h2>

    <div class="wdx-cr-layout">
      <div class="wdx-cr-left">
        <h3>Review Us</h3>
        <div class="wdx-cr-stars" aria-label="Average rating <?= number_format($avgRating, 1) ?> out of 5">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="<?= $i <= $avgRounded ? 'filled' : '' ?>">★</span>
          <?php endfor; ?>
        </div>
        <p class="wdx-cr-total"><?= number_format($reviewCount) ?> Google reviews</p>
        <button type="button" class="wdx-cr-btn" data-bs-toggle="modal" data-bs-target="#wdxReviewModal">Write a review</button>
      </div>

      <div class="wdx-cr-right">
        <div class="wdx-cr-carousel owl-carousel">
          <?php if (!empty($customerReviews)): ?>
            <?php foreach ($customerReviews as $review): ?>
              <?php
                $name = trim((string)($review['customer_name'] ?? 'Customer'));
                if ($name === '') $name = 'Customer';
                $initial = strtoupper(substr($name, 0, 1));
                $rating = max(0, min(5, (int)($review['rating'] ?? 0)));
                $timeAgo = wdx_time_ago($review['review_date'] ?? '');
                $text = trim((string)($review['review_text'] ?? ''));
                $imgPath = trim((string)($review['profile_image'] ?? ''));
                $imgUrl = $imgPath !== '' ? wdx_join_url($domain, $imgPath) : '';
              ?>
              <article class="wdx-cr-card">
                <div class="wdx-cr-card-head">
                  <?php if ($imgUrl !== ''): ?>
                    <img class="wdx-cr-avatar-img" src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                  <?php else: ?>
                    <div class="wdx-cr-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <div>
                    <h4><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h4>
                    <p><?= htmlspecialchars($timeAgo, ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
                <div class="wdx-cr-card-stars" aria-label="<?= (int)$rating ?> out of 5 stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="<?= $i <= $rating ? 'filled' : '' ?>">★</span>
                  <?php endfor; ?>
                </div>
                <p class="wdx-cr-card-text"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <article class="wdx-cr-card">
              <div class="wdx-cr-card-head">
                <div class="wdx-cr-avatar">W</div>
                <div>
                  <h4>Woodex Customer</h4>
                  <p>Recently</p>
                </div>
              </div>
              <div class="wdx-cr-card-stars"><span class="filled">★</span><span class="filled">★</span><span class="filled">★</span><span class="filled">★</span><span class="filled">★</span></div>
              <p class="wdx-cr-card-text">Add your first customer review from admin to show testimonials here.</p>
            </article>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade wdx-review-modal" id="wdxReviewModal" tabindex="-1" aria-labelledby="wdxReviewModalLabel" aria-hidden="true" style="z-index:2000;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="wdxReviewModalLabel">Write a review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert d-none" id="wdxReviewMsg"></div>
        <form id="wdxReviewForm">
          <div class="mb-3">
            <label class="form-label">Your Name</label>
            <input type="text" class="form-control" name="customer_name" required maxlength="150">
          </div>
          <div class="mb-3">
            <label class="form-label">Rating</label>
            <select class="form-select" name="rating" required>
              <option value="5">5 Stars</option>
              <option value="4">4 Stars</option>
              <option value="3">3 Stars</option>
              <option value="2">2 Stars</option>
              <option value="1">1 Star</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Review</label>
            <textarea class="form-control" name="review_text" rows="4" required maxlength="1500"></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100" id="wdxReviewSubmit">Submit Review</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.wdx-customer-reviews{
  background:#f4f5f6;
  padding: 56px 0;
}
.wdx-cr-main-title{
  text-align:center;
  margin:0 0 30px;
  color:#10233a;
  font-weight:800;
  font-size:clamp(1.6rem, 2.6vw, 2.25rem);
}
.wdx-cr-layout{
  display:grid;
  grid-template-columns: 280px 1fr;
  gap: 28px;
  align-items:start;
}
.wdx-cr-left h3{
  margin:0 0 6px;
  font-size:2rem;
  line-height:1.3;
  font-weight:800;
  color:#111827;
}
.wdx-cr-stars{ margin:0 0 4px; }
.wdx-cr-stars span{ color:#cfd5db; font-size:1.06rem; margin-right:1px; }
.wdx-cr-stars span.filled{ color:#f4c531; }
.wdx-cr-total{ margin:0 0 16px; color:#64748b; font-size:.93rem; }
.wdx-cr-btn{
  display:inline-block;
  border:1.5px solid #78be20;
  border-radius:2px;
  padding:.58rem 1.05rem;
  color:#111;
  background:#fff;
  text-decoration:none;
  font-weight:700;
  line-height:1;
}
.wdx-cr-btn:hover{ background:#78be20; color:#fff; }

.wdx-cr-card{
  background:#f1f3f4;
  border:1px solid #dbe1e7;
  border-radius:12px;
  padding:18px;
  box-shadow:0 8px 18px rgba(15, 23, 42, .08);
  min-height:178px;
  display:flex;
  flex-direction:column;
}
.wdx-cr-card-head{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
.wdx-cr-avatar,
.wdx-cr-avatar-img{
  width:44px;
  height:44px;
  border-radius:50%;
  flex:0 0 44px;
}
.wdx-cr-avatar{
  background:#dcebd0;
  color:#5f874f;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:800;
}
.wdx-cr-avatar-img{ object-fit:cover; display:block; }
.wdx-cr-card-head h4{
  margin:0;
  color:#14273f;
  font-size:1rem;
  font-weight:800;
  line-height:1.3;
}
.wdx-cr-card-head p{
  margin:2px 0 0;
  color:#718096;
  font-size:.84rem;
}
.wdx-cr-card-stars{ margin:0 0 10px; }
.wdx-cr-card-stars span{ color:#d2d8de; font-size:.95rem; }
.wdx-cr-card-stars span.filled{ color:#f4c531; }
.wdx-cr-card-text{
  margin:0;
  color:#4f5f70;
  font-size:.94rem;
  line-height:1.5;
}

.wdx-review-modal .modal-dialog{ max-width:560px; }
.wdx-review-modal .modal-content{
  margin:0;
  width:100%;
  max-width:none;
  border-radius:12px;
  border:1px solid #dbe1e7;
  padding:0;
}
.wdx-review-modal .modal-body{ padding:1rem 1.25rem 1.25rem; }
.wdx-review-modal .form-control,
.wdx-review-modal .form-select{
  pointer-events:auto;
}

@media (max-width: 1199.98px){
  .wdx-cr-layout{ grid-template-columns: 1fr; }
}

.wdx-cr-carousel .owl-stage{
  display:flex;
}
.wdx-cr-carousel .owl-item{
  display:flex;
}
.wdx-cr-carousel .owl-item .wdx-cr-card{
  width:100%;
}
.wdx-cr-carousel .owl-nav{
  display:flex;
  justify-content:center;
  gap:12px;
  margin-top:14px;
}
.wdx-cr-carousel .owl-nav button.owl-prev,
.wdx-cr-carousel .owl-nav button.owl-next{
  width:34px;
  height:34px;
  border-radius:999px;
  border:1px solid #d3d9e0 !important;
  background:#fff !important;
  color:#2b3d52 !important;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:16px !important;
  line-height:1;
}
.wdx-cr-carousel .owl-nav button.owl-prev:hover,
.wdx-cr-carousel .owl-nav button.owl-next:hover{
  background:#78be20 !important;
  border-color:#78be20 !important;
  color:#fff !important;
}
.wdx-cr-carousel .owl-dots{
  margin-top:10px;
}
.wdx-cr-carousel .owl-dots .owl-dot span{
  width:8px;
  height:8px;
  background:#c7d0da;
}
.wdx-cr-carousel .owl-dots .owl-dot.active span,
.wdx-cr-carousel .owl-dots .owl-dot:hover span{
  background:#78be20;
}

@media (max-width: 767.98px){
  .wdx-customer-reviews{ padding:42px 0; }
}
</style>
<!-- ================== EXPLORE (Logos / social proof) ================== -->
<section class="explore-band">
  <div class="container">
    <header class="explore-head">
      <h2>Our Prestigious Clients</h2>
      <p>Join 1,200+ satisfied clients across Pakistan who trust WoodEx for their office furniture needs.</p>
    </header>

    <!-- Logo row -->
    <div class="explore-logos">
      <!-- Replace src with your real logo files (SVG/PNG). Keep similar aspect for best results -->
      <img src="assets/images/clients/etihad-town-300x88.jpg"     alt="Etihad Town">
      <img src="assets/images/clients/Haier.jpg"      alt="Haier">
      <img src="assets/images/clients/mindbridge.jpg" alt="MindBridge">
      <img src="assets/images/clients/Pachem-Global-Pvt-Limited.jpg"     alt="Pachem Global">
      <img src="assets/images/clients/PAF.jpg"        alt="Pakistan Air Force">
    </div>
  </div>
</section>

<?php
$latestProjects = [];
$projectsTableExists = false;
$projectsTableCheck = $conn->query("SHOW TABLES LIKE 'projects'");
if ($projectsTableCheck && $projectsTableCheck->num_rows > 0) {
  $projectsTableExists = true;
}

if ($projectsTableExists) {
  $latestProjectsRes = $conn->query("SELECT id, title, slug, category, location_city, area_sqft, cover_image, short_description, created_at FROM projects WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 3");
  if ($latestProjectsRes) {
    $latestProjects = $latestProjectsRes->fetch_all(MYSQLI_ASSOC);
  }
}

if (!function_exists('wdx_excerpt')) {
  function wdx_excerpt($text, $max = 125) {
    $text = trim((string)$text);
    if ($text === '') {
      return '';
    }

    if (function_exists('mb_strimwidth')) {
      return mb_strimwidth($text, 0, (int)$max, '...');
    }

    if (strlen($text) <= (int)$max) {
      return $text;
    }

    return rtrim(substr($text, 0, (int)$max)) . '...';
  }
}
?>

<!-- ================== Latest Projects ================== -->
<section
  class="workspace-showcase"
>
  <div class="container">
    <div class="workspace-head">
      <h2 class="workspace-title">Workspace Trasfprmation</h2>
      <p class="workspace-sub">Transforming Spaces with Design that Blends Creativity, Comfort, and Function</p>
    </div>

    <?php if (!empty($latestProjects)): ?>
      <div class="workspace-carousel-wrap">
        <div class="owl-carousel workspace-carousel">
        <?php foreach ($latestProjects as $project): ?>
          <?php
            $projTitle = trim((string)($project['title'] ?? 'Project'));
            $projSlug = trim((string)($project['slug'] ?? ''));
            $projCategory = trim((string)($project['category'] ?? ''));
            $projLocation = trim((string)($project['location_city'] ?? ''));
            $projArea = trim((string)($project['area_sqft'] ?? ''));
            $projImage = trim((string)($project['cover_image'] ?? ''));
            $projDesc = trim((string)($project['short_description'] ?? ''));
            $projYear = date('Y', strtotime((string)($project['created_at'] ?? 'now')));
            $projUrl = $projSlug !== '' ? ($domain . 'projects/' . rawurlencode($projSlug)) : ($domain . 'projects');
            $projImgUrl = $projImage !== '' ? wdx_join_url($domain, $projImage) : wdx_join_url($domain, 'assets/images/placeholder.png');
          ?>
          <div class="workspace-item">
            <article class="workspace-card">
              <a class="workspace-media" href="<?= htmlspecialchars($projUrl, ENT_QUOTES, 'UTF-8') ?>">
                <img loading="lazy" src="<?= htmlspecialchars($projImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($projTitle, ENT_QUOTES, 'UTF-8') ?>">
                <span class="workspace-year"><?= htmlspecialchars($projYear, ENT_QUOTES, 'UTF-8') ?></span>
              </a>
              <div class="workspace-body">
                <div class="workspace-pills">
                  <?php if ($projCategory !== ''): ?>
                    <span class="workspace-pill workspace-pill-cat"><?= htmlspecialchars($projCategory, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <?php if ($projArea !== ''): ?>
                    <span class="workspace-pill workspace-pill-metric"><?= htmlspecialchars($projArea, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
                <h3><a href="<?= htmlspecialchars($projUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($projTitle, ENT_QUOTES, 'UTF-8') ?></a></h3>
                <?php if ($projLocation !== ''): ?>
                  <p class="workspace-location"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($projLocation, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($projDesc !== ''): ?>
                  <p class="workspace-desc"><?= htmlspecialchars(wdx_excerpt($projDesc, 125), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="workspace-empty">No projects found. Add projects from admin panel to show them here.</p>
    <?php endif; ?>

    <div class="workspace-actions">
      <a href="projects" class="workspace-btn">View Projects</a>
    </div>
  </div>
</section>

<style>
/* ========= Explore band ========= */
.explore-band{
  background:#fff;
  padding: 48px 0 40px;
}
.explore-head{
  text-align:center;
  margin-bottom: 22px;
}
.explore-head h2{
  margin:0 0 8px;
  font-weight: 800;
  color:#111;
  font-size: clamp(1.4rem, 2.4vw, 2rem);
}
.explore-head p{
  margin:0;
  color:#636a72;
}

/* Logo row */
.explore-logos{
  display:grid;
  gap: clamp(18px, 3vw, 42px);
  align-items:center;
  justify-items:center;
  grid-template-columns: repeat(5, 1fr);
  padding: 8px 0 6px;
}
.explore-logos img{
  max-height:48px;
  width:auto;
  filter: grayscale(100%) contrast(0.95);
  opacity:.85;
  transition: filter .2s ease, opacity .2s ease, transform .2s ease;
}
.explore-logos img:hover{
  filter: grayscale(0);
  opacity:1;
  transform: translateY(-2px);
}

/* Stack logos on small screens */
@media (max-width: 991.98px){
  .explore-logos{ grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 575.98px){
  .explore-logos{ grid-template-columns: repeat(2, 1fr); }
  .explore-logos img{ max-height:40px; }
}

.workspace-showcase{
  background:#f3f4f6;
  padding:56px 0 54px;
}
.workspace-head{
  text-align:center;
  margin-bottom:22px;
}
.workspace-title{
  margin:0 0 6px;
  color:#11253e;
  font-size:clamp(2rem, 3.5vw, 2.95rem);
  font-weight:800;
}
.workspace-sub{
  margin:0;
  color:#7a8593;
  font-size:1.02rem;
}

.workspace-carousel-wrap{ position:relative; }
.workspace-item{ padding:2px 4px; }
.workspace-card{
  background:#fff;
  border:1px solid #d9dee5;
  border-radius:6px;
  overflow:hidden;
  box-shadow:0 5px 14px rgba(15, 23, 42, .08);
}
.workspace-media{
  display:block;
  position:relative;
  height:286px;
  background:#f3f5f7;
}
.workspace-media img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}
.workspace-year{
  position:absolute;
  top:10px;
  right:10px;
  background:rgba(255,255,255,.92);
  color:#455467;
  border:1px solid #d3d9df;
  font-size:.76rem;
  border-radius:999px;
  padding:2px 8px;
  font-weight:700;
}
.workspace-body{ padding:12px 14px 14px; }
.workspace-pills{ display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.workspace-pill{
  display:inline-flex;
  align-items:center;
  border-radius:999px;
  border:1px solid #d5dec7;
  padding:2px 10px;
  font-size:.78rem;
  font-weight:700;
  line-height:1.2;
}
.workspace-pill-cat{ color:#73aa26; background:#edf6df; }
.workspace-pill-metric{ color:#4b5563; background:#f4f6f8; border-color:#dde3ea; }
.workspace-body h3{
  margin:0 0 4px;
  font-size:1.2rem;
  line-height:1.3;
  font-weight:800;
}
.workspace-body h3 a{ color:#192a41; text-decoration:none; }
.workspace-location{
  margin:0 0 6px;
  color:#6b7280;
  font-size:.86rem;
}
.workspace-desc{
  margin:0;
  color:#64707b;
  font-size:.93rem;
  line-height:1.45;
}
.workspace-empty{ text-align:center; color:#6b7280; margin:0 0 18px; }

.workspace-carousel .owl-nav button.owl-prev,
.workspace-carousel .owl-nav button.owl-next{
  position:absolute;
  top:44%;
  transform:translateY(-50%);
  width:40px;
  height:40px;
  border-radius:50%;
  border:1px solid #d4d9df !important;
  background:#eceff2 !important;
  color:#8a94a0 !important;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.workspace-carousel .owl-nav button.owl-prev{ left:-62px; }
.workspace-carousel .owl-nav button.owl-next{ right:-62px; }
.workspace-carousel .owl-nav button.owl-prev:hover,
.workspace-carousel .owl-nav button.owl-next:hover{
  background:#78be20 !important;
  border-color:#78be20 !important;
  color:#fff !important;
}
.workspace-carousel .owl-dots{ margin-top:14px; }
.workspace-carousel .owl-dots .owl-dot span{ background:#c7d0da; width:8px; height:8px; }
.workspace-carousel .owl-dots .owl-dot.active span{ background:#78be20; }

.workspace-actions{ display:flex; justify-content:center; margin-top:24px; }
.workspace-btn{
  display:inline-block;
  border:1.5px solid #78be20;
  color:#111;
  font-weight:700;
  text-decoration:none;
  border-radius:2px;
  padding:.78rem 1.6rem;
  background:#fff;
}
.workspace-btn:hover{ background:#78be20; color:#fff; }

@media (max-width: 1399.98px){
  .workspace-carousel .owl-nav button.owl-prev{ left:-20px; }
  .workspace-carousel .owl-nav button.owl-next{ right:-20px; }
}
@media (max-width: 991.98px){
  .workspace-media{ height:240px; }
}
@media (max-width: 767.98px){
  .workspace-showcase{ padding:44px 0 46px; }
  .workspace-carousel .owl-nav button.owl-prev,
  .workspace-carousel .owl-nav button.owl-next{ display:none; }
}
</style>

<style>
/* Unified section heading style: Market We Served */
.services-intro h2,
.sbc-title,
.os-title,
.product-section-title-v2,
.wdx-cr-main-title,
.explore-head h2,
.workspace-title,
.cta-title,
section.py-5 > .container > h2.fw-bold{
  font-family: inherit;
  font-weight: 800;
  font-size: clamp(1.1rem, 2.2vw, 1.6rem);
  line-height: 1.5;
  color: #111;
  letter-spacing: 0;
}
</style>

<section class="py-5">
  <div class="container">

    <!-- Heading -->
    <h2 class="fw-bold mb-4">FAQs – Woodex</h2>

    <!-- Small About Section -->
    <div class="card border-0 shadow-sm mb-5">
      <div class="card-header bg-white fw-bold fs-5">
        Modern Office Furniture Lahore Pakistan
      </div>
      <div class="card-body text-secondary lh-lg">
        <p>Ergonomic Office Chairs in Pakistan – Comfort That Works as Hard as You Do! At Woodex, we design ergonomic office chairs that go beyond basic seating. These chairs are built to support your posture, reduce strain, and keep you comfortable throughout long working hours. By combining Master MoltyFoam cushioning, adjustable features, and premium materials, Woodex chairs help improve not only your health but also your focus and productivity at work.</p>

        <p>A great chair isn’t just where you sit—it’s the foundation of a smarter, healthier, and more productive workspace. Poor seating leads to back pain, fatigue, and low productivity.</p>

        <p>At Woodex Furniture Pakistan, our ergonomic chairs are designed with adjustable support and durable construction to help you stay productive and comfortable throughout your workday.</p>
      </div>
    </div>

    <!-- FAQ Rows -->
    <div class="row g-4">

      <!-- Column 1 -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="accordion" id="faqColumn1">
          <div class="accordion-item">
            <h2 class="accordion-header" id="q1-1">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a1-1">
                Contact Us for More Options
              </button>
            </h2>
            <div id="a1-1" class="accordion-collapse collapse" data-bs-parent="#faqColumn1">
              <div class="accordion-body">We provide custom sizing, finishes & workspace planning suggestions.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q1-2">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a1-2">
                Are Woodex chairs durable?
              </button>
            </h2>
            <div id="a1-2" class="accordion-collapse collapse" data-bs-parent="#faqColumn1">
              <div class="accordion-body">Yes — built with alloy steel frames & high-density cushioning.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q1-3">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a1-3">
                Do Woodex chairs come assembled?
              </button>
            </h2>
            <div id="a1-3" class="accordion-collapse collapse" data-bs-parent="#faqColumn1">
              <div class="accordion-body">We offer both DIY assembly kits and full installation services.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q1-4">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a1-4">
                Where does Woodex deliver?
              </button>
            </h2>
            <div id="a1-4" class="accordion-collapse collapse" data-bs-parent="#faqColumn1">
              <div class="accordion-body">Nationwide delivery is available across Pakistan.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q1-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a1-5">
                What types of ergonomic chairs are available?
              </button>
            </h2>
            <div id="a1-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn1">
              <div class="accordion-body">Executive, manager, mesh-back, visitor, and conference seating.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Column 2 -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="accordion" id="faqColumn2">
          <div class="accordion-item">
            <h2 class="accordion-header" id="q2-1">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-1">
                Contact Us for More Options
              </button>
            </h2>
            <div id="a2-1" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">We assist with workplace planning & layout customization.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q2-2">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-2">
                Are Woodex chairs durable?
              </button>
            </h2>
            <div id="a2-2" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Backrest tilt, lumbar support & breathable materials ensure long use.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q2-3">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-3">
                Do Woodex chairs have warranty?
              </button>
            </h2>
            <div id="a2-3" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">1–3 year product warranty depending on model.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q2-4">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-4">
                Where does Woodex deliver?
              </button>
            </h2>
            <div id="a2-4" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Priority shipping available for Lahore, Karachi & Islamabad.</div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Column 3 (copy same structure) -->
      <div class="col-12 col-md-6 col-lg-4">
        <div class="accordion" id="faqColumn3">
                   <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>         <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>         <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>         <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>         <div class="accordion-item">
            <h2 class="accordion-header" id="q2-5">
              <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#a2-5">
                What types of ergonomic chairs?
              </button>
            </h2>
            <div id="a2-5" class="accordion-collapse collapse" data-bs-parent="#faqColumn2">
              <div class="accordion-body">Choose based on posture needs & desk height.</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<?php include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

<script>
    $(document).ready(function() {
        const popularCatCount = $('.popular-cats .cat-card').length;
        $('.popular-cats .cat-strip').owlCarousel({
            loop: popularCatCount > 6,
            rewind: true,
            margin: 20,
            nav: popularCatCount > 1,
            dots: false,
            navText: ['<i class="bi bi-chevron-left"></i>', '<i class="bi bi-chevron-right"></i>'],
            autoplay: popularCatCount > 1,
            autoplayTimeout: 1500,
            autoplayHoverPause: true,
            smartSpeed: 450,
            responsive: {
                0: { items: 2, margin: 12 },
                576: { items: 3, margin: 16 },
                768: { items: 4, margin: 18 },
                992: { items: 5, margin: 20 },
                1200: { items: 6, margin: 24 }
            }
        });

        $('.categories-slider').owlCarousel({
            loop: true,
            margin: 10,
            nav: false,
            autoplay:true,
            autoplayTimeout:3000,
            autoplayHoverPause:true,
            responsive: {
                0: {
                    items: 1
                },
                600: {
                    items: 2
                },
                1000: {
                    items: 3
                }
            }
        }),
        $(".home-review-slider").owlCarousel({
                loop: true,
                margin: 20,
                nav: false,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                autoplayHoverPause: true,
                responsive: {
                    0: { items: 1 },
                    600: { items: 2 },
                    1000: { items: 3 }
                }
            }),
        $(".wdx-cr-carousel").owlCarousel({
            loop: true,
            margin: 24,
            nav: true,
            dots: true,
            navText: ['<i class="bi bi-chevron-left"></i>', '<i class="bi bi-chevron-right"></i>'],
            autoplay: true,
            autoplayTimeout: 4500,
            autoplayHoverPause: true,
            responsive: {
                0: { items: 1 },
                768: { items: 2 },
                1200: { items: 3 }
            }
        });

        const workspaceCount = $('.workspace-carousel .workspace-item').length;
        $(".workspace-carousel").owlCarousel({
            loop: workspaceCount > 3,
            margin: 16,
            nav: true,
            dots: false,
            navText: ['<i class="bi bi-chevron-left"></i>', '<i class="bi bi-chevron-right"></i>'],
            autoplay: false,
            responsive: {
                0: { items: 1 },
                768: { items: 2 },
                1200: { items: 3 }
            }
        });

        $('#wdxReviewForm').on('submit', function(e){
            e.preventDefault();
            const $form = $(this);
            const $btn = $('#wdxReviewSubmit');
            const $msg = $('#wdxReviewMsg');

            $btn.prop('disabled', true).text('Submitting...');
            $msg.addClass('d-none').removeClass('alert-success alert-danger').text('');

            $.ajax({
                url: '<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?>api/reviews/submit-customer-review.php',
                type: 'POST',
                dataType: 'json',
                data: $form.serialize(),
                success: function(res){
                    if (res && res.success) {
                        $msg.removeClass('d-none').addClass('alert-success').text('Thanks! Your review is submitted and waiting for admin approval.');
                        $form[0].reset();
                    } else {
                        $msg.removeClass('d-none').addClass('alert-danger').text((res && res.message) ? res.message : 'Unable to submit review.');
                    }
                },
                error: function(xhr){
                    let message = 'Network error. Please try again.';
                    if (xhr && xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                message = parsed.message;
                            }
                        } catch (e) {}
                    }
                    $msg.removeClass('d-none').addClass('alert-danger').text(message);
                },
                complete: function(){
                    $btn.prop('disabled', false).text('Submit Review');
                }
            });
        });

    });
</script>

<script src="<?php echo $domain;?>assets/script/cart.js"></script>
<script>
$(document).ready(function(){
    $('#subscribe-btn').on('click', function(e) {
        e.preventDefault();
        
        var email = $('#newsletter-email').val().trim();
        if (email === '') {
            alert('Please enter a valid email address.');
            return;
        }

        $.ajax({
            url: 'subscribe.php',
            type: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#newsletter-message').text('Thank you for subscribing!').css('color', 'green').show();
                    $('#newsletter-email').val('');
                } else {
                    $('#newsletter-message').text(response.message).css('color', 'red').show();
                }
            },
            error: function() {
                $('#newsletter-message').text('An error occurred. Please try again.').css('color', 'red').show();
            }
        });
    });
});
</script>
<script>
(function(){
  const viewport = document.querySelector('.hero-viewport');
  const track    = document.querySelector('.hero-track');
  const prevBtn  = document.querySelector('.hero-prev');
  const nextBtn  = document.querySelector('.hero-next');
  const pager    = document.querySelector('.hero-pagination');
  if (!viewport || !track) return;

  // ----- Build base list & clones -----
  const realSlides = Array.from(track.children);
  const N = realSlides.length;
  if (!N) return;

  pager.innerHTML = '';
  for (let i = 0; i < N; i++) {
    const dot = document.createElement('button');
    dot.className = 'hero-dot'; dot.type = 'button';
    dot.setAttribute('role','tab');
    dot.setAttribute('aria-label', `Go to slide ${i+1}`);
    dot.addEventListener('click', () => goTo(i+1)); // +1 because of leading clone
    pager.appendChild(dot);
  }

  // clones
  const firstClone = realSlides[0].cloneNode(true);
  const lastClone  = realSlides[N-1].cloneNode(true);
  track.insertBefore(lastClone, realSlides[0]);
  track.appendChild(firstClone);

  let idx = 1;                               // [0]=lastClone, [1..N]=real, [N+1]=firstClone
  let width = viewport.clientWidth;
  let isAnimating = false;                   // prevents double updates during snap
  const TRANSITION = 'transform .45s cubic-bezier(.22,.61,.36,1)';

  function setTransition(on){
    track.style.transition = on ? TRANSITION : 'none';
  }
  function setTransform(){
    track.style.transform = `translateX(${-idx * width}px)`;
  }
  function forceReflow(){ /* ensure the browser applies styles in this frame */
    void track.offsetHeight;                 // reading layout flushes the style changes
  }
  function updateDots(){
    const dots = pager.querySelectorAll('.hero-dot');
    const logical = (idx - 1 + N) % N;       // 0..N-1 (real slides)
    dots.forEach((d,i)=> d.setAttribute('aria-selected', i===logical ? 'true' : 'false'));
  }

  function goTo(targetIdx){
    if (isAnimating) return;
    isAnimating = true;
    idx = targetIdx;
    setTransition(true);
    setTransform();
  }
  function prev(){ goTo(idx - 1); }
  function next(){ goTo(idx + 1); }

  // ----- Transition end: handle clones and restart autoplay
  track.addEventListener('transitionend', () => {
    // If we landed on a clone, snap to the corresponding real slide with transition OFF
    if (idx === 0) {
      setTransition(false);
      idx = N; setTransform(); forceReflow();       // snap to last real
      setTransition(true);
    } else if (idx === N + 1) {
      setTransition(false);
      idx = 1; setTransform(); forceReflow();       // snap to first real
      setTransition(true);
    }
    isAnimating = false;
    updateDots();
    scheduleAuto();                                   // only schedule after we’re settled
  });

  // ----- Controls
  prevBtn.addEventListener('click', () => { cancelAuto(); prev(); });
  nextBtn.addEventListener('click', () => { cancelAuto(); next(); });

  // Keyboard & swipe
  viewport.setAttribute('tabindex','0');
  viewport.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft')  { cancelAuto(); prev(); }
    if (e.key === 'ArrowRight') { cancelAuto(); next(); }
  });
  let startX=null;
  viewport.addEventListener('pointerdown', e => startX = e.clientX);
  viewport.addEventListener('pointerup',   e => {
    if (startX == null) return;
    const dx = e.clientX - startX;
    if (dx > 40) { cancelAuto(); prev(); }
    if (dx < -40){ cancelAuto(); next(); }
    startX = null;
  });

  // ----- Autoplay (fires only after a slide has fully settled)
  const INTERVAL = 5000;
  let autoTimer = null;
  function scheduleAuto(){
    cancelAuto();
    autoTimer = setTimeout(() => next(), INTERVAL);
  }
  function cancelAuto(){ if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; } }

  viewport.addEventListener('mouseenter', cancelAuto);
  viewport.addEventListener('mouseleave', scheduleAuto);

  // ----- Keep alignment on resize (no flash)
  window.addEventListener('resize', () => {
    width = viewport.clientWidth;
    setTransition(false); setTransform(); forceReflow();
    setTransition(true);
  });

  // ----- Init position + dots + autoplay
  setTransition(false);
  setTransform(); forceReflow();
  setTransition(true);
  updateDots();
  scheduleAuto();
})();
</script>

<script>
(function(){
  const viewport = document.querySelector('.hero2-viewport');
  const track    = document.querySelector('.hero2-track');
  const prevBtn  = document.querySelector('.hero2-prev');
  const nextBtn  = document.querySelector('.hero2-next');
  const pager    = document.querySelector('.hero2-pagination');
  if (!viewport || !track) return;

  const realSlides = Array.from(track.children);
  const N = realSlides.length;
  if (!N) return;

  pager.innerHTML = '';
  for (let i = 0; i < N; i++) {
    const dot = document.createElement('button');
    dot.className = 'hero2-dot';
    dot.type = 'button';
    dot.setAttribute('role', 'tab');
    dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
    dot.addEventListener('click', () => goTo(i + 1));
    pager.appendChild(dot);
  }

  const firstClone = realSlides[0].cloneNode(true);
  const lastClone = realSlides[N - 1].cloneNode(true);
  track.insertBefore(lastClone, realSlides[0]);
  track.appendChild(firstClone);

  let idx = 1;
  let width = viewport.clientWidth;
  let isAnimating = false;
  const TRANSITION = 'transform .45s cubic-bezier(.22,.61,.36,1)';

  function setTransition(on) { track.style.transition = on ? TRANSITION : 'none'; }
  function setTransform() { track.style.transform = `translateX(${-idx * width}px)`; }
  function forceReflow() { void track.offsetHeight; }
  function updateDots() {
    const logical = (idx - 1 + N) % N;
    pager.querySelectorAll('.hero2-dot').forEach((d, i) => d.setAttribute('aria-selected', i === logical ? 'true' : 'false'));
  }
  function goTo(targetIdx) {
    if (isAnimating) return;
    isAnimating = true;
    idx = targetIdx;
    setTransition(true);
    setTransform();
  }
  function prev() { goTo(idx - 1); }
  function next() { goTo(idx + 1); }

  track.addEventListener('transitionend', () => {
    if (idx === 0) {
      setTransition(false); idx = N; setTransform(); forceReflow(); setTransition(true);
    } else if (idx === N + 1) {
      setTransition(false); idx = 1; setTransform(); forceReflow(); setTransition(true);
    }
    isAnimating = false;
    updateDots();
    scheduleAuto();
  });

  prevBtn && prevBtn.addEventListener('click', () => { cancelAuto(); prev(); });
  nextBtn && nextBtn.addEventListener('click', () => { cancelAuto(); next(); });

  let startX = null;
  viewport.addEventListener('pointerdown', e => startX = e.clientX);
  viewport.addEventListener('pointerup', e => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    if (dx > 40) { cancelAuto(); prev(); }
    if (dx < -40) { cancelAuto(); next(); }
    startX = null;
  });

  const INTERVAL = 5500;
  let autoTimer = null;
  function scheduleAuto() {
    cancelAuto();
    autoTimer = setTimeout(() => next(), INTERVAL);
  }
  function cancelAuto() {
    if (autoTimer) {
      clearTimeout(autoTimer);
      autoTimer = null;
    }
  }

  viewport.addEventListener('mouseenter', cancelAuto);
  viewport.addEventListener('mouseleave', scheduleAuto);

  window.addEventListener('resize', () => {
    width = viewport.clientWidth;
    setTransition(false); setTransform(); forceReflow(); setTransition(true);
  });

  setTransition(false);
  setTransform();
  forceReflow();
  setTransition(true);
  updateDots();
  scheduleAuto();
})();
</script>

<script>
$(function(){
  const $car = $('.sbc-carousel').owlCarousel({
    loop:false,
    margin:16,
    nav:false,
    dots:false,
    smartSpeed:450,
    responsive:{
      0:{ items:1 },
      576:{ items:2 },
      992:{ items:3 },
      1200:{ items:4 }
    }
  });
  $('.sbc-prev').on('click', ()=> $car.trigger('prev.owl.carousel'));
  $('.sbc-next').on('click', ()=> $car.trigger('next.owl.carousel'));
});
</script>

</body>
</html>
