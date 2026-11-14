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
    <title>Privacy Policy • Woodex</title>
    <meta name="description" content="Learn how Woodex protects your privacy. Read our detailed privacy policy about data collection, cookies, and how we safeguard your personal information.">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain; ?>assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
        }
        .privacy-policy-section {
            background: linear-gradient(to bottom right, #f5f6fb, #ffffff);
            padding: 5rem 0 3rem;
            position: relative;
            overflow: hidden;
        }
        .privacy-policy-section h1 {
            font-size: 3rem;
            color: var(--primary-color);
            font-weight: 700;
        }
        .privacy-policy-section h2 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 30px;
        }
        .privacy-policy-section p {
            font-size: 1.1rem;
            color: #555;
            line-height: 1.7;
        }
        .privacy-policy-section ul {
            font-size: 1.05rem;
            color: #555;
        }
        .privacy-policy-section li {
            margin-bottom: 6px;
        }
        .highlight {
            color: var(--primary-color);
            font-weight: 600;
        }
        .wave-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            overflow: hidden;
            line-height: 0;
        }
        .wave {
            fill: var(--primary-color-lighter);
        }
    </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<section class="privacy-policy-section">
    <div class="container">
        <div class="text-center mb-5">
            <h1>Privacy Policy</h1>
            <p class="mt-3">
                At <span class="highlight">Woodex</span>, your privacy and trust mean everything to us.  
                This Privacy Policy explains how we collect, use, protect, and share your information when you visit our website, create an account, or make a purchase.
            </p>
        </div>

        <div class="row">
            <div class="col-12">
                <h2>1. Introduction</h2>
                <p>
                    Woodex (“we”, “our”, “us”) operates as an online platform for leftover and export surplus fashion products.  
                    By using our website (<a href="<?php echo $domain;?>" class="highlight"><?php echo $domain;?></a>), you agree to the practices described in this policy.  
                    We comply with global data protection standards and take all reasonable steps to safeguard your information.
                </p>

                <h2>2. Information We Collect</h2>
                <p>We collect personal and non-personal data to help us provide a better shopping experience:</p>
                <ul>
                    <li><strong>Personal Information:</strong> name, email address, phone number, billing and shipping address, and payment details.</li>
                    <li><strong>Account Data:</strong> login credentials and account preferences.</li>
                    <li><strong>Transactional Data:</strong> purchase history, cart activity, and refund requests.</li>
                    <li><strong>Technical Data:</strong> IP address, browser type, device information, and operating system.</li>
                    <li><strong>Cookies & Tracking:</strong> behavioral data (pages visited, time spent, interactions) to enhance site performance and personalize content.</li>
                </ul>

                <h2>3. How We Use Your Information</h2>
                <p>Your data allows us to deliver the best possible experience. We use it to:</p>
                <ul>
                    <li>Process, fulfill, and track your orders</li>
                    <li>Communicate order updates and respond to inquiries</li>
                    <li>Send newsletters and promotional offers (only if you opt in)</li>
                    <li>Improve our website’s performance and user interface</li>
                    <li>Prevent fraudulent transactions and enhance security</li>
                    <li>Comply with legal and financial obligations</li>
                </ul>

                <h2>4. How We Protect Your Data</h2>
                <p>
                    We use advanced security measures, including <strong>SSL encryption</strong>, <strong>firewalls</strong>, and <strong>secure servers</strong> to protect your data.  
                    Sensitive information such as payment details is encrypted and transmitted through verified payment gateways.
                </p>
                <p>
                    While we do everything possible to protect your data, no online platform can guarantee 100% security.  
                    We encourage you to use strong passwords and log out of shared devices.
                </p>

                <h2>5. Sharing of Information</h2>
                <p>
                    We respect your privacy and never sell your data. However, we may share your information with trusted partners solely for operational purposes:
                </p>
                <ul>
                    <li><strong>Payment Processors:</strong> to securely complete your transactions.</li>
                    <li><strong>Shipping Partners:</strong> to deliver your products efficiently.</li>
                    <li><strong>Analytics Providers:</strong> like Google Analytics to understand customer behavior and improve services.</li>
                    <li><strong>Legal Authorities:</strong> if required by law or for fraud prevention.</li>
                </ul>

                <h2>6. Cookies & Tracking Technologies</h2>
                <p>
                    We use cookies, pixels, and similar technologies to:
                </p>
                <ul>
                    <li>Remember your preferences and login details</li>
                    <li>Keep your cart active between visits</li>
                    <li>Analyze website traffic and performance</li>
                    <li>Deliver personalized content and recommendations</li>
                </ul>
                <p>
                    You can disable cookies through your browser settings, but some features of the site may not work as intended.
                </p>

                <h2>7. Email & Marketing Preferences</h2>
                <p>
                    You will only receive promotional emails if you choose to subscribe.  
                    Every marketing email contains an easy <strong>“Unsubscribe”</strong> link.  
                    Transactional emails (order updates, password resets, etc.) are automatically sent as part of service communication.
                </p>

                <h2>8. Your Rights</h2>
                <p>You have full control over your data. You may:</p>
                <ul>
                    <li>Request access to the personal data we hold about you</li>
                    <li>Ask for correction of inaccurate information</li>
                    <li>Request deletion (“right to be forgotten”) where applicable</li>
                    <li>Withdraw consent for marketing communications</li>
                    <li>Request a copy of your data in electronic format</li>
                </ul>
                <p>
                    To exercise these rights, contact us at <span class="highlight">sales@woodexfurniture.pk</span>.
                </p>

                <h2>9. Data Retention</h2>
                <p>
                    We retain your personal information only as long as necessary for business, legal, or tax purposes.  
                    When no longer needed, we securely delete or anonymize your data.
                </p>

                <h2>10. Links to Other Websites</h2>
                <p>
                    Our website may include links to third-party platforms (such as payment gateways or courier partners).  
                    We are not responsible for their privacy practices and recommend reviewing their privacy policies separately.
                </p>

                <h2>11. Children's Privacy</h2>
                <p>
                    Woodex does not knowingly collect personal data from children under 13 years of age.  
                    If you believe a child has provided us information, please contact us immediately to delete it.
                </p>

                <h2>12. Changes to This Policy</h2>
                <p>
                    We may update this Privacy Policy periodically to reflect changes in law or business practices.  
                    The revised date will always be shown at the top of this page.  
                    We encourage you to review it regularly to stay informed.
                </p>

                <h2>13. Contact Us</h2>
                <p>
                    For any privacy concerns, questions, or requests, please contact our Data Protection Team:
                </p>
                <p>
                    📧 Email: <span class="highlight">sales@woodexfurniture.pk</span><br>
                    🌐 Website: <a href="<?php echo $domain;?>" class="highlight"><?php echo $domain;?></a><br>
                    📍 Address: G66 Zainab Tower, Model Town Link Road, Lahore, Pakistan
                </p>

                <p class="mt-4 text-muted"><em>Last updated: October 2025</em></p>
            </div>
        </div>
    </div>

    <div class="wave-container">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path class="wave" fill-opacity="1" d="M0,224L80,213.3C160,203,320,181,480,192C640,203,800,245,960,240C1120,235,1280,181,1360,154.7L1440,128L1440,320L1360,320C1280,320,1120,320,960,320C800,320,640,320,480,320C320,320,160,320,80,320L0,320Z"></path>
        </svg>
    </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>
</body>
</html>
