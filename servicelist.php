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

  <title>Services - Woodex Furniture</title>
  <meta name="description" content="360-degree office solutions by Woodex — from space planning and custom design to delivery, installation, and project quoting across Pakistan.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel (kept same stack as your old layout) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    :root{
      --wdx-green:#79be1f;
      --wdx-dark:#111315;
      --wdx-muted:#6b7280;
      --wdx-border:rgba(17,19,21,.10);
    }

    /* ============ HERO ============ */
    .wdx-srv-hero{
      position:relative;
      min-height:440px;
      color:#fff;
      overflow:hidden;
      background:#0b1220;
    }
    .wdx-srv-hero .wdx-bg{
      position:absolute; inset:0;
      background:url("<?php echo $domain; ?>assets/images/services/360-Degree-hero.jpg") center/cover no-repeat;
      transform:scale(1);
    }
    .wdx-srv-hero .wdx-overlay{
      position:absolute; inset:0;
      background:rgba(0,0,0,.45);
    }
    .wdx-srv-hero .wdx-hero-inner{
      position:relative;
      z-index:2;
      padding:98px 0 52px;
    }
    .wdx-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      background:rgba(121,190,31,.20);
      border:1px solid rgba(121,190,31,.35);
      color:#eaffd6;
      padding:6px 12px;
      border-radius:999px;
      font-weight:850;
      font-size:12px;
      letter-spacing:.02em;
      margin-bottom:14px;
    }
    .wdx-hero-h1{
      font-weight:750;
      letter-spacing:-.02em;
      line-height:1.04;
      font-size:clamp(34px, 4.0vw, 58px);
      margin:0 0 12px;
    }
    .wdx-hero-h1 .accent{ color:var(--wdx-green); }
    .wdx-hero-p{
      max-width:760px;
      color:rgba(255,255,255,.90);
      font-size:16px;
      line-height:1.7;
      margin:0 0 22px;
    }
    .wdx-hero-actions{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      align-items:center;
    }
    .wdx-btn-green{
      background:var(--wdx-green);
      border:1px solid var(--wdx-green);
      color:#0b0f14;
      border-radius:10px;
      padding:11px 16px;
      font-weight:750;
      display:inline-flex;
      align-items:center;
      gap:10px;
      text-decoration:none;
      min-width:165px;
      justify-content:center;
    }
    .wdx-btn-green:hover{ filter:brightness(.98); }
    .wdx-hero-input{
      border-radius:10px;
      border:1px solid rgba(255,255,255,.35);
      background:rgba(255,255,255,.10);
      color:#fff;
      padding:11px 14px;
      min-width:240px;
      backdrop-filter: blur(6px);
    }
    .wdx-hero-input::placeholder{ color:rgba(255,255,255,.85); }
    .wdx-hero-input:focus{
      background:rgba(255,255,255,.12);
      color:#fff;
      border-color:rgba(121,190,31,.55);
      box-shadow:0 0 0 .2rem rgba(121,190,31,.16);
    }

    /* ============ STATS STRIP ============ */
    .wdx-srv-strip{
      background:var(--wdx-green);
      color:#fff;
      padding:18px 0;
    }
    .wdx-stat{
      text-align:center;
      padding:6px 8px;
    }
    .wdx-stat .num{
      font-weight:750;
      font-size:22px;
      line-height:1.1;
      margin:0;
    }
    .wdx-stat .lbl{
      margin:2px 0 0;
      font-size:12px;
      opacity:.95;
    }

    /* ============ TITLES ============ */
    .wdx-section{ padding:70px 0; background:#fff; }
    .wdx-kicker{
      text-align:center;
      color:var(--wdx-green);
      font-weight:900;
      letter-spacing:.18em;
      font-size:11px;
      margin-bottom:8px;
    }
    .wdx-title{
      font-weight:750;
      letter-spacing:-.02em;
      margin:0;
      font-size:clamp(30px, 3.2vw, 46px);
      text-align:center;
    }
    .wdx-subtitle{
      text-align:center;
      color:var(--wdx-muted);
      margin:10px auto 0;
      font-size:14px;
      max-width:820px;
      line-height:1.7;
    }

    /* ============ SERVICE BLOCKS (alternating) ============ */
    .wdx-svc{
      border:1px solid var(--wdx-border);
      border-radius:5px;
      background:#fff;
      box-shadow:0 10px 26px rgba(0,0,0,.06);
      overflow:hidden;
      height:100%;
    }
    .wdx-svc-img{
      width:100%;
      height:100%;
      min-height:260px;
      object-fit:cover;
      display:block;
    }
    .wdx-svc-body{
      padding:26px 24px;
    }
    .wdx-ic{
      width:44px; height:44px;
      border-radius:14px;
      display:grid; place-items:center;
      background:rgba(121,190,31,.12);
      border:1px solid rgba(121,190,31,.18);
      color:var(--wdx-green);
      font-size:22px;
      margin-bottom:14px;
    }
    .wdx-svc h4{
      font-weight:750;
      margin:0 0 10px;
      font-size:20px;
    }
    .wdx-svc p{
      margin:0 0 14px;
      color:var(--wdx-muted);
      line-height:1.7;
      font-size:14px;
    }
    .wdx-bullets{
      list-style:none;
      padding:0; margin:0 0 16px;
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:8px 14px;
    }
    .wdx-bullets li{
      display:flex;
      gap:8px;
      align-items:flex-start;
      color:#3b414a;
      font-size:13px;
      line-height:1.5;
    }
    .wdx-bullets i{
      color:var(--wdx-green);
      margin-top:2px;
      flex:0 0 auto;
    }
    .wdx-link{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding:9px 12px;
      border-radius:10px;
      border:1px solid rgba(121,190,31,.35);
      color:#1b2a10;
      text-decoration:none;
      font-weight:900;
      font-size:13px;
      background:rgba(121,190,31,.10);
    }
    .wdx-link:hover{ filter:brightness(.98); }

    /* ============ WHY CHOOSE ============ */
    .wdx-why-card{
      border:1px solid var(--wdx-border);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 26px rgba(0,0,0,.06);
      padding:22px 20px;
      text-align:center;
      height:100%;
    }
    .wdx-why-ico{
      width:54px; height:54px;
      border-radius:16px;
      margin:0 auto 12px;
      display:grid; place-items:center;
      background:rgba(121,190,31,.12);
      border:1px solid rgba(121,190,31,.18);
      color:var(--wdx-green);
      font-size:22px;
    }
    .wdx-why-card h6{
      font-weight:750;
      margin:0 0 8px;
      font-size:16px;
    }
    .wdx-why-card p{
      margin:0;
      color:var(--wdx-muted);
      font-size:13px;
      line-height:1.7;
    }

    /* ============ CTA DARK ============ */
    .wdx-cta-wrap{ padding:30px 0 90px; }
    .wdx-cta{
      position:relative;
      overflow:hidden;
      border-radius:18px;
      background:#151718;
      color:#fff;
      padding:42px 22px;
      box-shadow:0 18px 46px rgba(0,0,0,.20);
    }
    .wdx-cta:before,
    .wdx-cta:after{
      content:"";
      position:absolute;
      width:240px; height:240px;
      border-radius:50%;
      background:rgba(121,190,31,.10);
      filter:blur(0px);
    }
    .wdx-cta:before{ left:-110px; bottom:-120px; }
    .wdx-cta:after{ right:-120px; top:-120px; }
    .wdx-cta .inner{
      position:relative;
      z-index:2;
      text-align:center;
      max-width:900px;
      margin:0 auto;
    }
    .wdx-cta h2{
      font-weight:750;
      letter-spacing:-.02em;
      margin:0 0 10px;
      font-size:clamp(26px, 3.0vw, 44px);
    }
    .wdx-cta p{
      margin:0 auto 18px;
      color:rgba(255,255,255,.82);
      font-size:14px;
      line-height:1.7;
      max-width:720px;
    }
    .wdx-cta-actions{
      display:flex;
      gap:12px;
      justify-content:center;
      flex-wrap:wrap;
      align-items:center;
    }
    .wdx-cta-input{
      border-radius:10px;
      border:1px solid rgba(255,255,255,.18);
      background:rgba(255,255,255,.06);
      color:#fff;
      padding:11px 14px;
      min-width:240px;
    }
    .wdx-cta-input::placeholder{ color:rgba(255,255,255,.70); }
    .wdx-cta-input:focus{
      border-color:rgba(121,190,31,.55);
      box-shadow:0 0 0 .2rem rgba(121,190,31,.16);
    }

    @media (max-width: 991px){
      .wdx-bullets{ grid-template-columns:1fr; }
      .wdx-srv-hero .wdx-hero-inner{ padding:88px 0 48px; }
    }
  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- ===================== HERO ===================== -->
  <section class="wdx-srv-hero">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>

    <div class="container wdx-hero-inner">
      <div class="row">
        <div class="col-lg-8">
          <div class="wdx-badge">End-to-End Solutions</div>

          <h1 class="wdx-hero-h1">
            360-Degree Office<br><span class="accent">Solutions</span>
          </h1>

          <p class="wdx-hero-p">
            From consultation to installation — complete turnkey solutions for your office furniture needs.
            One partner for all your workspace requirements.
          </p>

          <div class="wdx-hero-actions">
            <a href="get-quote.php" class="wdx-btn-green">Get Free Quote <i class="bi bi-arrow-right"></i></a>
            <input type="text" class="form-control wdx-hero-input" placeholder="Company / Requirements" aria-label="Company or requirements">
          </div>
        </div>
      </div>
    </div>

    <!-- stats strip -->
    <div class="wdx-srv-strip">
      <div class="container">
        <div class="row g-2">
          <div class="col-6 col-lg-3">
            <div class="wdx-stat">
              <p class="num">1,200+</p>
              <p class="lbl">Happy Clients</p>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat">
              <p class="num">25+</p>
              <p class="lbl">Years Experience</p>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat">
              <p class="num">50+</p>
              <p class="lbl">Cities Covered</p>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat">
              <p class="num">98%</p>
              <p class="lbl">Satisfaction Rate</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== OUR SERVICES ===================== -->
  <section class="wdx-section">
    <div class="container">
      <div class="wdx-kicker">WHAT WE OFFER</div>
      <h2 class="wdx-title">Our Services</h2>
      <p class="wdx-subtitle">Comprehensive solutions for every aspect of your office furniture needs</p>

      <div class="row g-4 mt-4">

        <!-- 1 -->
        <div class="col-12">
          <div class="wdx-svc">
            <div class="row g-0 align-items-stretch">
              <div class="col-lg-6">
                <img class="wdx-svc-img" src="<?php echo $domain; ?>assets/images/services/space-planning.jpg" alt="Space Planning & Design">
              </div>
              <div class="col-lg-6">
                <div class="wdx-svc-body">
                  <div class="wdx-ic"><i class="bi bi-rulers"></i></div>
                  <h4>Space Planning &amp; Design</h4>
                  <p>
                    Transform your workspace with our professional 3D design and floor planning services.
                    Our expert consultants analyze your space, workflow, and team needs to create optimal office layouts.
                  </p>

                  <ul class="wdx-bullets">
                    <li><i class="bi bi-check-circle-fill"></i> Free on-site consultation</li>
                    <li><i class="bi bi-check-circle-fill"></i> CAD floor plans</li>
                    <li><i class="bi bi-check-circle-fill"></i> Professional 3D visualization</li>
                    <li><i class="bi bi-check-circle-fill"></i> Ergonomic assessments</li>
                  </ul>

                  <a class="wdx-link" href="service-space-planning.php">Learn More <i class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2 (reverse) -->
        <div class="col-12">
          <div class="wdx-svc">
            <div class="row g-0 align-items-stretch flex-lg-row-reverse">
              <div class="col-lg-6">
                <img class="wdx-svc-img" src="<?php echo $domain; ?>assets/images/services/custom-design.jpg" alt="Custom Design Studio">
              </div>
              <div class="col-lg-6">
                <div class="wdx-svc-body">
                  <div class="wdx-ic"><i class="bi bi-palette2"></i></div>
                  <h4>Custom Design Studio</h4>
                  <p>
                    Bespoke furniture manufacturing tailored to your exact specifications.
                    From corporate branding integration to unique space requirements, we bring your vision to life.
                  </p>

                  <ul class="wdx-bullets">
                    <li><i class="bi bi-check-circle-fill"></i> Custom dimensions</li>
                    <li><i class="bi bi-check-circle-fill"></i> Brand integration</li>
                    <li><i class="bi bi-check-circle-fill"></i> 200+ material options</li>
                    <li><i class="bi bi-check-circle-fill"></i> In-house production</li>
                  </ul>

                  <a class="wdx-link" href="service-custom-design.php">Learn More <i class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3 -->
        <div class="col-12">
          <div class="wdx-svc">
            <div class="row g-0 align-items-stretch">
              <div class="col-lg-6">
                <img class="wdx-svc-img" src="<?php echo $domain; ?>assets/images/services/delivery-installation.jpg" alt="Delivery & Installation">
              </div>
              <div class="col-lg-6">
                <div class="wdx-svc-body">
                  <div class="wdx-ic"><i class="bi bi-truck"></i></div>
                  <h4>Delivery &amp; Installation</h4>
                  <p>
                    Professional delivery across Pakistan with expert installation services.
                    Our trained teams ensure your furniture is assembled correctly and ready to use.
                  </p>

                  <ul class="wdx-bullets">
                    <li><i class="bi bi-check-circle-fill"></i> 50+ cities covered</li>
                    <li><i class="bi bi-check-circle-fill"></i> Professional assembly</li>
                    <li><i class="bi bi-check-circle-fill"></i> White glove service</li>
                    <li><i class="bi bi-check-circle-fill"></i> Quality inspection</li>
                  </ul>

                  <a class="wdx-link" href="service-delivery-installation.php">Learn More <i class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 4 (reverse) -->
        <div class="col-12">
          <div class="wdx-svc">
            <div class="row g-0 align-items-stretch flex-lg-row-reverse">
              <div class="col-lg-6">
                <img class="wdx-svc-img" src="<?php echo $domain; ?>assets/images/services/project-quoting.jpg" alt="Project Quoting">
              </div>
              <div class="col-lg-6">
                <div class="wdx-svc-body">
                  <div class="wdx-ic"><i class="bi bi-clipboard-data"></i></div>
                  <h4>Project Quoting</h4>
                  <p>
                    Get detailed quotations for large-scale projects with dedicated project management.
                    We handle everything from assessment to final installation.
                  </p>

                  <ul class="wdx-bullets">
                    <li><i class="bi bi-check-circle-fill"></i> Itemized quotations</li>
                    <li><i class="bi bi-check-circle-fill"></i> Project manager assigned</li>
                    <li><i class="bi bi-check-circle-fill"></i> Volume discounts</li>
                    <li><i class="bi bi-check-circle-fill"></i> Timeline tracking</li>
                  </ul>

                  <a class="wdx-link" href="service-project-quoting.php">Learn More <i class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 5 -->
        <div class="col-12">
          <div class="wdx-svc">
            <div class="row g-0 align-items-stretch">
              <div class="col-lg-6">
                <img class="wdx-svc-img" src="<?php echo $domain; ?>assets/images/services/factory-direct.jpg" alt="Factory Direct">
              </div>
              <div class="col-lg-6">
                <div class="wdx-svc-body">
                  <div class="wdx-ic"><i class="bi bi-buildings"></i></div>
                  <h4>Factory Direct</h4>
                  <p>
                    Buy directly from our manufacturing facility and save compared to retail prices.
                    No middlemen — premium quality at honest prices.
                  </p>

                  <ul class="wdx-bullets">
                    <li><i class="bi bi-check-circle-fill"></i> Better value pricing</li>
                    <li><i class="bi bi-check-circle-fill"></i> Priority production</li>
                    <li><i class="bi bi-check-circle-fill"></i> 5–7 year warranty</li>
                    <li><i class="bi bi-check-circle-fill"></i> Direct QC</li>
                  </ul>

                  <a class="wdx-link" href="service-factory-direct.php">Learn More <i class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /row -->
    </div>
  </section>

  <!-- ===================== WHY CHOOSE ===================== -->
  <section class="wdx-section pt-0">
    <div class="container">
      <div class="wdx-kicker">WHY US</div>
      <h2 class="wdx-title">Why Choose WoodEx?</h2>
      <p class="wdx-subtitle">Trusted office furniture manufacturer with proven delivery, quality, and support</p>

      <div class="row g-4 mt-4 justify-content-center">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-why-card">
            <div class="wdx-why-ico"><i class="bi bi-box-seam"></i></div>
            <h6>Factory-Direct Quality</h6>
            <p>No middlemen — direct from our facility with strict quality control and better value.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-why-card">
            <div class="wdx-why-ico"><i class="bi bi-clock"></i></div>
            <h6>Fast Turnaround</h6>
            <p>Ready-stock items ship quickly. Custom orders delivered with clear timelines and updates.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-why-card">
            <div class="wdx-why-ico"><i class="bi bi-headset"></i></div>
            <h6>Lifetime Support</h6>
            <p>Comprehensive after-sales support available via phone, WhatsApp, and email.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== CTA ===================== -->
  <section class="wdx-cta-wrap">
    <div class="container">
      <div class="wdx-cta">
        <div class="inner">
          <h2>Ready to Get Started?</h2>
          <p>Request a free consultation with our design experts and receive a detailed quote within 24 hours.</p>

          <div class="wdx-cta-actions">
            <a href="get-quote.php" class="wdx-btn-green">Get Free Quote</a>
            <input type="text" class="form-control wdx-cta-input" placeholder="Your Phone / WhatsApp" aria-label="Phone or WhatsApp">
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
