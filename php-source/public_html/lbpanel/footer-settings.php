<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access footer settings.';
    header('Location: index.php');
    exit;
}

function footer_default_settings() {
    return [
        'about_title' => 'WOODEX',
        'about_tagline' => 'Make your space work®',
        'about_text' => "Pakistan's premium custom office furniture manufacturer.\nDesign-to-delivery solutions for modern workspaces.",
        'quick_links_title' => 'Quick Links',
        'shop_title' => 'Shop',
        'learn_more_title' => 'Learn More',
        'contact_title' => 'Contact',
        'address_text' => '123 Gulberg III, Main Boulevard, Lahore, Pakistan',
        'email_text' => 'sales@woodexfurniture.pk',
        'hours_text' => 'Mon-Sat: 9am-7pm',
        'phone_number' => '+92 42 111 WOODEX',
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
}

function footer_ensure_column($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

function normalize_footer_section_key($section) {
    $section = (string)$section;
    if ($section === 'learn_1') {
        return 'quick_links';
    }
    if ($section === 'learn_2') {
        return 'learn_more';
    }
    return $section;
}

function ensure_footer_tables($conn) {
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

    $defaults = footer_default_settings();
    $exists = $conn->query("SELECT id FROM footer_settings WHERE id = 1 LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
        $paymentJson = json_encode($defaults['payment_logos'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $socialJson = json_encode($defaults['social_links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $conn->prepare("INSERT INTO footer_settings (id, about_title, about_tagline, about_text, quick_links_title, shop_title, learn_more_title, contact_title, address_text, email_text, hours_text, phone_number, newsletter_title, newsletter_placeholder, newsletter_button_text, payment_logos_json, social_label, social_links_json, copyright_text, legal_link_1_title, legal_link_1_url, legal_link_2_title, legal_link_2_url, legal_link_3_title, legal_link_3_url) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssssssssssssssssssss', $defaults['about_title'], $defaults['about_tagline'], $defaults['about_text'], $defaults['quick_links_title'], $defaults['shop_title'], $defaults['learn_more_title'], $defaults['contact_title'], $defaults['address_text'], $defaults['email_text'], $defaults['hours_text'], $defaults['phone_number'], $defaults['newsletter_title'], $defaults['newsletter_placeholder'], $defaults['newsletter_button_text'], $paymentJson, $defaults['social_label'], $socialJson, $defaults['copyright_text'], $defaults['legal_link_1_title'], $defaults['legal_link_1_url'], $defaults['legal_link_2_title'], $defaults['legal_link_2_url'], $defaults['legal_link_3_title'], $defaults['legal_link_3_url']);
        $stmt->execute();
        $stmt->close();
    }

    $linkCount = $conn->query("SELECT COUNT(*) AS c FROM footer_links");
    $count = $linkCount ? (int)($linkCount->fetch_assoc()['c'] ?? 0) : 0;
    if ($count === 0) {
        $seedLinks = [
            'quick_links' => [
                ['About Us', 'about-us'],
                ['Portfolio', 'portfolio'],
                ['Blog', 'blog'],
                ['Careers', 'careers'],
                ['Contact', 'contact-us'],
                ['Showrooms', 'showrooms.php'],
                ['Series', 'categories'],
                ['B2B / Markets', 'b2b-markets'],
            ],
            'shop' => [
                ['Ergonomic Chair', 'categories/ergonomic-chair'],
                ['Executive Desks', 'categories/executive-desks'],
                ['Manager Tables', 'categories/manager-tables'],
                ['Staff Tables', 'categories/staff-table'],
                ['Meeting Table', 'categories/meeting-table'],
                ['Office Chairs', 'categories/office-chair'],
                ['Workstations', 'categories/workstations'],
                ['Cubicle Systems', 'categories/cubicle-system'],
                ['Office Sofas', 'categories/office-sofa'],
                ['Office Storage', 'categories/office-storage'],
            ],
            'learn_more' => [
                ['Awards', 'awards'],
                ['Ideas & Inspiration', 'ideas-and-inspiration'],
                ['Terms of Use', 'terms-and-conditions'],
                ['Resources', 'resources'],
                ['Support', 'support'],
                ['Warranty', 'warranty'],
                ['Distributors', 'distributors'],
                ['FAQ', 'faqs'],
            ],
        ];
        $stmt = $conn->prepare("INSERT INTO footer_links (section_key, link_title, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
        foreach ($seedLinks as $section => $rows) {
            $sort = 1;
            foreach ($rows as $link) {
                $title = $link[0];
                $url = $link[1];
                $stmt->bind_param('sssi', $section, $title, $url, $sort);
                $stmt->execute();
                $sort++;
            }
        }
        $stmt->close();
    }
}

function valid_footer_section($value) {
    return in_array($value, ['quick_links', 'shop', 'learn_more', 'learn_1', 'learn_2'], true);
}

ensure_footer_tables($conn);

if (isset($_GET['toggle_link']) && is_numeric($_GET['toggle_link'])) {
    $id = (int)$_GET['toggle_link'];
    $stmt = $conn->prepare("UPDATE footer_links SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Footer link status updated.';
    } else {
        $_SESSION['error'] = 'Failed to update footer link status.';
    }
    $stmt->close();
    header('Location: footer-settings.php');
    exit;
}

if (isset($_GET['delete_link']) && is_numeric($_GET['delete_link'])) {
    $id = (int)$_GET['delete_link'];
    $stmt = $conn->prepare("DELETE FROM footer_links WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Footer link deleted.';
    } else {
        $_SESSION['error'] = 'Failed to delete footer link.';
    }
    $stmt->close();
    header('Location: footer-settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_footer_settings'])) {
    $aboutTitle = trim((string)($_POST['about_title'] ?? 'WOODEX'));
    $aboutTagline = trim((string)($_POST['about_tagline'] ?? 'Make your space work®'));
    $aboutText = trim((string)($_POST['about_text'] ?? ''));
    $quickLinksTitle = trim((string)($_POST['quick_links_title'] ?? 'Quick Links'));
    $shopTitle = trim((string)($_POST['shop_title'] ?? 'Shop'));
    $learnMoreTitle = trim((string)($_POST['learn_more_title'] ?? 'Learn More'));
    $contactTitle = trim((string)($_POST['contact_title'] ?? 'Contact'));
    $addressText = trim((string)($_POST['address_text'] ?? ''));
    $emailText = trim((string)($_POST['email_text'] ?? ''));
    $hoursText = trim((string)($_POST['hours_text'] ?? ''));
    $phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
    $newsletterTitle = trim((string)($_POST['newsletter_title'] ?? 'Newsletter'));
    $newsletterPlaceholder = trim((string)($_POST['newsletter_placeholder'] ?? 'Your email'));
    $newsletterButtonText = trim((string)($_POST['newsletter_button_text'] ?? '->'));
    $socialLabel = trim((string)($_POST['social_label'] ?? 'Follow Us:'));
    $copyrightText = trim((string)($_POST['copyright_text'] ?? 'WoodEx Furniture. All rights reserved.'));
    $legal1Title = trim((string)($_POST['legal_link_1_title'] ?? 'Privacy Policy'));
    $legal1Url = trim((string)($_POST['legal_link_1_url'] ?? 'privacy-policy'));
    $legal2Title = trim((string)($_POST['legal_link_2_title'] ?? 'Terms of Service'));
    $legal2Url = trim((string)($_POST['legal_link_2_url'] ?? 'terms-and-conditions'));
    $legal3Title = trim((string)($_POST['legal_link_3_title'] ?? 'Sitemap'));
    $legal3Url = trim((string)($_POST['legal_link_3_url'] ?? 'sitemap.xml'));

    $paymentLogoSrc = $_POST['payment_logo_src'] ?? [];
    $paymentLogoAlt = $_POST['payment_logo_alt'] ?? [];
    $paymentLogos = [];
    if (is_array($paymentLogoSrc)) {
        foreach ($paymentLogoSrc as $idx => $src) {
            $src = trim((string)$src);
            $alt = is_array($paymentLogoAlt) ? trim((string)($paymentLogoAlt[$idx] ?? 'Payment Logo')) : 'Payment Logo';
            if ($src !== '') {
                $paymentLogos[] = ['src' => $src, 'alt' => ($alt !== '' ? $alt : 'Payment Logo')];
            }
        }
    }
    if (empty($paymentLogos)) {
        $paymentLogos = footer_default_settings()['payment_logos'];
    }

    $socialMap = [
        'twitter' => ['icon' => 'bi-twitter-x', 'label' => 'X (Twitter)'],
        'facebook' => ['icon' => 'bi-facebook', 'label' => 'Facebook'],
        'instagram' => ['icon' => 'bi-instagram', 'label' => 'Instagram'],
        'linkedin' => ['icon' => 'bi-linkedin', 'label' => 'LinkedIn'],
        'youtube' => ['icon' => 'bi-youtube', 'label' => 'YouTube'],
    ];
    $socialLinks = [];
    foreach ($socialMap as $key => $meta) {
        $url = trim((string)($_POST['social_' . $key] ?? ''));
        if ($url !== '') {
            $socialLinks[] = ['icon' => $meta['icon'], 'url' => $url, 'label' => $meta['label']];
        }
    }

    $paymentJson = json_encode($paymentLogos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $socialJson = json_encode($socialLinks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $conn->prepare("UPDATE footer_settings SET about_title = ?, about_tagline = ?, about_text = ?, quick_links_title = ?, shop_title = ?, learn_more_title = ?, contact_title = ?, address_text = ?, email_text = ?, hours_text = ?, phone_number = ?, newsletter_title = ?, newsletter_placeholder = ?, newsletter_button_text = ?, payment_logos_json = ?, social_label = ?, social_links_json = ?, copyright_text = ?, legal_link_1_title = ?, legal_link_1_url = ?, legal_link_2_title = ?, legal_link_2_url = ?, legal_link_3_title = ?, legal_link_3_url = ? WHERE id = 1");
    $stmt->bind_param('ssssssssssssssssssssssss', $aboutTitle, $aboutTagline, $aboutText, $quickLinksTitle, $shopTitle, $learnMoreTitle, $contactTitle, $addressText, $emailText, $hoursText, $phoneNumber, $newsletterTitle, $newsletterPlaceholder, $newsletterButtonText, $paymentJson, $socialLabel, $socialJson, $copyrightText, $legal1Title, $legal1Url, $legal2Title, $legal2Url, $legal3Title, $legal3Url);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Footer settings saved successfully.';
    } else {
        $_SESSION['error'] = 'Failed to save footer settings.';
    }
    $stmt->close();
    header('Location: footer-settings.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_footer_link'])) {
    $linkId = (int)($_POST['link_id'] ?? 0);
    $sectionKey = normalize_footer_section_key(trim((string)($_POST['section_key'] ?? '')));
    $linkTitle = trim((string)($_POST['link_title'] ?? ''));
    $linkUrl = trim((string)($_POST['link_url'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if (!valid_footer_section($sectionKey) || $linkTitle === '' || $linkUrl === '') {
        $_SESSION['error'] = 'Section, title, and URL are required for footer links.';
    } else {
        if ($linkId > 0) {
            $stmt = $conn->prepare("UPDATE footer_links SET section_key = ?, link_title = ?, link_url = ?, sort_order = ? WHERE id = ?");
            $stmt->bind_param('sssii', $sectionKey, $linkTitle, $linkUrl, $sortOrder, $linkId);
            $ok = $stmt->execute();
            $stmt->close();
            $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Footer link updated.' : 'Failed to update footer link.';
        } else {
            $stmt = $conn->prepare("INSERT INTO footer_links (section_key, link_title, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('sssi', $sectionKey, $linkTitle, $linkUrl, $sortOrder);
            $ok = $stmt->execute();
            $stmt->close();
            $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Footer link added.' : 'Failed to add footer link.';
        }
    }

    header('Location: footer-settings.php');
    exit;
}

$settings = footer_default_settings();
$res = $conn->query("SELECT * FROM footer_settings WHERE id = 1 LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    foreach (['about_title','about_tagline','about_text','quick_links_title','shop_title','learn_more_title','contact_title','address_text','email_text','hours_text','phone_number','newsletter_title','newsletter_placeholder','newsletter_button_text','social_label','copyright_text','legal_link_1_title','legal_link_1_url','legal_link_2_title','legal_link_2_url','legal_link_3_title','legal_link_3_url'] as $key) {
        if (isset($row[$key])) {
            $settings[$key] = (string)$row[$key];
        }
    }
    $logos = json_decode((string)($row['payment_logos_json'] ?? ''), true);
    if (is_array($logos) && !empty($logos)) {
        $settings['payment_logos'] = $logos;
    }
    $socials = json_decode((string)($row['social_links_json'] ?? ''), true);
    if (is_array($socials)) {
        $settings['social_links'] = $socials;
    }
}

$socialValues = [
    'twitter' => '',
    'facebook' => '',
    'instagram' => '',
    'linkedin' => '',
    'youtube' => '',
];
foreach ($settings['social_links'] as $s) {
    $icon = (string)($s['icon'] ?? '');
    $url = (string)($s['url'] ?? '');
    if ($icon === 'bi-twitter-x') $socialValues['twitter'] = $url;
    if ($icon === 'bi-facebook') $socialValues['facebook'] = $url;
    if ($icon === 'bi-instagram') $socialValues['instagram'] = $url;
    if ($icon === 'bi-linkedin') $socialValues['linkedin'] = $url;
    if ($icon === 'bi-youtube') $socialValues['youtube'] = $url;
}

$editLink = null;
if (isset($_GET['edit_link']) && is_numeric($_GET['edit_link'])) {
    $id = (int)$_GET['edit_link'];
    $stmt = $conn->prepare("SELECT id, section_key, link_title, link_url, sort_order FROM footer_links WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editLink = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editLink) {
        $editLink['section_key'] = normalize_footer_section_key($editLink['section_key']);
    }
}

$links = [];
$list = $conn->query("SELECT id, section_key, link_title, link_url, sort_order, is_active FROM footer_links ORDER BY section_key ASC, sort_order ASC, id ASC");
if ($list) {
    while ($row = $list->fetch_assoc()) {
        $row['section_key'] = normalize_footer_section_key($row['section_key']);
        $links[] = $row;
    }
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container" style="max-width:1200px; margin-top:24px; margin-bottom:40px;">
    <h2 class="mb-3">Footer Settings</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Footer Content</strong></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="save_footer_settings" value="1">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Brand Title</label>
                        <input type="text" name="about_title" class="form-control" value="<?= htmlspecialchars($settings['about_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Brand Tagline</label>
                        <input type="text" name="about_tagline" class="form-control" value="<?= htmlspecialchars($settings['about_tagline'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">About Text</label>
                        <textarea name="about_text" class="form-control" rows="3"><?= htmlspecialchars($settings['about_text'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Quick Links Title</label>
                        <input type="text" name="quick_links_title" class="form-control" value="<?= htmlspecialchars($settings['quick_links_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shop Title</label>
                        <input type="text" name="shop_title" class="form-control" value="<?= htmlspecialchars($settings['shop_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Learn More Title</label>
                        <input type="text" name="learn_more_title" class="form-control" value="<?= htmlspecialchars($settings['learn_more_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Column Title</label>
                        <input type="text" name="contact_title" class="form-control" value="<?= htmlspecialchars($settings['contact_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <input type="text" name="address_text" class="form-control" value="<?= htmlspecialchars($settings['address_text'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="text" name="email_text" class="form-control" value="<?= htmlspecialchars($settings['email_text'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hours Text</label>
                        <input type="text" name="hours_text" class="form-control" value="<?= htmlspecialchars($settings['hours_text'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($settings['phone_number'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Newsletter Title</label>
                        <input type="text" name="newsletter_title" class="form-control" value="<?= htmlspecialchars($settings['newsletter_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Newsletter Placeholder</label>
                        <input type="text" name="newsletter_placeholder" class="form-control" value="<?= htmlspecialchars($settings['newsletter_placeholder'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Newsletter Button Text</label>
                        <input type="text" name="newsletter_button_text" class="form-control" value="<?= htmlspecialchars($settings['newsletter_button_text'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <?php for ($i = 0; $i < 3; $i++):
                        $src = $settings['payment_logos'][$i]['src'] ?? '';
                        $alt = $settings['payment_logos'][$i]['alt'] ?? '';
                    ?>
                    <div class="col-md-6">
                        <label class="form-label">Payment Logo <?= $i + 1 ?> URL/Path</label>
                        <input type="text" name="payment_logo_src[]" class="form-control" value="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Logo <?= $i + 1 ?> Alt Text</label>
                        <input type="text" name="payment_logo_alt[]" class="form-control" value="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <?php endfor; ?>

                    <div class="col-md-4">
                        <label class="form-label">Social Label</label>
                        <input type="text" name="social_label" class="form-control" value="<?= htmlspecialchars($settings['social_label'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Copyright Text (year is automatic)</label>
                        <input type="text" name="copyright_text" class="form-control" value="<?= htmlspecialchars($settings['copyright_text'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Bottom Link 1 Title</label>
                        <input type="text" name="legal_link_1_title" class="form-control" value="<?= htmlspecialchars($settings['legal_link_1_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Bottom Link 1 URL/Path</label>
                        <input type="text" name="legal_link_1_url" class="form-control" value="<?= htmlspecialchars($settings['legal_link_1_url'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bottom Link 2 Title</label>
                        <input type="text" name="legal_link_2_title" class="form-control" value="<?= htmlspecialchars($settings['legal_link_2_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Bottom Link 2 URL/Path</label>
                        <input type="text" name="legal_link_2_url" class="form-control" value="<?= htmlspecialchars($settings['legal_link_2_url'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bottom Link 3 Title</label>
                        <input type="text" name="legal_link_3_title" class="form-control" value="<?= htmlspecialchars($settings['legal_link_3_title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Bottom Link 3 URL/Path</label>
                        <input type="text" name="legal_link_3_url" class="form-control" value="<?= htmlspecialchars($settings['legal_link_3_url'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Twitter URL</label>
                        <input type="text" name="social_twitter" class="form-control" value="<?= htmlspecialchars($socialValues['twitter'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Facebook URL</label>
                        <input type="text" name="social_facebook" class="form-control" value="<?= htmlspecialchars($socialValues['facebook'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Instagram URL</label>
                        <input type="text" name="social_instagram" class="form-control" value="<?= htmlspecialchars($socialValues['instagram'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">LinkedIn URL</label>
                        <input type="text" name="social_linkedin" class="form-control" value="<?= htmlspecialchars($socialValues['linkedin'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">YouTube URL</label>
                        <input type="text" name="social_youtube" class="form-control" value="<?= htmlspecialchars($socialValues['youtube'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Footer Content</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Footer Pages / Links</strong></div>
        <div class="card-body">
            <form method="POST" class="row g-3 mb-3">
                <input type="hidden" name="save_footer_link" value="1">
                <input type="hidden" name="link_id" value="<?= (int)($editLink['id'] ?? 0) ?>">
                <div class="col-md-3">
                    <label class="form-label">Section</label>
                    <select name="section_key" class="form-select" required>
                        <option value="quick_links" <?= (($editLink['section_key'] ?? '') === 'quick_links') ? 'selected' : '' ?>>Quick Links</option>
                        <option value="shop" <?= (($editLink['section_key'] ?? '') === 'shop') ? 'selected' : '' ?>>Shop</option>
                        <option value="learn_more" <?= (($editLink['section_key'] ?? '') === 'learn_more') ? 'selected' : '' ?>>Learn More</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Link Title</label>
                    <input type="text" name="link_title" class="form-control" required value="<?= htmlspecialchars($editLink['link_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">URL or Path</label>
                    <input type="text" name="link_url" class="form-control" required value="<?= htmlspecialchars($editLink['link_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="about-us or https://example.com/page">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int)($editLink['sort_order'] ?? 0) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><?= $editLink ? 'Update Link' : 'Add Link' ?></button>
                    <?php if ($editLink): ?><a href="footer-settings.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Section</th>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($links)): ?>
                            <tr><td colspan="7" class="text-center">No footer links found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                                <?php
                                    $sectionLabel = $link['section_key'];
                                    if ($sectionLabel === 'quick_links') $sectionLabel = 'Quick Links';
                                    if ($sectionLabel === 'shop') $sectionLabel = 'Shop';
                                    if ($sectionLabel === 'learn_more') $sectionLabel = 'Learn More';
                                ?>
                                <tr>
                                    <td><?= (int)$link['id'] ?></td>
                                    <td><?= htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($link['link_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><code><?= htmlspecialchars($link['link_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                    <td><?= (int)$link['sort_order'] ?></td>
                                    <td>
                                        <span class="badge <?= (int)$link['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= (int)$link['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="footer-settings.php?edit_link=<?= (int)$link['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="footer-settings.php?toggle_link=<?= (int)$link['id'] ?>" class="btn btn-sm btn-info text-white"><?= (int)$link['is_active'] === 1 ? 'Disable' : 'Enable' ?></a>
                                        <a href="footer-settings.php?delete_link=<?= (int)$link['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this footer link?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
  ClassicEditor.create(document.querySelector('textarea[name="about_text"]')).catch(function(error){ console.error(error); });
</script>
</body>
</html>
