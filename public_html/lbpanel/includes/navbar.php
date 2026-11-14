<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

// Build base URLs without relying on connection.php include order.
// Works for local subfolder installs (e.g. /woodex/lbpanel/...) and live root installs (/lbpanel/...).
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $p = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
  $scheme = ($p === 'https') ? 'https' : $scheme;
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/';
$pos = strpos($script, '/lbpanel/');
$rootPath = ($pos !== false) ? substr($script, 0, $pos) : rtrim(str_replace('\\', '/', dirname($script)), '/');
$rootPath = '/' . trim($rootPath, '/');
if ($rootPath === '/') $rootPath = '';

$SITE_BASE  = $scheme . '://' . $host . $rootPath . '/';
$ADMIN_BASE = $SITE_BASE . 'lbpanel/';
?>

<style>
    /* Header Styles */
        .pos-header {
            background-color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 100;
            position: relative;
        }

        .logo {
            height: 40px;
        }

        .user-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--wdx-admin-primary, #484848);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Scope button styling to header only (avoid breaking Bootstrap buttons in pages) */
        .pos-header .btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pos-header .btn-primary {
            background-color: var(--wdx-admin-accent, #78be20);
            color: white;
        }

        .pos-header .btn-primary:hover {
            background-color: #67a61b;
        }

        .pos-header .btn-outline {
            background-color: transparent;
            border: 1px solid var(--wdx-admin-primary, #484848);
            color: var(--wdx-admin-primary, #484848);
        }

        .pos-header .btn-outline:hover {
            background-color: var(--wdx-admin-primary, #484848);
            color: white;
        }

        .pos-container {
            display: grid;
            grid-template-columns: 70% 30%;
            height: calc(100vh - 70px);
            overflow: hidden;
        }
</style>
<div class="pos-header">
        <div class="logo">
            <img src="<?php echo $SITE_BASE; ?>assets/images/header-logo.png" alt="Woodex Furniture POS" height="40">
        </div>
        <div class="user-controls">
            <a class="btn btn-primary" href="<?php echo $ADMIN_BASE; ?>pos/">
                <i class="fas fa-tachometer-alt"></i> POS
            </a>
            <div class="user-info">
                <?php
                  $username = isset($_SESSION['username']) && is_string($_SESSION['username']) && $_SESSION['username'] !== ''
                    ? $_SESSION['username']
                    : 'Admin';
                  $initial = strtoupper(substr($username, 0, 1));
                ?>
                <div class="user-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></div>
                <span><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <a class="btn btn-primary" href="<?php echo $ADMIN_BASE; ?>logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
<!-- Sidebar -->
<div class="sidebar bg-light">
    <div class="sidebar-header">Admin Panel</div>
    <a href="<?php echo $ADMIN_BASE; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="<?php echo $ADMIN_BASE; ?>manage_hero_slider.php"><i class="fas fa-image"></i> Home Sliders</a>

    <!-- Users Section -->
    <a href="#usersSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="usersSubmenu">
        <i class="fas fa-user"></i> Users
    </a>
    <div class="collapse" id="usersSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>add-users.php">Add New Users</a>
            <a href="<?php echo $ADMIN_BASE; ?>view-users.php">View Users</a>
        </div>
    </div>

    <!-- Products Section -->
    <a href="#productsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="productsSubmenu">
        <i class="fas fa-tags"></i> Products
    </a>
    <div class="collapse" id="productsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>add-products.php">Add New Product</a>
            <a href="<?php echo $ADMIN_BASE; ?>view-products.php">View All Products</a>
            <a href="<?php echo $ADMIN_BASE; ?>manage-attributes.php">Product Attributes</a>
            <a href="<?php echo $ADMIN_BASE; ?>brand_management.php">Product Brands</a>
        </div>
    </div>

    <!-- Orders Section -->
    <a href="#ordersSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ordersSubmenu">
        <i class="fas fa-shopping-cart"></i> Orders
    </a>
    <div class="collapse" id="ordersSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>manage-orders.php">Manage Orders</a>
            <a href="<?php echo $ADMIN_BASE; ?>pending-orders.php">Pending Orders</a>
            <a href="<?php echo $ADMIN_BASE; ?>processing-orders.php">Processing Orders</a>
            <a href="<?php echo $ADMIN_BASE; ?>completed-orders.php">Completed Orders</a>
            <a href="<?php echo $ADMIN_BASE; ?>cancelled-orders.php">Cancelled Orders</a>
        </div>
    </div>

    <!-- Category Section -->
    <a href="#categorySubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="categorySubmenu">
        <i class="fas fa-layer-group"></i> Category
    </a>
    <div class="collapse" id="categorySubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>category-management/add-category.php">Add Category</a>
            <a href="<?php echo $ADMIN_BASE; ?>category-management/view-categories.php">View Categories</a>
        </div>
    </div>


    <!-- Reports Section -->
    <a href="#reportsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="reportsSubmenu">
        <i class="fas fa-chart-line"></i> Reports
    </a>
    <div class="collapse" id="reportsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>sales-report.php">Sales Reports</a>
        </div>
    </div>

    <!-- Coupons Section -->
    <a href="#couponsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="couponsSubmenu">
        <i class="fas fa-gift"></i> Coupons
    </a>
    <div class="collapse" id="couponsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>add-coupon.php">Add Coupons</a>
            <a href="<?php echo $ADMIN_BASE; ?>view-coupons.php">View Coupons</a>
        </div>
    </div>

    <!-- Clients Section -->
    <a href="#clientsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="clientsSubmenu">
        <i class="fas fa-circle"></i> Clients
    </a>
    <div class="collapse" id="clientsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>view-customers.php">Customers List</a>
            <a href="<?php echo $ADMIN_BASE; ?>manage_reviews.php">Product Reviews</a>
            <a href="<?php echo $ADMIN_BASE; ?>customer-reviews.php">Customer Reviews</a>
            <a href="<?php echo $ADMIN_BASE; ?>add-customer-review.php">Add Customer Review</a>
        </div>
    </div>
    <!-- Blogs Section -->
    <a href="#blogsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="blogsSubmenu">
        <i class="fas fa-blog"></i> Blogs
    </a>
    <div class="collapse" id="blogsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>view-blogs.php">Blogs List</a>
            <a href="<?php echo $ADMIN_BASE; ?>add-blog.php">Add Blog</a>
        </div>
    </div>
    <!-- Projects Section -->
    <a href="#projectsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="projectsSubmenu">
        <i class="fas fa-briefcase"></i> Projects
    </a>
    <div class="collapse" id="projectsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>projects.php">Projects List</a>
            <a href="<?php echo $ADMIN_BASE; ?>add-project.php">Add Project</a>
        </div>
    </div>
 <!-- FAQs Section -->
    <a href="#faqsSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="faqsSubmenu">
        <i class="fas fa-question"></i> FAQs
    </a>
    <div class="collapse" id="faqsSubmenu">
        <div class="collapse-inner">
            <a href="<?php echo $ADMIN_BASE; ?>view-faqs.php">FAQs List</a>
            <a href="<?php echo $ADMIN_BASE; ?>add-faq.php">Add FAQ</a>
        </div>
    </div>
    <!-- Subscribers -->
    <a href="<?php echo $ADMIN_BASE; ?>view-subscribers.php"><i class="fas fa-users"></i> Subscribers</a>
    <a href="<?php echo $ADMIN_BASE; ?>queries.php"><i class="fas fa-envelope"></i> Contact Form</a>
    <a href="<?php echo $ADMIN_BASE; ?>seo-redirects.php"><i class="fas fa-random"></i> SEO Redirects</a>
    <a href="<?php echo $ADMIN_BASE; ?>quotation-requests.php"><i class="fas fa-file-signature"></i> Quotation Requests</a>
    <a href="<?php echo $ADMIN_BASE; ?>hot-products-settings.php"><i class="fas fa-fire"></i> Hot Products Settings</a>
    <a href="<?php echo $ADMIN_BASE; ?>sale-settings.php"><i class="fas fa-tags"></i> Sale Page Settings</a>
    <a href="<?php echo $ADMIN_BASE; ?>topbar-settings.php"><i class="fas fa-text-width"></i> Top Bar Settings</a>
    <a href="<?php echo $ADMIN_BASE; ?>footer-settings.php"><i class="fas fa-window-maximize"></i> Footer Settings</a>
    <!-- Settings -->
    <a href="<?php echo $ADMIN_BASE; ?>setting.php"><i class="fas fa-cog"></i> Settings</a>
    <a href="<?php echo $ADMIN_BASE; ?>email-settings.php"><i class="fas fa-envelope-open-text"></i> Email Settings</a>
    <!-- Logout -->
    <a href="<?php echo $ADMIN_BASE; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<script>
  (function () {
    function makeEllipsisItem() {
      var li = document.createElement('li');
      li.className = 'page-item disabled js-ellipsis';
      var span = document.createElement('span');
      span.className = 'page-link';
      span.textContent = '...';
      li.appendChild(span);
      return li;
    }

    function compactPagination(ul) {
      if (!ul) return;

      ul.querySelectorAll('.js-ellipsis').forEach(function (el) { el.remove(); });

      var numericItems = [];
      ul.querySelectorAll('li.page-item').forEach(function (li) {
        var a = li.querySelector('.page-link');
        if (!a) return;
        var n = parseInt((a.textContent || '').trim(), 10);
        if (!isNaN(n)) {
          numericItems.push({ n: n, li: li });
        }
      });

      if (numericItems.length <= 7) {
        numericItems.forEach(function (it) { it.li.style.display = ''; });
        return;
      }

      var current = 1;
      var active = ul.querySelector('li.page-item.active .page-link');
      if (active) {
        var parsed = parseInt((active.textContent || '').trim(), 10);
        if (!isNaN(parsed)) current = parsed;
      }

      var total = numericItems[numericItems.length - 1].n;
      var keep = new Set([1, 2, total, total - 1, current - 1, current, current + 1]);

      numericItems.forEach(function (it) {
        it.li.style.display = keep.has(it.n) ? '' : 'none';
      });

      var visible = numericItems.filter(function (it) { return keep.has(it.n); });
      for (var i = 0; i < visible.length - 1; i++) {
        if (visible[i + 1].n - visible[i].n > 1) {
          visible[i].li.insertAdjacentElement('afterend', makeEllipsisItem());
        }
      }
    }

    function run() {
      document.querySelectorAll('ul.pagination').forEach(compactPagination);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }
  })();
</script>
