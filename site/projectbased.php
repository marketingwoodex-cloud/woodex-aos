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

<!-- ===== Compact Project Based Quoting Section (Bootstrap) ===== -->
<section>
  <div class="wdx-hero text-white" style="--wdx-hero-bg: url('<?php echo $domain; ?>assets/images/herobg.webp');">
    <div class="wdx-bg" aria-hidden="true"></div>
    <div class="wdx-overlay" aria-hidden="true"></div>

    <div class="container h-100 position-relative">
      <div class="row h-100 align-items-center">
        <div class="col-lg-7 col-xl-6">
          <!-- TEXT CHANGE -->
          <div class="wdx-badge">Enterprise Solutions</div>

          <!-- TEXT CHANGE -->
          <h2 class="wdx-hero-title">
            Project Based <span>Quoting</span>
          </h2>

          <!-- TEXT CHANGE -->
          <p class="wdx-desc">
            Specialized comprehensive quotes for large-scale furniture projects across Pakistan.
            From initial concept to final completion, we handle everything with transparent fixed pricing and guaranteed timelines.
          </p>

          <div class="d-flex flex-wrap gap-3 align-items-center">
            <!-- TEXT CHANGE -->
            <a href="project-quote.php" class="btn wdx-btn">
              Request Project Quote <i class="bi bi-arrow-right ms-2"></i>
            </a>

            <!-- TEXT CHANGE -->
            <input type="text" class="form-control wdx-input"
                   placeholder="Enter Company / City" aria-label="Enter Company or City">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="wdx-stats">
    <div class="container">
      <div class="row text-center g-4">
        <div class="col-6 col-lg-3">
          <!-- TEXT CHANGE -->
          <div class="wdx-stat-number">200+</div>
          <div class="wdx-stat-label">Projects Completed</div>
        </div>

        <div class="col-6 col-lg-3">
          <!-- TEXT CHANGE -->
          <div class="wdx-stat-number">PKR 2B+</div>
          <div class="wdx-stat-label">Total Value Delivered</div>
        </div>

        <div class="col-6 col-lg-3">
          <!-- TEXT CHANGE -->
          <div class="wdx-stat-number">98%</div>
          <div class="wdx-stat-label">On-Time Delivery</div>
        </div>

        <div class="col-6 col-lg-3">
          <!-- TEXT CHANGE -->
          <div class="wdx-stat-number">14</div>
          <div class="wdx-stat-label">Days to Quote</div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- ===== Comprehensive Project Support (What's Included) ===== -->
<section class="wdx-why">
  <div class="container">
    <div class="text-center">
      <div class="wdx-kicker">WHAT'S INCLUDED</div>
      <h2 class="wdx-title">Comprehensive Project Support</h2>
      <p class="wdx-subtitle">
        Our project-based quotes include everything you need for a successful, hassle-free furniture<br class="d-none d-md-block">
        installation
      </p>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-file-earmark-text"></i></div>
          <h5>Detailed Proposals</h5>
          <p>Comprehensive quotes with itemized breakdowns, 3D renderings, and material specifications.</p>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-calculator"></i></div>
          <h5>Transparent Pricing</h5>
          <p>Clear pricing structure with no hidden costs. All taxes, delivery, and installation included.</p>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-people"></i></div>
          <h5>Dedicated Project Manager</h5>
          <p>Assigned project manager from quote to completion. Single point of contact throughout.</p>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-clipboard-check"></i></div>
          <h5>Turnkey Solutions</h5>
          <p>Complete project management including design, manufacturing, delivery, and installation.</p>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-graph-up-arrow"></i></div>
          <h5>Volume Discounts</h5>
          <p>Special pricing for large projects. Bulk ordering discounts up to 25% for qualified projects.</p>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-4">
        <div class="wdx-card">
          <div class="wdx-icon"><i class="bi bi-chat-square-text"></i></div>
          <h5>Flexible Terms</h5>
          <p>Customized payment plans and leasing options available for large-scale projects.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- ===== Project Types We Handle (Industries) ===== -->
<section class="wdx-why wdx-industries">
  <div class="container">
    <div class="text-center">
      <div class="wdx-kicker">INDUSTRIES</div>
      <h2 class="wdx-title">Project Types We Handle</h2>
    </div>

    <div class="row g-4 mt-2">
      <!-- Office Fit-Outs -->
      <div class="col-12 col-lg-6">
        <div class="wdx-card wdx-industry-card">
          <div class="d-flex gap-3 align-items-start">
            <div class="wdx-icon wdx-industry-icon"><i class="bi bi-building"></i></div>

            <div class="flex-grow-1">
              <h5 class="wdx-industry-title">Office Fit-Outs</h5>
              <p class="wdx-industry-desc">
                Complete office furniture solutions from 10 to<br class="d-none d-md-block">
                1000+ workstations across Pakistan
              </p>

              <span class="wdx-chip">PKR 1,500,000+</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Commercial Spaces -->
      <div class="col-12 col-lg-6">
        <div class="wdx-card wdx-industry-card">
          <div class="d-flex gap-3 align-items-start">
            <div class="wdx-icon wdx-industry-icon"><i class="bi bi-buildings"></i></div>

            <div class="flex-grow-1">
              <h5 class="wdx-industry-title">Commercial Spaces</h5>
              <p class="wdx-industry-desc">
                Hotels, restaurants, retail spaces, and hospitality<br class="d-none d-md-block">
                furniture installations
              </p>

              <span class="wdx-chip">PKR 3,000,000+</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Educational Institutions -->
      <div class="col-12 col-lg-6">
        <div class="wdx-card wdx-industry-card">
          <div class="d-flex gap-3 align-items-start">
            <div class="wdx-icon wdx-industry-icon"><i class="bi bi-mortarboard"></i></div>

            <div class="flex-grow-1">
              <h5 class="wdx-industry-title">Educational Institutions</h5>
              <p class="wdx-industry-desc">
                Schools, universities, training centers, libraries,<br class="d-none d-md-block">
                and modern learning spaces
              </p>

              <span class="wdx-chip">PKR 2,250,000+</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Healthcare Facilities -->
      <div class="col-12 col-lg-6">
        <div class="wdx-card wdx-industry-card">
          <div class="d-flex gap-3 align-items-start">
            <div class="wdx-icon wdx-industry-icon"><i class="bi bi-heart-pulse"></i></div>

            <div class="flex-grow-1">
              <h5 class="wdx-industry-title">Healthcare Facilities</h5>
              <p class="wdx-industry-desc">
                Hospitals, clinics, medical offices, and healthcare<br class="d-none d-md-block">
                furniture solutions
              </p>

              <span class="wdx-chip">PKR 4,500,000+</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ===== Why Choose Us: End-to-End Project Management ===== -->
<section class="wdx-facility wdx-project-mgmt">
  <div class="container">
    <div class="row align-items-center g-5">
      <!-- Left: Image -->
      <div class="col-lg-6">
        <div class="wdx-facility-media wdx-pm-media">
          <img
            src="assets/images/project/project-team.jpg"
            alt="Project team planning office furniture installation"
            class="wdx-facility-img"
          />

          <!-- Floating Discount Badge -->
          <div class="wdx-pm-discount">
            <div class="wdx-pm-discount-big">25%</div>
            <div class="wdx-pm-discount-small">Volume Discount</div>
          </div>
        </div>
      </div>

      <!-- Right: Content -->
      <div class="col-lg-6">
        <div class="wdx-kicker">WHY CHOOSE US</div>
        <h2 class="wdx-title">End-to-End Project Management</h2>

        <p class="wdx-subtitle wdx-pm-desc">
          From initial consultation to final installation, our dedicated project managers
          ensure your furniture project runs smoothly. We handle all the complexities so you
          can focus on your business.
        </p>

        <ul class="wdx-facility-list wdx-pm-list">
          <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Single point of contact throughout</li>
          <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Regular progress updates &amp; reporting</li>
          <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Quality assurance at every stage</li>
          <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> On-time delivery guarantee</li>
          <li><span class="wdx-check" aria-hidden="true"><i class="bi bi-check"></i></span> Post-installation support</li>
        </ul>

        <a href="project-team.php" class="btn wdx-outline-btn wdx-pm-btn">
          Meet Our Project Team <i class="bi bi-arrow-right ms-2"></i>
        </a>
      </div>
    </div>
  </div>
</section>
<!-- ===== Timeline: Our Quoting Process ===== -->
<section class="wdx-timeline">
  <div class="container">
    <div class="text-center">
      <div class="wdx-kicker">TIMELINE</div>
      <h2 class="wdx-title">Our Quoting Process</h2>
    </div>

    <div class="wdx-timeline-wrap mt-4">
      <!-- vertical line -->
      <div class="wdx-timeline-line" aria-hidden="true"></div>

      <!-- Step 1 -->
      <div class="wdx-timeline-item">
        <div class="wdx-timeline-dot">1</div>
        <div class="wdx-timeline-card">
          <div class="wdx-timeline-card-top">
            <h5 class="wdx-timeline-title">Initial Consultation</h5>
            <span class="wdx-timeline-pill">Day 1-2</span>
          </div>
          <p class="wdx-timeline-desc">
            Comprehensive site visit and detailed requirement gathering session with your team
          </p>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="wdx-timeline-item">
        <div class="wdx-timeline-dot">2</div>
        <div class="wdx-timeline-card">
          <div class="wdx-timeline-card-top">
            <h5 class="wdx-timeline-title">Design &amp; Planning</h5>
            <span class="wdx-timeline-pill">Day 3-7</span>
          </div>
          <p class="wdx-timeline-desc">
            Professional space planning, photorealistic 3D visualization, and strategic product selection
          </p>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="wdx-timeline-item">
        <div class="wdx-timeline-dot">3</div>
        <div class="wdx-timeline-card">
          <div class="wdx-timeline-card-top">
            <h5 class="wdx-timeline-title">Quote Preparation</h5>
            <span class="wdx-timeline-pill">Day 8-10</span>
          </div>
          <p class="wdx-timeline-desc">
            Detailed comprehensive proposal with transparent pricing, realistic timeline, and complete specifications
          </p>
        </div>
      </div>

      <!-- Step 4 -->
      <div class="wdx-timeline-item">
        <div class="wdx-timeline-dot">4</div>
        <div class="wdx-timeline-card">
          <div class="wdx-timeline-card-top">
            <h5 class="wdx-timeline-title">Presentation &amp; Revision</h5>
            <span class="wdx-timeline-pill">Day 11-14</span>
          </div>
          <p class="wdx-timeline-desc">
            Professional presentation of proposal and refinement based on your detailed feedback
          </p>
        </div>
      </div>

      <!-- Step 5 -->
      <div class="wdx-timeline-item">
        <div class="wdx-timeline-dot">5</div>
        <div class="wdx-timeline-card">
          <div class="wdx-timeline-card-top">
            <h5 class="wdx-timeline-title">Final Approval</h5>
            <span class="wdx-timeline-pill">Day 15</span>
          </div>
          <p class="wdx-timeline-desc">
            Official sign-off on final quote, project schedule, and commencement plan
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Add only if these classes don't already exist -->
<style>

</style>

<!-- ===== CTA: Start Your Project Quote ===== -->
<section class="wdx-project-cta">
  <div class="container">
    <div class="wdx-project-cta-box">
      <div class="wdx-project-cta-inner text-center">
        <h3 class="wdx-project-cta-title">Start Your Project Quote</h3>

        <p class="wdx-project-cta-desc">
          Ready to discuss your large-scale project? Get a comprehensive<br>
          detailed quote within 14 business days. Minimum project value:<br>
          PKR 1,500,000.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
          <a href="project-quote.php" class="btn wdx-btn">
            Request Project Quote
          </a>

          <input
            type="text"
            class="form-control wdx-input"
            placeholder="Enter Project Type / Budget"
            aria-label="Enter Project Type or Budget"
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
