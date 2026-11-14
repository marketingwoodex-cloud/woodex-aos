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

  <title>Best Office Furniture in Pakistan - Woodex Furniture</title>
  <meta name="description" content="Buy office furniture direct from our Lahore manufacturing facility. Factory-direct pricing, custom options, faster lead times, and quality-controlled production.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel CSS should be in HEAD -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

  <!-- Page-only fixes + sections styling (kept same classes) -->
  <style>

  </style>
</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- ===== Compact Factory Direct Pricing Section (Bootstrap) ===== -->
  <section>
    <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo $domain; ?>assets/images/herobg.webp');">
      <div class="wdx-bg" aria-hidden="true"></div>
      <div class="wdx-overlay" aria-hidden="true"></div>

      <div class="container h-100 position-relative">
        <div class="row h-100 align-items-center">
          <div class="col-lg-7 col-xl-6">
            <div class="wdx-badge">Save 30-50% on Every Order</div>

            <h2 class="wdx-hero-title">
              Factory Direct <span>Pricing</span>
            </h2>

            <p class="wdx-desc">
              Buy directly from our Lahore manufacturing facility and save significantly compared to retail prices.
              No middlemen, no markups—just premium quality furniture at honest wholesale prices.
            </p>

            <div class="d-flex flex-wrap gap-3 align-items-center">
              <a href="factory-quote.php" class="btn wdx-btn">
                Get Factory Quote <i class="bi bi-arrow-right ms-2"></i>
              </a>

              <input type="text" class="form-control wdx-input"
                     placeholder="Enter Product / Quantity" aria-label="Enter Product or Quantity">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="wdx-stats">
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">25+</div>
            <div class="wdx-stat-label">Years Manufacturing</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">50,000+</div>
            <div class="wdx-stat-label">Units Produced Annually</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">ISO 9001</div>
            <div class="wdx-stat-label">Certified Quality</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">5,000+</div>
            <div class="wdx-stat-label">Happy Customers</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Why Buy Factory Direct ===== -->
  <section class="wdx-why">
    <div class="container">
      <div class="text-center">
        <div class="wdx-kicker">BENEFITS</div>
        <h2 class="wdx-title">Why Buy Factory Direct?</h2>
        <p class="wdx-subtitle">
          Skip the retail markup and buy directly from Pakistan's leading office furniture manufacturer
        </p>
      </div>

      <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-truck"></i></div>
            <h5>30-50% Lower Prices</h5>
            <p>Eliminate middleman markups. Buy directly from our factory at wholesale prices with no hidden costs.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-clock"></i></div>
            <h5>Full Manufacturing Control</h5>
            <p>We control every step of production, ensuring consistent quality and faster turnaround times.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-shield-check"></i></div>
            <h5>Quality Guaranteed</h5>
            <p>ISO-certified manufacturing facility with rigorous quality control at every production stage.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-box-seam"></i></div>
            <h5>Fast Production</h5>
            <p>Direct ordering means faster production schedules. Custom orders delivered in 4-6 weeks.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-geo-alt"></i></div>
            <h5>Extended Warranty</h5>
            <p>Factory-direct buyers receive extended 5-year warranty on all furniture products.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-patch-check"></i></div>
            <h5>Priority Service</h5>
            <p>Factory-direct customers get priority production slots and a dedicated support team.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Retail vs Factory Direct (Comparison) ===== -->
  <section class="wdx-compare">
    <div class="container">
      <div class="text-center">
        <div class="wdx-kicker">COMPARISON</div>
        <h2 class="wdx-title">Retail vs Factory Direct</h2>
      </div>

      <div class="wdx-compare-card mx-auto">
        <div class="wdx-compare-head">
          <div class="wdx-head-cell wdx-head-dark">Feature</div>
          <div class="wdx-head-cell wdx-head-dark">Retail</div>
          <div class="wdx-head-cell wdx-head-green">Factory Direct</div>
        </div>

        <div class="table-responsive">
          <table class="table wdx-compare-table mb-0">
            <tbody>
              <tr>
                <td class="wdx-td-feature">Product Price</td>
                <td class="text-center wdx-td-retail">100%</td>
                <td class="text-center wdx-td-factory">50-70%</td>
              </tr>
              <tr>
                <td class="wdx-td-feature">Customization</td>
                <td class="text-center wdx-td-retail">Limited</td>
                <td class="text-center wdx-td-factory">Full Custom</td>
              </tr>
              <tr>
                <td class="wdx-td-feature">Lead Time</td>
                <td class="text-center wdx-td-retail">8-12 weeks</td>
                <td class="text-center wdx-td-factory">4-6 weeks</td>
              </tr>
              <tr>
                <td class="wdx-td-feature">Warranty</td>
                <td class="text-center wdx-td-retail">1-2 years</td>
                <td class="text-center wdx-td-factory">5 years</td>
              </tr>
              <tr>
                <td class="wdx-td-feature">Support</td>
                <td class="text-center wdx-td-retail">Standard</td>
                <td class="text-center wdx-td-factory">Priority</td>
              </tr>
              <tr>
                <td class="wdx-td-feature">Quality Control</td>
                <td class="text-center wdx-td-retail">Basic</td>
                <td class="text-center wdx-td-factory">Direct QC</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Our Facility: State-of-the-Art Manufacturing ===== -->
  <section class="wdx-facility">
    <div class="container">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <div class="wdx-kicker">OUR FACILITY</div>
          <h2 class="wdx-title">State-of-the-Art Manufacturing</h2>

          <p class="wdx-subtitle wdx-facility-desc">
            Our 100,000 sq. ft. Lahore facility houses the latest in furniture manufacturing
            technology. From precision CNC machines to climate-controlled finishing rooms,
            every aspect is designed for quality.
          </p>

          <ul class="wdx-facility-list">
            <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Modern CNC &amp; automated machinery</li>
            <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> In-house wood seasoning &amp; treatment</li>
            <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Dedicated quality control lab</li>
            <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Climate-controlled finishing area</li>
            <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Eco-friendly manufacturing processes</li>
          </ul>

          <a href="factory-tour.php" class="btn wdx-outline-btn">
            Book Factory Tour <i class="bi bi-arrow-right ms-2"></i>
          </a>
        </div>

        <div class="col-lg-6">
          <div class="wdx-facility-media">
            <img
              src="assets/images/factory/facility.jpg"
              alt="Modern office furniture manufacturing facility"
              class="wdx-facility-img"
              loading="lazy"
            />

            <div class="wdx-facility-badge">
              <div class="wdx-badge-big">100K+</div>
              <div class="wdx-badge-small">Sq. Ft. Facility</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== How Factory Direct Works + CTA ===== -->
  <section class="wdx-process">
    <div class="container">
      <div class="text-center">
        <div class="wdx-kicker">PROCESS</div>
        <h2 class="wdx-title">How Factory Direct Works</h2>
      </div>

      <div class="row g-4 justify-content-center mt-3">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-step-card text-center">
            <div class="wdx-step-emoji" aria-hidden="true">🔎</div>
            <div class="wdx-step-num">1</div>
            <h5 class="wdx-step-title">Browse &amp; Select</h5>
            <p class="wdx-step-desc">
              Choose from our catalog or request custom designs.<br>
              Get instant factory pricing with no hidden fees.
            </p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-step-card text-center">
            <div class="wdx-step-emoji" aria-hidden="true">🏭</div>
            <div class="wdx-step-num">2</div>
            <h5 class="wdx-step-title">Production</h5>
            <p class="wdx-step-desc">
              Your order goes straight to our production line. Track<br>
              progress with regular updates from our team.
            </p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-step-card text-center">
            <div class="wdx-step-emoji" aria-hidden="true">🚚</div>
            <div class="wdx-step-num">3</div>
            <h5 class="wdx-step-title">Direct Delivery</h5>
            <p class="wdx-step-desc">
              Delivered straight from factory to your door.<br>
              Professional installation included with every order.
            </p>
          </div>
        </div>
      </div>

      <div class="wdx-process-cta mt-5">
        <div class="wdx-cta-inner text-center">
          <h3 class="wdx-cta-title">Start Saving Today</h3>
          <p class="wdx-cta-desc">
            Contact us for factory-direct wholesale pricing on your next<br>
            furniture order. Minimum order value: PKR 300,000.
          </p>

          <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="factory-quote.php" class="btn wdx-btn">
              Get Factory Quote
            </a>

            <input
              type="text"
              class="form-control wdx-input"
              placeholder="Enter City / ZIP"
              aria-label="Enter City or ZIP"
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
