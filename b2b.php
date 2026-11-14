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

  <title>B2B Office Furniture Solutions - Woodex Furniture</title>
  <meta name="description" content="Partner with Woodex for B2B office furniture solutions. Volume pricing, dedicated support, flexible terms, and professional delivery & setup across Pakistan.">


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

    /* =========================
       HERO (NO OVERLAY)
    ========================== */
    .wdx-b2b-hero{
      position:relative;
      min-height:460px;
      color:#fff;
      overflow:hidden;
      background:#0b1220;
    }
    .wdx-b2b-hero .wdx-bg{
      position:absolute; inset:0;
      background:url("<?php echo $domain; ?>assets/images/b2b-hero.jpg") center/cover no-repeat;
      transform:scale(1.02);
    }
    .wdx-b2b-hero .wdx-hero-inner{
      position:relative;
      z-index:2;
      padding:105px 0 60px;
    }

    .wdx-hero-h1{
      font-weight:950;
      letter-spacing:-.02em;
      line-height:1.05;
      font-size:clamp(36px, 4.2vw, 60px);
      margin:0 0 14px;
      text-shadow:0 6px 18px rgba(0,0,0,.35); /* readability without overlay */
    }
    .wdx-hero-h1 .accent{ color:var(--wdx-green); }

    .wdx-hero-p{
      max-width:760px;
      font-size:18px;
      color:rgba(255,255,255,.90);
      margin:0 0 22px;
      text-shadow:0 6px 18px rgba(0,0,0,.35); /* readability without overlay */
    }

    .wdx-hero-actions{
      display:flex;
      gap:14px;
      flex-wrap:wrap;
      align-items:center;
    }

    .wdx-btn-white{
      background:#fff;
      border:1px solid #fff;
      color:var(--wdx-dark);
      border-radius:12px;
      padding:12px 18px;
      font-weight:950;
      min-width:230px;
    }
    .wdx-btn-white:hover{ background:#f8fafc; border-color:#f8fafc; }

    .wdx-hero-input{
      min-width:260px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,.45);
      background:rgba(255,255,255,.18);
      color:#fff;
      padding:12px 14px;
      backdrop-filter: blur(6px);
    }
    .wdx-hero-input::placeholder{ color:rgba(255,255,255,.85); }
    .wdx-hero-input:focus{
      background:rgba(255,255,255,.22);
      color:#fff;
      border-color:rgba(121,190,31,.65);
      box-shadow:0 0 0 .2rem rgba(121,190,31,.16);
    }

    /* =========================
       GREEN FEATURE STRIP
    ========================== */
    .wdx-strip{
      background:var(--wdx-green);
      color:#fff;
      padding:18px 0;
    }
    .wdx-strip .s-item{
      display:flex;
      gap:14px;
      align-items:center;
      padding:8px 8px;
    }
    .wdx-strip .s-ico{
      width:48px; height:48px;
      border-radius:14px;
      display:grid; place-items:center;
      background:rgba(0,0,0,.10);
      border:1px solid rgba(255,255,255,.18);
      font-size:22px;
      flex:0 0 auto;
    }
    .wdx-strip .s-title{
      font-weight:950;
      margin:0;
      line-height:1.1;
    }
    .wdx-strip .s-sub{
      margin:2px 0 0;
      opacity:.92;
      font-size:13px;
    }

    /* =========================
       SECTIONS
    ========================== */
    .wdx-section{ padding:70px 0; background:#fff; }
    .wdx-title{
      font-weight:950;
      letter-spacing:-.02em;
      margin:0;
      font-size:clamp(30px, 3.2vw, 46px);
      text-align:center;
    }
    .wdx-subtitle{
      text-align:center;
      color:var(--wdx-muted);
      margin:10px auto 0;
      font-size:16px;
      max-width:820px;
    }

    /* WHY CHOOSE cards */
    .wdx-grid-card{
      border:1px solid var(--wdx-border);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 26px rgba(0,0,0,.06);
      padding:26px 22px;
      height:100%;
      transition:.18s ease;
    }
    .wdx-grid-card:hover{
      transform:translateY(-2px);
      box-shadow:0 16px 34px rgba(0,0,0,.08);
    }
    .wdx-grid-card.active{
      border-color:rgba(121,190,31,.75);
      box-shadow:0 18px 44px rgba(121,190,31,.18);
    }
    .wdx-ibox{
      width:52px; height:52px;
      border-radius:16px;
      display:grid; place-items:center;
      background:rgba(121,190,31,.12);
      border:1px solid rgba(121,190,31,.18);
      color:var(--wdx-green);
      font-size:24px;
      margin-bottom:16px;
    }
    .wdx-grid-card h5{
      font-weight:950;
      margin:0 0 8px;
      font-size:20px;
    }
    .wdx-grid-card p{
      margin:0;
      color:var(--wdx-muted);
      line-height:1.6;
    }

    /* Industries */
    .wdx-ind-card{
      border:1px solid var(--wdx-border);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 26px rgba(0,0,0,.06);
      padding:28px 22px;
      text-align:center;
      height:100%;
      transition:.18s ease;
    }
    .wdx-ind-card:hover{
      transform:translateY(-2px);
      box-shadow:0 16px 34px rgba(0,0,0,.08);
    }
    .wdx-ind-card.active{
      border-color:rgba(121,190,31,.75);
      box-shadow:0 18px 44px rgba(121,190,31,.18);
    }
    .wdx-ind-ico{
      font-size:34px;
      color:#7b7f86;
      margin-bottom:10px;
    }
    .wdx-ind-card.active .wdx-ind-ico{ color:var(--wdx-green); }
    .wdx-ind-card h6{
      font-weight:950;
      margin:0 0 8px;
      font-size:18px;
    }
    .wdx-ind-card .count{
      color:var(--wdx-green);
      font-weight:950;
      margin:0;
    }

    /* Process */
    .wdx-process{ padding-top:10px; }
    .wdx-step{
      text-align:center;
      padding:18px 10px;
    }
    .wdx-step .num{
      width:84px; height:84px;
      border-radius:50%;
      display:grid; place-items:center;
      margin:0 auto 16px;
      background:var(--wdx-green);
      color:#fff;
      font-weight:950;
      font-size:22px;
      box-shadow:0 16px 34px rgba(121,190,31,.22);
    }
    .wdx-step h5{
      font-weight:950;
      margin:0 0 8px;
      font-size:22px;
    }
    .wdx-step p{
      margin:0;
      color:var(--wdx-muted);
      line-height:1.6;
      max-width:260px;
      margin-left:auto;
      margin-right:auto;
    }

    /* CTA */
    .wdx-big-cta{ padding:34px 0 90px; background:#fff; }
    .wdx-cta-box{
      background:var(--wdx-green);
      color:#fff;
      border-radius:22px;
      padding:56px 28px;
      text-align:center;
      box-shadow:0 18px 46px rgba(121,190,31,.26);
    }
    .wdx-cta-box h2{
      font-weight:950;
      letter-spacing:-.02em;
      margin:0 0 10px;
      font-size:clamp(28px, 3.2vw, 50px);
    }
    .wdx-cta-box p{
      margin:0 auto 24px;
      max-width:860px;
      color:rgba(255,255,255,.92);
      font-size:18px;
      line-height:1.6;
    }
    .wdx-cta-btn{
      background:#fff;
      color:var(--wdx-dark);
      border:1px solid rgba(255,255,255,.55);
      border-radius:12px;
      padding:12px 18px;
      font-weight:950;
      min-width:240px;
      text-decoration:none;
      display:inline-flex;
      justify-content:center;
      align-items:center;
      gap:10px;
    }
    .wdx-cta-btn:hover{ background:#f8fafc; color:#000; }

    @media (max-width: 991px){
      .wdx-b2b-hero .wdx-hero-inner{ padding:92px 0 54px; }
      .wdx-btn-white{ min-width:200px; }
      .wdx-hero-input{ min-width:220px; }
    }
  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- =========================
       HERO (NO OVERLAY)
  ========================== -->
  <section class="wdx-b2b-hero">
    <div class="wdx-bg" aria-hidden="true"></div>

    <div class="container wdx-hero-inner">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="wdx-hero-h1">
            B2B Office Furniture<br><span class="accent">Solutions</span>
          </h1>

          <p class="wdx-hero-p">
            Partner with Pakistan's leading office furniture manufacturer. Volume pricing, dedicated support,
            and flexible terms for businesses of all sizes.
          </p>

          <div class="wdx-hero-actions">
            <a href="contact.php" class="btn wdx-btn-white">Request B2B Quote</a>
            <input type="text" class="form-control wdx-hero-input"
                   placeholder="Company Name / Requirements" aria-label="Company Name or Requirements">
          </div>
        </div>
      </div>
    </div>

    <!-- green strip -->
    <div class="wdx-strip">
      <div class="container">
        <div class="row g-3 align-items-center">

          <div class="col-12 col-md-6 col-lg-3">
            <div class="s-item">
              <div class="s-ico"><i class="bi bi-people"></i></div>
              <div>
                <p class="s-title">1,200+ Satisfied Clients</p>
                <p class="s-sub">Trusted by businesses across Pakistan</p>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <div class="s-item">
              <div class="s-ico"><i class="bi bi-shield-check"></i></div>
              <div>
                <p class="s-title">Quality Guaranteed</p>
                <p class="s-sub">Premium materials with ISO certification</p>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <div class="s-item">
              <div class="s-ico"><i class="bi bi-truck"></i></div>
              <div>
                <p class="s-title">Nationwide Delivery</p>
                <p class="s-sub">Free delivery &amp; installation in all major cities</p>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <div class="s-item">
              <div class="s-ico"><i class="bi bi-clipboard-check"></i></div>
              <div>
                <p class="s-title">Custom Solutions</p>
                <p class="s-sub">Tailored furniture to match your brand</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <!-- =========================
       WHY CHOOSE
  ========================== -->
  <section class="wdx-section">
    <div class="container">
      <h2 class="wdx-title">Why Choose WoodEx for B2B?</h2>
      <p class="wdx-subtitle">
        We understand the unique needs of businesses. Our B2B program is designed to provide maximum value, flexibility, and support.
      </p>

      <div class="row g-4 mt-4">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card">
            <div class="wdx-ibox"><i class="bi bi-graph-down-arrow"></i></div>
            <h5>Volume Discounts</h5>
            <p>Up to 40% off on bulk orders for corporate clients across Pakistan.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card active">
            <div class="wdx-ibox"><i class="bi bi-headset"></i></div>
            <h5>Dedicated Account Manager</h5>
            <p>Personal support for all your furniture needs and queries.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card">
            <div class="wdx-ibox"><i class="bi bi-journal-check"></i></div>
            <h5>Flexible Payment Terms</h5>
            <p>Customized payment plans, credit facility, and leasing options.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card">
            <div class="wdx-ibox"><i class="bi bi-clock-history"></i></div>
            <h5>Priority Delivery</h5>
            <p>Fast-track production and installation for business clients.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card">
            <div class="wdx-ibox"><i class="bi bi-patch-check"></i></div>
            <h5>Extended 7-Year Warranty</h5>
            <p>Enhanced warranty coverage exclusively for enterprise clients.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-grid-card">
            <div class="wdx-ibox"><i class="bi bi-building-gear"></i></div>
            <h5>Project Management</h5>
            <p>End-to-end coordination for large-scale office fit-outs.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================
       INDUSTRIES
  ========================== -->
  <section class="wdx-section pt-0">
    <div class="container">
      <h2 class="wdx-title">Industries We Serve</h2>
      <p class="wdx-subtitle">
        From startups to Fortune 500 companies, we've furnished workspaces across every major industry in Pakistan.
      </p>

      <div class="row g-4 mt-4">
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-buildings"></i></div>
            <h6>Corporate Offices</h6>
            <p class="count">450+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-people"></i></div>
            <h6>Co-working Spaces</h6>
            <p class="count">120+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card active">
            <div class="wdx-ind-ico"><i class="bi bi-mortarboard"></i></div>
            <h6>Educational Institutions</h6>
            <p class="count">200+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-hospital"></i></div>
            <h6>Healthcare Facilities</h6>
            <p class="count">85+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-bank"></i></div>
            <h6>Government Organizations</h6>
            <p class="count">65+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-icowrap wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-house-heart"></i></div>
            <h6>Hospitality &amp; Hotels</h6>
            <p class="count">95+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-buildings-fill"></i></div>
            <h6>Real Estate Developers</h6>
            <p class="count">150+ Projects</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-ind-card">
            <div class="wdx-ind-ico"><i class="bi bi-cash-coin"></i></div>
            <h6>Banks &amp; Financial</h6>
            <p class="count">180+ Projects</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================
       PROCESS
  ========================== -->
  <section class="wdx-section pt-0">
    <div class="container">
      <h2 class="wdx-title">Our B2B Process</h2>
      <p class="wdx-subtitle">A streamlined process designed for efficiency and transparency</p>

      <div class="row g-4 mt-4 wdx-process justify-content-center">
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="num">01</div>
            <h5>Consultation</h5>
            <p>Free needs assessment and space planning consultation with our experts</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="num">02</div>
            <h5>Custom Proposal</h5>
            <p>Detailed quotation with 3D visualization and product recommendations</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="num">03</div>
            <h5>Agreement</h5>
            <p>Flexible payment terms, timelines, and project milestones</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="num">04</div>
            <h5>Delivery &amp; Setup</h5>
            <p>Professional installation with minimal disruption to your operations</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================
       CTA
  ========================== -->
  <section class="wdx-big-cta">
    <div class="container">
      <div class="wdx-cta-box">
        <h2>Ready to Partner With Us?</h2>
        <p>Contact our B2B team to discuss volume pricing, custom solutions, and partnership opportunities.</p>

        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
          <a href="contact.php" class="wdx-cta-btn">Request B2B Quote</a>
          <a href="tel:+923001234567" class="wdx-cta-btn">Call: +92 300 1234567</a>
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
