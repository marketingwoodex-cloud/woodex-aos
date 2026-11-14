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

  <title>Contact Us - Woodex Furniture</title>
  <meta name="description" content="Contact Woodex Furniture for quotes, consultations, and showroom visits. We respond within 24 hours.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel CSS (kept same stack as your layout) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    :root{
      --wdx-green:#79be1f;
      --wdx-dark:#111315;
      --wdx-muted:#6b7280;
      --wdx-border:rgba(17,19,21,.10);
      --wdx-soft:#f3f4f6;
      --wdx-radius:18px;
      --wdx-shadow:0 14px 40px rgba(0,0,0,.10);
    }

    /* HERO */
    .wdx-contact-hero{
      position:relative;
      min-height:420px;
      color:#fff;
      overflow:hidden;
      background:#0b1220;
    }
    .wdx-contact-hero .wdx-bg{
      position:absolute; inset:0;
      background:url("<?php echo $domain; ?>assets/images/contact-hero.jpg") center/cover no-repeat;
      transform:scale(1.02);
    }
    .wdx-contact-hero .wdx-overlay{
      position:absolute; inset:0;
      background:linear-gradient(90deg, rgba(0,0,0,.70) 0%, rgba(0,0,0,.45) 45%, rgba(0,0,0,.20) 100%);
    }
    .wdx-contact-hero .wdx-hero-inner{ position:relative; z-index:2; padding:110px 0 70px; }
    .wdx-hero-h1{
      font-weight:900;
      letter-spacing:-.02em;
      line-height:1.05;
      font-size:clamp(36px, 4vw, 58px);
      margin:0 0 14px;
    }
    .wdx-hero-h1 .accent{ color:var(--wdx-green); }
    .wdx-hero-p{
      max-width:720px;
      font-size:18px;
      color:rgba(255,255,255,.85);
      margin:0;
    }

    /* GREEN STRIP */
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
      font-weight:800;
    }
    .wdx-strip .item i{ font-size:20px; opacity:.95; }
    .wdx-strip .item a{ color:#fff; text-decoration:none; }
    .wdx-strip .item a:hover{ text-decoration:underline; }

    /* PAGE */
    .wdx-section{ padding:60px 0; background:#fff; }

    .wdx-card{
      border:1px solid var(--wdx-border);
      border-radius:var(--wdx-radius);
      box-shadow:0 10px 28px rgba(0,0,0,.06);
      background:#fff;
      overflow:hidden;
    }
    .wdx-card .body{ padding:26px 22px 22px; }
    .wdx-card h3{
      font-weight:950;
      letter-spacing:-.01em;
      margin:0 0 6px;
      font-size:28px;
    }
    .wdx-card .sub{
      color:var(--wdx-muted);
      margin:0 0 18px;
    }

    /* FORM */
    .wdx-label{ font-weight:900; font-size:13px; margin-bottom:6px; }
    .wdx-input, .wdx-select, .wdx-textarea{
      border:1px solid rgba(17,19,21,.10);
      border-radius:12px;
      padding:12px 14px;
      background:#fff;
      font-size:14px;
    }
    .wdx-input:focus, .wdx-select:focus, .wdx-textarea:focus{
      border-color:rgba(121,190,31,.55);
      box-shadow:0 0 0 .2rem rgba(121,190,31,.12);
    }
    .wdx-textarea{ min-height:140px; resize:vertical; }

    .wdx-btn-dark{
      background:var(--wdx-dark);
      border-color:var(--wdx-dark);
      color:#fff;
      border-radius:12px;
      padding:12px 14px;
      font-weight:900;
    }
    .wdx-btn-dark:hover{ background:#000; border-color:#000; color:#fff; }

    /* Simple contact block (requested layout) */
    .wdx-contact-layout{
      padding-top:18px;
      background:#f7f8fa;
    }
    .wdx-contact-heading{
      font-size:40px;
      line-height:1.05;
      font-weight:950;
      letter-spacing:-.02em;
      margin:0;
      color:#172c49;
    }
    .wdx-contact-sub{ color:#59667a; margin:8px 0 0; font-size:30px; }
    .wdx-contact-panel{
      border:1px solid #d3dae4;
      border-radius:4px;
      background:#fff;
      box-shadow:none;
      padding:20px;
    }
    .wdx-info-box{
      border:1px solid #d3dae4;
      border-radius:0;
      padding:15px;
      display:flex;
      gap:12px;
      align-items:flex-start;
      margin-bottom:14px;
      background:#fff;
    }
    .wdx-info-box:last-child{ margin-bottom:0; }
    .wdx-info-box .icon{
      width:36px;
      height:36px;
      background:#f2f6ec;
      border:1px solid #e5edd8;
      color:#78be20;
      display:grid;
      place-items:center;
      font-size:18px;
      flex:0 0 auto;
    }
    .wdx-info-box .title{ font-weight:800; margin:0; color:#202938; }
    .wdx-info-box .text{ color:#6b7280; margin:2px 0 0; line-height:1.45; }
    .wdx-info-box .text a{ color:#6b7280; text-decoration:none; }
    .wdx-info-box .text a:hover{ text-decoration:underline; }

    .wdx-contact-panel .wdx-label{ font-size:16px; font-weight:700; color:#273143; }
    .wdx-contact-panel .wdx-input,
    .wdx-contact-panel .wdx-textarea{
      border-radius:0;
      border-color:#d4dbe5;
      font-size:16px;
      padding:11px 12px;
    }
    .wdx-contact-panel .wdx-textarea{ min-height:140px; }
    .wdx-contact-layout h4{
      margin:0 0 14px;
      font-size:24px;
      font-weight:900;
      letter-spacing:-.015em;
      color:#10243f;
    }
    .wdx-contact-layout .wdx-label{
      font-size:14px;
      font-weight:700;
      color:#1f2937;
      margin-bottom:6px;
      text-transform:none;
    }
    .wdx-contact-layout input.form-control.wdx-input,
    .wdx-contact-layout select.form-select.wdx-select,
    .wdx-contact-layout textarea.form-control.wdx-textarea{
      font-size:16px;
      padding:8px 12px;
      min-height:44px;
      border:1px solid #cfd7e2 !important;
      border-radius:0 !important;
      box-shadow:none !important;
      background:#fff;
    }
    .wdx-contact-layout .wdx-input::placeholder,
    .wdx-contact-layout .wdx-textarea::placeholder,
    .wdx-contact-layout .wdx-select{
      color:#8b95a5;
    }
    .wdx-contact-layout textarea.form-control.wdx-textarea{ min-height:118px; }
    .wdx-contact-layout .wdx-form-fit{
      height:100%;
    }
    .wdx-contact-layout .wdx-message-wrap{
      display:flex;
      flex-direction:column;
      flex:1 1 auto;
    }
    .wdx-contact-layout .wdx-message-wrap .wdx-textarea{
      min-height:220px;
      height:100%;
      flex:1 1 auto;
    }
    .wdx-contact-layout input.form-control.wdx-input:focus,
    .wdx-contact-layout select.form-select.wdx-select:focus,
    .wdx-contact-layout textarea.form-control.wdx-textarea:focus{
      border:1px solid #a9b6c8 !important;
      box-shadow:none !important;
    }
    .wdx-btn-green{
      background:#78be20;
      border:1px solid #78be20;
      color:#fff;
      font-weight:800;
      border-radius:0;
      padding:10px 12px;
      font-size:16px;
    }
    .wdx-btn-green:hover{ background:#67a61b; border-color:#67a61b; color:#fff; }

    .wdx-map-section{ padding:0 0 42px; background:#f7f8fa; }
    .wdx-map-frame{
      border:1px solid #d3dae4;
      border-radius:4px;
      overflow:hidden;
      background:#fff;
    }
    .wdx-map-frame iframe{
      width:100%;
      height:360px;
      border:0;
      display:block;
    }
    .wdx-map-address{
      margin:10px 0 0;
      font-size:14px;
      color:#4b5563;
    }

    .wdx-showrooms{ padding:10px 0 46px; background:#f7f8fa; }
    .wdx-showrooms-head{ text-align:center; margin-bottom:20px; }
    .wdx-showrooms-head .line{
      width:44px;
      height:3px;
      background:#78be20;
      margin:0 auto 10px;
    }
    .wdx-showrooms-head h3{
      margin:0;
      font-size:44px;
      font-weight:900;
      letter-spacing:-.02em;
      color:#10243f;
    }
    .wdx-showrooms-head p{
      margin:8px 0 0;
      color:#6b7280;
      font-size:14px;
    }
    .wdx-showroom-card{
      border:1px solid #d3dae4;
      border-radius:0;
      background:#fff;
      padding:18px 18px 14px;
      height:100%;
    }
    .wdx-showroom-card .bar{
      width:28px;
      height:3px;
      background:#78be20;
      margin-bottom:12px;
    }
    .wdx-showroom-card h5{
      margin:0 0 10px;
      font-size:26px;
      font-weight:800;
      color:#18273a;
    }
    .wdx-showroom-line{
      display:flex;
      align-items:flex-start;
      gap:8px;
      color:#667085;
      margin:6px 0;
      font-size:14px;
    }
    .wdx-showroom-line i{
      color:#78be20;
      margin-top:2px;
      font-size:14px;
    }

    .wdx-faq-lite{ padding:14px 0 46px; background:#f7f8fa; }
    .wdx-faq-lite-head{ text-align:center; margin-bottom:18px; }
    .wdx-faq-lite-head .line{
      width:44px;
      height:3px;
      background:#78be20;
      margin:0 auto 10px;
    }
    .wdx-faq-lite-head h3{
      margin:0;
      font-size:24px;
      font-weight:900;
      color:#10243f;
    }
    .wdx-faq-lite .accordion{ max-width:760px; margin:0 auto; }
    .wdx-faq-lite .accordion-item{
      border:1px solid #d3dae4 !important;
      border-radius:0 !important;
      background:#fff;
      margin-bottom:10px;
      overflow:hidden;
    }
    .wdx-faq-lite .accordion-button{
      font-weight:700;
      color:#1f2937;
      padding:14px 18px;
      background:#fff;
      box-shadow:none;
    }
    .wdx-faq-lite .accordion-button:not(.collapsed){
      background:#fff;
      color:#1f2937;
      box-shadow:none;
    }
    .wdx-faq-lite .accordion-button:focus{
      border:0;
      box-shadow:none;
    }
    .wdx-faq-lite .accordion-body{
      color:#667085;
      padding:0 18px 14px;
    }

    @media (max-width: 991px){
      .wdx-contact-layout h4{ font-size:24px; }
      .wdx-contact-layout .wdx-input,
      .wdx-contact-layout .wdx-select,
      .wdx-contact-layout .wdx-textarea{ font-size:15px; }
      .wdx-contact-layout .wdx-message-wrap .wdx-textarea{ min-height:150px; }
      .wdx-showrooms-head h3{ font-size:34px; }
    }

    /* GET IN TOUCH LIST */
    .wdx-touch h4{ font-weight:950; margin:0 0 16px; }
    .wdx-info-row{
      display:flex;
      gap:14px;
      align-items:flex-start;
      margin-bottom:18px;
    }
    .wdx-ibox{
      width:44px; height:44px;
      border-radius:14px;
      display:grid; place-items:center;
      background:rgba(121,190,31,.12);
      color:var(--wdx-green);
      flex:0 0 auto;
      border:1px solid rgba(121,190,31,.18);
    }
    .wdx-info-row .lbl{ font-weight:950; margin:0; }
    .wdx-info-row .val{ margin:2px 0 0; color:var(--wdx-muted); }
    .wdx-info-row .val a{ color:var(--wdx-dark); text-decoration:none; }
    .wdx-info-row .val a:hover{ text-decoration:underline; }

    /* WhatsApp panel */
    .wdx-soft-panel{
      background:#eef7df;
      border:1px solid rgba(121,190,31,.18);
      border-radius:18px;
      padding:18px 18px;
      margin-top:16px;
    }
    .wdx-soft-panel h6{ font-weight:950; margin:0 0 6px; }
    .wdx-soft-panel p{ color:var(--wdx-muted); margin:0 0 12px; }
    .wdx-btn-wa{
      background:var(--wdx-dark);
      border:1px solid var(--wdx-dark);
      color:#fff;
      border-radius:12px;
      padding:10px 14px;
      font-weight:900;
      display:inline-flex;
      align-items:center;
      gap:10px;
      text-decoration:none;
    }
    .wdx-btn-wa:hover{ background:#000; border-color:#000; color:#fff; }

    /* Locations */
    .wdx-title{
      font-weight:950;
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
    .wdx-loc-card{
      border:1px solid var(--wdx-border);
      border-radius:18px;
      background:#fff;
      box-shadow:0 10px 26px rgba(0,0,0,.06);
      padding:22px 20px;
      text-align:center;
      height:100%;
    }
    .wdx-loc-icon{
      width:70px; height:70px;
      border-radius:50%;
      display:grid; place-items:center;
      margin:0 auto 10px;
      background:rgba(121,190,31,.12);
      border:1px solid rgba(121,190,31,.18);
      color:var(--wdx-green);
      font-size:30px;
    }
    .wdx-pill{
      display:inline-block;
      padding:6px 12px;
      border-radius:999px;
      background:rgba(121,190,31,.12);
      color:var(--wdx-green);
      font-weight:900;
      font-size:12px;
      border:1px solid rgba(121,190,31,.18);
      margin-bottom:10px;
    }
    .wdx-loc-card h4{
      font-weight:950;
      margin:0 0 12px;
      letter-spacing:-.01em;
    }
    .wdx-loc-line{
      display:flex;
      gap:10px;
      align-items:flex-start;
      justify-content:center;
      color:var(--wdx-muted);
      margin:10px 0;
    }
    .wdx-loc-line i{ color:var(--wdx-green); margin-top:2px; }
    .wdx-outline-btn{
      border:1px solid rgba(17,19,21,.16);
      border-radius:12px;
      padding:10px 14px;
      font-weight:900;
      background:#fff;
      width:100%;
    }
    .wdx-outline-btn:hover{ border-color:rgba(17,19,21,.35); }

    /* FAQ */
    .wdx-faq-wrap{
      background:#f6f7f8;
      border-radius:22px;
      padding:44px 18px;
    }
    .accordion-item{
      border:1px solid rgba(17,19,21,.10) !important;
      border-radius:14px !important;
      overflow:hidden;
      background:#fff;
    }
    .accordion-button{
      font-weight:950;
      padding:18px 18px;
    }
    .accordion-button:not(.collapsed){
      background:#fff;
      color:var(--wdx-dark);
      box-shadow:none;
    }
    .accordion-button:focus{
      border-color:rgba(121,190,31,.55);
      box-shadow:0 0 0 .2rem rgba(121,190,31,.12);
    }
    .accordion-body{ color:var(--wdx-muted); padding:0 18px 18px; }

    /* BIG CTA */
    .wdx-big-cta{ padding:34px 0 80px; background:#fff; }
    .wdx-cta-box{
      background:var(--wdx-green);
      color:#fff;
      border-radius:22px;
      padding:54px 28px;
      text-align:center;
      box-shadow:0 18px 46px rgba(121,190,31,.26);
    }
    .wdx-cta-box h2{
      font-weight:950;
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
      font-weight:950;
      min-width:220px;
      text-decoration:none;
      display:inline-flex;
      justify-content:center;
      align-items:center;
      gap:10px;
    }
    .wdx-cta-btn:hover{ background:#f8fafc; color:#000; }
    .wdx-cta-btn-outline{
      background:rgba(255,255,255,.16);
      color:#fff;
      border:1px solid rgba(255,255,255,.50);
      border-radius:12px;
      padding:12px 18px;
      font-weight:950;
      min-width:220px;
      text-decoration:none;
      display:inline-flex;
      justify-content:center;
      align-items:center;
      gap:10px;
    }
    .wdx-cta-btn-outline:hover{ background:rgba(255,255,255,.22); color:#fff; }

    @media (max-width: 991px){
      .wdx-contact-hero .wdx-hero-inner{ padding:92px 0 58px; }
      .wdx-strip .item{ justify-content:flex-start; }
    }
  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- HERO -->
  <section class="wdx-contact-hero">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>

    <div class="container wdx-hero-inner">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="wdx-hero-h1">
            Contact<br><span class="accent">Us</span>
          </h1>
          <p class="wdx-hero-p">
            Let's transform your office together. Reach out to our team and we'll respond within 24 hours.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- FORM + CONTACT INFO -->
  <section class="wdx-section wdx-contact-layout">
    <div class="container">
      <div class="row g-4 align-items-stretch">

        <div class="col-12 col-lg-4 d-flex flex-column">
          <h4 class="mb-3" style="font-weight:900;color:#202938;">Contact Information</h4>

          <div class="wdx-contact-panel h-100">
            <div class="wdx-info-box">
              <div class="icon"><i class="bi bi-telephone"></i></div>
              <div>
                <p class="title">Call Us</p>
                <p class="text"><a href="tel:+923001234567">+92 300 1234567</a><br><a href="tel:+9242111WOOD">+92 42 111 WOODEX</a></p>
              </div>
            </div>

            <div class="wdx-info-box">
              <div class="icon"><i class="bi bi-envelope"></i></div>
              <div>
                <p class="title">Email Us</p>
                <p class="text"><a href="mailto:sales@woodexfurniture.pk">sales@woodexfurniture.pk</a></p>
              </div>
            </div>

            <div class="wdx-info-box">
              <div class="icon"><i class="bi bi-geo-alt"></i></div>
              <div>
                <p class="title">Head Office</p>
                <p class="text">123 Gulberg III<br>Lahore, Pakistan</p>
              </div>
            </div>

            <div class="wdx-info-box">
              <div class="icon"><i class="bi bi-clock"></i></div>
              <div>
                <p class="title">Business Hours</p>
                <p class="text">Mon-Sat: 9am-7pm<br>Sun: Closed</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-8 d-flex flex-column">
          <h4 class="mb-3" style="font-weight:900;color:#202938;">Send a Message</h4>
          <div class="wdx-contact-panel h-100">
            <form id="contactForm" action="submit_contact.php" method="post" class="row g-3 h-100 wdx-form-fit">
              <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="position:absolute; left:-9999px; height:1px; width:1px; opacity:0;">
              <input type="hidden" name="inquiry_type" value="General Inquiry">

              <div class="col-12 col-md-6">
                <label class="wdx-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control wdx-input" placeholder="Muhammad Ali" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="wdx-label">Email *</label>
                <input type="email" name="email" class="form-control wdx-input" placeholder="your@email.com" required>
              </div>

              <div class="col-12 col-md-6">
                <label class="wdx-label">Phone</label>
                <input type="text" name="phone" class="form-control wdx-input" placeholder="+92 300 1234567">
              </div>
              <div class="col-12 col-md-6">
                <label class="wdx-label">Subject</label>
                <input type="text" name="subject" class="form-control wdx-input" placeholder="Product inquiry">
              </div>

              <div class="col-12 wdx-message-wrap">
                <label class="wdx-label">Message *</label>
                <textarea name="message" class="form-control wdx-textarea" placeholder="Tell us about your project..." required></textarea>
              </div>

              <input type="hidden" name="consent" value="1">

              <div class="col-12">
                <div id="contactAlert" class="alert d-none" role="alert"></div>
                <button id="contactSubmitBtn" type="submit" class="btn wdx-btn-green w-100">Send Message</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </section>

  <section class="wdx-map-section">
    <div class="container">
      <div class="wdx-map-frame">
        <iframe
          title="Woodex Furniture Location"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps?q=Woodex%20Furniture%2C%20LG%2089%2C%20Model%2C%20Zainab%20Tower%2C%20Link%20Road%2C%20Phase%203%20Town%2C%20Lahore%2C%2054700%2C%20Pakistan&output=embed">
        </iframe>
      </div>
      <p class="wdx-map-address">
        Woodex Furniture, LG 89, Model, Zainab Tower, Link Road, Phase 3 Town, Lahore, 54700, Pakistan
      </p>
    </div>
  </section>

  <section class="wdx-showrooms">
    <div class="container">
      <div class="wdx-showrooms-head">
        <div class="line"></div>
        <h3>Visit Our Showrooms</h3>
        <p>Experience our furniture in person</p>
      </div>

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <article class="wdx-showroom-card">
            <div class="bar"></div>
            <h5>Lahore (HQ)</h5>
            <div class="wdx-showroom-line"><i class="bi bi-geo-alt"></i> 123 Gulberg III, Main Boulevard, Lahore</div>
            <div class="wdx-showroom-line"><i class="bi bi-telephone"></i> +92 42 111 WOODEX</div>
            <div class="wdx-showroom-line"><i class="bi bi-clock"></i> Mon-Sat: 9am-7pm</div>
          </article>
        </div>
        <div class="col-12 col-md-4">
          <article class="wdx-showroom-card">
            <div class="bar"></div>
            <h5>Karachi</h5>
            <div class="wdx-showroom-line"><i class="bi bi-geo-alt"></i> 456 Clifton Block 5, Karachi</div>
            <div class="wdx-showroom-line"><i class="bi bi-telephone"></i> +92 21 111 WOODEX</div>
            <div class="wdx-showroom-line"><i class="bi bi-clock"></i> Mon-Sat: 9am-7pm</div>
          </article>
        </div>
        <div class="col-12 col-md-4">
          <article class="wdx-showroom-card">
            <div class="bar"></div>
            <h5>Islamabad</h5>
            <div class="wdx-showroom-line"><i class="bi bi-geo-alt"></i> 789 Blue Area, F-7, Islamabad</div>
            <div class="wdx-showroom-line"><i class="bi bi-telephone"></i> +92 51 111 WOODEX</div>
            <div class="wdx-showroom-line"><i class="bi bi-clock"></i> Mon-Sat: 10am-6pm</div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="wdx-faq-lite">
    <div class="container">
      <div class="wdx-faq-lite-head">
        <div class="line"></div>
        <h3>Frequently Asked Questions</h3>
      </div>

      <div class="accordion" id="wdxFaqLite">
        <div class="accordion-item">
          <h2 class="accordion-header" id="faqLiteQ1">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqLiteA1" aria-expanded="false" aria-controls="faqLiteA1">
              How quickly can you deliver?
            </button>
          </h2>
          <div id="faqLiteA1" class="accordion-collapse collapse" aria-labelledby="faqLiteQ1" data-bs-parent="#wdxFaqLite">
            <div class="accordion-body">Standard delivery is usually 7-14 business days depending on location and stock availability.</div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="faqLiteQ2">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqLiteA2" aria-expanded="false" aria-controls="faqLiteA2">
              Do you offer installation services?
            </button>
          </h2>
          <div id="faqLiteA2" class="accordion-collapse collapse" aria-labelledby="faqLiteQ2" data-bs-parent="#wdxFaqLite">
            <div class="accordion-body">Yes, our team can provide installation and setup support with your order.</div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header" id="faqLiteQ3">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqLiteA3" aria-expanded="false" aria-controls="faqLiteA3">
              Can I visit the factory?
            </button>
          </h2>
          <div id="faqLiteA3" class="accordion-collapse collapse" aria-labelledby="faqLiteQ3" data-bs-parent="#wdxFaqLite">
            <div class="accordion-body">Factory visits can be arranged by appointment. Please contact us to schedule a visit.</div>
          </div>
        </div>

        <div class="accordion-item mb-0">
          <h2 class="accordion-header" id="faqLiteQ4">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqLiteA4" aria-expanded="false" aria-controls="faqLiteA4">
              What payment methods do you accept?
            </button>
          </h2>
          <div id="faqLiteA4" class="accordion-collapse collapse" aria-labelledby="faqLiteQ4" data-bs-parent="#wdxFaqLite">
            <div class="accordion-body">We accept bank transfer, online payment options, and approved business payment terms.</div>
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
    $(function(){
      const $form = $('#contactForm');
      const $btn = $('#contactSubmitBtn');
      const $alert = $('#contactAlert');

      function showAlert(type, msg){
        $alert.removeClass('d-none alert-success alert-danger alert-warning')
              .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
              .text(msg || 'Something went wrong.');
      }

      $form.on('submit', function(e){
        e.preventDefault();
        $alert.addClass('d-none').text('');

        const fullName = ($form.find('[name="full_name"]').val() || '').trim();
        const first = ($form.find('[name="first_name"]').val() || '').trim();
        const last = ($form.find('[name="last_name"]').val() || '').trim();
        const name = fullName || (first + ' ' + last).trim();
        const email = ($form.find('[name="email"]').val() || '').trim();
        const phone = ($form.find('[name="phone"]').val() || '').trim();
        const topic = ($form.find('[name="inquiry_type"]').val() || 'General Inquiry').trim();
        const subject = ($form.find('[name="subject"]').val() || '').trim();
        const orderNo = ($form.find('[name="order_number"]').val() || '').trim();
        const message = ($form.find('[name="message"]').val() || '').trim();
        const consent = true;
        const website = ($form.find('[name="website"]').val() || '').trim();

        if (!name || !email || !message) {
          showAlert('error', 'Please fill in all required fields.');
          return;
        }
        const payload = {
          name: name,
          email: email,
          phone: phone,
          topic: topic,
          order_number: orderNo,
          message: (subject ? ('Subject: ' + subject + ' | ') : '') + message,
          consent: 1,
          website: website
        };

        $btn.prop('disabled', true).text('Sending...');
        $.ajax({
          url: (window.SITE_BASE || '').toString() + 'submit_contact.php',
          method: 'POST',
          dataType: 'json',
          data: payload
        }).done(function(res){
          if (res && res.status === 'success') {
            showAlert('success', res.message || 'Your message has been sent successfully!');
            $form[0].reset();
          } else {
            showAlert('error', (res && res.message) ? res.message : 'Failed to send message.');
          }
        }).fail(function(){
          showAlert('error', 'Failed to send message. Please try again.');
        }).always(function(){
          $btn.prop('disabled', false).text('Send Message');
        });
      });
    });
  </script>
</body>
</html>
