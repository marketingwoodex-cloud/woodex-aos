<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">

  <title>Space Planning & Design - Woodex Furniture</title>
  <meta name="description" content="Transform your workspace with expert space planning & design. CAD floor plans, space optimization, and workplace strategy by WoodEx Furniture Pakistan.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel CSS should be in HEAD -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <!-- Page-only fixes + sections styling (kept same classes) -->
  <style>
    /* ========= Interface polish only (no content/sections changed) ========= */

    :root{
      --sp-green:#78b12b;
      --sp-dark:#0b1220;
      --sp-text:#0f172a;
      --sp-muted:#475569;
      --sp-border: rgba(15,23,42,.10);
      --sp-shadow: 0 14px 50px rgba(2,6,23,.06);
      --sp-radius: 18px;
    }

    /* Never allow horizontal cut-off */
    html, body { overflow-x: hidden; }
    img { max-width: 100%; height: auto; }

    /* Container safety on very small screens */
    .container { padding-left: 16px; padding-right: 16px; }

    /* HERO uses shared .wdx-hero styles from assets/style.css */

    /* Services cards: match premium soft UI */
    .wdx-why{ padding: 70px 0; }
    .wdx-card{
      border: 1px solid var(--sp-border);
      border-radius: var(--sp-radius);
      box-shadow: var(--sp-shadow);
      transition: transform .18s ease, box-shadow .18s ease;
      overflow: hidden;
    }
    .wdx-card:hover{
      transform: translateY(-3px);
      box-shadow: 0 18px 70px rgba(2,6,23,.10);
    }
    .wdx-icon{
      width: 46px;
      height: 46px;
      border-radius: 14px;
      background: rgba(120,177,43,.12);
      border: 1px solid rgba(120,177,43,.20);
      display:flex;
      align-items:center;
      justify-content:center;
      color: var(--sp-green);
      font-size: 1.2rem;
    }
    .wdx-card h5{
      font-family: Inter, system-ui;
      font-weight: 900;
      color: var(--sp-text);
      margin-top: 14px;
      margin-bottom: 8px;
    }
    .wdx-card p{
      font-family: "Nunito Sans", system-ui;
      color: var(--sp-muted);
      line-height: 1.6;
      font-size: .95rem;
      margin-bottom: 0;
    }

    /* Headings */
    .wdx-kicker{
      font-family: Inter, system-ui;
      letter-spacing: .16em;
      font-weight: 900;
      font-size: .75rem;
      text-transform: uppercase;
      opacity: .75;
    }
    .wdx-title{
      font-family: Inter, system-ui;
      font-weight: 900;
      letter-spacing: -0.01em;
      color: var(--sp-text);
      margin: 10px 0 10px;
    }
    .wdx-subtitle{
      font-family: "Nunito Sans", system-ui;
      color: var(--sp-muted);
      line-height: 1.65;
    }

    /* Why Matters block: modern card shell */
    .wdx-space-why{ padding: 70px 0; background:#fff; }
    .wdx-space-why-card{
      border: 1px solid var(--sp-border);
      border-radius: var(--sp-radius);
      box-shadow: var(--sp-shadow);
      overflow: hidden;
      background:#fff;
    }
    .wdx-space-why-media{
      position: relative;
      height: 100%;
      min-height: 320px;
    }
    .wdx-space-why-media img{
      width:100%;
      height:100%;
      object-fit: cover;
      display:block;
    }
    .wdx-space-badge{
      position:absolute;
      right: 14px;
      bottom: 14px;
      background: var(--sp-green);
      color:#fff;
      border-radius: 14px;
      padding: 12px 14px;
      box-shadow: 0 18px 60px rgba(2,6,23,.30);
    }
    .wdx-space-badge .big{
      font-family: Inter, system-ui;
      font-weight: 950;
      font-size: 1.15rem;
      line-height:1;
    }
    .wdx-space-badge .small{
      font-family: "Nunito Sans", system-ui;
      font-weight: 800;
      font-size: .78rem;
      opacity:.95;
      margin-top: 3px;
    }

    /* Why list spacing */
    .wdx-space-list{
      list-style:none;
      padding:0;
      margin: 14px 0 0;
    }
    .wdx-space-list li{
      display:flex;
      gap:10px;
      margin: 10px 0;
      color: #334155;
      font-family: "Nunito Sans", system-ui;
      line-height: 1.5;
    }
    .wdx-space-list i{
      color: var(--sp-green);
      margin-top: 3px;
      flex: 0 0 auto;
    }

    /* Process section: cleaner timeline */
    .wdx-space-process{
      padding: 80px 0;
      background:#f8fafc;
    }
    .wdx-space-timeline{
      background:#fff;
      border: 1px solid var(--sp-border);
      border-radius: var(--sp-radius);
      box-shadow: var(--sp-shadow);
      padding: 26px;
    }
    .wdx-t-step{
      position: relative;
      padding-left: 60px;
      padding-bottom: 18px;
    }
    .wdx-t-step:before{
      content:"";
      position:absolute;
      left: 23px;
      top: 0;
      width: 2px;
      height: 100%;
      background: rgba(120,177,43,.25);
    }
    .wdx-t-step:last-child:before{ height: 30px; }
    .wdx-t-num{
      position:absolute;
      left: 0;
      top: 0;
      width: 46px;
      height: 46px;
      border-radius: 999px;
      background: var(--sp-green);
      color:#fff;
      display:flex;
      align-items:center;
      justify-content:center;
      font-family: Inter, system-ui;
      font-weight: 950;
      box-shadow: 0 16px 40px rgba(2,6,23,.18);
    }
    .wdx-t-card{
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 16px;
      padding: 16px 18px;
      background:#fff;
    }
    .wdx-t-card h5{
      margin:0 0 6px;
      font-family: Inter, system-ui;
      font-weight: 950;
      color: var(--sp-text);
    }
    .wdx-t-card p{
      margin:0 0 10px;
      color: var(--sp-muted);
      font-family: "Nunito Sans", system-ui;
      line-height:1.6;
      font-size:.95rem;
    }
    .wdx-chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      background: rgba(120,177,43,.12);
      border: 1px solid rgba(120,177,43,.22);
      color:#14532d;
      padding: 6px 10px;
      border-radius: 999px;
      font-family: "Nunito Sans", system-ui;
      font-weight: 900;
      font-size:.78rem;
      white-space: normal;
    }

    /* Two image cards: more premium, prevent cut */
    .wdx-space-tiles{ padding: 70px 0; background:#fff; }
    .wdx-space-tile{
      position:relative;
      border-radius: var(--sp-radius);
      overflow:hidden;
      border: 1px solid var(--sp-border);
      box-shadow: var(--sp-shadow);
      background:#e2e8f0;
    }
    .wdx-space-tile img{
      width:100%;
      height: 260px;
      object-fit: cover;
      display:block;
    }
    .wdx-space-tile:after{
      content:"";
      position:absolute;
      inset:0;
      background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.60) 100%);
    }
    .wdx-space-txt{
      position:absolute;
      left: 16px;
      bottom: 16px;
      z-index: 2;
      color:#fff;
      font-family: Inter, system-ui;
      font-weight: 950;
      text-shadow: 0 16px 50px rgba(0,0,0,.45);
    }
    .wdx-space-txt small{
      display:block;
      font-family: "Nunito Sans", system-ui;
      font-weight: 800;
      opacity:.95;
      margin-top: 3px;
    }

    /* Bottom CTA: same section, cleaner */
    .wdx-space-cta{ padding: 70px 0; background:#fff; }
    .wdx-space-cta .wdx-process-cta{
      background:#0b1220;
      border-radius: var(--sp-radius);
      box-shadow: 0 22px 70px rgba(2,6,23,.30);
      border: 1px solid rgba(255,255,255,.08);
      position: relative;
      overflow:hidden;
    }
    .wdx-space-cta .wdx-process-cta:before{
      content:"";
      position:absolute;
      left:-120px;
      top:-120px;
      width: 280px;
      height: 280px;
      background: rgba(120,177,43,.18);
      border-radius: 50%;
    }
    .wdx-space-cta .wdx-process-cta:after{
      content:"";
      position:absolute;
      right:-160px;
      bottom:-160px;
      width: 340px;
      height: 340px;
      background: rgba(120,177,43,.12);
      border-radius: 50%;
    }
    .wdx-space-cta .wdx-cta-inner{ position:relative; }
    .wdx-space-cta .wdx-cta-title,
    .wdx-space-cta .wdx-cta-desc{
      color:#fff;
    }
    .wdx-space-cta .wdx-cta-desc{ opacity:.92; }
    .wdx-space-cta .wdx-btn{
      border-radius: 5px;
      padding: 10px 18px;
      font-weight: 950;
    }
    .wdx-space-cta .wdx-input{
      border-radius: 999px !important;
      min-height: 44px;
      max-width: 360px;
    }

    /* Responsive tweaks */
    @media (max-width: 991.98px){
      .wdx-space-why-media{ min-height: 260px; }
      .wdx-space-timeline{ padding: 18px; }
    }
  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- ===== HERO (same structure as your template, different copy) ===== -->
  <section>
     <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo $domain; ?>assets/images/services/space.jpg');">
       <div class="wdx-bg" aria-hidden="true"></div>
       <div class="wdx-overlay" aria-hidden="true"></div>

      <div class="container h-100 position-relative">
        <div class="row h-100 align-items-center">
          <div class="col-lg-7 col-xl-6">
            <div class="wdx-badge">Space Planning & Design</div>

            <h2 class="wdx-hero-title">
              Space Planning <span>&amp; Design</span>
            </h2>

            <p class="wdx-desc">
              Transform your workspace with expert space planning services. We create efficient, productive office layouts
              that maximize every square foot while enhancing employee wellbeing.
            </p>

            <div class="d-flex flex-wrap gap-3 align-items-center">
              <a href="quotation.php" class="btn wdx-btn">
                Get Space Plan Quote <i class="bi bi-arrow-right ms-2"></i>
              </a>

              <input type="text" class="form-control wdx-input"
                     placeholder="Enter Area / Staff Count" aria-label="Enter Area or Staff Count">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats bar (same placement + structure) -->
     <div class="wdx-stats">
       <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">300+</div>
            <div class="wdx-stat-label">Spaces Designed</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">2M+</div>
            <div class="wdx-stat-label">Sq. Ft. Planned</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">35%</div>
            <div class="wdx-stat-label">Avg. Space Saved</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">5-7</div>
            <div class="wdx-stat-label">Days Turnaround</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== SERVICES (same section pattern as wdx-why cards) ===== -->
  <section class="wdx-why">
    <div class="container">
      <div class="text-center">
        <div class="wdx-kicker">OUR SERVICES</div>
        <h2 class="wdx-title">Comprehensive Space Planning</h2>
        <p class="wdx-subtitle">
          From initial concept to final completion, we handle every aspect of professional workspace design.
        </p>
      </div>

      <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-lightbulb"></i></div>
            <h5>Space Analysis</h5>
            <p>Comprehensive evaluation of your current or planned workspace. We analyze traffic flow, natural light, acoustics, and ergonomics.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-layers"></i></div>
            <h5>CAD Floor Plans</h5>
            <p>Professional 2D and 3D floor plans showing furniture placement, dimensions, and spatial relationships.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-arrows-angle-expand"></i></div>
            <h5>Space Optimization</h5>
            <p>Maximize efficiency and productivity. We help you use every square foot effectively without feeling cramped.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-people"></i></div>
            <h5>Workplace Strategy</h5>
            <p>Design workspaces that support collaboration, focus work, and employee wellbeing based on your team’s needs.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <h5>Future-Proofing</h5>
            <p>Flexible layouts that adapt as your business grows. Plan for expansion without complete redesigns.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-lightning-charge"></i></div>
            <h5>Quick Turnaround</h5>
            <p>Receive initial space plans within 5–7 business days. Revisions completed within 48 hours.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== WHY IT MATTERS (new block but still uses wdx heading pattern) ===== -->
  <section class="wdx-space-why">
    <div class="container">
      <div class="wdx-space-why-card">
        <div class="row g-0 align-items-stretch">
          <div class="col-lg-6">
            <div class="p-4 p-lg-5">
              <div class="wdx-kicker">WHY IT MATTERS</div>
              <h2 class="wdx-title" style="margin-bottom:10px;">Why Space Planning Matters</h2>

              <p class="wdx-subtitle" style="max-width: 100%; margin: 0;">
                A well-planned workspace isn’t just about fitting desks — it’s about creating an environment where people thrive.
                Good space planning improves productivity, reduces costs, and creates better experiences for employees and clients.
              </p>

              <div class="row mt-3">
                <div class="col-md-6">
                  <ul class="wdx-space-list">
                    <li><i class="bi bi-check-circle-fill"></i> Increase workspace efficiency by 20–40%</li>
                    <li><i class="bi bi-check-circle-fill"></i> Reduce wasted space and rental costs</li>
                    <li><i class="bi bi-check-circle-fill"></i> Comply with building codes &amp; accessibility</li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <ul class="wdx-space-list">
                    <li><i class="bi bi-check-circle-fill"></i> Improve employee satisfaction and productivity</li>
                    <li><i class="bi bi-check-circle-fill"></i> Support hybrid &amp; flexible work arrangements</li>
                    <li><i class="bi bi-check-circle-fill"></i> Optimize for collaboration and privacy</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="wdx-space-why-media">
              <img src="<?php echo $domain; ?>assets/images/services/space-planning-why.jpg"
                   alt="Boardroom workspace planning preview" loading="lazy">
              <div class="wdx-space-badge">
                <div class="big">35%</div>
                <div class="small">More Efficient</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== PROCESS (timeline) ===== -->
  <section class="wdx-space-process">
    <div class="container">
      <div class="text-center mb-4">
        <div class="wdx-kicker">HOW IT WORKS</div>
        <h2 class="wdx-title">Our Planning Process</h2>
      </div>

      <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
          <div class="wdx-space-timeline">

            <div class="wdx-t-step">
              <div class="wdx-t-num">1</div>
              <div class="wdx-t-card">
                <h5>Consultation</h5>
                <p>Site visit to measure space, understand your needs, and discuss your vision.</p>
                <span class="wdx-chip"><i class="bi bi-dot"></i> Meeting notes &amp; preliminary measurements</span>
              </div>
            </div>

            <div class="wdx-t-step">
              <div class="wdx-t-num">2</div>
              <div class="wdx-t-card">
                <h5>Analysis</h5>
                <p>Evaluate space constraints, opportunities, and develop multiple layout concepts.</p>
                <span class="wdx-chip"><i class="bi bi-dot"></i> Concept sketches &amp; initial recommendations</span>
              </div>
            </div>

            <div class="wdx-t-step">
              <div class="wdx-t-num">3</div>
              <div class="wdx-t-card">
                <h5>Design</h5>
                <p>Create detailed CAD drawings with furniture placement and 3D visualization.</p>
                <span class="wdx-chip"><i class="bi bi-dot"></i> Professional floor plans &amp; 3D renderings</span>
              </div>
            </div>

            <div class="wdx-t-step">
              <div class="wdx-t-num">4</div>
              <div class="wdx-t-card">
                <h5>Refinement</h5>
                <p>Review plans together, make adjustments based on your feedback.</p>
                <span class="wdx-chip"><i class="bi bi-dot"></i> Revised plans &amp; final approval</span>
              </div>
            </div>

            <div class="wdx-t-step">
              <div class="wdx-t-num">5</div>
              <div class="wdx-t-card">
                <h5>Implementation</h5>
                <p>Furniture selection, ordering, and installation coordination.</p>
                <span class="wdx-chip"><i class="bi bi-dot"></i> Complete project management to completion</span>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== IMAGE TILES ===== -->
  <section class="wdx-space-tiles">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-6">
          <div class="wdx-space-tile">
            <img src="<?php echo $domain; ?>assets/images/services/open-plan.jpg" alt="Open plan workspaces" loading="lazy">
            <div class="wdx-space-txt">
              Open Plan Workspaces
              <small>Optimized for collaboration</small>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="wdx-space-tile">
            <img src="<?php echo $domain; ?>assets/images/services/meeting-spaces.jpg" alt="Meeting spaces" loading="lazy">
            <div class="wdx-space-txt">
              Meeting Spaces
              <small>Designed for productivity</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== CTA (reuse your existing wdx-process-cta structure) ===== -->
  <section class="wdx-space-cta">
    <div class="container">
      <div class="wdx-process-cta">
        <div class="wdx-cta-inner text-center">
          <h3 class="wdx-cta-title">Ready to Transform Your Space?</h3>
          <p class="wdx-cta-desc">
            Schedule a complimentary 30-minute consultation to discuss your space planning needs.
            We’ll provide preliminary expert recommendations during the call.
          </p>

          <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="quotation.php" class="btn wdx-btn">
              Get Space plan Quote
            </a>

            <input
              type="text"
              class="form-control wdx-input"
              placeholder="Enter Phone / Email"
              aria-label="Enter Phone or Email"
            >
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include_once('footer/footer.php'); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
</body>
</html>
