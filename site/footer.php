<?php
$footerDefaults = [
  'about_title' => 'WOODEX',
  'about_tagline' => 'Make your space work®',
  'about_text' => "Pakistan's premium custom office furniture manufacturer.\nDesign-to-delivery solutions for modern workspaces.",
  'quick_links_title' => 'Quick Links',
  'shop_title' => 'Shop',
  'learn_more_title' => 'Learn More',
  'contact_title' => 'COLUMN1',
  'address_text' => '123 Gulberg III, Main Boulevard, Lahore, Pakistan',
  'phone_number' => '+92 321 4000 768',
  'email_text' => 'sales@woodex.pk',
  'hours_text' => 'Mon-Fri: 10:30 AM - 7:30 PM',
  'newsletter_title' => 'Newsletter',
  'newsletter_placeholder' => 'Your email',
  'newsletter_button_text' => '->',
  'payment_logos' => [
    ['src' => 'assets/images/misc/meezan-bank-logo.png', 'alt' => 'Meezan Logo'],
    ['src' => 'assets/images/misc/new-Jazzcash-logo.png', 'alt' => 'Jazzcash Logo'],
    ['src' => 'assets/images/misc/Easypaisa-logo.png', 'alt' => 'EasyPaisa Logo'],
  ],
  'social_label' => '',
  'social_links' => [
    ['icon' => 'bi-facebook', 'url' => 'https://www.facebook.com/woodexfurniturepk/', 'label' => 'Facebook'],
    ['icon' => 'bi-twitter-x', 'url' => 'https://twitter.com/woodexfurniture', 'label' => 'X (Twitter)'],
    ['icon' => 'bi-instagram', 'url' => 'https://www.instagram.com/woodexfurniturepk/', 'label' => 'Instagram'],
    ['icon' => 'bi-linkedin', 'url' => 'https://www.linkedin.com/company/woodexfurniture/', 'label' => 'LinkedIn'],
    ['icon' => 'bi-youtube', 'url' => 'https://www.youtube.com/', 'label' => 'YouTube'],
  ],
  'copyright_text' => 'WoodEx Furniture. All rights reserved.',
  'legal_link_1_title' => 'Privacy Policy',
  'legal_link_1_url' => 'privacy-policy',
  'legal_link_2_title' => 'Terms of Service',
  'legal_link_2_url' => 'terms-and-conditions',
  'legal_link_3_title' => 'Sitemap',
  'legal_link_3_url' => 'sitemap.xml',
];

$footerLinks = [
  'quick_links' => [
    ['title' => 'About Us', 'url' => 'about-us'],
    ['title' => 'Portfolio', 'url' => 'portfolio'],
    ['title' => 'Blog', 'url' => 'blog'],
    ['title' => 'Careers', 'url' => 'careers'],
    ['title' => 'Contact', 'url' => 'contact-us'],
    ['title' => 'Showrooms', 'url' => 'showrooms.php'],
    ['title' => 'Series', 'url' => 'categories'],
    ['title' => 'B2B / Markets', 'url' => 'b2b-markets'],
  ],
  'shop' => [
    ['title' => 'Ergonomic Chair', 'url' => 'categories/ergonomic-chair'],
    ['title' => 'Executive Desks', 'url' => 'categories/executive-desks'],
    ['title' => 'Manager Tables', 'url' => 'categories/manager-tables'],
    ['title' => 'Staff Tables', 'url' => 'categories/staff-table'],
    ['title' => 'Meeting Tables', 'url' => 'categories/meeting-table'],
    ['title' => 'Office Chairs', 'url' => 'categories/office-chair'],
    ['title' => 'Workstations', 'url' => 'categories/workstations'],
    ['title' => 'Cubicle Systems', 'url' => 'categories/cubicle-system'],
    ['title' => 'Office Sofas', 'url' => 'categories/office-sofa'],
    ['title' => 'Office Storage', 'url' => 'categories/office-storage'],
  ],
  'learn_more' => [
    ['title' => 'Awards', 'url' => 'awards'],
    ['title' => 'Ideas & Inspiration', 'url' => 'ideas-and-inspiration'],
    ['title' => 'Terms of Use', 'url' => 'terms-and-conditions'],
    ['title' => 'Resources', 'url' => 'resources'],
    ['title' => 'Support', 'url' => 'support'],
    ['title' => 'Warranty', 'url' => 'warranty'],
    ['title' => 'Distributors', 'url' => 'distributors'],
    ['title' => 'FAQ', 'url' => 'faqs'],
  ],
];

function normalize_footer_section_key($section) {
  $section = (string)$section;
  if ($section === 'learn_1') return 'quick_links';
  if ($section === 'learn_2') return 'learn_more';
  return $section;
}

function footer_ensure_column($conn, $table, $column, $definition) {
  $safeTable = str_replace('`', '', $table);
  $safeColumn = str_replace('`', '', $column);
  $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
  }
}

if (isset($conn) && $conn instanceof mysqli) {
  $conn->query("CREATE TABLE IF NOT EXISTS footer_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    about_title VARCHAR(100) NOT NULL,
    about_tagline VARCHAR(160) NOT NULL,
    about_text TEXT NOT NULL,
    quick_links_title VARCHAR(100) NOT NULL,
    shop_title VARCHAR(100) NOT NULL,
    learn_more_title VARCHAR(100) NOT NULL,
    contact_title VARCHAR(100) NOT NULL,
    address_text VARCHAR(255) NOT NULL,
    email_text VARCHAR(180) NOT NULL,
    hours_text VARCHAR(255) NOT NULL,
    phone_number VARCHAR(60) NOT NULL,
    newsletter_title VARCHAR(100) NOT NULL,
    newsletter_placeholder VARCHAR(160) NOT NULL,
    newsletter_button_text VARCHAR(30) NOT NULL,
    payment_logos_json TEXT NOT NULL,
    social_label VARCHAR(100) NOT NULL,
    social_links_json TEXT NOT NULL,
    copyright_text VARCHAR(255) NOT NULL,
    legal_link_1_title VARCHAR(120) NOT NULL,
    legal_link_1_url VARCHAR(255) NOT NULL,
    legal_link_2_title VARCHAR(120) NOT NULL,
    legal_link_2_url VARCHAR(255) NOT NULL,
    legal_link_3_title VARCHAR(120) NOT NULL,
    legal_link_3_url VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  footer_ensure_column($conn, 'footer_settings', 'about_tagline', "VARCHAR(160) NOT NULL DEFAULT 'Make your space work®' AFTER about_title");
  footer_ensure_column($conn, 'footer_settings', 'quick_links_title', "VARCHAR(100) NOT NULL DEFAULT 'Quick Links' AFTER about_text");
  footer_ensure_column($conn, 'footer_settings', 'shop_title', "VARCHAR(100) NOT NULL DEFAULT 'Shop' AFTER quick_links_title");
  footer_ensure_column($conn, 'footer_settings', 'learn_more_title', "VARCHAR(100) NOT NULL DEFAULT 'Learn More' AFTER shop_title");
  footer_ensure_column($conn, 'footer_settings', 'address_text', "VARCHAR(255) NOT NULL DEFAULT '' AFTER contact_title");
  footer_ensure_column($conn, 'footer_settings', 'email_text', "VARCHAR(180) NOT NULL DEFAULT '' AFTER address_text");
  footer_ensure_column($conn, 'footer_settings', 'newsletter_title', "VARCHAR(100) NOT NULL DEFAULT 'Newsletter' AFTER phone_number");
  footer_ensure_column($conn, 'footer_settings', 'newsletter_placeholder', "VARCHAR(160) NOT NULL DEFAULT 'Your email' AFTER newsletter_title");
  footer_ensure_column($conn, 'footer_settings', 'newsletter_button_text', "VARCHAR(30) NOT NULL DEFAULT '->' AFTER newsletter_placeholder");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_1_title', "VARCHAR(120) NOT NULL DEFAULT 'Privacy Policy' AFTER copyright_text");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_1_url', "VARCHAR(255) NOT NULL DEFAULT 'privacy-policy' AFTER legal_link_1_title");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_2_title', "VARCHAR(120) NOT NULL DEFAULT 'Terms of Service' AFTER legal_link_1_url");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_2_url', "VARCHAR(255) NOT NULL DEFAULT 'terms-and-conditions' AFTER legal_link_2_title");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_3_title', "VARCHAR(120) NOT NULL DEFAULT 'Sitemap' AFTER legal_link_2_url");
  footer_ensure_column($conn, 'footer_settings', 'legal_link_3_url', "VARCHAR(255) NOT NULL DEFAULT 'sitemap.xml' AFTER legal_link_3_title");

  $conn->query("CREATE TABLE IF NOT EXISTS footer_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(20) NOT NULL,
    link_title VARCHAR(180) NOT NULL,
    link_url VARCHAR(2048) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_footer_section (section_key, is_active, sort_order)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $check = $conn->query("SELECT id FROM footer_settings WHERE id = 1 LIMIT 1");
  if ($check && $check->num_rows === 0) {
    $pLogos = json_encode($footerDefaults['payment_logos'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $sLinks = json_encode($footerDefaults['social_links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO footer_settings (id, about_title, about_tagline, about_text, quick_links_title, shop_title, learn_more_title, contact_title, address_text, email_text, hours_text, phone_number, newsletter_title, newsletter_placeholder, newsletter_button_text, payment_logos_json, social_label, social_links_json, copyright_text, legal_link_1_title, legal_link_1_url, legal_link_2_title, legal_link_2_url, legal_link_3_title, legal_link_3_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param('sssssssssssssssssssssssss', $footerDefaults['about_title'], $footerDefaults['about_tagline'], $footerDefaults['about_text'], $footerDefaults['quick_links_title'], $footerDefaults['shop_title'], $footerDefaults['learn_more_title'], $footerDefaults['contact_title'], $footerDefaults['address_text'], $footerDefaults['email_text'], $footerDefaults['hours_text'], $footerDefaults['phone_number'], $footerDefaults['newsletter_title'], $footerDefaults['newsletter_placeholder'], $footerDefaults['newsletter_button_text'], $pLogos, $footerDefaults['social_label'], $sLinks, $footerDefaults['copyright_text'], $footerDefaults['legal_link_1_title'], $footerDefaults['legal_link_1_url'], $footerDefaults['legal_link_2_title'], $footerDefaults['legal_link_2_url'], $footerDefaults['legal_link_3_title'], $footerDefaults['legal_link_3_url']);
      $stmt->execute();
      $stmt->close();
    }
  }

  $existingLinks = $conn->query("SELECT COUNT(*) AS c FROM footer_links");
  $existingCount = $existingLinks ? (int)($existingLinks->fetch_assoc()['c'] ?? 0) : 0;
  if ($existingCount === 0) {
    $stmt = $conn->prepare("INSERT INTO footer_links (section_key, link_title, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
    if ($stmt) {
      foreach ($footerLinks as $sectionKey => $rows) {
        $order = 1;
        foreach ($rows as $r) {
          $title = $r['title'];
          $url = $r['url'];
          $stmt->bind_param('sssi', $sectionKey, $title, $url, $order);
          $stmt->execute();
          $order++;
        }
      }
      $stmt->close();
    }
  }

  $settingsRes = $conn->query("SELECT * FROM footer_settings WHERE id = 1 LIMIT 1");
  if ($settingsRes && ($row = $settingsRes->fetch_assoc())) {
    foreach ($footerDefaults as $k => $v) {
      if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
        $footerDefaults[$k] = $row[$k];
      }
    }
    $logos = json_decode((string)($row['payment_logos_json'] ?? ''), true);
    if (is_array($logos) && !empty($logos)) {
      $footerDefaults['payment_logos'] = $logos;
    }
    $socials = json_decode((string)($row['social_links_json'] ?? ''), true);
    if (is_array($socials) && !empty($socials)) {
      $footerDefaults['social_links'] = $socials;
    }
  }

  $footerLinks = ['quick_links' => [], 'shop' => [], 'learn_more' => []];
  $linksRes = $conn->query("SELECT section_key, link_title, link_url FROM footer_links WHERE is_active = 1 ORDER BY section_key ASC, sort_order ASC, id ASC");
  if ($linksRes) {
    while ($r = $linksRes->fetch_assoc()) {
      $sec = normalize_footer_section_key((string)($r['section_key'] ?? ''));
      if (!isset($footerLinks[$sec])) {
        continue;
      }
      $footerLinks[$sec][] = [
        'title' => (string)$r['link_title'],
        'url' => (string)$r['link_url'],
      ];
    }
  }
}

function footer_link_href($domain, $url) {
  $url = trim((string)$url);
  if ($url === '') {
    return '#';
  }
  if (preg_match('~^https?://~i', $url) || strpos($url, '#') === 0 || strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) {
    return $url;
  }
  return rtrim((string)$domain, '/') . '/' . ltrim($url, '/');
}
?>
<!-- ===== WoodEx Footer ===== -->
<style>
  .site-footer{
    background:#1e2530;
    color:#d7dee8;
    padding:46px 0 0;
    border-top:1px solid rgba(255,255,255,.08);
  }
  .site-footer .about-title,
  .site-footer .ft-title,
  .site-footer .contact-title,
  .site-footer .newsletter-title{
    font-size:1.16rem;
    font-weight:700;
    margin:0 0 14px;
    text-transform:uppercase;
    letter-spacing:.02em;
    color:#f2f6fa;
  }
  .site-footer .brand-mark{
    margin:0 0 6px;
    width:100%;
    text-align:left !important;
    display:flex;
    justify-content:flex-start;
  }
  .site-footer .brand-mark img{
    display:block;
    margin-left:0 !important;
    margin-right:auto !important;
  }
  .site-footer .brand-head{
    padding:0;
    margin:0 0 14px;
    max-width: 340px;
    text-align:left;
  }
  .site-footer .brand-head-title{
    margin:0;
    color:#fff;
    font-size:2.1rem;
    font-weight:900;
    letter-spacing:.02em;
    line-height:1.05;
    text-transform:uppercase;
  }
  .site-footer .brand-head-tagline{
    margin:8px 0 0;
    color:#9fb0c4;
    font-size:1.05rem;
    font-weight:700;
    line-height:1.2;
  }
  .site-footer .brand-tagline{ color:#92a1b3; font-weight:700; margin:0 0 14px; }
  .site-footer .about-text{
    color:#c5d0dc;
    max-width: 320px;
    line-height:1.6;
    margin:0;
    text-align:left;
  }
  .site-footer .left-contact{ margin-top:16px; }
  .site-footer .left-contact .line{ margin:0 0 10px; color:#c5d0dc; font-weight:600; }
  .site-footer .left-contact .line i{ color:#78be20; margin-right:8px; }

  .site-footer .ft-list{
    list-style:none;
    padding:0;
    margin:0;
  }
  .site-footer .ft-list li{ margin:0 0 8px; }
  .site-footer .ft-list a{
    text-decoration:none;
    color:#c5d0dc;
    font-weight:600;
    line-height:1.45;
  }
  .site-footer .ft-list a:hover{ color:#fff; }

  .site-footer .contact-list .line{ margin:0 0 10px; color:#c5d0dc; font-weight:600; display:flex; align-items:flex-start; gap:10px; }
  .site-footer .contact-list .line i{ color:#78be20; margin-top:2px; }
  .site-footer .contact-list a{ color:#c5d0dc; text-decoration:none; }

  .site-footer .newsletter-form{ margin-top:12px; display:flex; gap:8px; }
  .site-footer .newsletter-form input{
    flex:1;
    height:42px;
    border:1px solid rgba(255,255,255,.15);
    border-radius:2px;
    background:rgba(255,255,255,.06);
    color:#f2f6fa;
    padding:0 12px;
  }
  .site-footer .newsletter-form input::placeholder{ color:#9cadc0; }
  .site-footer .newsletter-form button{
    border:0;
    min-width:42px;
    height:42px;
    border-radius:2px;
    background:#78be20;
    color:#fff;
    font-weight:700;
  }

  .site-footer .ft-divider{
    border-top:1px solid rgba(255,255,255,.08);
    margin:28px 0 0;
  }
  .site-footer .ft-bottom{
    padding:14px 0 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }
  .site-footer .copyright{
    color:#9fb0c4;
    margin:0;
    font-weight:600;
  }
  .site-footer .bottom-links{ display:flex; gap:22px; flex-wrap:wrap; }
  .site-footer .bottom-links a{ color:#9fb0c4; text-decoration:none; font-weight:600; }
  .site-footer .bottom-links a:hover{ color:#fff; }

  .site-footer .socials{ margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; }
  .site-footer .socials a{
    width:34px;
    height:34px;
    border:1px solid rgba(255,255,255,.2);
    border-radius:50%;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#c5d0dc;
    text-decoration:none;
  }
  .site-footer .socials a:hover{ color:#fff; border-color:#78be20; }

  .site-footer .payment-strip{ margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
  .site-footer .payment-strip img{ height:34px; width:auto; padding:3px 6px; background:#fff; border-radius:4px; }

  @media (max-width: 991.98px){
    .site-footer .about-text{ max-width:none; }
    .site-footer .ft-bottom{ padding-bottom:20px; }
  }
</style>

<footer class="site-footer">
  <div class="container">
    <div class="row gy-4">
      <!-- About -->
      <div class="col-12 col-lg-3">
        <div class="brand-head">
          <p class="brand-head-title"><?php echo htmlspecialchars($footerDefaults['about_title'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="brand-head-tagline"><?php echo htmlspecialchars($footerDefaults['about_tagline'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <p class="about-text"><?php echo nl2br(htmlspecialchars($footerDefaults['about_text'], ENT_QUOTES, 'UTF-8')); ?></p>
        <div class="socials" aria-label="Social media">
          <?php foreach ($footerDefaults['social_links'] as $social):
            $socialUrl = isset($social['url']) ? trim((string)$social['url']) : '';
            $icon = isset($social['icon']) ? trim((string)$social['icon']) : '';
            $label = isset($social['label']) ? trim((string)$social['label']) : 'Social Link';
            if ($socialUrl === '' || $icon === '') { continue; }
          ?>
            <a href="<?php echo htmlspecialchars($socialUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
              <i class="bi <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-6 col-lg-2">
        <div class="ft-title"><?php echo htmlspecialchars($footerDefaults['quick_links_title'], ENT_QUOTES, 'UTF-8'); ?></div>
        <ul class="ft-list">
          <?php foreach ($footerLinks['quick_links'] as $item): ?>
            <li><a href="<?php echo htmlspecialchars(footer_link_href($domain, $item['url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Shop -->
      <div class="col-6 col-lg-2">
        <div class="ft-title"><?php echo htmlspecialchars($footerDefaults['shop_title'], ENT_QUOTES, 'UTF-8'); ?></div>
        <ul class="ft-list">
          <?php foreach ($footerLinks['shop'] as $item): ?>
            <li><a href="<?php echo htmlspecialchars(footer_link_href($domain, $item['url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Learn More -->
      <div class="col-6 col-lg-2">
        <div class="ft-title"><?php echo htmlspecialchars($footerDefaults['learn_more_title'], ENT_QUOTES, 'UTF-8'); ?></div>
        <ul class="ft-list">
          <?php foreach ($footerLinks['learn_more'] as $item): ?>
            <li><a href="<?php echo htmlspecialchars(footer_link_href($domain, $item['url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Contact / Newsletter -->
      <div class="col-12 col-lg-3">
        <div class="contact-title">COLUMN1</div>
        <div class="contact-list">
          <p class="line"><i class="bi bi-geo-alt"></i><span>123 Gulberg III, Main Boulevard, Lahore, Pakistan</span></p>
          <p class="line"><i class="bi bi-telephone"></i><a href="tel:+923214000768">+92 321 4000 768</a></p>
          <p class="line"><i class="bi bi-envelope"></i><a href="mailto:sales@woodex.pk">sales@woodex.pk</a></p>
          <p class="line"><i class="bi bi-clock"></i><span>Mon-Fri: 10:30 AM - 7:30 PM</span></p>
        </div>

        <div class="newsletter-title"><?php echo htmlspecialchars($footerDefaults['newsletter_title'], ENT_QUOTES, 'UTF-8'); ?></div>
        <form class="newsletter-form" action="javascript:void(0)">
          <input type="email" placeholder="<?php echo htmlspecialchars($footerDefaults['newsletter_placeholder'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Newsletter email">
          <button type="button" aria-label="Subscribe"><?php echo htmlspecialchars($footerDefaults['newsletter_button_text'], ENT_QUOTES, 'UTF-8'); ?></button>
        </form>

        <div class="payment-strip">
          <?php foreach ($footerDefaults['payment_logos'] as $logo):
            $logoSrc = isset($logo['src']) ? footer_link_href($domain, $logo['src']) : '';
            if ($logoSrc === '') { continue; }
            $logoAlt = isset($logo['alt']) && $logo['alt'] !== '' ? $logo['alt'] : 'Payment Logo';
          ?>
            <img src="<?php echo htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($logoAlt, ENT_QUOTES, 'UTF-8'); ?>">
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="ft-divider"></div>

    <!-- Bottom bar -->
    <div class="ft-bottom">
      <p class="copyright">
        &copy; <span id="ft-year"></span> <?php echo htmlspecialchars($footerDefaults['copyright_text'], ENT_QUOTES, 'UTF-8'); ?>
      </p>

      <div class="bottom-links">
        <a href="<?php echo htmlspecialchars(footer_link_href($domain, $footerDefaults['legal_link_1_url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerDefaults['legal_link_1_title'], ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo htmlspecialchars(footer_link_href($domain, $footerDefaults['legal_link_2_url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerDefaults['legal_link_2_title'], ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="<?php echo htmlspecialchars(footer_link_href($domain, $footerDefaults['legal_link_3_url']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerDefaults['legal_link_3_title'], ENT_QUOTES, 'UTF-8'); ?></a>
      </div>
    </div>
  </div>
</footer>

<script>
  // dynamic year
  (function(){
    const y=document.getElementById('ft-year');
    if(y) y.textContent=new Date().getFullYear();
  })();
</script>


<!-- Mobile Navigation -->
<section class="mobile-navigation bg-white">
    <div class="container">
        <div class="row">
            <div class="col text-center">
                <a href="<?php echo $domain;?>">
                    <i class="bi bi-house-door" aria-hidden="true"></i>
                    <span class="mobile-nav-title d-block">Home</span>
                </a>
            </div>
            <div class="col text-center">
                <a href="#" id="search-icon" aria-controls="search-overlay" aria-expanded="false">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <span class="mobile-nav-title d-block">Search</span>
                </a>
            </div>
            <div class="col text-center">
                <a href="<?php echo $domain;?>shop">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <span class="mobile-nav-title d-block">Shop</span>
                </a>
            </div>
            <div class="col text-center">
                <a href="<?php echo $domain;?>categories">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span class="mobile-nav-title d-block">Categories</span>
                </a>
            </div>
            <div class="col text-center">
                <a href="#" id="footer-cart-toggle" class="cart-icon position-relative" aria-label="Open cart">
                    <i class="bi bi-basket" aria-hidden="true"></i>
                    <span class="badge-notification" id="footer-cart-count">0</span>
                    <span class="mobile-nav-title d-block">Cart</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Success Message -->
<div id="success-toast" class="toast-container" role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-content">
        <span id="toast-message"></span>
        <div class="toast-progress-bar"></div>
    </div>
</div>

<!-- Search Overlay -->
<div id="search-overlay" 
     style="display: none; position: fixed; inset: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="display: flex; justify-content: center; align-items: center; height: 100%; position: relative;">
        <!-- Close Icon -->
        <button id="close-search-overlay" 
                style="position: absolute; top: 15px; right: 15px; background: #fff; 
                       border: none; font-size: 1.5rem; color: var(--primary-color); 
                       cursor: pointer; width: 34px; height: 34px; 
                       display: flex; justify-content: center; align-items: center; 
                       border-radius: 50%;"
                aria-label="Close search">
            &times;
        </button>

        <div class="text-center" style="cursor: default;">
            <input type="text" id="search-bar" 
                   placeholder="Search for furniture, categories…" 
                   class="form-control" style="margin-bottom: 15px;">
            <button id="search-button" class="btn btn-primary">Search</button>
        </div>
    </div>
</div>

<!-- Global Quick View Modal -->
<style>
  #wdxQuickViewModal .modal-dialog {
    width: min(920px, calc(100% - 2rem));
    max-width: min(920px, calc(100% - 2rem));
    margin: 1rem auto;
  }
  #wdxQuickViewModal .modal-content {
    border: 0;
    border-radius: 14px;
    overflow: hidden;
  }
  #wdxQuickViewModal .qv-layout {
    display: flex;
    flex-wrap: wrap;
  }
  #wdxQuickViewModal .qv-left,
  #wdxQuickViewModal .qv-right {
    flex: 0 0 50%;
    max-width: 50%;
  }
  #wdxQuickViewModal .qv-left { background: #f7f8fa; }
  #wdxQuickViewModal .qv-media {
    height: 360px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  #wdxQuickViewModal .qv-media img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
  }
  #wdxQuickViewModal .qv-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 20px 22px;
  }
  #wdxQuickViewModal .qv-description {
    margin: 0;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  @media (max-width: 991.98px) {
    #wdxQuickViewModal .qv-left,
    #wdxQuickViewModal .qv-right {
      flex: 0 0 100%;
      max-width: 100%;
    }
    #wdxQuickViewModal .qv-media { height: 300px; }
    #wdxQuickViewModal .qv-content { padding: 16px 16px 18px; }
  }
  @media (max-width: 575.98px) {
    #wdxQuickViewModal .modal-dialog {
      width: calc(100% - 1rem);
      max-width: calc(100% - 1rem);
      margin: .75rem auto;
    }
    #wdxQuickViewModal .qv-media { height: 240px; }
  }
</style>
<div class="modal fade" id="wdxQuickViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered qv-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Quick View</h5>
        <button type="button" class="btn-close qv-close-btn" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-0 qv-layout">
          <div class="col-lg-6 qv-left">
            <div class="qv-media">
              <img id="wdxQvImage" src="" alt="Product" loading="lazy" decoding="async">
            </div>
          </div>
          <div class="col-lg-6 qv-right">
            <div class="qv-content">
            <div id="wdxQvCategory" class="qv-cat"></div>
            <h3 id="wdxQvName" class="qv-name">Product</h3>
            <div id="wdxQvPrice" class="qv-price">PKR 0</div>
            <p id="wdxQvDescription" class="qv-description">Description</p>
            <a id="wdxQvLink" href="#" class="qv-cta-btn">View Product</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Dock mobile nav near footer
  document.addEventListener('DOMContentLoaded', function() {
    const mobileNav = document.querySelector('.mobile-navigation');
    const footerEl  = document.querySelector('.footer-section');

    function handleScroll() {
      if (!mobileNav || !footerEl) return;
      const footerRect = footerEl.getBoundingClientRect();
      const footerTop  = footerRect.top + window.scrollY;
      const scrollPosition = window.scrollY + window.innerHeight;

      if (scrollPosition >= footerTop) {
        mobileNav.classList.add('footer-anchored');
      } else {
        mobileNav.classList.remove('footer-anchored');
      }
    }

    window.addEventListener('scroll', handleScroll);
    handleScroll();

    // Dynamic year in credits
    const y = document.getElementById('year');
    if (y) y.textContent = new Date().getFullYear();

    // Search overlay toggles
    const openBtn  = document.getElementById('search-icon');
    const closeBtn = document.getElementById('close-search-overlay');
    const overlay  = document.getElementById('search-overlay');

    function openOverlay(e){
      e && e.preventDefault();
      overlay.style.display = 'block';
      openBtn?.setAttribute('aria-expanded', 'true');
      document.getElementById('search-bar')?.focus();
    }
    function closeOverlay(e){
      e && e.preventDefault();
      overlay.style.display = 'none';
      openBtn?.setAttribute('aria-expanded', 'false');
    }

    openBtn?.addEventListener('click', openOverlay);
    closeBtn?.addEventListener('click', closeOverlay);
    overlay?.addEventListener('click', (e) => {
      if (e.target === overlay) closeOverlay(e);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.style.display === 'block') closeOverlay(e);
    });
  });
</script>

<!-- WhatsApp Floating Widget (Responsive) -->
<div class="whatsapp-widget">
    <a href="https://wa.me/923224000768" target="_blank" class="whatsapp-button" title="Chat with us on WhatsApp" aria-label="Chat on WhatsApp">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp" width="30" height="30" />
    </a>
</div>

<style>
    .whatsapp-widget {
        position: fixed;
        z-index: 9999;
    }
    .whatsapp-button {
        background-color: #25d366;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s, background-color 0.3s;
        text-decoration: none;
    }
    .whatsapp-button:hover {
        transform: scale(1.1);
        background-color: #128c7e;
    }
    /* Desktop position - bottom right */
    @media (min-width: 769px) {
        .whatsapp-widget { bottom: 20px; right: 20px; }
    }
    /* Mobile position - center right */
    @media (max-width: 768px) {
        .whatsapp-widget { top: 50%; right: 15px; transform: translateY(-50%); }
    }
</style>

<script src="<?php echo $domain; ?>assets/script/search.js"></script>
<script src="<?php echo $domain; ?>assets/script/quick-view.js"></script>
