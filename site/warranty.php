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
  <title>Warranty Policy • Woodex</title>
  <meta name="description" content="Woodex warranty policy: coverage, duration, exclusions, and how to claim." />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <style>
    .war-hero .wdx-hero-title{ margin-bottom: 10px; }
    .war-hero .wdx-desc{ max-width: 640px; }

    .war-statbar {
      background: #78b12b;
      color: #fff;
      padding: 18px 0;
    }
    .war-statbar .war-stat-number {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 800;
      font-size: 1.35rem;
      line-height: 1;
    }
    .war-statbar .war-stat-label {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      opacity: .95;
      font-size: .9rem;
      margin-top: 6px;
    }

    .war-wrap{ padding: 56px 0; background:#fff; }
    .war-kicker {
      letter-spacing: .16em;
      font-weight: 800;
      font-size: .75rem;
      opacity: .75;
      text-transform: uppercase;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .war-title {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 800;
      color: #0f172a;
      margin: 8px 0 12px;
    }
    .war-subtitle {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: #334155;
      max-width: 820px;
      margin: 0 auto;
    }

    .war-overview { padding: 40px 0 10px; background: #fff; }
    .war-overview-card {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(2,6,23,.06);
      background: #fff;
    }
    .war-overview-media img {
      width: 100%;
      height: 100%;
      min-height: 260px;
      object-fit: cover;
      display: block;
    }
    .war-overview-body { padding: 28px; }
    .war-list { margin: 0; padding: 0; list-style: none; }
    .war-list li {
      display:flex;
      gap:10px;
      margin:10px 0;
      color:#334155;
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .war-list i{ color:#78b12b; margin-top:3px; }

    .war-grid{ padding: 44px 0 8px; }
    .war-card{
      height:100%;
      border:1px solid rgba(15,23,42,.1);
      border-radius:12px;
      background:#fff;
      box-shadow:0 6px 16px rgba(2,6,23,.05);
      padding:18px 18px;
    }
    .war-card h3{
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-size:1.12rem;
      font-weight:800;
      color:#0f172a;
      margin:0 0 10px;
    }
    .war-card p,
    .war-card li{
      color:#475569;
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      line-height:1.65;
    }

    .war-cta {
      background: #0b1220;
      color: #fff;
      border-radius: 14px;
      padding: 38px 24px;
      box-shadow: 0 12px 40px rgba(2,6,23,.25);
      margin-top: 24px;
    }
    .war-cta h3 {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 900;
      margin-bottom: 10px;
    }
    .war-cta p {
      font-family: "Nunito Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      opacity: .92;
      margin-bottom: 18px;
    }
    .war-btn {
      border-radius: 999px;
      padding: 10px 18px;
      font-weight: 800;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }
    .war-btn-primary {
      background: #78b12b;
      border: none;
      color: #fff;
    }
    .war-btn-primary:hover { opacity: .92; color:#fff; }
  </style>
</head>
<body>
  <?php include_once('header/header.php'); ?>

  <section class="war-hero">
    <div class="wdx-hero text-white">
      <div class="wdx-bg" aria-hidden="true" style="background-image:url('<?php echo $domain; ?>assets/images/Warranty/WoodEx-warranty.jpg'); background-size:cover; background-position:center;"></div>
      <div class="wdx-overlay" aria-hidden="true"></div>

      <div class="container h-100 position-relative">
        <div class="row h-100 align-items-center">
          <div class="col-lg-7 col-xl-6">
            <div class="wdx-badge">After-Sales Protection</div>
            <h1 class="wdx-hero-title">Warranty <span>Policy</span></h1>
            <p class="wdx-desc">Clear and dependable warranty support for your Woodex products, from coverage details to simple claim handling.</p>
            <div class="d-flex flex-wrap gap-3 mt-3">
              <a href="mailto:sales@woodexfurniture.pk" class="btn wdx-btn">Start a Claim <i class="bi bi-arrow-right ms-2"></i></a>
              <a href="<?php echo $domain; ?>contact-us" class="btn btn-outline-light rounded-pill px-4">Contact Support</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="war-statbar">
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-lg-3">
            <div class="war-stat-number">1-5 Years</div>
            <div class="war-stat-label">Coverage (Model Based)</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="war-stat-number">48 Hours</div>
            <div class="war-stat-label">Initial Claim Response</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="war-stat-number">Nationwide</div>
            <div class="war-stat-label">Support Network</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="war-stat-number">Repair / Replace</div>
            <div class="war-stat-label">Case-Based Resolution</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="war-overview">
    <div class="container">
      <div class="war-overview-card">
        <div class="row g-0 align-items-stretch">
          <div class="col-lg-5 war-overview-media">
            <img src="<?php echo $domain; ?>assets/images/Warranty/Warranty-inspection-and-support.jpg" alt="Warranty inspection and support" loading="lazy">
          </div>
          <div class="col-lg-7">
            <div class="war-overview-body">
              <div class="war-kicker">Coverage Highlights</div>
              <h2 class="war-title">What Our Warranty Covers</h2>
              <p class="war-subtitle mb-3" style="margin-left:0;">Woodex warranty protects against genuine manufacturing defects under normal indoor use conditions.</p>

              <ul class="war-list">
                <li><i class="bi bi-check-circle-fill"></i> Structural defects and workmanship issues</li>
                <li><i class="bi bi-check-circle-fill"></i> Manufacturing faults in moving mechanisms</li>
                <li><i class="bi bi-check-circle-fill"></i> Material defects found during normal use</li>
                <li><i class="bi bi-check-circle-fill"></i> Replacement parts support (where applicable)</li>
                <li><i class="bi bi-check-circle-fill"></i> Repair or replacement based on technical assessment</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="war-grid">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="war-card">
            <h3>What is not covered</h3>
            <ul class="mb-0">
              <li>Normal wear and tear (fading, minor scratches, pilling, foam softening)</li>
              <li>Misuse, accidental damage, improper handling, or unauthorized modifications</li>
              <li>Water, heat, or chemical exposure damage</li>
              <li>Commercial use unless specifically covered in writing</li>
              <li>Damage caused by third-party handling or incorrect self-assembly</li>
            </ul>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="war-card">
            <h3>How to submit a claim</h3>
            <p class="mb-2">To file a claim, please share:</p>
            <ul>
              <li>Order number / invoice</li>
              <li>Clear photos or videos of the issue and full product</li>
              <li>Your delivery address and phone number</li>
            </ul>
            <p class="mb-0">Email us at <strong>sales@woodexfurniture.pk</strong>. Typical initial response: 1–2 business days.</p>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="war-card">
            <h3>Resolution process</h3>
            <p class="mb-0">Once approved, we may provide repair, replacement parts, full replacement (subject to stock), or another suitable resolution based on technical evaluation.</p>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="war-card">
            <h3>Warranty duration</h3>
            <p class="mb-0">Warranty period depends on product category and model. The invoice and product page warranty details are treated as final for that specific purchase.</p>
          </div>
        </div>
      </div>

      <div class="war-cta text-center">
        <h3>Need Warranty Assistance?</h3>
        <p>Our support team can help verify your warranty and guide you through the claim process quickly.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
          <a href="mailto:sales@woodexfurniture.pk" class="btn war-btn war-btn-primary">Email Warranty Team</a>
          <a href="https://wa.me/923224000768" target="_blank" class="btn war-btn btn-outline-light">WhatsApp Support</a>
        </div>
      </div>
    </div>
  </section>

  <?php include_once('footer/footer.php'); ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
