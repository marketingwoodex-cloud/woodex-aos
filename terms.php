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
    <title>Terms of Service • WoodEx Furniture Pakistan</title>
    <meta name="description" content="Read the Terms of Service for WoodEx Furniture Pakistan regarding our products, quotations, pricing, delivery, warranty, and limitations.">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">

    <!-- Fonts & CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">

    <style>
        /* Hero Section */
        .tos-hero {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 70px 0 40px;
        }
        .tos-hero h1 {
            font-family: var(--fancy);
            font-weight: 700;
            color: var(--primary-color);
        }
        .tos-hero p {
            color: var(--secondary-color);
            max-width: 700px;
            font-size: 1rem;
        }
        .badge-soft {
            background: var(--accent-color);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.8rem;
            padding: .4rem .9rem;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* Main Content */
        .tos-section {
            background-color: #fff;
            padding: 60px 0 100px;
        }
        .tos-content {
            max-width: 900px;
            margin: 0 auto;
        }
        .tos-content h2 {
            font-family: var(--fancy);
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.4rem;
            margin-top: 40px;
            margin-bottom: 12px;
        }
        .tos-content p, .tos-content li {
            color: var(--secondary-color);
            font-size: 1rem;
            line-height: 1.8;
        }
        .tos-content ul {
            list-style-type: disc;
            margin-left: 22px;
        }
        .tos-content a {
            color: var(--primary-color);
            text-decoration: underline;
        }
        .tos-footer-note {
            font-size: 0.9rem;
            color: var(--secondary-color);
            text-align: center;
            margin-top: 40px;
        }
    </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<!-- Hero -->
<section class="tos-hero">
    <div class="container">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="mb-2">Terms of Service</h1>
                <p class="mb-0">By accessing or using WoodEx Furniture’s website and services, you agree to these terms and conditions.</p>
            </div>
            <div class="text-nowrap align-self-center">
                <span class="badge-soft">Updated: November 2025</span>
            </div>
        </div>
    </div>
</section>

<!-- Terms of Service Content -->
<section class="tos-section">
    <div class="container">
        <div class="tos-content">
            <p>Welcome to <strong>WoodEx Furniture Pakistan</strong>. Please read these Terms of Service carefully before using our website or placing an order. By accessing or using our services, you agree to comply with and be bound by the following terms.</p>

            <h2>1. Our Services</h2>
            <p>WoodEx Furniture provides office furniture manufacturing, customization, delivery, and installation services. We reserve the right to:</p>
            <ul>
                <li>Modify or discontinue products without prior notice</li>
                <li>Limit quantities of products available for purchase</li>
                <li>Refuse service to anyone for any reason</li>
            </ul>

            <h2>2. Quotations & Orders</h2>
            <p>All quotations are valid for 30 days from the date of issue. Orders are subject to:</p>
            <ul>
                <li>Product availability and confirmation</li>
                <li>Advance payment as specified in the quotation</li>
                <li>Delivery timeline based on production schedule</li>
                <li>Written acceptance of our quotation terms</li>
            </ul>

            <h2>3. Pricing & Payments</h2>
            <p>Prices are quoted in Pakistani Rupees (PKR) and:</p>
            <ul>
                <li>Are subject to change without notice</li>
                <li>May not include delivery charges unless specified</li>
                <li>Require advance payment (typically 50% deposit)</li>
                <li>Final payment is due before delivery or upon installation</li>
            </ul>

            <h2>4. Delivery & Installation</h2>
            <p>Delivery timelines are estimates and subject to production schedules. We make reasonable efforts to meet agreed timelines but are not liable for delays beyond our control. Installation services are provided as specified in your order agreement.</p>

            <h2>5. Returns & Warranty</h2>
            <p>Custom-made furniture cannot be returned unless defective. Standard products may be returned within 7 days if unused and in original packaging. We provide:</p>
            <ul>
                <li>12-month warranty on manufacturing defects</li>
                <li>Free repair or replacement for warranted defects</li>
                <li>Exclusions for damage from misuse or normal wear</li>
            </ul>

            <h2>6. Intellectual Property</h2>
            <p>All content on this website, including designs, logos, images, and text, is the property of WoodEx Furniture and protected by copyright laws. You may not reproduce, distribute, or create derivative works without written permission.</p>

            <h2>7. Limitation of Liability</h2>
            <p>WoodEx Furniture shall not be liable for any indirect, incidental, or consequential damages arising from the use of our products or services. Our liability is limited to the purchase price of the product or service in question.</p>

            <h2>8. Governing Law</h2>
            <p>These terms are governed by the laws of Pakistan. Any disputes shall be resolved in the courts of Karachi, Pakistan.</p>

            <h2>9. Contact Information</h2>
            <p>If you have questions about these Terms of Service, please contact us:</p>
            <p><strong>Email:</strong> <a href="mailto:sales@woodexfurniture.pk">sales@woodexfurniture.pk</a><br>
            <strong>Phone:</strong> +92 322 4000 768<br>
            <strong>Address:</strong> WoodEx Furniture, Karachi, Pakistan</p>

            <div class="tos-footer-note">
                © 2025 WoodEx Furniture Pakistan. All rights reserved.
            </div>
        </div>
    </div>
</section>

<?php include_once('footer/footer.php'); ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
