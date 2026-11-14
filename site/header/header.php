<?php
function getActiveCategoryHierarchy($conn) {
    $categories = [];
    $query = "SELECT c1.id, c1.category_name, c1.slug, c1.level, 
                     c1.parent_id, c2.category_name as parent_name
              FROM categories c1
              LEFT JOIN categories c2 ON c1.parent_id = c2.id
              WHERE c1.is_active = TRUE
              ORDER BY c1.path ASC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) { $categories[] = $row; }

    $hierarchy = [];
    foreach ($categories as $category) {
        if ($category['level'] == 1) {
            $hierarchy[$category['id']] = $category;
            $hierarchy[$category['id']]['children'] = [];
        } elseif ($category['level'] == 2) {
            if (isset($hierarchy[$category['parent_id']])) {
                $hierarchy[$category['parent_id']]['children'][$category['id']] = $category;
                $hierarchy[$category['parent_id']]['children'][$category['id']]['children'] = [];
            }
        } elseif ($category['level'] == 3) {
            foreach ($hierarchy as &$mainCat) {
                if (isset($mainCat['children'][$category['parent_id']])) {
                    $mainCat['children'][$category['parent_id']]['children'][$category['id']] = $category;
                    break;
                }
            }
        }
    }
    return $hierarchy;
}

// Build base URLs that work on both:
// - local subfolder installs (e.g. http://localhost/woodex/)
// - live root installs (e.g. https://woodexfurniture.pk/)
// Also avoid mixed-content by keeping API_BASE root-relative.
$scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $scheme = 'https';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    if ($scheme !== 'https') $scheme = 'http';
} elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
    $scheme = ($_SERVER['REQUEST_SCHEME'] === 'https') ? 'https' : 'http';
}

function ensureTopbarSettingsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS topbar_settings (
      id TINYINT UNSIGNED PRIMARY KEY,
      heading_text VARCHAR(255) NOT NULL,
      heading_url VARCHAR(2048) NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $exists = $conn->query("SELECT id FROM topbar_settings WHERE id = 1 LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
        $defaultHeading = 'Up to 60% Off Sale Collection';
        $defaultUrl = 'shop';
        $stmt = $conn->prepare("INSERT INTO topbar_settings (id, heading_text, heading_url, is_active) VALUES (1, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param('ss', $defaultHeading, $defaultUrl);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function resolveTopbarUrl($url, $domain) {
    $url = trim((string)$url);
    if ($url === '') {
        return '#';
    }
    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }
    if (strpos($url, 'javascript:') === 0) {
        return '#';
    }
    return rtrim($domain, '/') . '/' . ltrim($url, '/');
}

if (!function_exists('ensureSaleSettingsTable')) {
    function ensureSaleSettingsTable($conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS sale_page_settings (
          id TINYINT UNSIGNED PRIMARY KEY,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          hero_background VARCHAR(2048) NOT NULL DEFAULT 'assets/images/herobg.webp',
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $exists = $conn->query("SELECT id FROM sale_page_settings WHERE id = 1 LIMIT 1");
        if ($exists && $exists->num_rows === 0) {
            $conn->query("INSERT INTO sale_page_settings (id, is_active, hero_background) VALUES (1, 1, 'assets/images/herobg.webp')");
        }
    }
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Prefer path from $domain (set by connection.php), fallback to current script directory.
$rootPath = '/';
if (!empty($domain)) {
    $p = parse_url($domain, PHP_URL_PATH);
    if (is_string($p) && $p !== '') {
        $p = '/' . trim($p, '/');
        $rootPath = ($p === '/') ? '/' : ($p . '/');
    }
}
if ($rootPath === '/' && !empty($_SERVER['SCRIPT_NAME'])) {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $dir = '/' . trim($dir, '/');
    $rootPath = ($dir === '/') ? '/' : ($dir . '/');
}

$root = $scheme . '://' . $host . $rootPath;
$domain = $root;

// Root-relative API paths (prevents http/https mismatch issues)
$apiBase = rtrim($rootPath, '/') . '/api/cart/';
$quoteApiBase = rtrim($rootPath, '/') . '/api/quote/';
$searchBase = rtrim($rootPath, '/') . '/api/search/';

// Fetch categories ONCE and reuse
$categories = getActiveCategoryHierarchy($conn);

ensureTopbarSettingsTable($conn);
$topbarHeadingText = 'Up to 60% Off Sale Collection';
$topbarHeadingUrl = resolveTopbarUrl('shop', $domain);
$topbarHeadingActive = true;
$topbarRes = $conn->query("SELECT heading_text, heading_url, is_active FROM topbar_settings WHERE id = 1 LIMIT 1");
if ($topbarRes && ($topbarRow = $topbarRes->fetch_assoc())) {
    $dbHeadingText = trim((string)($topbarRow['heading_text'] ?? ''));
    if ($dbHeadingText !== '') {
        $topbarHeadingText = $dbHeadingText;
    }
    $topbarHeadingUrl = resolveTopbarUrl((string)($topbarRow['heading_url'] ?? ''), $domain);
    $topbarHeadingActive = ((int)($topbarRow['is_active'] ?? 1) === 1);
}

ensureSaleSettingsTable($conn);
$salePageActive = true;
$saleSettingsRes = $conn->query("SELECT is_active FROM sale_page_settings WHERE id = 1 LIMIT 1");
if ($saleSettingsRes && ($saleSettings = $saleSettingsRes->fetch_assoc())) {
    $salePageActive = ((int)($saleSettings['is_active'] ?? 1) === 1);
}

// Temporary quick-access links for frontend pages (requested).
$frontendQuickLinks = [
    ['name' => 'Home', 'url' => $domain],
    ['name' => 'Shop', 'url' => $domain . 'shop'],
    ['name' => 'Categories', 'url' => $domain . 'categories'],
    ['name' => 'Services', 'url' => $domain . 'services'],
    ['name' => 'Factory Direct', 'url' => $domain . 'factory-direct'],
    ['name' => 'Delivery & Installation', 'url' => $domain . 'delivery-installation'],
    ['name' => 'Project Based', 'url' => $domain . 'project-based'],
    ['name' => 'Space Planning', 'url' => $domain . 'space-planning'],
    ['name' => 'B2B', 'url' => $domain . 'b2b.php'],
    ['name' => 'Material & Colors', 'url' => $domain . 'material-and-color'],
    ['name' => 'Warranty', 'url' => $domain . 'warranty'],
    ['name' => 'Showrooms', 'url' => $domain . 'showrooms.php'],
    ['name' => 'About Us', 'url' => $domain . 'about-us'],
    ['name' => 'Blog', 'url' => $domain . 'blog'],
    ['name' => 'Projects', 'url' => $domain . 'projects'],
    ['name' => 'Contact', 'url' => $domain . 'contact'],
    ['name' => 'E-Quotation', 'url' => $domain . 'equotation.php'],
    ['name' => 'Cart', 'url' => $domain . 'cart'],
    ['name' => 'Checkout', 'url' => $domain . 'checkout'],
];
if ($salePageActive) {
    array_splice($frontendQuickLinks, 2, 0, [[
        'name' => 'Sale',
        'url' => $domain . 'sale'
    ]]);
}
?>

<!-- ===== HEADER WRAPPER ===== -->
<header class="hdr">

  <!-- Primary header -->
  <div class="hdr-primary" id="hdrPrimary">
    <div class="hdr-utility">
      <div class="container">
        <div class="hdr-utility-inner">
          <div class="hdr-utility-links">
            <a href="<?php echo $domain; ?>showrooms.php" class="top-header-link">Showrooms</a>
            <a href="<?php echo $domain; ?>material-and-color" class="top-header-link">Material and Colors</a>
            <a href="<?php echo $domain; ?>warranty" class="top-header-link">Warranty</a>
            <?php if ($salePageActive): ?>
              <a href="<?php echo $domain; ?>sale" class="top-header-link top-header-sale-link">Sale</a>
            <?php endif; ?>
            <div class="dropdown hdr-top-dropdown">
              <a href="#" class="top-header-link top-quick-trigger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">All Pages</a>
              <ul class="dropdown-menu dropdown-menu-dark">
                <?php foreach ($frontendQuickLinks as $lnk): ?>
                  <li><a class="dropdown-item" href="<?php echo htmlspecialchars($lnk['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lnk['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <?php if ($topbarHeadingActive && $salePageActive): ?>
          <div class="hdr-topbar-ticker" aria-label="Top announcement">
            <a href="<?php echo htmlspecialchars($topbarHeadingUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hdr-topbar-ticker-link">
              <span><?php echo htmlspecialchars($topbarHeadingText, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="hdr-main">
      <div class="container">
        <div class="navbar navbar-expand-xxl hdr-navbar">
          <a href="<?php echo $domain; ?>" class="navbar-brand d-flex align-items-center">
            <img src="<?php echo $domain; ?>assets/images/header-logo.png" alt="Logo" class="logo-lg">
          </a>

          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrimary" aria-controls="navPrimary" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse" id="navPrimary">
            <ul class="navbar-nav hdr-menu me-auto">
              <?php foreach ($categories as $mainCat):
                $hasChildren = !empty($mainCat['children']); ?>
                <li class="nav-item <?php echo $hasChildren ? 'dropdown' : ''; ?>">
                  <a class="nav-link <?php echo $hasChildren ? 'dropdown-toggle' : ''; ?>"
                     href="<?php echo $domain.'categories/'.$mainCat['slug']; ?>">
                    <?php echo htmlspecialchars($mainCat['category_name']); ?>
                  </a>
                  <?php if ($hasChildren): ?>
                    <div class="dropdown-menu">
                      <div class="row">
                        <?php $colCount = 0;
                        foreach ($mainCat['children'] as $subCat):
                          if ($colCount % 3 == 0 && $colCount > 0) { echo '</div><div class="row">'; }
                          $colCount++; $hasSubChildren = !empty($subCat['children']); ?>
                          <div class="col-md-4">
                            <h6><a href="<?php echo $domain.'categories/'.$subCat['slug']; ?>"><?php echo htmlspecialchars($subCat['category_name']); ?></a></h6>
                            <?php if ($hasSubChildren): ?>
                              <ul>
                                <?php foreach ($subCat['children'] as $childCat): ?>
                                  <li><a href="<?php echo $domain.'categories/'.$childCat['slug']; ?>"><?php echo htmlspecialchars($childCat['category_name']); ?></a></li>
                                <?php endforeach; ?>
                              </ul>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>

              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>shop">Shop</a></li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="<?php echo $domain; ?>services">Services</a>
                <div class="dropdown-menu">
                  <div class="row">
                    <div class="col-md-4">
                      <h6><a href="<?php echo $domain; ?>services">All Services</a></h6>
                      <ul>
                        <li><a href="<?php echo $domain; ?>factory-direct">Factory Direct</a></li>
                        <li><a href="<?php echo $domain; ?>delivery-installation">Delivery & Installation</a></li>
                        <li><a href="<?php echo $domain; ?>project-based">Project Based</a></li>
                        <li><a href="<?php echo $domain; ?>space-planning">Space Planing</a></li>
                        <li><a href="<?php echo $domain; ?>b2b">B2B</a></li>
                      </ul>
                    </div>
                  </div>
                </div>
              </li>

              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>about-us">About Us</a></li>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>blog">Blog</a></li>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>projects">Projects</a></li>
            </ul>

            <div class="hdr-actions ms-lg-3">
              <button class="btn p-0 icon-btn" id="btnSearchPrimary" aria-label="Open search">
                <i class="bi bi-search"></i>
              </button>
              <a href="#" id="btnCartPrimary" class="position-relative hdr-cart-link" aria-label="Open cart">
                <i class="bi bi-cart"></i>
                <span class="badge-notification" id="cart-count">0</span>
              </a>
              <a class="btn hdr-quote-btn" href="<?php echo $domain; ?>requested-quote"><i class="bi bi-file-earmark-text"></i> E-Quotation</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sticky header -->
  <div class="hdr-sticky py-3" id="hdrSticky" aria-hidden="true">
    <div class="container">
      <div class="row align-items-center g-3">
        <div class="col-auto">
          <a href="<?php echo $domain; ?>" class="navbar-brand d-flex align-items-center">
            <img src="<?php echo $domain; ?>assets/images/header-logo.png" alt="Logo" class="logo-sm">
          </a>
        </div>

        <div class="col d-none d-lg-block">
          <nav class="navbar navbar-light justify-content-center">
            <ul class="navbar-nav hdr-menu flex-row gap-1">
              <?php foreach ($categories as $mainCat):
                $hasChildren = !empty($mainCat['children']); ?>
                <li class="nav-item <?php echo $hasChildren ? 'dropdown' : ''; ?>">
                  <a class="nav-link <?php echo $hasChildren ? 'dropdown-toggle' : ''; ?>"
                     href="<?php echo $domain.'categories/'.$mainCat['slug']; ?>">
                    <?php echo htmlspecialchars($mainCat['category_name']); ?>
                  </a>
                  <?php if ($hasChildren): ?>
                    <div class="dropdown-menu">
                      <div class="row">
                        <?php $colCount = 0;
                        foreach ($mainCat['children'] as $subCat):
                          if ($colCount % 3 == 0 && $colCount > 0) { echo '</div><div class="row">'; }
                          $colCount++; $hasSubChildren = !empty($subCat['children']); ?>
                          <div class="col-md-4">
                            <h6><a href="<?php echo $domain.'categories/'.$subCat['slug']; ?>"><?php echo htmlspecialchars($subCat['category_name']); ?></a></h6>
                            <?php if ($hasSubChildren): ?>
                              <ul>
                                <?php foreach ($subCat['children'] as $childCat): ?>
                                  <li><a href="<?php echo $domain.'categories/'.$childCat['slug']; ?>"><?php echo htmlspecialchars($childCat['category_name']); ?></a></li>
                                <?php endforeach; ?>
                              </ul>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>shop">Shop</a></li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="<?php echo $domain; ?>services">Services</a>
                <div class="dropdown-menu">
                  <div class="row">
                    <div class="col-md-4">
                      <h6><a href="<?php echo $domain; ?>services">All Services</a></h6>
                      <ul>
                        <li><a href="<?php echo $domain; ?>factory-direct">Factory Direct</a></li>
                        <li><a href="<?php echo $domain; ?>delivery-installation">Delivery & Installation</a></li>
                        <li><a href="<?php echo $domain; ?>project-based">Project Based</a></li>
                        <li><a href="<?php echo $domain; ?>space-planning">Space Planing</a></li>
                        <li><a href="<?php echo $domain; ?>b2b">B2B</a></li>
                      </ul>
                    </div>
                  </div>
                </div>
              </li>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>about-us">About Us</a></li>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>blog">Blog</a></li>
              <li class="nav-item"><a class="nav-link" href="<?php echo $domain; ?>projects">Projects</a></li>
            </ul>
          </nav>
        </div>

        <div class="col-auto ms-auto d-flex align-items-center gap-3">
          <button class="btn p-0 icon-btn" id="btnSearchSticky" aria-label="Open search">
            <i class="bi bi-search"></i>
          </button>
          <a href="#" id="btnCartSticky" class="position-relative">
            <i class="bi bi-cart"></i>
            <span class="badge-notification" id="cart-count">0</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Drop-down search -->
<div id="searchDrop" class="search-drop" aria-hidden="true">
  <div class="container">
    <form id="site-search-form" class="position-relative" action="<?php echo $domain; ?>search" method="get" autocomplete="off">
      <div class="search-drop-inner">
        <input id="site-search-input" class="form-control form-control-lg" type="search" name="query" placeholder="Search products, brands, categories…" />
        <div id="search-suggestions" class="search-suggest shadow-sm"></div>

        <button class="btn btn-link p-0" type="button" id="btnSearchClose" aria-label="Close">
          <i class="bi bi-x-lg fs-4"></i>
        </button>
        <button class="btn btn-link p-0 d-none d-md-inline" type="submit" aria-label="Go">
          <i class="bi bi-arrow-right-circle fs-4"></i>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Cart panel -->
<div id="cartPanel" class="cart-panel" aria-hidden="true">
  <div class="cart-panel-header d-flex justify-content-between align-items-center">
    <h5 id="cart-panel-title">Your Cart</h5>
    <button id="btnCartClose" class="btn-close" aria-label="Close"></button>
  </div>
  <div class="cart-panel-body">
    <div id="cart-items"><p class="text-muted">Your cart is empty.</p></div>
  </div>
  <div class="cart-panel-footer">
    <p id="cart-panel-total-line">Total: <span id="cart-total">PKR 0.00</span></p>
    <div class="d-flex justify-content-between">
      <a id="cart-panel-primary-link" href="<?php echo $domain;?>cart" class="btn btn-outline-primary border-text-primary">View Cart</a>
      <a id="cart-panel-secondary-link" href="<?php echo $domain;?>checkout" class="btn btn-primary bg-primary-color">Checkout</a>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="success-toast" class="cart-toast">
  <div class="cart-toast-inner">
    <span id="toast-image-wrap"></span>
    <span id="toast-message"></span>
  </div>
</div>

<script>
  window.SITE_BASE   = "<?php echo htmlspecialchars($root, ENT_QUOTES); ?>";
  window.API_BASE    = "<?php echo htmlspecialchars($apiBase,   ENT_QUOTES); ?>";
  window.QUOTE_API_BASE = "<?php echo htmlspecialchars($quoteApiBase, ENT_QUOTES); ?>";
  window.SEARCH_BASE = "<?php echo htmlspecialchars($searchBase,ENT_QUOTES); ?>";

  // Mega menu hover
  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.hdr-primary .nav-item.dropdown, .hdr-sticky .nav-item.dropdown').forEach(dd => {
      const menu = dd.querySelector('.dropdown-menu');
      if (!menu) return;
      dd.addEventListener('mouseenter', () => menu.classList.add('show'));
      dd.addEventListener('mouseleave', () => menu.classList.remove('show'));
    });
  });
</script>

<script>
(function(){
  // Elements
  const hdrPrimary = document.getElementById('hdrPrimary');
  const hdrSticky  = document.getElementById('hdrSticky');

  const btnSearchPrimary = document.getElementById('btnSearchPrimary');
  const btnSearchSticky  = document.getElementById('btnSearchSticky');
  const btnSearchClose   = document.getElementById('btnSearchClose');

  const searchDrop  = document.getElementById('searchDrop');
  const searchInput = document.getElementById('site-search-input');

  const overlay     = document.getElementById('overlay');

  const cartPanel   = document.getElementById('cartPanel');
  const btnCartPrimary = document.getElementById('btnCartPrimary');
  const btnCartSticky  = document.getElementById('btnCartSticky');
  const btnCartClose   = document.getElementById('btnCartClose');

  // Sticky header visibility
  const THRESHOLD = 120;
  function handleScroll(){
    if ((window.scrollY || document.documentElement.scrollTop) > THRESHOLD) {
      hdrSticky.classList.add('is-visible');
      hdrSticky.setAttribute('aria-hidden','false');
    } else {
      hdrSticky.classList.remove('is-visible');
      hdrSticky.setAttribute('aria-hidden','true');
    }
    positionSearch();
  }
  window.addEventListener('scroll', handleScroll, {passive:true});
  window.addEventListener('load', handleScroll);
  document.addEventListener('DOMContentLoaded', handleScroll);

  // Compute whichever header is active
  function currentHeaderHeight(){
    return hdrSticky.classList.contains('is-visible') ? hdrSticky.offsetHeight : hdrPrimary.offsetHeight;
  }

  // Position search directly under the active header via CSS variable
  function positionSearch(){
    const hdrOffset = currentHeaderHeight() || 0;
    document.documentElement.style.setProperty('--hdr-offset', hdrOffset + 'px');
  }
  window.addEventListener('resize', positionSearch);

  // Open/Close search
  function openSearch(){
    positionSearch();
    searchDrop.classList.add('open');
    searchDrop.setAttribute('aria-hidden','false');
    overlay.classList.add('show');
    setTimeout(()=> searchInput && searchInput.focus(), 60);
  }
  function closeSearch(){
    searchDrop.classList.remove('open');
    searchDrop.setAttribute('aria-hidden','true');
    overlay.classList.remove('show');
  }
  btnSearchPrimary && btnSearchPrimary.addEventListener('click', openSearch);
  btnSearchSticky && btnSearchSticky.addEventListener('click', openSearch);
  btnSearchClose && btnSearchClose.addEventListener('click', closeSearch);

  // Cart open/close
  function openCart(e){ e && e.preventDefault(); cartPanel.classList.add('open'); cartPanel.setAttribute('aria-hidden','false'); overlay.classList.add('show'); }
  function closeCart(){ cartPanel.classList.remove('open'); cartPanel.setAttribute('aria-hidden','true'); overlay.classList.remove('show'); }
  btnCartPrimary && btnCartPrimary.addEventListener('click', openCart);
  btnCartSticky && btnCartSticky.addEventListener('click', openCart);
  btnCartClose && btnCartClose.addEventListener('click', closeCart);

  // Overlay click closes whichever is open
  overlay && overlay.addEventListener('click', ()=>{
    if (searchDrop.classList.contains('open')) closeSearch();
    if (cartPanel.classList.contains('open')) closeCart();
  });

  // Esc to close
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') {
      if (searchDrop.classList.contains('open')) closeSearch();
      if (cartPanel.classList.contains('open')) closeCart();
    }
  });
})();
</script>

<style>
/* ================= Z-INDEX SCALE =================
   Highest to lowest to avoid accidental overlaps
   ------------------------------------------------- */
:root{
  --z-cart:   1200; /* cart panel */
  --z-sticky: 1150; /* sticky header (above search) */
  --z-head:   1120; /* primary header */
  --z-search: 1100; /* dropdown search */
  --z-ovrly:  1050; /* overlay */
}
i{
    color: var(--primary-color);
}
.form-control:focus{
    box-shadow: none !important;
}

/* ===== Base header ===== */
.hdr{ position:relative; display:block; }
.logo-lg{ height:44px; width:auto; display:block; }
.logo-sm{ height:32px; width:auto; display:block; }
.navbar-brand img{ max-width: 170px; object-fit: contain; }

/* Primary header */
.hdr-primary{
  position: relative;
  z-index: var(--z-head);
  background:#fff;
  border-bottom:1px solid #dfe5eb;
}

.hdr-utility{
  background:#1e2733;
  color:#dbe3ec;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.hdr-utility-inner{
  min-height: 36px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-wrap: nowrap;
  gap: 16px;
}
.hdr-topbar-ticker{
  flex:1;
  max-width: 620px;
  overflow:hidden;
  position:relative;
  height: 24px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.hdr-topbar-ticker-link{
  color:#ffffff;
  text-decoration:none;
  font-size:.86rem;
  font-weight:700;
  white-space:nowrap;
  display:inline-block;
  animation: hdrTickerMove 12s linear infinite;
}
.hdr-topbar-ticker-link:hover{
  color:#cfeaa9;
}

@keyframes hdrTickerMove {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}
.hdr-utility-links{
  display:flex;
  align-items:center;
  gap: 0;
}
.hdr-primary .top-header-link{
  color:#fff;
  text-decoration:none;
  font-size:.86rem;
  font-weight:600;
  padding: 0 14px;
  line-height:1;
  opacity:1;
}
.hdr-primary .top-header-link:first-child{ padding-left: 0; }
.hdr-primary .hdr-utility-links:last-child .top-header-link:last-child{ padding-right: 0; }
.hdr-primary .top-header-link:hover{ color:#fff; opacity:1; }
.hdr-primary .top-header-sale-link,
.hdr-primary .top-header-sale-link:hover{
  color:#dc3545;
}

.hdr-top-dropdown .dropdown-menu{
  margin-top:8px;
  min-width:240px;
  max-height:56vh;
  overflow:auto;
  border:1px solid rgba(255,255,255,.12);
  background:#1f2834;
  box-shadow:0 12px 30px rgba(0,0,0,.25);
}
.hdr-top-dropdown .dropdown-item{
  color:#e6eef8;
  font-size:.86rem;
  font-weight:600;
  padding:.45rem .75rem;
}
.hdr-top-dropdown .dropdown-item:hover,
.hdr-top-dropdown .dropdown-item:focus{
  color:#fff;
  background:rgba(120,190,32,.28);
}

.hdr-main{ background:#fff; }
.hdr-navbar{ min-height: 62px; padding: 10px 0; }
.hdr-navbar .navbar-brand{ margin-right: 18px; }

.hdr-menu{ gap: 2px; }
.hdr-menu .nav-link{
  color:#15202b;
  font-weight:600;
  font-size:.90rem;
  padding: .6rem .78rem;
  border-radius:6px;
  white-space: nowrap;
  transition: color .16s ease, background-color .16s ease;
}
.hdr-menu .nav-link:hover,
.hdr-menu .nav-item:hover > .nav-link{
  color:#111;
  background:#f4f6f8;
}

.hdr-actions{
  display:flex;
  align-items:center;
  gap:20px;
}
.hdr-actions .icon-btn,
.hdr-actions .hdr-cart-link{
  color:#202a34;
  text-decoration:none;
  line-height:1;
}
.hdr-actions .icon-btn i,
.hdr-actions .hdr-cart-link i,
.hdr-sticky .icon-btn i,
.hdr-sticky a i{
  font-size:1.2rem;
}
.hdr-quote-btn{
  background:#78be20;
  border:1px solid #78be20;
  color:#fff;
  font-weight:600;
  padding:.5rem .95rem;
  border-radius:3px;
  font-size:.75rem;
  white-space:nowrap;
}
.hdr-quote-btn:hover{ background:#67a61b; border-color:#67a61b; color:#fff; }
.hdr-quote-btn i{ color:#fff; font-size:.75rem; }

@media (max-width: 1599.98px){
  .hdr-menu .nav-link{
    font-size:.90rem;
    padding:.52rem .56rem;
  }
  .hdr-actions{ gap:10px; }
  .hdr-quote-btn{
    font-size:.85rem;
    padding:.46rem .78rem;
  }
}

/* Mega menu (hover) */
.hdr-primary .navbar-nav,
.hdr-sticky .navbar-nav{ position:relative; }
.hdr-primary .nav-item.dropdown,
.hdr-sticky .nav-item.dropdown{ position:static; }
.hdr-primary .nav-item.dropdown .dropdown-menu,
.hdr-sticky .nav-item.dropdown .dropdown-menu{ display:none; }
.hdr-primary .nav-item.dropdown:hover .dropdown-menu,
.hdr-sticky .nav-item.dropdown:hover .dropdown-menu{ display:block; }
.hdr-primary .dropdown-menu,
.hdr-sticky .dropdown-menu{
  position:absolute; top:100%; left:0; width:100%;
  padding:20px; background:#fff; border:1px solid #e4e9ee;
  box-shadow:0 8px 16px rgba(0,0,0,.1);
  z-index:10;
}
.hdr-primary .dropdown-menu .row,
.hdr-sticky .dropdown-menu .row{ display:flex; flex-wrap:wrap; margin:-10px; }
.hdr-primary .dropdown-menu .col-md-4,
.hdr-sticky .dropdown-menu .col-md-4{ flex:0 0 33.333%; max-width:33.333%; padding:10px; }
.hdr-primary .dropdown-menu h6,
.hdr-sticky .dropdown-menu h6{ font-size:16px; font-weight:700; margin-bottom:10px; color:#333; border-bottom:1px solid #eee; padding-bottom:8px; }
.hdr-primary .dropdown-menu h6 a,
.hdr-sticky .dropdown-menu h6 a{ color:inherit; text-decoration:none; }
.hdr-primary .dropdown-menu ul,
.hdr-sticky .dropdown-menu ul{ list-style:none; padding:0; margin:0 0 20px 0; }
.hdr-primary .dropdown-menu ul li,
.hdr-sticky .dropdown-menu ul li{ margin-bottom:8px; }
.hdr-primary .dropdown-menu ul li a,
.hdr-sticky .dropdown-menu ul li a{ color:#666; text-decoration:none; font-size:14px; transition:all .2s ease; }
.hdr-primary .dropdown-menu ul li a:hover,
.hdr-sticky .dropdown-menu ul li a:hover{ color:#000; padding-left:5px; }

/* Sticky header */
.hdr-sticky{
  position:fixed; top:-80px; left:0; right:0;
  z-index: var(--z-sticky);
  background:#fff; border-bottom:1px solid #e4e9ee;
  box-shadow:0 2px 12px rgba(0,0,0,.06);
  transition: top .22s ease;
}
.hdr-sticky.is-visible{ top:0; }
.hdr-sticky .nav-link{ padding:.52rem .56rem; }

/* Search drop (sits directly under whichever header is visible) */
.search-drop{
  position:fixed; left:0; right:0;
  top:var(--hdr-offset, 0px);   /* ← dynamic from JS */
  z-index: var(--z-search);
  background:#fff;
  border-bottom:1px solid #eaeaea;
  box-shadow:0 12px 24px rgba(0,0,0,.08);

  /* hidden state: no layout jump */
  visibility:hidden; opacity:0; transform: translateY(-8px);
  pointer-events:none;
  transition: transform .18s ease, opacity .18s ease, visibility 0s linear .18s;
}
.search-drop.open{
  visibility:visible; opacity:1; transform: translateY(0);
  pointer-events:auto;
  transition: transform .18s ease, opacity .18s ease, visibility 0s;
}
.search-drop .search-drop-inner{
  display:grid; grid-template-columns: 1fr auto auto; align-items:center;
  gap:12px; padding:16px 0;
}
.search-drop .form-control-lg{ height:52px; }

/* Cart panel (always above headers) */
.cart-panel{
  position:fixed; top:0; right:-420px; width:400px; max-width:100%; height:100%;
  background:#fff; box-shadow:-2px 0 14px rgba(0,0,0,.12);
  z-index: var(--z-cart);
  transition:right .22s ease;
}
.cart-panel.open{ right:0; }
.cart-panel .cart-panel-header{ padding:14px 16px; border-bottom:1px solid #eee; }
.cart-panel .cart-panel-body{ max-height:calc(100vh - 180px); overflow:auto; padding:10px 16px; }
.cart-panel .cart-panel-footer{ padding:14px 16px; border-top:1px solid #eee; }

/* Overlay (kept under search so header controls remain above) */
.overlay{
  position:fixed; inset:0;
  background:rgba(0,0,0,.35);
  backdrop-filter:saturate(90%) blur(2px);
  z-index: var(--z-ovrly);
  opacity:0; visibility:hidden;
  transition:opacity .18s ease, visibility 0s linear .18s;
}
.overlay.show{
  opacity:1; visibility:visible; transition:opacity .18s ease, visibility 0s;
}

/* Misc */
.icon-btn{ background:none; border:0; line-height:1; }
.badge-notification{ position:absolute; top:-6px; right:-8px; background:#dc3545; color:#fff; border-radius:999px; font-size:12px; padding:2px 6px; }

/* Global price color override */
.product-card-v2 .product-price-v2,
.product-price-v2,
.product-price,
.shop-product-price,
.price-now,
#wdxQuickViewModal .qv-current {
  color:#111827 !important;
}

/* Responsive */
@media (max-width: 1399.98px){
  .hdr-utility{ display:none; }
  .hdr-navbar{ min-height:58px; }
  .hdr-navbar .navbar-brand{ margin-right:0; }
  .logo-lg{ height:40px; }
  .hdr-navbar .navbar-collapse{
    margin-top: 12px;
    border-top:1px solid #edf1f5;
    padding-top: 10px;
  }
  .hdr-menu .nav-link{
    padding:.65rem .35rem;
    border-radius:0;
  }
  .hdr-actions{
    margin-top: 8px;
    padding: 8px 0 2px;
    border-top:1px solid #edf1f5;
  }
  .hdr-quote-btn{ flex:1; text-align:center; }
  .hdr-primary .dropdown-menu{ position:static; width:100%; box-shadow:none; border:none; padding:10px 0; }
  .hdr-primary .dropdown-menu .col-md-4{ flex:0 0 100%; max-width:100%; padding:0 15px; margin-bottom:15px; }
}

@media (max-width: 575.98px){
  .logo-lg{
    height: 34px;
    max-width: 132px;
  }
}
@media (max-width: 768px){
  .logo-lg{ height:36px; }
  .search-drop .search-drop-inner{ grid-template-columns: 1fr auto; }
}
</style>

<script src="<?php echo $domain; ?>assets/script/quote.js"></script>
