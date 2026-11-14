<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico" />
  <title>About Us • Woodex Furniture</title>
  <meta name="description" content="Woodex – Pakistan’s leading office furniture manufacturer based in Lahore. Design-to-delivery solutions for modern workspaces." />

  <!-- Bootstrap / Icons / Base -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">

  <style>
    :root{
      --ink:#111; --ink-2:#5f6b75; --line:#e9edf2; --card:#fff;
      --accent:#b7d800; /* small lime hint used in screenshots */
    }
    body{
      color:var(--ink);
      background:#fff;
      font-family:'Manrope', sans-serif;
      font-size:16px;
      line-height:1.65;
      -webkit-font-smoothing:antialiased;
      text-rendering:optimizeLegibility;
    }

    /* ---------- HERO (topographic dark) ---------- */
    .about-hero{
      position:relative;
      color:#fff;
      padding: clamp(72px, 12vw, 160px) 0 clamp(180px, 10px, 80px);
      background: #0f1114 url('<?php echo $domain;?>assets/images/about/About-Hero.jpg') center/cover no-repeat;
      isolation:isolate;
    }
    .about-hero::after{
      content:""; position:absolute; inset:0; z-index:0;
      background: linear-gradient(to bottom, rgba(0,0,0,.65) 0, rgba(0,0,0,.35) 45%, rgba(0,0,0,.25) 100%);
    }
    .about-hero .container{ position:relative; z-index:1; }
    .about-hero h1{ font-weight:900; letter-spacing:.2px; font-size: clamp(2rem, 4.2vw, 3.4rem); margin:0; text-align: center;}

    /* tabs */
    .about-tabs-wrap{
      border-bottom:1px solid #e5e7eb;
      background:#fff;
      position:sticky;
      top:72px;
      z-index:8;
    }
    .about-tabs{
      display:flex;
      gap:8px;
      overflow:auto;
      white-space:nowrap;
      padding:8px 0;
    }
    .about-tabs a{
      text-decoration:none;
      color:#18273a;
      font-weight:700;
      font-size:15px;
      padding:8px 14px;
      border-radius:2px;
    }
    .about-tabs a.active,
    .about-tabs a:hover{ background:#78be20; color:#fff; }

    /* section shells */
    .section{ padding: clamp(44px, 5vw, 72px) 0; }
    .section-light{ background:#fff; }
    .section-ghost{ background:#fafbfc; }

    .section-title{
      font-size:clamp(2rem, 2.2vw, 2.6rem);
      font-weight:700;
      letter-spacing:-.02em;
      margin:0 0 10px;
      color:#10243f;
      line-height:1.15;
    }
    .about-copy{
      color:#5f6b75;
      line-height:1.75;
      font-size:1.08rem;
      margin:0 0 16px;
    }
    .section-head{
      text-align:center;
      margin-bottom:24px;
    }
    .section-subtitle{
      color:#667085;
      margin:0 0 20px;
      font-size:1.08rem;
      line-height:1.75;
    }

    .eyebrow{ color:#98a2ad; text-transform:uppercase; letter-spacing:.12em; font-size:.8rem; }

    /* ---------- WHY CHOOSE (split) ---------- */
    .lead-copy{ color:var(--ink-2); line-height:1.75; font-size:1.08rem; }
    .wc-figure{ border-radius:0; overflow:hidden; box-shadow:none; border:1px solid var(--line); }
    .wc-figure img{ width:100%; display:block; object-fit:cover; aspect-ratio: 16/10; }
    .wc-title{ font-weight:700; font-size: clamp(2rem, 2.5vw, 2.8rem); color:#10243f; margin-bottom:12px; letter-spacing:-.02em; line-height:1.15; }

    /* ---------- MISSION (centered) ---------- */
    .mission-wrap{ text-align:center; max-width:980px; margin-inline:auto; }
    .mission-card{
      border:1px solid var(--line);
      background:#fff;
      padding:22px 24px;
      height:100%;
      border-radius:0;
      box-shadow:none;
    }
    .mission-title{ font-weight:700; margin-bottom:8px; font-size:1.9rem; color:#10243f; letter-spacing:-.015em; line-height:1.2; }
    .mission-text{ color:var(--ink-2); line-height:1.75; font-size:1.05rem; }

    /* ---------- MANUFACTURING ADVANTAGE ---------- */
    .pill-card{
      background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px;
      box-shadow:0 8px 24px rgba(15,23,42,.05); height:100%;
    }
    .pill-card{ border-radius:0; box-shadow:none; }
    .pill-icon{
      width:44px; height:44px; border-radius:12px; display:grid; place-items:center;
      background:#f5f7fb; color:#5f6368; font-size:1.15rem; border:1px solid var(--line);
    }
    .pill-title{ font-weight:800; margin:8px 0 4px; font-size:1.25rem; line-height:1.25; color:#10243f; }
    .pill-text{ color:var(--ink-2); margin:0; font-size:1.03rem; line-height:1.7; }

    .visit-card .meta{ color:var(--ink-2); margin:0; }
    .right-photo{ border-radius:0; overflow:hidden; box-shadow:none; border:1px solid var(--line); }
    .right-photo img{ width:100%; display:block; object-fit:cover; aspect-ratio: 16/10; }

    /* ---------- VALUES ---------- */
    .value-card{
      background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px; height:100%;
      text-align:center; box-shadow:0 6px 18px rgba(15,23,42,.05);
    }
    .value-card{ border-radius:0; box-shadow:none; }
    .value-icn{ width:42px; height:42px; border-radius:10px; display:grid; place-items:center; background:#f6f8fb; border:1px solid var(--line); color:#6b7280; margin-inline:auto; }
    .value-title{ font-weight:800; margin:10px 0 6px; font-size:1.25rem; color:#10243f; line-height:1.25; }
    .value-sub{ color:var(--ink-2); margin:0; font-size:1rem; line-height:1.7; }

    /* ---------- CORE VALUES (split with checklist cards) ---------- */
    .core-values-title{ font-weight:700; font-size:clamp(2rem, 2.8vw, 2.9rem); color:#10243f; margin:0 0 6px; }
    .core-values-sub{ color:#667085; margin:0 0 18px; font-size:1.05rem; }
    .core-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .core-item{
      border:1px solid var(--line);
      background:#fff;
      padding:16px;
      min-height:116px;
      border-radius:0;
    }
    .core-item .head{ display:flex; align-items:center; gap:10px; margin:0 0 6px; }
    .core-item .head i{ color:#78be20; font-size:1rem; }
    .core-item .head h5{ margin:0; font-size:1.18rem; font-weight:800; color:#12263f; line-height:1.25; }
    .core-item p{ margin:0; color:#667085; line-height:1.7; font-size:1rem; }
    .core-figure{ border-radius:0; overflow:hidden; box-shadow:none; border:1px solid var(--line); }
    .core-figure img{ width:100%; display:block; object-fit:cover; aspect-ratio:16/10; }

    /* ---------- SHOWROOM SPLIT ---------- */
    .showroom-title{
      font-weight:700;
      font-size:clamp(2rem, 2.8vw, 2.85rem);
      line-height:1.1;
      letter-spacing:-.02em;
      color:#10243f;
      margin:0 0 16px;
    }
    .showroom-copy{
      color:#5f6b75;
      line-height:1.75;
      font-size:1.08rem;
      margin:0 0 18px;
      max-width: 95%;
    }
    .showroom-photo{
      border-radius:0;
      overflow:hidden;
      box-shadow:none;
      border:1px solid var(--line);
    }
    .showroom-photo img{
      width:100%;
      display:block;
      object-fit:cover;
      aspect-ratio:16/9;
    }

    /* ---------- WHO WE ARE (long text) ---------- */
    .whoweare{
      border:1px solid var(--line); border-radius:0; padding:18px; background:#fff;
    }
    .whoweare h6{ font-weight:800; margin-top:10px; font-size:1.15rem; color:#10243f; }
    .whoweare p, .whoweare li{ color:#525b66; font-size:1.05rem; line-height:1.75; }

    /* logos strip */
    .logos{ display:grid; grid-template-columns: repeat(5, 1fr); gap:18px; align-items:center; justify-items:center; }
    .logos img{ max-height:44px; width:auto; filter:grayscale(100%) contrast(.95); opacity:.85; }
    .logos img:hover{ filter:none; opacity:1; }

    @media (max-width: 991.98px){
      .about-tabs-wrap{ top:58px; }
      .core-grid{ grid-template-columns:1fr; }
      .logos{ grid-template-columns: repeat(3, 1fr); }
      .showroom-copy{ max-width:100%; }
      .section-title{ font-size:clamp(1.7rem, 6vw, 2.2rem); }
      .wc-title, .showroom-title{ font-size:clamp(1.8rem, 7vw, 2.3rem); }
      .lead-copy, .about-copy, .section-subtitle, .showroom-copy{ font-size:1rem; line-height:1.7; }
    }
    @media (max-width: 575.98px){
      .logos{ grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<?php include_once('header/header.php'); ?>

<!-- HERO -->
<section class="about-hero">
  <div class="container">
    <h1>About WoodEx Furniture</h1>
  </div>
</section>

<section class="about-tabs-wrap">
  <div class="container">
    <nav class="about-tabs">
      <a href="#story" class="active">Story</a>
      <a href="#manufacturing">Manufacturing</a>
      <a href="#core-values">Values</a>
      <a href="#showroom">Showroom</a>
      <a href="#mission-vision">Mission & Vision</a>
      <a href="#advantage">Advantage</a>
      <a href="#who-we-are">Who We Are</a>
    </nav>
  </div>
</section>

<!-- WHY CHOOSE -->
<section id="story" class="section section-light">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <h2 class="wc-title">Our Story</h2>
        <p class="lead-copy">
          WoodEx began with a simple vision: to revolutionize the Pakistani office furniture industry by combining traditional craftsmanship with modern technology and design.
        </p>
        <p class="lead-copy">
          Founded in Lahore, we started as a small manufacturing unit with a team of skilled artisans. Today, we've grown into Pakistan's leading B2B furniture platform, serving businesses nationwide.
        </p>
        <p class="lead-copy mb-0">
          Our quote-first strategy prioritizes relationship building over immediate sales, allowing us to deeply understand client needs and deliver truly customized solutions.
        </p>
      </div>
      <div class="col-lg-6">
        <figure class="wc-figure m-0">
          <img src="<?php echo $domain;?>assets/images/about/why-woodex.jpg" alt="Why choose WoodEx - modern office">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- MISSION -->
<section id="manufacturing" class="section section-ghost">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <h2 class="section-title">State-of-the-Art Manufacturing</h2>
        <p class="section-subtitle">Our 50,000 sq ft manufacturing facility in Lahore is equipped with the latest CNC machinery, precision cutting tools, and automated finishing lines to deliver consistent quality at scale.</p>
        <p class="lead-copy mb-0">
          ISO 9001:2015 Certified Facility<br>
          Advanced CNC and robotic manufacturing<br>
          Rigorous quality control at every stage<br>
          Eco-friendly paints and materials<br>
          In-house upholstery and foam division<br>
          Capacity: 5,000+ units per month
        </p>
      </div>
      <div class="col-lg-6">
        <figure class="wc-figure m-0">
          <img src="<?php echo $domain;?>assets/images/about/floor.jpg" alt="State-of-the-art manufacturing facility">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- CORE VALUES -->
<section id="core-values" class="section section-light">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <h2 class="core-values-title">Our Core Values</h2>
        <p class="core-values-sub">What sets us apart from the competition</p>

        <div class="core-grid">
          <article class="core-item">
            <div class="head"><i class="bi bi-check-circle"></i><h5>Quality First</h5></div>
            <p>Every piece is crafted with premium materials and rigorous quality control processes.</p>
          </article>
          <article class="core-item">
            <div class="head"><i class="bi bi-check-circle"></i><h5>Innovation Driven</h5></div>
            <p>Continuously investing in design research and manufacturing technology.</p>
          </article>
          <article class="core-item">
            <div class="head"><i class="bi bi-check-circle"></i><h5>Customer Focused</h5></div>
            <p>Building long-term partnerships through exceptional service and support.</p>
          </article>
          <article class="core-item">
            <div class="head"><i class="bi bi-check-circle"></i><h5>Sustainable Practices</h5></div>
            <p>Committed to eco-friendly manufacturing and responsible sourcing.</p>
          </article>
        </div>
      </div>

      <div class="col-lg-5">
        <figure class="core-figure m-0">
          <img src="<?php echo $domain;?>assets/images/about/Our-Core-Values.jpg" alt="Our core values at WoodEx">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- SHOWROOM SPLIT -->
<section id="showroom" class="section section-light pt-0">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <h2 class="showroom-title">Woodex Office Furniture Showroom</h2>
        <p class="showroom-copy">
          Experience our furniture in immersive 3D. Click on any piece in the room to change its material in real time - wood, metal, fabric, or laminate.
        </p>
        <p class="showroom-copy">
          Our journey began with a simple belief: that well-designed workspaces drive better work. This philosophy has guided every piece of furniture we've crafted, every client we've served, and every innovation we've brought to the market.
        </p>
        <p class="showroom-copy mb-0">
          Today, WOODEX is proud to be the trusted furniture partner for corporations, government institutions, educational facilities, and healthcare organizations across Pakistan.
        </p>
      </div>
      <div class="col-lg-6">
        <figure class="showroom-photo m-0">
          <img src="<?php echo $domain;?>assets/images/about/floor.jpg" alt="Woodex office furniture showroom">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- MANUFACTURING ADVANTAGE -->
<section id="advantage" class="section section-light">
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Our Manufacturing Advantage</h2>
      <div class="section-subtitle mb-0">State-of-the-art facility in Lahore with comprehensive capabilities.</div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-4">
        <div class="pill-card h-100">
          <div class="pill-icon"><i class="bi bi-pencil-square"></i></div>
          <div class="pill-title">In-House Design Team</div>
          <p class="pill-text">Experienced designers create custom solutions tailored to your specific needs and space constraints.</p>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="pill-card h-100">
          <div class="pill-icon"><i class="bi bi-buildings"></i></div>
          <div class="pill-title">Factory-Direct Production</div>
          <p class="pill-text">Complete control over quality and timelines with our own manufacturing facility in Lahore.</p>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="pill-card h-100">
          <div class="pill-icon"><i class="bi bi-shield-check"></i></div>
          <div class="pill-title">Rigorous Quality Control</div>
          <p class="pill-text mb-0">Every piece undergoes multiple checks before delivery to ensure it meets our standards.</p>
        </div>
      </div>
    </div>

    <div class="row g-4 align-items-stretch">
      <div class="col-lg-6">
        <div class="pill-card visit-card h-100">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-geo-alt-fill text-warning"></i>
            <h5 class="mb-0 fw-bold">Visit Our Showroom</h5>
          </div>
          <p class="meta"><strong>Address:</strong> LG 89 Zainab Tower, Model Town Link Road, Lahore</p>
          <p class="meta"><strong>Phone:</strong> (+92) 322 4000 768</p>
          <p class="meta"><strong>Email:</strong> sales@woodexfurniture.pk</p>
          <p class="meta mb-0"><strong>Hours:</strong> Monday – Saturday, 9:00 AM – 7:00 PM</p>
        </div>
      </div>
      <div class="col-lg-6">
        <figure class="right-photo m-0 h-100">
          <img src="<?php echo $domain;?>assets/images/about/WoodEx-manufacturing-%26-showroom.jpg" alt="WoodEx manufacturing & showroom">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- VALUES -->
<section id="values" class="section section-ghost">
  <div class="container">
    <div class="section-head">
      <h2 class="section-title">Our Values</h2>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-card">
          <div class="value-icn"><i class="bi bi-award"></i></div>
          <div class="value-title">Quality Craftsmanship</div>
          <p class="value-sub mb-0">Every piece is built to last with premium materials and meticulous attention to detail.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-card">
          <div class="value-icn"><i class="bi bi-people"></i></div>
          <div class="value-title">Customer Partnership</div>
          <p class="value-sub mb-0">We work closely with you throughout the entire process, from design to delivery.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-card">
          <div class="value-icn"><i class="bi bi-lightbulb"></i></div>
          <div class="value-title">Innovation</div>
          <p class="value-sub mb-0">Continuously evolving designs to meet modern workspace needs and trends.</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-3">
        <div class="value-card">
          <div class="value-icn"><i class="bi bi-shield-lock"></i></div>
          <div class="value-title">Reliability</div>
          <p class="value-sub mb-0">Warranty and nationwide service ensure your investment is protected.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- MISSION & VISION -->
<section id="mission-vision" class="section section-light">
  <div class="container">
    <div class="mission-wrap mb-4">
      <h2 class="section-title text-center">Mission & Vision</h2>
    </div>
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-4">
        <div class="mission-card">
          <h3 class="mission-title">Our Mission</h3>
          <p class="mission-text mb-0">Empowering Pakistani businesses with world-class office furniture solutions through innovation, quality craftsmanship, and exceptional customer service.</p>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="mission-card">
          <h3 class="mission-title">Our Vision</h3>
          <p class="mission-text mb-0">To be Pakistan's most trusted and technologically advanced office furniture manufacturer, setting the standard for B2B furniture excellence across South Asia.</p>
        </div>
      </div>
      <div class="col-lg-4">
        <figure class="wc-figure m-0 h-100">
          <img src="<?php echo $domain;?>assets/images/about/why-woodex.jpg" alt="WoodEx mission and vision">
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- WHO WE ARE + LOGOS -->
<section id="who-we-are" class="section section-light">
  <div class="container">
    <div class="whoweare mb-4">
      <h2 class="section-title" style="font-size:2rem;">Who We Are</h2>
      <p class="about-copy">WoodEx is a premium office furniture brand dedicated to providing businesses with innovative and reliable furniture solutions. Our journey began with a simple yet powerful vision: to create work environments that empower people to perform at their best.</p>
      <h6>Our Values</h6>
      <ol class="mb-2">
        <li><strong>Quality-first:</strong> Every piece undergoes strict checks and is produced with premium materials.</li>
        <li><strong>Innovation:</strong> We constantly adopt new ideas and techniques to deliver furniture that is both functional and timeless.</li>
        <li><strong>Sustainability:</strong> We reduce environmental impact through responsible material choices and efficient processes.</li>
        <li><strong>Customer-centricity:</strong> We work closely with clients to meet expectations at every step — from consultation to after-sales support.</li>
      </ol>
    </div>

    <div class="logos">
      <img src="assets/images/clients/etihad-town-300x88.jpg"     alt="Etihad Town">
      <img src="assets/images/clients/Haier.jpg"      alt="Haier">
      <img src="assets/images/clients/mindbridge.jpg" alt="MindBridge">
      <img src="assets/images/clients/Pachem-Global-Pvt-Limited.jpg"     alt="Pachem Global">
      <img src="assets/images/clients/PAF.jpg"        alt="Pakistan Air Force">
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    const tabs = Array.from(document.querySelectorAll('.about-tabs a'));
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener('click', function () {
        tabs.forEach((t) => t.classList.remove('active'));
        this.classList.add('active');
      });
    });
  })();
</script>
</body>
</html>
