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

  <title>Visit Our Showrooms - Woodex Furniture</title>
  <meta name="description" content="Visit our showrooms to experience Pakistan's finest office furniture collection in person. Walk-ins welcome or schedule a personalized visit.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel CSS (kept same stack as your layout) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    :root{
      --wdx-green:#79be1f; /* close to your reference */
      --wdx-dark:#111315;
      --wdx-muted:#6b7280;
      --wdx-border:rgba(17,19,21,.10);
      --wdx-soft:#f6f7f8;
      --wdx-radius:18px;
      --wdx-shadow:0 14px 40px rgba(0,0,0,.10);
    }

    /* HERO */
    .wdx-show-hero{
      position:relative;
      min-height:420px;
      color:#fff;
      overflow:hidden;
      background:#0b1220;
    }
    .wdx-show-hero .wdx-bg{
      position:absolute; inset:0;
      background:url("<?php echo $domain; ?>assets/images/showroom-hero.jpg") center/cover no-repeat;
      transform:scale(1.02);
    }
    .wdx-show-hero .wdx-overlay{
      position:absolute; inset:0;
      background:linear-gradient(90deg, rgba(0,0,0,.70) 0%, rgba(0,0,0,.45) 45%, rgba(0,0,0,.20) 100%);
    }
    .wdx-show-hero .wdx-hero-inner{ position:relative; z-index:2; padding:110px 0 70px; }
    .wdx-hero-h1{
      font-weight:800;
      letter-spacing:-.02em;
      line-height:1.05;
      font-size:clamp(36px, 4vw, 58px);
      margin:0 0 14px;
    }
    .wdx-hero-h1 .accent{ color:var(--wdx-green); }
    .wdx-hero-p{
      max-width:640px;
      font-size:18px;
      color:rgba(255,255,255,.85);
      margin:0;
    }

    /* GREEN STATS STRIP */
    .wdx-strip{
      background:var(--wdx-green);
      color:#fff;
      padding:16px 0;
    }
    .wdx-strip .item{
      display:flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      font-weight:700;
    }
    .wdx-strip .item i{ font-size:20px; opacity:.95; }
    .wdx-strip .item span{ font-weight:700; }
    .wdx-strip .item small{ display:block; font-weight:600; opacity:.95; }

    /* FEATURES ROW under strip */
    .wdx-mini-feats{ padding:34px 0 10px; }
    .wdx-mini-feats .feat-title{ font-weight:800; margin:0; }
    .wdx-mini-feats .feat-sub{ color:var(--wdx-muted); margin:6px 0 0; }
    .wdx-mini-feats .feat{
      text-align:center;
      padding:12px 10px;
    }
    .wdx-mini-feats .feat h6{ font-weight:800; margin:0 0 6px; }
    .wdx-mini-feats .feat p{ color:var(--wdx-muted); margin:0; }

    /* SHOWROOM CARDS */
    .wdx-showrooms{ padding:42px 0 64px; background:#fff; }
    .wdx-card{
      border:1px solid var(--wdx-border);
      border-radius:var(--wdx-radius);
      box-shadow:0 10px 28px rgba(0,0,0,.06);
      background:#fff;
      overflow:hidden;
      height:100%;
    }
    .wdx-card .headbar{
      background:var(--wdx-green);
      color:#fff;
      font-weight:800;
      text-align:center;
      padding:12px 14px;
      font-size:14px;
      letter-spacing:.02em;
    }
    .wdx-card .body{ padding:26px 22px 22px; }
    .wdx-card h3{
      font-weight:800;
      letter-spacing:-.01em;
      margin:0 0 18px;
      font-size:28px;
    }
    .wdx-info-row{
      display:flex;
      gap:14px;
      align-items:flex-start;
      margin-bottom:14px;
    }
    .wdx-ibox{
      width:40px; height:40px;
      border-radius:12px;
      display:grid; place-items:center;
      background:rgba(121,190,31,.12);
      color:var(--wdx-green);
      flex:0 0 auto;
      border:1px solid rgba(121,190,31,.18);
    }
    .wdx-info-row .lbl{ font-weight:700; margin:0; }
    .wdx-info-row .val{ margin:2px 0 0; color:var(--wdx-muted); }

    .wdx-divider{ height:1px; background:rgba(17,19,21,.10); margin:18px 0; }

    .wdx-feat-list{
      list-style:none;
      padding:0; margin:0;
      display:grid;
      gap:10px;
      color:var(--wdx-muted);
    }
    .wdx-feat-list li{
      display:flex;
      gap:10px;
      align-items:flex-start;
    }
    .wdx-feat-list i{
      color:var(--wdx-green);
      margin-top:2px;
    }

    .wdx-btn-dark{
      background:var(--wdx-dark);
      border-color:var(--wdx-dark);
      color:#fff;
      border-radius:12px;
      padding:12px 14px;
      font-weight:800;
    }
    .wdx-btn-dark:hover{ background:#000; border-color:#000; color:#fff; }
    .wdx-btn-outline{
      border:1px solid rgba(17,19,21,.18);
      border-radius:12px;
      padding:12px 14px;
      font-weight:800;
      background:#fff;
    }
    .wdx-btn-outline:hover{ border-color:rgba(17,19,21,.35); }

    /* WHAT TO EXPECT */
    .wdx-expect{ padding:64px 0; background:#fff; }
    .wdx-title{
      font-weight:750;
      letter-spacing:-.02em;
      margin:0;
      font-size:clamp(28px, 3vw, 44px);
      text-align:center;
    }
    .wdx-subtitle{
      text-align:center;
      color:var(--wdx-muted);
      margin:10px 0 0;
    }

    .wdx-step{
      text-align:center;
      padding:18px 12px;
    }
    .wdx-step .badge-num{
      width:58px; height:58px;
      margin:0 auto 14px;
      border-radius:50%;
      background:var(--wdx-green);
      color:#fff;
      display:grid; place-items:center;
      font-weight:900;
      letter-spacing:.06em;
      box-shadow:0 12px 26px rgba(121,190,31,.26);
    }
    .wdx-step h5{ font-weight:700; margin:0 0 8px; }
    .wdx-step p{ color:var(--wdx-muted); margin:0; max-width:260px; margin-inline:auto; }

    /* CAN'T VISIT */
    .wdx-soft-panel{
      background:#f3f4f6;
      border-radius:22px;
      padding:34px 28px;
      margin-top:34px;
    }
    .wdx-soft-panel h3{ font-weight:950; margin:0 0 8px; letter-spacing:-.02em; }
    .wdx-soft-panel p{ color:var(--wdx-muted); margin:0 0 18px; max-width:640px; }

    .wdx-stat-card{
      background:#fff;
      border-radius:18px;
      box-shadow:0 14px 30px rgba(0,0,0,.10);
      padding:26px 22px;
      text-align:center;
      border:1px solid rgba(17,19,21,.08);
      max-width:320px;
      margin-left:auto;
    }
    .wdx-stat-card .num{
      font-size:64px;
      line-height:1;
      font-weight:750;
      color:var(--wdx-green);
      letter-spacing:-.03em;
    }
    .wdx-stat-card .t{ font-weight:900; margin-top:8px; }
    .wdx-stat-card .s{ color:var(--wdx-muted); margin-top:2px; }

    /* SCHEDULE BIG CTA */
    .wdx-big-cta{
      padding:56px 0 78px;
      background:#fff;
    }
    .wdx-cta-box{
      background:var(--wdx-green);
      color:#fff;
      border-radius:22px;
      padding:54px 28px;
      text-align:center;
      box-shadow:0 18px 46px rgba(121,190,31,.26);
    }
    .wdx-cta-box h2{
      font-weight:750;
      letter-spacing:-.02em;
      margin:0 0 10px;
      font-size:clamp(26px, 3vw, 46px);
    }
    .wdx-cta-box p{
      margin:0 auto 22px;
      max-width:820px;
      color:rgba(255,255,255,.92);
      font-size:18px;
    }
    .wdx-cta-btn{
      background:#fff;
      color:var(--wdx-dark);
      border:1px solid rgba(255,255,255,.55);
      border-radius:12px;
      padding:12px 18px;
      font-weight:900;
      min-width:220px;
    }
    .wdx-cta-btn:hover{ background:#f8fafc; color:#000; }
    .wdx-cta-btn-outline{
      background:rgba(255,255,255,.16);
      color:#fff;
      border:1px solid rgba(255,255,255,.50);
      border-radius:12px;
      padding:12px 18px;
      font-weight:700;
      min-width:220px;
    }
    .wdx-cta-btn-outline:hover{ background:rgba(255,255,255,.22); color:#fff; }

    @media (max-width: 991px){
      .wdx-show-hero .wdx-hero-inner{ padding:92px 0 58px; }
      .wdx-stat-card{ margin:22px auto 0; }
    }
    @media (max-width: 575px){
      .wdx-strip .item{ justify-content:flex-start; }
      .wdx-soft-panel{ padding:26px 18px; }
      .wdx-card .body{ padding:22px 18px 18px; }
    }
  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- HERO -->
  <section class="wdx-show-hero">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>

    <div class="container wdx-hero-inner">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="wdx-hero-h1">
            Visit Our<br>
            <span class="accent">Showrooms</span>
          </h1>
          <p class="wdx-hero-p">
            Experience Pakistan's finest office furniture collection in person. Walk-ins welcome or schedule a personalized visit.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- GREEN STRIP STATS -->
  <section class="wdx-strip">
    <div class="container">
      <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
          <div class="item">
            <i class="bi bi-box-seam"></i>
            <span>56 Products</span>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="item">
            <i class="bi bi-shield-check"></i>
            <span>5-7 Year Warranty</span>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="item">
            <i class="bi bi-people"></i>
            <span>Expert Consultants</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MINI FEATURES -->
  <section class="wdx-mini-feats">
    <div class="container">
      <div class="row g-3">
        <div class="col-12 col-md-3">
          <div class="feat">
            <h6>Live Product Displays</h6>
            <p>Experience our furniture in real office setups</p>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="feat">
            <h6>3D Design Studio</h6>
            <p>Visualize your workspace before ordering</p>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="feat">
            <h6>Expert Consultants</h6>
            <p>Get personalized advice from our team</p>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="feat">
            <h6>Material Samples</h6>
            <p>Touch and feel our premium materials</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- SHOWROOMS -->
  <section class="wdx-showrooms">
    <div class="container">
      <div class="row g-4 justify-content-center">

        <!-- Lahore -->
        <div class="col-12 col-lg-4">
          <div class="wdx-card h-100" style="border-color: rgba(121,190,31,.55); box-shadow: var(--wdx-shadow);">
            <div class="headbar">Factory &amp; Headquarters</div>
            <div class="body">
              <h3>Lahore - Headquarters</h3>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-geo-alt"></i></div>
                <div>
                  <p class="lbl mb-0">Address</p>
                  <p class="val">45-A Industrial Area, Gulberg III, Lahore, Punjab</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-telephone"></i></div>
                <div>
                  <p class="lbl mb-0">Phone</p>
                  <p class="val">+92 42 3578 9012</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-envelope"></i></div>
                <div>
                  <p class="lbl mb-0">Email</p>
                  <p class="val">sales@woodexfurniture.pk</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-clock"></i></div>
                <div>
                  <p class="lbl mb-0">Hours</p>
                  <p class="val">Mon-Sat: 9:00 AM - 7:00 PM<br>Sunday: Closed</p>
                </div>
              </div>

              <div class="wdx-divider"></div>

              <p class="fw-bold mb-2" style="font-weight:950;">Showroom Features:</p>
              <ul class="wdx-feat-list">
                <li><i class="bi bi-check-circle"></i> Full 56 product range</li>
                <li><i class="bi bi-check-circle"></i> 3D visualization studio</li>
                <li><i class="bi bi-check-circle"></i> Design consultation center</li>
                <li><i class="bi bi-check-circle"></i> Factory tours available</li>
              </ul>

              <div class="mt-4 d-grid gap-2">
                <a href="#" class="btn wdx-btn-dark">
                  <i class="bi bi-send me-2"></i> Get Directions
                </a>
                <a href="#" class="btn wdx-btn-outline">
                  <i class="bi bi-calendar2-event me-2"></i> Schedule Visit
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Karachi -->
        <div class="col-12 col-lg-4">
          <div class="wdx-card h-100">
            <div class="body">
              <h3>Karachi</h3>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-geo-alt"></i></div>
                <div>
                  <p class="lbl mb-0">Address</p>
                  <p class="val">Plot 12, SITE Industrial Area, Karachi, Sindh</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-telephone"></i></div>
                <div>
                  <p class="lbl mb-0">Phone</p>
                  <p class="val">+92 21 3456 7890</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-envelope"></i></div>
                <div>
                  <p class="lbl mb-0">Email</p>
                  <p class="val">sales@woodexfurniture.pk</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-clock"></i></div>
                <div>
                  <p class="lbl mb-0">Hours</p>
                  <p class="val">Mon-Sat: 9:00 AM - 6:00 PM<br>Sunday: Closed</p>
                </div>
              </div>

              <div class="wdx-divider"></div>

              <p class="fw-bold mb-2" style="font-weight:950;">Showroom Features:</p>
              <ul class="wdx-feat-list">
                <li><i class="bi bi-check-circle"></i> Executive furniture displays</li>
                <li><i class="bi bi-check-circle"></i> Corporate solutions center</li>
                <li><i class="bi bi-check-circle"></i> B2B services hub</li>
              </ul>

              <div class="mt-4 d-grid gap-2">
                <a href="#" class="btn wdx-btn-dark">
                  <i class="bi bi-send me-2"></i> Get Directions
                </a>
                <a href="#" class="btn wdx-btn-outline">
                  <i class="bi bi-calendar2-event me-2"></i> Schedule Visit
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Islamabad -->
        <div class="col-12 col-lg-4">
          <div class="wdx-card h-100">
            <div class="body">
              <h3>Islamabad</h3>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-geo-alt"></i></div>
                <div>
                  <p class="lbl mb-0">Address</p>
                  <p class="val">Blue Area, Jinnah Avenue, Islamabad, Federal Capital</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-telephone"></i></div>
                <div>
                  <p class="lbl mb-0">Phone</p>
                  <p class="val">+92 51 2345 6789</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-envelope"></i></div>
                <div>
                  <p class="lbl mb-0">Email</p>
                  <p class="val">sales@woodexfurniture.pk</p>
                </div>
              </div>

              <div class="wdx-info-row">
                <div class="wdx-ibox"><i class="bi bi-clock"></i></div>
                <div>
                  <p class="lbl mb-0">Hours</p>
                  <p class="val">Mon-Sat: 9:30 AM - 6:30 PM<br>Sunday: Closed</p>
                </div>
              </div>

              <div class="wdx-divider"></div>

              <p class="fw-bold mb-2" style="font-weight:950;">Showroom Features:</p>
              <ul class="wdx-feat-list">
                <li><i class="bi bi-check-circle"></i> Government project specialists</li>
                <li><i class="bi bi-check-circle"></i> Premium collections</li>
                <li><i class="bi bi-check-circle"></i> Consultation services</li>
              </ul>

              <div class="mt-4 d-grid gap-2">
                <a href="#" class="btn wdx-btn-dark">
                  <i class="bi bi-send me-2"></i> Get Directions
                </a>
                <a href="#" class="btn wdx-btn-outline">
                  <i class="bi bi-calendar2-event me-2"></i> Schedule Visit
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- WHAT TO EXPECT -->
  <section class="wdx-expect">
    <div class="container">
      <h2 class="wdx-title">What to Expect</h2>
      <p class="wdx-subtitle">Make the most of your showroom visit</p>

      <div class="row g-4 mt-4 justify-content-center">
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="badge-num">01</div>
            <h5>Browse</h5>
            <p>Explore our complete range of 56 premium products across 11 categories</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="badge-num">02</div>
            <h5>Experience</h5>
            <p>Test chairs, desks, and workstations to find your perfect match</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="badge-num">03</div>
            <h5>Consult</h5>
            <p>Speak with our design experts about your specific requirements</p>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="wdx-step">
            <div class="badge-num">04</div>
            <h5>Visualize</h5>
            <p>See your office design come to life with 3D visualization</p>
          </div>
        </div>
      </div>

      <!-- Can't visit panel -->
      <div class="wdx-soft-panel">
        <div class="row align-items-center g-4">
          <div class="col-lg-7">
            <h3>Can't Visit in Person?</h3>
            <p>
              Experience our showroom from anywhere in Pakistan. Take a virtual tour,
              schedule a video consultation, or browse our complete catalog online.
            </p>

            <div class="d-flex flex-wrap gap-3">
              <a href="#" class="btn wdx-btn-dark px-4">Virtual Showroom Tour</a>
              <a href="#" class="btn wdx-btn-outline px-4">Browse Catalog</a>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="wdx-stat-card">
              <div class="num">56</div>
              <div class="t">Products Available</div>
              <div class="s">Across 11 categories</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- BIG CTA -->
  <section class="wdx-big-cta">
    <div class="container">
      <div class="wdx-cta-box">
        <h2>Schedule Your Showroom Visit</h2>
        <p>Book an appointment with our design consultants for a personalized showroom tour and expert advice.</p>

        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
          <a href="#" class="btn wdx-cta-btn">Schedule Appointment</a>
          <a href="tel:+923001234567" class="btn wdx-cta-btn-outline">Call: +92 300 1234567</a>
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
