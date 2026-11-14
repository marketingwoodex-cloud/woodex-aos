<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Ultra HD Wireless Headphones</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    :root {
      --primary: #f8c537;
      --primary-dark: #e0b031;
      --gray: #888;
      --border: #ececec;
      --radius: 18px;
      --radius-s: 10px;
      --shadow: 0 2px 24px rgba(0,0,0,.06);
      --bg: #fafbfc;
    }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg);
      margin: 0;
      color: #18181a;
      font-size: 1.09rem;
    }
    .container {
      max-width: 1280px;
      margin: 32px auto;
      padding: 0 18px;
    }
    .section {
      margin-top: 40px;
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 38px 38px 34px 38px;
    }
    .section-title {
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--primary-dark);
      margin-bottom: 20px;
      letter-spacing: .02em;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .product-main {
      display: flex;
      gap: 64px;
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 40px 38px 34px 38px;
      align-items: flex-start;
    }
    .gallery-section {
      flex: 1 1 440px;
      max-width: 520px;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }
    .main-img-wrap {
      background: #f4f4f6;
      border-radius: var(--radius);
      width: 100%;
      aspect-ratio: 1/1;
      overflow: hidden;
      box-shadow: 0 1px 14px rgba(0,0,0,.06);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: box-shadow .3s;
    }
    .main-img-wrap img {
      width: 85%;
      height: 85%;
      object-fit: contain;
      transition: transform .25s;
      display: block;
      margin: 0 auto;
    }
    .gallery-thumbs {
      display: flex;
      gap: 14px;
      margin: 0 auto;
    }
    .gallery-thumb {
      width: 65px;
      height: 65px;
      background: #fff;
      border-radius: var(--radius-s);
      box-shadow: 0 1px 5px rgba(0,0,0,.05);
      overflow: hidden;
      border: 2.5px solid transparent;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color .18s;
    }
    .gallery-thumb.selected, .gallery-thumb:hover {
      border-color: var(--primary);
    }
    .gallery-thumb img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }
    .info-section {
      flex: 1 1 430px;
      max-width: 460px;
      position: sticky;
      top: 32px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .brand-row {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 3px;
    }
    .brand-label {
      font-size: 1.09rem;
      color: var(--gray);
      letter-spacing: 1px;
      text-transform: uppercase;
      font-weight: 600;
    }
    .product-title {
      font-size: 2.3rem;
      font-weight: 700;
      margin-bottom: 10px;
      line-height: 1.13;
      letter-spacing: .01em;
    }
    .product-price {
      font-size: 2rem;
      color: var(--primary-dark);
      font-weight: 700;
      margin-bottom: 6px;
      margin-top: 6px;
      letter-spacing: .01em;
    }
    .variation-row {
      display: flex;
      gap: 24px;
      flex-wrap: wrap;
      margin-bottom: 7px;
      align-items: center;
    }
    .variation-block {
      display: flex;
      flex-direction: column;
      gap: 7px;
      min-width: 96px;
    }
    .variation-label {
      font-size: 1.01rem;
      color: #555;
      font-weight: 600;
      margin-bottom: 1px;
    }
    /* Color swatches */
    .swatch-row {
      display: flex;
      gap: 9px;
      margin-top: 2px;
    }
    .color-swatch {
      width: 26px; height: 26px;
      border-radius: 50%;
      border: 2.5px solid #eaeaea;
      cursor: pointer;
      display: inline-block;
      transition: border-color .19s;
      position: relative;
      box-sizing: border-box;
    }
    .color-swatch.selected {
      border: 3px solid var(--primary);
    }
    .color-swatch[data-color="black"] { background: #18181a; }
    .color-swatch[data-color="silver"] { background: #d3d3d7; }
    .color-swatch[data-color="blue"] { background: #3581e6; }
    /* Size dropdown */
    .size-dropdown, .brand-dropdown {
      padding: 8px 18px 8px 9px;
      border-radius: 6px;
      border: 1.8px solid #e4e4e7;
      font-size: 1.06rem;
      background: #fafbfc;
      color: #222;
      min-width: 80px;
      cursor: pointer;
      outline: none;
      font-weight: 500;
      transition: border-color .14s;
    }
    .size-dropdown:focus, .brand-dropdown:focus { border-color: var(--primary-dark);}
    .reviews-row {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--gray);
      font-size: 1.02rem;
    }
    .reviews-row .stars {
      color: #fbbf24;
      font-size: 1.13rem;
      margin-right: 2px;
    }
    .desc-short {
      color: #444;
      font-size: 1.07rem;
      margin-bottom: 6px;
      margin-top: 8px;
      line-height: 1.7;
    }
    .product-actions {
      display: flex;
      gap: 18px;
      margin: 22px 0 7px 0;
      align-items: center;
    }
    .btn-main {
      padding: 16px 44px;
      border: none;
      background: var(--primary);
      color: #18181a;
      font-weight: 700;
      font-size: 1.13rem;
      border-radius: 9px;
      cursor: pointer;
      box-shadow: 0 2px 12px rgba(248,197,55,.07);
      transition: background .18s, color .2s;
      letter-spacing: .02em;
    }
    .btn-main:hover {
      background: var(--primary-dark);
      color: #fff;
    }
    .btn-ghost {
      padding: 16px 22px;
      border: 2px solid var(--primary);
      background: #fff;
      color: var(--primary-dark);
      border-radius: 9px;
      font-weight: 600;
      font-size: 1.11rem;
      cursor: pointer;
      transition: background .18s, color .2s;
    }
    .btn-ghost:hover {
      background: var(--primary-dark);
      color: #fff;
    }
    .features-row {
      display: flex;
      gap: 22px;
      flex-wrap: wrap;
      font-size: 1.04rem;
      color: var(--gray);
      margin-top: 12px;
    }
    .features-row .meta-item {
      display: flex;
      align-items: center;
      gap: 7px;
    }
    /* Tabs */
    .tabs-section {
      margin-top: 40px;
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 0;
      overflow: hidden;
    }
    .tabs {
      display: flex;
      border-bottom: 1.5px solid var(--border);
      background: #fcfcfd;
    }
    .tab-btn {
      padding: 22px 44px;
      font-size: 1.13rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      color: #888;
      border-bottom: 3.5px solid transparent;
      transition: color .18s, border-color .18s;
      outline: none;
      letter-spacing: .01em;
    }
    .tab-btn.active {
      color: var(--primary-dark);
      border-color: var(--primary);
      background: #fff;
    }
    .tab-content {
      padding: 34px 42px 38px 42px;
      animation: fadein .34s;
      min-height: 180px;
    }
    @keyframes fadein {
      from {opacity: 0;transform: translateY(30px);}
      to {opacity: 1;transform: none;}
    }
    /* Description content */
    .desc-long {
      color: #444;
      font-size: 1.11rem;
      line-height: 1.8;
      margin-bottom: 4px;
    }
    /* Specs Table */
    .specs-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0 0 6px 0;
    }
    .specs-table th, .specs-table td {
      text-align: left;
      padding: 12px 14px;
      font-size: 1rem;
    }
    .specs-table th {
      background: #f8f8f8;
      color: #444;
      font-weight: 600;
      border-bottom: 2px solid #ececec;
    }
    .specs-table tr:not(:last-child) td {
      border-bottom: 1px solid #f1f1f1;
    }
    .specs-table td {
      color: #555;
    }
    /* Reviews */
    .reviews-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .review-item {
      padding: 24px 0;
      border-bottom: 1px solid #f2f2f2;
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }
    .review-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      background: #ececec;
      flex-shrink: 0;
      display: block;
    }
    .review-content {
      flex: 1;
    }
    .review-stars {
      color: #fbbf24;
      font-size: 1.06rem;
      margin-bottom: 3px;
    }
    .review-name {
      font-weight: 600;
      font-size: 1rem;
      margin-right: 6px;
    }
    .review-date {
      color: var(--gray);
      font-size: 0.93rem;
    }
    .review-text {
      margin-top: 8px;
      font-size: 1.06rem;
      color: #444;
      line-height: 1.7;
    }
    /* Responsive */
    @media (max-width: 1100px) {
      .product-main {
        flex-direction: column;
        gap: 28px;
        padding: 26px 3vw;
      }
      .info-section {position: static; max-width: 100%;}
      .section {padding: 20px 3vw;}
    }
    @media (max-width: 700px) {
      .main-img-wrap, .gallery-section {max-width: 98vw;}
      .tabs .tab-btn {padding: 13px 13px;}
      .tab-content {padding: 18px 9px;}
      .product-main {padding: 16px 1vw;}
    }
    @media (max-width: 520px) {
      .product-title {font-size: 1.19rem;}
      .main-img-wrap {border-radius: 10px;}
      .gallery-thumb {width: 43px;height: 43px;}
    }
  </style>
</head>
<body>
  <div class="container">

    <!-- Main Product Section -->
    <div class="product-main">
      <!-- Gallery -->
      <div class="gallery-section">
        <div class="main-img-wrap" id="mainImgWrap">
          <img id="mainImg" src="https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=600&q=80" alt="Product">
        </div>
        <div class="gallery-thumbs" id="thumbs">
          <div class="gallery-thumb selected"><img src="https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=600&q=80" alt=""></div>
          <div class="gallery-thumb"><img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80" alt=""></div>
          <div class="gallery-thumb"><img src="https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=600&q=80" alt=""></div>
          <div class="gallery-thumb"><img src="https://images.unsplash.com/photo-1491553895911-0055eca6402d?auto=format&fit=crop&w=600&q=80" alt=""></div>
        </div>
      </div>

      <!-- Product Info with Variations -->
      <div class="info-section">
        <div class="brand-row">
          <span class="brand-label">Brand:</span>
          <select class="brand-dropdown" id="brandSelect">
            <option>QHS Audio</option>
            <option>SoundMax</option>
            <option>EliteAudio</option>
          </select>
        </div>
        <div class="product-title">Ultra HD Wireless Headphones</div>
        <div class="product-price">$179.99</div>
        <!-- Product Variations -->
        <div class="variation-row">
          <div class="variation-block">
            <span class="variation-label">Color:</span>
            <div class="swatch-row" id="colorSwatchRow">
              <span class="color-swatch selected" data-color="black" title="Black"></span>
              <span class="color-swatch" data-color="silver" title="Silver"></span>
              <span class="color-swatch" data-color="blue" title="Blue"></span>
            </div>
          </div>
          <div class="variation-block">
            <span class="variation-label">Size:</span>
            <select class="size-dropdown" id="sizeSelect">
              <option>Standard</option>
              <option>Large</option>
              <option>Small</option>
            </select>
          </div>
        </div>
        <div class="reviews-row">
          <span class="stars">
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="fas fa-star"></i>
            <i class="far fa-star"></i>
          </span>
          <span>4.0 (285 reviews)</span>
        </div>
        <div class="desc-short">
          Immerse yourself in crystal clear sound with our Ultra HD Wireless Headphones.<br>
          Featuring noise cancellation, 30hr battery life, and a super comfortable design—perfect for music lovers!
        </div>
        <div class="product-actions">
          <button class="btn-main"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
          <button class="btn-ghost"><i class="fas fa-heart"></i></button>
        </div>
        <div class="features-row">
          <span class="meta-item"><i class="fa fa-truck"></i> Free Shipping</span>
          <span class="meta-item"><i class="fa fa-shield-alt"></i> 1-Year Warranty</span>
          <span class="meta-item"><i class="fa fa-undo"></i> 30-Day Return</span>
        </div>
      </div>
    </div>

    <!-- Tabbed Content: Description, Specs, Reviews -->
    <div class="tabs-section" id="tabsSection">
      <div class="tabs">
        <button class="tab-btn active" data-tab="desc">Description</button>
        <button class="tab-btn" data-tab="specs">Specifications</button>
        <button class="tab-btn" data-tab="reviews">Reviews</button>
      </div>
      <div class="tab-content" id="descTab">
        <div class="desc-long">
          <strong>Experience true wireless freedom.</strong>  
          Enjoy up to 30 hours of high-fidelity music, advanced active noise cancellation (ANC), and ergonomic comfort with memory foam earcups. Connect via Bluetooth 5.2 or use the classic 3.5mm audio jack. 
          <ul>
            <li>Dual device connection – switch seamlessly between phone & laptop</li>
            <li>Built-in mic for clear calls</li>
            <li>Comes with hard case, Type-C cable, and aux cable</li>
            <li>Perfect for work, travel, and gaming</li>
          </ul>
        </div>
      </div>
      <div class="tab-content" id="specsTab" style="display:none;">
        <table class="specs-table">
          <tr><th>Feature</th><th>Details</th></tr>
          <tr><td>Model</td><td>HD-WH2025</td></tr>
          <tr><td>Wireless Range</td><td>Up to 15 meters</td></tr>
          <tr><td>Battery Life</td><td>Up to 30 hours</td></tr>
          <tr><td>Noise Cancellation</td><td>Active (ANC)</td></tr>
          <tr><td>Weight</td><td>245 grams</td></tr>
          <tr><td>Charging Time</td><td>2 hours</td></tr>
          <tr><td>Audio Jack</td><td>3.5mm + Bluetooth 5.2</td></tr>
          <tr><td>Colors Available</td><td>Black, Silver, Blue</td></tr>
          <tr><td>Warranty</td><td>1 Year Official</td></tr>
        </table>
      </div>
      <div class="tab-content" id="reviewsTab" style="display:none;">
        <ul class="reviews-list">
          <li class="review-item">
            <img class="review-avatar" src="https://randomuser.me/api/portraits/men/32.jpg" alt="Reviewer">
            <div class="review-content">
              <div class="review-stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="far fa-star"></i>
              </div>
              <span class="review-name">Adeel Qureshi</span>
              <span class="review-date">June 2025</span>
              <div class="review-text">
                Amazing sound quality and battery life! Used these for 10 hours straight, no discomfort. The ANC works pretty well even in busy offices.
              </div>
            </div>
          </li>
          <li class="review-item">
            <img class="review-avatar" src="https://randomuser.me/api/portraits/women/58.jpg" alt="Reviewer">
            <div class="review-content">
              <div class="review-stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
              </div>
              <span class="review-name">Sarah Lee</span>
              <span class="review-date">May 2025</span>
              <div class="review-text">
                Comfortable and stylish. The Bluetooth range is solid, and I love that I can use them wired too. Would recommend!
              </div>
            </div>
          </li>
          <li class="review-item">
            <img class="review-avatar" src="https://randomuser.me/api/portraits/men/55.jpg" alt="Reviewer">
            <div class="review-content">
              <div class="review-stars">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="far fa-star"></i>
                <i class="far fa-star"></i>
              </div>
              <span class="review-name">Junaid Ahmed</span>
              <span class="review-date">May 2025</span>
              <div class="review-text">
                Decent value, but the ANC could be stronger. Great for travel and the battery really lasts!
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <script>
    // Gallery image switching
    const thumbs = document.querySelectorAll('.gallery-thumb');
    const mainImg = document.getElementById('mainImg');
    thumbs.forEach(thumb => {
      thumb.addEventListener('click', function () {
        document.querySelector('.gallery-thumb.selected').classList.remove('selected');
        thumb.classList.add('selected');
        mainImg.src = thumb.querySelector('img').src;
      });
    });
    // Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    const descTab = document.getElementById('descTab');
    const specsTab = document.getElementById('specsTab');
    const reviewsTab = document.getElementById('reviewsTab');
    tabBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        descTab.style.display = btn.dataset.tab === "desc" ? "block" : "none";
        specsTab.style.display = btn.dataset.tab === "specs" ? "block" : "none";
        reviewsTab.style.display = btn.dataset.tab === "reviews" ? "block" : "none";
      });
    });
    // Color swatches
    document.querySelectorAll('.color-swatch').forEach((swatch) => {
      swatch.addEventListener('click', function() {
        document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
        swatch.classList.add('selected');
      });
    });
  </script>
</body>
</html>
