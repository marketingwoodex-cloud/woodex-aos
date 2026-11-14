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
    <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">
    <title>Shipping & Returns • Woodex</title>
    <meta name="description" content="Woodex Shipping & Returns: delivery timelines across Pakistan, international delivery, tracking, exchange/return window, and refund guidelines.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
    <style>
        body{font-family:'Kanit',sans-serif;}
        .shipping-returns-section {
            background: linear-gradient(to bottom right, #f5f6fb, #ffffff);
            padding: 5rem 0 3rem;
            position: relative;
            overflow: hidden;
        }
        .shipping-returns-section h1 {
            font-size: 3rem;
            color: var(--primary-color);
            font-weight: 700;
        }
        .shipping-returns-section h2, .shipping-returns-section h3 {
            color: var(--primary-color);
            margin-top: 28px;
            font-weight: 600;
        }
        .shipping-returns-section p, .shipping-returns-section ul li {
            font-size: 1.05rem;
            color: #555;
            line-height: 1.75;
        }
        .lead-text{font-size:1.15rem;color:#586070}
        .policy-card{
            background:#fff;border:1px solid #e9edf2;border-radius:14px;padding:1.25rem 1.25rem;
            box-shadow:0 4px 10px rgba(0,0,0,.05);
        }
        .badge-soft{
            background:#eef4ff;color:#2b5fff;border:1px solid #dbe7ff;border-radius:999px;padding:.25rem .6rem;font-size:.75rem;font-weight:700
        }
        ul{margin-left:1.1rem}
        .wave-container {position:absolute;bottom:0;left:0;width:100%;height:100px;overflow:hidden;line-height:0;}
        .wave {fill: var(--primary-color-lighter);}
    </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<section class="shipping-returns-section">
    <div class="container">
        <div class="text-center mb-4">
            <h1>Shipping & Returns</h1>
            <p class="lead-text mt-2">Everything you need to know about delivery, tracking, exchanges, and returns at <strong>Woodex</strong>.</p>
            <span class="badge-soft mt-2 d-inline-block">Last updated: October 2025</span>
        </div>

        <div class="row g-4">
            <!-- Shipping Policy -->
            <div class="col-12">
                <div class="policy-card">
                    <h2>1) Shipping Policy</h2>
                    <p>
                        We ship nationwide across Pakistan using trusted courier partners to ensure safe and timely delivery. Orders are typically
                        <strong>processed within 1–2 business days</strong> after confirmation. You’ll receive an email/SMS once your order is packed and dispatched.
                    </p>

                    <h3>Domestic Delivery Timelines</h3>
                    <ul>
                        <li><strong>Major cities:</strong> 2–4 business days</li>
                        <li><strong>Other locations:</strong> 3–6 business days</li>
                        <li><strong>Remote areas:</strong> 5–8 business days</li>
                    </ul>
                    <p class="mb-0"><em>Note:</em> Business days exclude Sundays and public holidays. Weather, strikes, or unforeseen events may cause delays.</p>

                    <h3 class="mt-3">Shipping Fees</h3>
                    <ul>
                        <li><strong>Standard shipping (Pakistan):</strong> shown at checkout (often free above a certain order value, when applicable).</li>
                        <li><strong>Cash on Delivery (COD) service:</strong> available on eligible orders; a small COD fee may apply.</li>
                    </ul>

                    <h3>International Shipping</h3>
                    <p>
                        We currently ship to select countries. Delivery typically takes <strong>7–14 business days</strong>, depending on destination and customs clearance.
                        Any <strong>customs duties, taxes, or import fees</strong> are the buyer’s responsibility. Please check local regulations before ordering.
                    </p>

                    <h3>Order Tracking</h3>
                    <p>
                        Once dispatched, we’ll share a tracking ID via email/SMS. You can also track orders from your account dashboard.
                    </p>

                    <h3>Address Changes & Delivery Attempts</h3>
                    <ul>
                        <li>Address changes can be requested <strong>before dispatch</strong> by emailing <strong>sales@woodexfurniture.pk</strong>.</li>
                        <li>Couriers typically make up to <strong>2 delivery attempts</strong>. If undelivered, the parcel may be returned to us.</li>
                    </ul>

                    <h3>Lost, Delayed, or Damaged Parcels</h3>
                    <ul>
                        <li>If your parcel is delayed beyond the estimated window, contact us and we’ll investigate with the courier.</li>
                        <li>If your parcel arrives damaged or tampered with, please <strong>take photos/videos</strong> of the packaging and items, and notify us within <strong>48 hours</strong>.</li>
                    </ul>
                </div>
            </div>

            <!-- Returns & Exchanges -->
            <div class="col-12">
                <div class="policy-card">
                    <h2>2) Returns & Exchanges</h2>
                    <p>
                        We want you to love your purchase. If something isn’t right, you can request an exchange or return within
                        <strong>7 days of delivery</strong>. We generally provide <strong>exchanges or store credit</strong>.
                        Refunds to the original payment method are issued <strong>only</strong> for defective/incorrect items.
                    </p>

                    <h3>Eligibility & Condition</h3>
                    <ul>
                        <li>Items must be <strong>unused, unwashed, and unworn</strong>, with all original tags/packaging intact.</li>
                        <li>Proof of purchase (order number/invoice) is required.</li>
                        <li>Color variance due to screen settings is not considered a defect.</li>
                    </ul>

                    <h3>Non-Returnable Items</h3>
                    <ul>
                        <li>Items marked as <strong>Final Sale</strong> or on <strong>clearance</strong></li>
                        <li><strong>Innerwear, socks, caps</strong> (for hygiene reasons)</li>
                        <li><strong>Customized or altered</strong> products</li>
                        <li>Accessories with broken hygiene seals</li>
                    </ul>

                    <h3>How to Start a Return/Exchange</h3>
                    <ol>
                        <li>Email <strong>sales@woodexfurniture.pk</strong> within 7 days of delivery with: order number, item(s), and reason.</li>
                        <li>Our team will respond with approval and the return address or pickup instructions (where available).</li>
                        <li>Pack items securely; include all tags, freebies, and invoice.</li>
                        <li>Share the return <strong>tracking ID</strong> (if self-shipping) so we can monitor the parcel.</li>
                    </ol>

                    <h3>Return Shipping Costs</h3>
                    <ul>
                        <li><strong>Customer-initiated returns/exchanges:</strong> Return shipping is buyer’s responsibility.</li>
                        <li><strong>Defective/incorrect item:</strong> We’ll cover/adjust reasonable return shipping costs after verification.</li>
                    </ul>

                    <h3>Inspections & Outcomes</h3>
                    <ul>
                        <li>Once received, items are inspected within <strong>3–5 business days</strong>.</li>
                        <li>Approved cases: we process <strong>exchange dispatch</strong> or issue <strong>store credit</strong> (valid for 6 months).</li>
                        <li>Refunds (if applicable) to original method are processed within <strong>5–7 business days</strong> after approval.</li>
                    </ul>

                    <h3>Size Exchanges</h3>
                    <p>
                        If your size doesn’t fit, we’ll happily exchange it if stock is available. If not, we can issue store credit.
                    </p>

                    <h3>Defective / Wrong Item Received</h3>
                    <p>
                        Please share clear photos/videos within <strong>48 hours</strong> of delivery. After verification, we’ll arrange a replacement,
                        store credit, or refund (where applicable).
                    </p>

                    <h3>Partial Refunds / Rejections</h3>
                    <ul>
                        <li>Returns showing signs of wear, washing, damage, or missing tags may be rejected or partially credited.</li>
                        <li>Unauthorized or late returns (after 7 days) may be declined.</li>
                    </ul>
                </div>
            </div>

            <!-- Payments, Cancellations, Refusals -->
            <div class="col-12">
                <div class="policy-card">
                    <h2>3) Payments, Cancellations & Order Changes</h2>

                    <h3>Cash on Delivery (COD)</h3>
                    <ul>
                        <li>COD is available on eligible orders and locations. A nominal COD fee may apply.</li>
                        <li>Please keep exact change where possible and ensure phone availability for courier coordination.</li>
                    </ul>

                    <h3>Order Cancellations</h3>
                    <ul>
                        <li>You can request cancellation <strong>before dispatch</strong> by emailing <strong>sales@woodexfurniture.pk</strong>.</li>
                        <li>If already dispatched, you may refuse delivery, but round-trip shipping charges may be deducted.</li>
                    </ul>

                    <h3>Modifying Your Order</h3>
                    <ul>
                        <li>Size/address changes are possible before dispatch. After dispatch, we’ll try to assist but cannot guarantee changes.</li>
                    </ul>

                    <h3>Refused Deliveries / Failed Attempts</h3>
                    <ul>
                        <li>Repeated failed deliveries or refusal without cause may lead to account limits and deduction of logistic costs.</li>
                    </ul>
                </div>
            </div>

            <!-- FAQs / Help -->
            <div class="col-12">
                <div class="policy-card">
                    <h2>4) Need Help?</h2>
                    <p>
                        Our support team is here to help with shipping questions, sizing, and returns.
                    </p>
                    <ul>
                        <li>📧 Email: <strong>sales@woodexfurniture.pk</strong></li>
                        <li>🌐 Website: <a href="<?php echo $domain;?>"><?php echo $domain;?></a></li>
                        <li>📍 G66 Zainab Tower, Model Town Link Road, Lahore, Pakistan</li>
                    </ul>
                    <p class="mb-0"><em>Tip:</em> For faster assistance, include your order number in the subject line.</p>
                </div>
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
