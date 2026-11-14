<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">

  <title>Materials & Colors • WoodEx Furniture Pakistan</title>
  <meta name="description" content="Explore premium materials, finishes, and color options to customize your office furniture with WoodEx Furniture Pakistan.">

  <!-- Fonts (match your other page) -->

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel (used like your other page) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    /* Page-specific (keeps your site tokens/colors from style.css) */
    .mat-hero .wdx-hero-title { margin-bottom: 10px; }
    .mat-hero .wdx-desc { max-width: 640px; }
    .mat-hero .wdx-input { max-width: 320px; }

    .mat-statbar {
      background: #78b12b; /* close to the green bar seen in PDF */
      color: #fff;
      padding: 18px 0;
    }
    .mat-statbar .mat-stat-number {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 800;
      font-size: 1.4rem;
      line-height: 1;
    }
    .mat-statbar .mat-stat-label {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      opacity: .95;
      font-size: .9rem;
      margin-top: 6px;
    }

    .mat-wrap { padding: 60px 0; background: #fff; }
    .mat-kicker {
      letter-spacing: .16em;
      font-weight: 800;
      font-size: .75rem;
      opacity: .75;
      text-transform: uppercase;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .mat-title {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 800;
      color: #0f172a;
      margin: 8px 0 10px;
    }
    .mat-subtitle {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: #334155;
      max-width: 820px;
      margin: 0 auto;
    }

    /* Why Stand Out block (matches PDF layout: image left + bullets right) */
    .mat-why { padding: 50px 0; background: #fff; }
    .mat-why-card {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(2,6,23,.06);
      background: #fff;
    }
    .mat-why-media img {
      width: 100%;
      height: 100%;
      min-height: 260px;
      object-fit: cover;
      display: block;
    }
    .mat-why-body { padding: 28px; }
    .mat-why-list { margin: 0; padding: 0; list-style: none; }
    .mat-why-list li {
      display: flex;
      gap: 10px;
      margin: 10px 0;
      color: #334155;
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .mat-why-list i { color: #78b12b; margin-top: 3px; }

    /* Swatch sections (carousel rows like PDF) */
    .mat-swatches { padding: 55px 0; background: #fff; }
    .mat-section-head { margin-bottom: 14px; }
    .mat-section-head h3 {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 800;
      color: #0f172a;
      margin: 0 0 6px;
      font-size: 1.35rem;
    }
    .mat-section-head p {
      margin: 0;
      color: #475569;
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .swatch-card {
      border: 1px solid rgba(15,23,42,.10);
      border-radius: 12px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 6px 16px rgba(2,6,23,.05);
    }
    .swatch-sample {
      height: 110px;
      background: #e2e8f0;
    }
    .swatch-label {
      padding: 10px 12px;
      text-align: center;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 700;
      font-size: .88rem;
      color: #0f172a;
      background: #fff;
    }

    /* Owl arrows */
    .owl-nav button.owl-prev,
    .owl-nav button.owl-next{
      width: 42px; height: 42px;
      border-radius: 50% !important;
      background: #ffffff !important;
      border: 1px solid rgba(15,23,42,.15) !important;
      box-shadow: 0 8px 18px rgba(2,6,23,.08);
      position: absolute;
      top: 35%;
      transform: translateY(-50%);
    }
    .owl-nav button.owl-prev { left: -12px; }
    .owl-nav button.owl-next { right: -12px; }
    .owl-nav button span { font-size: 28px; line-height: 1; }

    /* CTA like PDF bottom */
    .mat-cta {
      background: #0b1220;
      color: #fff;
      border-radius: 14px;
      padding: 40px 24px;
      box-shadow: 0 12px 40px rgba(2,6,23,.25);
    }
    .mat-cta h3 {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 900;
      margin-bottom: 10px;
    }
    .mat-cta p {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      opacity: .92;
      margin-bottom: 18px;
    }
    .mat-btn {
      border-radius: 999px;
      padding: 10px 18px;
      font-weight: 800;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .mat-btn-primary {
      background: #78b12b;
      border: none;
      color: #fff;
    }
    .mat-btn-primary:hover { opacity: .92; }
    .mat-input {
      max-width: 340px;
      border-radius: 999px;
      padding: 10px 14px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08);
      color: #fff;
    }
    .mat-input::placeholder { color: rgba(255,255,255,.75); }
  </style>
</head>

<body>
<?php include_once('header/header.php'); ?>

<?php
  // --- Swatch Data (You can replace names + colors anytime) ---
  $woodFinishes = [
    ['name'=>'Partax EP-171', 'color'=>'#c9b28e'],
    ['name'=>'Oak Natural', 'color'=>'#d7c39b'],
    ['name'=>'Walnut Classic', 'color'=>'#a07a4a'],
    ['name'=>'Espresso Brown', 'color'=>'#4b3526'],
    ['name'=>'Ash Grey', 'color'=>'#b7b6b2'],
    ['name'=>'Teak Warm', 'color'=>'#b6844f'],
  ];

  $laminates = [
    ['name'=>'Snow White', 'color'=>'#f8fafc'],
    ['name'=>'Jet Black', 'color'=>'#0b1220'],
    ['name'=>'Steel Grey', 'color'=>'#9aa3ad'],
    ['name'=>'Sun Yellow', 'color'=>'#facc15'],
    ['name'=>'Sand Beige', 'color'=>'#d6c7ad'],
    ['name'=>'Charcoal', 'color'=>'#3b3f46'],
  ];

  $fabrics = [
    ['name'=>'Mustard Fabric', 'color'=>'#d6a23b'],
    ['name'=>'Rust Orange', 'color'=>'#b4532a'],
    ['name'=>'Navy Weave', 'color'=>'#1f3a5f'],
    ['name'=>'Cocoa Brown', 'color'=>'#7a4a2b'],
    ['name'=>'Stone Grey', 'color'=>'#9ca3af'],
    ['name'=>'Olive Weave', 'color'=>'#3f5f3e'],
  ];

  $leather = [
    ['name'=>'Ivory Leather', 'color'=>'#e5e1d6'],
    ['name'=>'Black Leather', 'color'=>'#111827'],
    ['name'=>'Forest Green', 'color'=>'#0f3d2e'],
    ['name'=>'Tan Brown', 'color'=>'#9a5a3b'],
    ['name'=>'Chocolate', 'color'=>'#4b2f24'],
  ];

  $metal = [
    ['name'=>'Matte White', 'color'=>'#f1f5f9'],
    ['name'=>'Matte Black', 'color'=>'#0b1220'],
    ['name'=>'Graphite', 'color'=>'#6b7280'],
    ['name'=>'Signal Yellow', 'color'=>'#facc15'],
    ['name'=>'Steel Silver', 'color'=>'#cbd5e1'],
  ];

  function renderSwatches($items){
    foreach($items as $it){
      $name = htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8');
      $color = htmlspecialchars($it['color'], ENT_QUOTES, 'UTF-8');
      echo '
        <div class="item">
          <div class="swatch-card">
            <div class="swatch-sample" style="background: '.$color.';"></div>
            <div class="swatch-label">'.$name.'</div>
          </div>
        </div>
      ';
    }
  }
?>

<!-- ===== Hero (same structure vibe as your Factory Direct page) ===== -->
<section class="mat-hero">
  <div class="wdx-hero text-white">
    <div class="wdx-bg" aria-hidden="true" style="background-image:url('<?php echo $domain; ?>assets/images/materials/material-hero.jpg'); background-size:cover; background-position:center;"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>

    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-7 col-xl-6">
          <div class="wdx-badge">Materials &amp; Finishes</div>

          <h1 class="wdx-hero-title">
            Materials &amp; <span>Colors</span>
          </h1>

          <p class="wdx-desc">
            Choose from our extensive range of premium materials and finishes to customize your furniture exactly the way you want it.
          </p>

          <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
            <a href="quotation.php" class="btn wdx-btn">
              Request Sample Kit <i class="bi bi-arrow-right ms-2"></i>
            </a>

            <input type="text" class="form-control wdx-input"
                   placeholder="Enter Finish / Color Name" aria-label="Enter Finish or Color Name">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats bar (like PDF) -->
  <div class="mat-statbar">
    <div class="container">
      <div class="row text-center g-4">
        <div class="col-6 col-lg-3">
          <div class="mat-stat-number">200+</div>
          <div class="mat-stat-label">Color Options</div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mat-stat-number">50+</div>
          <div class="mat-stat-label">Wood Finishes</div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mat-stat-number">100%</div>
          <div class="mat-stat-label">Customizable</div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="mat-stat-number">Free</div>
          <div class="mat-stat-label">Sample Kit</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== Why Our Materials Stand Out (image left + bullets right) ===== -->
<section class="mat-why">
  <div class="container">
    <div class="mat-why-card">
      <div class="row g-0 align-items-stretch">
        <div class="col-lg-5 mat-why-media">
          <img src="<?php echo $domain; ?>assets/images/materials/materials-why.jpg"
               alt="WoodEx materials and finishes preview" loading="lazy">
        </div>
        <div class="col-lg-7">
          <div class="mat-why-body">
            <div class="mat-kicker">QUALITY &amp; DURABILITY</div>
            <h2 class="mat-title">Why Our Materials Stand Out</h2>
            <p class="mb-3" style="color:#475569; font-family:'Nunito Sans', system-ui;">
              We source premium-grade materials and test each finish to ensure long-term durability, easy maintenance,
              and consistent appearance across installations.
            </p>

            <ul class="mat-why-list">
              <li><i class="bi bi-check-circle-fill"></i> Scratch &amp; stain resistant surfaces (selected ranges)</li>
              <li><i class="bi bi-check-circle-fill"></i> Moisture-resistant laminates for daily office use</li>
              <li><i class="bi bi-check-circle-fill"></i> Multiple texture choices: matte, woodgrain, soft-touch</li>
              <li><i class="bi bi-check-circle-fill"></i> Color matching support for branding consistency</li>
              <li><i class="bi bi-check-circle-fill"></i> Custom combinations available for large projects</li>
            </ul>

            <div class="d-flex flex-wrap gap-2 mt-4">
              <a href="quotation.php" class="btn mat-btn mat-btn-primary">Get Free Quote</a>
              <a href="https://wa.me/923224000768" target="_blank" class="btn mat-btn btn-outline-dark" style="border-radius:999px;">
                WhatsApp Us
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ===== Swatch Sections (carousel rows like PDF) ===== -->
<section class="mat-swatches">
  <div class="container">

    <!-- Wood -->
    <div class="mat-section-head">
      <h3>Wood Finishes</h3>
      <p>Premium wood finishes for desks, tables, and storage solutions.</p>
    </div>
    <div class="owl-carousel mat-carousel mb-5">
      <?php renderSwatches($woodFinishes); ?>
    </div>

    <!-- Laminates -->
    <div class="mat-section-head">
      <h3>Laminates Finishes</h3>
      <p>Durable laminate options in various colors and textures.</p>
    </div>
    <div class="owl-carousel mat-carousel mb-5">
      <?php renderSwatches($laminates); ?>
    </div>

    <!-- Fabrics -->
    <div class="mat-section-head">
      <h3>Fabrics &amp; Leathers</h3>
      <p>Comfort-focused upholstery options designed for long-lasting performance.</p>
    </div>
    <div class="owl-carousel mat-carousel mb-5">
      <?php renderSwatches($fabrics); ?>
    </div>

    <!-- Leather -->
    <div class="mat-section-head">
      <h3>Leatherware</h3>
      <p>Premium leather textures for executive seating and feature areas.</p>
    </div>
    <div class="owl-carousel mat-carousel mb-5">
      <?php renderSwatches($leather); ?>
    </div>

    <!-- Metal -->
    <div class="mat-section-head">
      <h3>Metal Finishes</h3>
      <p>Powder-coated and durable metal finishes for frames and bases.</p>
    </div>
    <div class="owl-carousel mat-carousel">
      <?php renderSwatches($metal); ?>
    </div>

    <!-- Bottom CTA (like PDF) -->
    <div class="mt-5">
      <div class="mat-cta text-center">
        <h3>Need Help Choosing?</h3>
        <p>
          Our design consultants can help you select the perfect materials and color combinations for your workspace.
          Request a free sample kit or visit our showroom.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
          <a href="quotation.php" class="btn mat-btn mat-btn-primary">Request Sample Kit</a>
          <input type="text" class="form-control mat-input" placeholder="Enter your email / phone" aria-label="Email or Phone">
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
  $(function () {
    $('.mat-carousel').owlCarousel({
      loop: true,
      margin: 18,
      nav: true,
      dots: false,
      autoplay: true,
      autoplayTimeout: 3500,
      responsive: {
        0: { items: 1 },
        576: { items: 2 },
        992: { items: 4 }
      }
    });
  });
</script>

<script src="<?php echo $domain; ?>assets/script/cart.js"></script>
</body>
</html>
