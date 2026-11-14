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
  <meta name="description" content="Discover Our Store, your one-stop destination for premium beauty products. Enhance your natural beauty with our personalized skincare, wellness, and cosmetic solutions.">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Owl Carousel CSS should be in HEAD -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

</head>

<body>
  <?php include_once('header/header.php'); ?>

  <!-- ===== Compact Delivery & Installation Section (Bootstrap) ===== -->
  <section>
    <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo $domain; ?>assets/images/9.webp');">
      <div class="wdx-bg" aria-hidden="true"></div>
      <div class="wdx-overlay" aria-hidden="true"></div>

      <div class="container h-100 position-relative">
        <div class="row h-100 align-items-center">
          <div class="col-lg-7 col-xl-6">
            <div class="wdx-badge">White Glove Service</div>

            <h2 class="wdx-hero-title">
              Delivery & <span>Installation</span>
            </h2>

            <p class="wdx-desc">
              Professional delivery and expert installation services across Pakistan.
              From our warehouse to your workplace, we handle everything with precision and care.
            </p>

            <div class="d-flex flex-wrap gap-3 align-items-center">
              <a href="delivery-quote.php" class="btn wdx-btn">
                Get Delivery Quote <i class="bi bi-arrow-right ms-2"></i>
              </a>

              <input type="text" class="form-control wdx-input"
                     placeholder="Enter City / ZIP" aria-label="Enter City or ZIP">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="wdx-stats">
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">50+</div>
            <div class="wdx-stat-label">Cities Covered</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">10,000+</div>
            <div class="wdx-stat-label">Deliveries Completed</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">99.5%</div>
            <div class="wdx-stat-label">On-Time Delivery</div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="wdx-stat-number">24/7</div>
            <div class="wdx-stat-label">Tracking Available</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Why Choose WoodEx Delivery ===== -->
  <section class="wdx-why">
    <div class="container">
      <div class="text-center">
        <div class="wdx-kicker">OUR SERVICES</div>
        <h2 class="wdx-title">Why Choose WoodEx Delivery</h2>
        <p class="wdx-subtitle">
          Professional, reliable, and fully insured delivery services across Pakistan
        </p>
      </div>

      <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-truck"></i></div>
            <h5>Pakistan-Wide Coverage</h5>
            <p>Delivery to all major cities across Pakistan with our modern fleet equipped with real-time GPS tracking.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-clock"></i></div>
            <h5>Flexible Scheduling</h5>
            <p>Choose delivery times that work for your business. Weekend and evening delivery available upon request.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-shield-check"></i></div>
            <h5>Fully Insured Transit</h5>
            <p>All deliveries are comprehensively insured. Your furniture is protected from warehouse to final installation.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-box-seam"></i></div>
            <h5>White Glove Service</h5>
            <p>Professional unpacking, expert assembly, and precise placement. Complete packaging material removal included.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-geo-alt"></i></div>
            <h5>Real-Time Tracking</h5>
            <p>Track your delivery in real-time with SMS and email updates at every stage of the delivery process.</p>
          </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
          <div class="wdx-card">
            <div class="wdx-icon"><i class="bi bi-patch-check"></i></div>
            <h5>Quality Inspection</h5>
            <p>Comprehensive quality check and detailed walkthrough to ensure everything exceeds your expectations.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Professional Installation Included ===== -->
  <section class="wdx-install">
    <div class="container">
      <div class="row g-5 align-items-center">
        <div class="col-lg-6">
          <div class="wdx-media">
            <div class="wdx-img-card">
              <img src="<?php echo $domain; ?>assets/images/installation.jpg" alt="Professional office installation">
            </div>

            <div class="wdx-warranty">
              <p class="big mb-1">5 Year</p>
              <p class="small mb-0">Installation Warranty</p>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="wdx-kicker">EXPERT TEAM</div>
          <h2 class="wdx-title">Professional Installation Included</h2>

          <p class="wdx-para">
            Our trained installation teams have years of experience setting up office spaces of all sizes.
            From single executive desks to complete corporate fit-outs, we ensure every piece is perfectly
            assembled and positioned.
          </p>

          <ul class="wdx-list">
            <li><span class="wdx-check"><i class="bi bi-check-lg"></i></span> Trained &amp; certified installers</li>
            <li><span class="wdx-check"><i class="bi bi-check-lg"></i></span> Full assembly &amp; positioning</li>
            <li><span class="wdx-check"><i class="bi bi-check-lg"></i></span> Cable management included</li>
            <li><span class="wdx-check"><i class="bi bi-check-lg"></i></span> Packaging removal &amp; cleanup</li>
            <li><span class="wdx-check"><i class="bi bi-check-lg"></i></span> Post-installation support</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Delivery Process + Zones & Pricing ===== -->
  <section class="wdx-wrap">
    <div class="container">

      <div class="wdx-panel">
        <div class="text-center">
          <div class="wdx-kicker">HOW IT WORKS</div>
          <h2 class="wdx-title">Our Delivery Process</h2>
        </div>

        <div class="wdx-timeline mt-4">
          <div class="wdx-step">
            <div class="wdx-dot">1</div>
            <div class="wdx-step-card">
              <h6>Order Confirmation</h6>
              <p>Receive order details and delivery estimate</p>
            </div>
          </div>

          <div class="wdx-step">
            <div class="wdx-dot">2</div>
            <div class="wdx-step-card">
              <h6>Preparation</h6>
              <p>Furniture prepared and packaged at our warehouse</p>
            </div>
          </div>

          <div class="wdx-step">
            <div class="wdx-dot">3</div>
            <div class="wdx-step-card">
              <h6>Dispatch</h6>
              <p>Team dispatched with real-time tracking link</p>
            </div>
          </div>

          <div class="wdx-step">
            <div class="wdx-dot">4</div>
            <div class="wdx-step-card">
              <h6>Delivery</h6>
              <p>Professional delivery and installation at your site</p>
            </div>
          </div>

          <div class="wdx-step">
            <div class="wdx-dot">5</div>
            <div class="wdx-step-card">
              <h6>Inspection</h6>
              <p>Final walkthrough and quality verification</p>
            </div>
          </div>
        </div>
      </div>

      <div class="wdx-zones">
        <div class="text-center">
          <div class="wdx-kicker">COVERAGE</div>
          <h2 class="wdx-title">Delivery Zones &amp; Pricing</h2>
          <p class="wdx-subtitle">Standard delivery rates for major cities</p>
        </div>

        <div class="row g-4">
          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Lahore</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">1-2 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 15,000</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Karachi</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">3-5 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 35,000</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Islamabad</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">2-3 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 25,000</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Faisalabad</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">1-2 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 18,000</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Multan</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">2-3 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 22,000</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6 col-lg-4">
            <div class="wdx-zone-card">
              <div class="wdx-zone-head">
                <h6 class="wdx-zone-city">Peshawar</h6>
                <div class="wdx-pin"><i class="bi bi-geo-alt-fill"></i></div>
              </div>
              <div class="wdx-zone-meta">
                <div>
                  <div class="label">Delivery Time</div>
                  <div class="value">3-4 days</div>
                </div>
                <div class="wdx-price">
                  <div class="label">Starting from</div>
                  <div class="value">PKR 30,000</div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </section>
<style>
    
</style>
  <?php include_once('footer/footer.php'); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
</body>
</html>
