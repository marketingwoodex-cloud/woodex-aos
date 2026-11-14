<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

// base vars
if (!isset($domain)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectRoot = realpath(__DIR__);
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $basePath = '';
    if ($projectRoot && $docRoot && strpos($projectRoot, $docRoot) === 0) {
        $rel = substr($projectRoot, strlen($docRoot));
        $rel = str_replace('\\', '/', $rel);
        $rel = '/' . trim($rel, '/');
        $basePath = ($rel === '/') ? '' : $rel;
    }
    $domain = $scheme . '://' . $host . $basePath . '/';
}
if (!isset($siteName)) {
    $siteName = 'Woodex Furniture';
}

// Load products from DB
$productSql = "
    SELECT 
        `id`,
        `product_name`,
        `short_description`,
        `old_price`,
        `current_price`,
        `quantity`,
        `sold_quantity`,
        `video_embed`,
        `product_details`,
        `specifications`,
        `images`,
        `category`,
        `brand_id`,
        `slug`,
        `product_sku`,
        `rating_count`,
        `rating_sum`,
        `product_type`
    FROM `products`
    WHERE 1
    ORDER BY `product_name` ASC
";

$productResult = $conn->query($productSql);
$products = [];
if ($productResult && $productResult->num_rows > 0) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

$pageTitle = "Generate Quotation | " . $siteName;

// Optional prefill (from product detail page)
$prefillProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$prefillQty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
if ($prefillQty < 1) $prefillQty = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Your main theme / fonts -->

    <!-- Bootstrap + icons (if already in your layout, you can remove duplicates) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Your existing site stylesheet with the color scheme you shared -->
    <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">

    <style>
        /* Use existing color tokens */
        body {
            background: #f5f6fb;
            font-family: var(--body, "Inter", system-ui, -apple-system, Segoe UI, sans-serif);
        }

        /* Align primary buttons with your palette */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .page-wrap-quote {
            padding: 32px 0 48px;
        }

        .quote-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 22px rgba(15,23,42,0.06);
            padding: 18px 18px 22px;
            margin-bottom: 18px;
        }

        .section-heading {
            font-family: var(--fancy);
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: .02em;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .small-note {
            font-size: 0.88rem;
            color: #6b7280;
        }

        .table-smaller td,
        .table-smaller th {
            padding: 0.45rem 0.5rem;
            vertical-align: middle;
            font-size: 0.86rem;
        }

        .table thead th {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6b7280;
        }

        .badge-sku {
            font-size: .7rem;
            font-weight: 600;
            background: #eef2ff;
            color: #4338ca;
            border-radius: 999px;
            padding: .15rem .55rem;
        }

        .form-label {
            font-weight: 600;
            font-size: .9rem;
            color: #374151;
        }

        .summary-box {
            background: #f9fafb;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: .92rem;
            margin-bottom: 4px;
        }

        .summary-row.highlight {
            font-size: 1rem;
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px dashed #d1d5db;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn-generate {
            border-radius: 999px;
            font-weight: 700;
            padding: .7rem 1.6rem;
        }

        .gst-options .form-check-label {
            font-weight: 500;
        }

        .gst-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: .8rem;
            color: #4b5563;
        }
        .gst-chip i {
            color: #10b981;
        }

        /* Step flow header */
        .flow-steps {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        .flow-step {
            flex: 1;
            min-width: 160px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 999px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            font-size: 0.86rem;
            color: #374151;
        }
        .flow-step span.badge-step {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
        }

        /* Top brand bar */
        .brand-bar {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 12px 16px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-left img.logo-lg {
            max-height: 60px;
            width: auto;
        }
        .brand-text-title {
            font-family: var(--fancy);
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--primary-color);
        }
        .brand-text-sub {
            font-size: 0.86rem;
            color: #6b7280;
        }
        .brand-right {
            text-align: right;
            font-size: 0.85rem;
            color: #4b5563;
        }
        .brand-right strong {
            color: var(--primary-color);
        }

        @media (max-width: 767.98px) {
            .quote-card {
                padding: 14px;
            }
            .brand-bar {
                align-items: flex-start;
            }
            .brand-right {
                text-align: left;
            }
        }
    </style>
</head>
<body>

<?php include_once('header/header.php'); ?>

<section class="page-wrap-quote">
    <div class="container">

        <!-- Brand / logo bar -->
        <div class="brand-bar">
            <div class="brand-left">
                <!-- your exact logo snippet -->
                <img src="<?php echo $domain; ?>assets/images/header-logo.png" alt="Logo" class="logo-lg">
                <div>
                    <div class="brand-text-title">Online Quotation Builder</div>
                    <div class="brand-text-sub">
                        Quickly prepare customer quotations using live product prices and Woodex branding.
                    </div>
                </div>
            </div>
            <div class="brand-right">
                <div><strong>Phone:</strong> +92 321 4000 768</div>
                <div><strong>Address:</strong> LG 89, Model, Zainab Tower, Link Road, Phase 3 Town, Lahore, 54700</div>
                <div><strong>Website:</strong> <a href="https://woodexfurniture.pk/" target="_blank" style="color:var(--primary-color);text-decoration:none;">woodexfurniture.pk</a></div>
            </div>
        </div>

        <!-- Logical flow: 4 steps -->
        <div class="flow-steps">
            <div class="flow-step">
                <span class="badge-step">1</span>
                <div>
                    <strong>Select Products</strong><br>
                    <span class="small-note">Search and add items to the quotation basket.</span>
                </div>
            </div>
            <div class="flow-step">
                <span class="badge-step">2</span>
                <div>
                    <strong>Review Basket</strong><br>
                    <span class="small-note">Adjust quantities and confirm item list.</span>
                </div>
            </div>
            <div class="flow-step">
                <span class="badge-step">3</span>
                <div>
                    <strong>Customer & GST</strong><br>
                    <span class="small-note">Fill customer details and GST preference.</span>
                </div>
            </div>
            <div class="flow-step">
                <span class="badge-step">4</span>
                <div>
                    <strong>Generate Quotation</strong><br>
                    <span class="small-note">Review totals and download as PDF.</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- LEFT: Step 1 + Step 2 -->
            <div class="col-lg-7">
                <!-- STEP 1: Product selection -->
                <div class="quote-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="section-heading mb-0">Step 1 – Select Products</h2>
                        <span class="small-note">Search from your existing product catalogue.</span>
                    </div>

                    <div class="mb-3">
                        <label for="product-search" class="form-label">Search products (name / SKU / code)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="product-search" class="form-control" placeholder="Type to filter products...">
                        </div>
                        <div class="small-note mt-1">Products are loaded from the <code>products</code> table using your live prices.</div>
                    </div>

                    <div class="table-responsive" style="max-height:380px; overflow:auto;">
                        <table class="table table-sm table-hover align-middle table-smaller mb-0">
                            <thead>
                            <tr>
                                <th style="width:12%;">SKU</th>
                                <th>Product</th>
                                <th style="width:18%;">Unit Price (PKR)</th>
                                <th style="width:10%;"></th>
                            </tr>
                            </thead>
                            <tbody id="product-table-body">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $p):
                                    $id   = (int)$p['id'];
                                    $name = htmlspecialchars($p['product_name']);
                                    $sku  = htmlspecialchars($p['product_sku'] ?? '');
                                    $price = (float)($p['current_price'] ?? 0);
                                ?>
                                    <tr class="product-row"
                                        data-id="<?php echo $id; ?>"
                                        data-name="<?php echo $name; ?>"
                                        data-sku="<?php echo $sku; ?>"
                                        data-price="<?php echo $price; ?>">
                                        <td>
                                            <?php if ($sku): ?>
                                                <span class="badge-sku"><?php echo $sku; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $name; ?></td>
                                        <td>PKR <?php echo number_format($price, 2); ?></td>
                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary add-to-basket-btn">
                                                <i class="bi bi-plus-circle me-1"></i> Add
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center small text-muted py-4">No products found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

          
            </div>

            <!-- RIGHT: Step 3 + Step 4 -->
            <div class="col-lg-5">
                      <!-- STEP 2: Basket review -->
                <div class="quote-card">
                    <h2 class="section-heading">Step 2 – Review Quotation Basket</h2>
                    <p class="small-note mb-2">All selected products appear here. Update quantities or remove items before proceeding.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle table-smaller mb-2">
                            <thead>
                            <tr>
                                <th style="width:12%;">SKU</th>
                                <th>Product</th>
                                <th style="width:10%;">Qty</th>
                                <th style="width:16%;">Unit Price</th>
                                <th style="width:16%;">Line Total</th>
                                <th style="width:7%;"></th>
                            </tr>
                            </thead>
                            <tbody id="basket-body">
                            <tr id="basket-empty-row">
                                <td colspan="6" class="text-center small text-muted py-3">
                                    No products added yet. Use the list above to add products into the quotation.
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small-note" id="basket-count-note">0 items in basket.</span>
                        <div class="fw-semibold">Sub-total: <span id="basket-subtotal-text">PKR 0.00</span></div>
                    </div>
                </div>
                <!-- STEP 3: Customer + GST -->
                <div class="quote-card mb-3">
                    <h2 class="section-heading">Step 3 – Customer Details</h2>
                    <p class="small-note mb-3">Enter the customer information that will appear on the quotation.</p>

                    <div class="mb-3">
                        <label for="cust-name" class="form-label">Full Name</label>
                        <input type="text" id="cust-name" class="form-control" placeholder="Customer full name">
                    </div>
                    <div class="mb-3">
                        <label for="cust-email" class="form-label">Email Address</label>
                        <input type="email" id="cust-email" class="form-control" placeholder="Customer email">
                    </div>
                    <div class="mb-3">
                        <label for="cust-phone" class="form-label">Phone Number</label>
                        <input type="text" id="cust-phone" class="form-control" placeholder="Customer phone number">
                    </div>
                    <div class="mb-3">
                        <label for="cust-address" class="form-label">Full Address</label>
                        <textarea id="cust-address" class="form-control" rows="3" placeholder="Customer complete address"></textarea>
                    </div>
                </div>

                <div class="quote-card mb-3">
                    <h2 class="section-heading">Step 3 – GST 17% Option</h2>
                    <p class="small-note mb-2">Select how GST will be handled for this quotation.</p>

                    <div class="gst-options mb-2">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="gst_option" id="gst-with" value="with" checked>
                            <label class="form-check-label" for="gst-with">
                                GST 17% Included (add 17% GST on sub-total)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gst_option" id="gst-without" value="without">
                            <label class="form-check-label" for="gst-without">
                                GST 17% Not Included (no GST added)
                            </label>
                        </div>
                    </div>
                    <div class="gst-chip">
                        <i class="bi bi-info-circle-fill"></i>
                        <span id="gst-mode-label">Current mode: GST 17% Included</span>
                    </div>
                </div>

                <!-- STEP 4: Summary & PDF -->
                <div class="quote-card">
                    <h2 class="section-heading">Step 4 – Quotation Summary & PDF</h2>
                    <p class="small-note mb-2">Review all amounts. Then generate a Woodex-branded quotation in a new tab.</p>

                    <div class="summary-box mb-3">
                        <div class="summary-row">
                            <span>Sub-total</span>
                            <span id="summary-subtotal">PKR 0.00</span>
                        </div>
                        <div class="summary-row" id="summary-gst-row">
                            <span>GST (17%)</span>
                            <span id="summary-gst">PKR 0.00</span>
                        </div>
                        <div class="summary-row highlight">
                            <span>Grand Total</span>
                            <span id="summary-grandtotal">PKR 0.00</span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary btn-generate w-100" id="generate-pdf-btn">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>
                        Download Quotation (PDF)
                    </button>
                    <p class="small-note mt-2 mb-0">
                        The quotation will open in a new tab. Use your browser’s “Print → Save as PDF” option to download.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    // Basket data structure: [{id, name, sku, price, qty}]
    let basket = [];
    const GST_RATE = 0.17;

    const productSearchInput = document.getElementById('product-search');
    const productRows = Array.from(document.querySelectorAll('.product-row'));
    const basketBody = document.getElementById('basket-body');
    const basketEmptyRow = document.getElementById('basket-empty-row');
    const basketCountNote = document.getElementById('basket-count-note');
    const basketSubtotalText = document.getElementById('basket-subtotal-text');

    const gstModeLabel = document.getElementById('gst-mode-label');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryGstRow = document.getElementById('summary-gst-row');
    const summaryGst = document.getElementById('summary-gst');
    const summaryGrand = document.getElementById('summary-grandtotal');

    const gstRadios = document.querySelectorAll('input[name="gst_option"]');
    const generateBtn = document.getElementById('generate-pdf-btn');

    // Customer fields
    const custName = document.getElementById('cust-name');
    const custEmail = document.getElementById('cust-email');
    const custPhone = document.getElementById('cust-phone');
    const custAddress = document.getElementById('cust-address');

    function formatPKR(amount) {
        return 'PKR ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function getGstMode() {
        const checked = document.querySelector('input[name="gst_option"]:checked');
        return checked ? checked.value : 'with';
    }

    function updateGstLabel() {
        const mode = getGstMode();
        if (mode === 'with') {
            gstModeLabel.textContent = 'Current mode: GST 17% Included (GST is added to sub-total).';
        } else {
            gstModeLabel.textContent = 'Current mode: GST 17% Not Included (GST is not added).';
        }
    }

    function calculateTotals() {
        let subTotal = 0;
        basket.forEach(item => {
            subTotal += item.price * item.qty;
        });

        const mode = getGstMode();
        let gstAmount = 0;
        if (mode === 'with') {
            gstAmount = subTotal * GST_RATE;
            summaryGstRow.style.display = '';
        } else {
            gstAmount = 0;
            summaryGstRow.style.display = 'none';
        }
        const grandTotal = subTotal + gstAmount;

        basketSubtotalText.textContent = formatPKR(subTotal);
        summarySubtotal.textContent = formatPKR(subTotal);
        summaryGst.textContent = formatPKR(gstAmount);
        summaryGrand.textContent = formatPKR(grandTotal);
    }

    function renderBasket() {
        basketBody.innerHTML = '';

        if (basket.length === 0) {
            if (basketEmptyRow) {
                basketEmptyRow.style.display = '';
                basketBody.appendChild(basketEmptyRow);
            }
            basketCountNote.textContent = '0 items in basket.';
            basketSubtotalText.textContent = 'PKR 0.00';
            summarySubtotal.textContent = 'PKR 0.00';
            summaryGst.textContent = 'PKR 0.00';
            summaryGrand.textContent = 'PKR 0.00';
            return;
        }

        if (basketEmptyRow) {
            basketEmptyRow.style.display = 'none';
        }

        let totalQty = 0;

        basket.forEach(item => {
            totalQty += item.qty;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.sku ? `<span class="badge-sku">${item.sku}</span>` : '<span class="text-muted small">—</span>'}</td>
                <td>${item.name}</td>
                <td style="width:10%;">
                    <input type="number" class="form-control form-control-sm qty-input" 
                           min="1" step="1"
                           data-id="${item.id}" value="${item.qty}">
                </td>
                <td>${formatPKR(item.price)}</td>
                <td>${formatPKR(item.price * item.qty)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-id="${item.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            basketBody.appendChild(tr);
        });

        basketCountNote.textContent = totalQty + (totalQty === 1 ? ' item in basket.' : ' items in basket.');
        calculateTotals();
    }

    function addToBasket(id, name, sku, price) {
        price = Number(price) || 0;
        id = String(id);

        let existing = basket.find(item => item.id === id);
        if (existing) {
            existing.qty += 1;
        } else {
            basket.push({
                id: id,
                name: name,
                sku: sku,
                price: price,
                qty: 1
            });
        }
        renderBasket();
    }

    function addToBasketQty(id, name, sku, price, qty) {
        qty = parseInt(qty, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        price = Number(price) || 0;
        id = String(id);

        let existing = basket.find(item => item.id === id);
        if (existing) {
            existing.qty += qty;
        } else {
            basket.push({
                id: id,
                name: name,
                sku: sku,
                price: price,
                qty: qty
            });
        }
        renderBasket();
    }

    function removeFromBasket(id) {
        id = String(id);
        basket = basket.filter(item => item.id !== id);
        renderBasket();
    }

    // Product search
    if (productSearchInput) {
        productSearchInput.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            productRows.forEach(row => {
                const name = (row.getAttribute('data-name') || '').toLowerCase();
                const sku  = (row.getAttribute('data-sku') || '').toLowerCase();
                if (!q || name.includes(q) || sku.includes(q)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Add / remove buttons
    document.addEventListener('click', function(e){
        const addBtn = e.target.closest('.add-to-basket-btn');
        if (addBtn) {
            const row = addBtn.closest('.product-row');
            if (!row) return;
            const id = row.getAttribute('data-id');
            const name = row.getAttribute('data-name') || '';
            const sku = row.getAttribute('data-sku') || '';
            const price = parseFloat(row.getAttribute('data-price') || '0');
            if (!id) return;
            addToBasket(id, name, sku, price);
        }

        const removeBtn = e.target.closest('.remove-item-btn');
        if (removeBtn) {
            const id = removeBtn.getAttribute('data-id');
            if (!id) return;
            removeFromBasket(id);
        }
    });

    // Quantity change
    document.addEventListener('input', function(e){
        const qtyInput = e.target.closest('.qty-input');
        if (qtyInput) {
            const id = String(qtyInput.getAttribute('data-id'));
            let qty = parseInt(qtyInput.value, 10);
            if (isNaN(qty) || qty <= 0) {
                qty = 1;
            }
            qtyInput.value = qty;
            const item = basket.find(i => i.id === id);
            if (item) {
                item.qty = qty;
                renderBasket();
            }
        }
    });

    // GST option change
    gstRadios.forEach(radio => {
        radio.addEventListener('change', function(){
            updateGstLabel();
            calculateTotals();
        });
    });

    updateGstLabel();
    calculateTotals();

    // Prefill basket from URL params
    const prefillId = <?php echo (int)$prefillProductId; ?>;
    const prefillQty = <?php echo (int)$prefillQty; ?>;
    if (prefillId > 0) {
        const row = document.querySelector('.product-row[data-id="' + String(prefillId) + '"]');
        if (row) {
            const id = row.getAttribute('data-id');
            const name = row.getAttribute('data-name') || '';
            const sku = row.getAttribute('data-sku') || '';
            const price = parseFloat(row.getAttribute('data-price') || '0');
            addToBasketQty(id, name, sku, price, prefillQty);

            setTimeout(function(){
                const basketEl = document.getElementById('basket-body');
                if (basketEl) basketEl.scrollIntoView({behavior:'smooth', block:'start'});
            }, 250);
        }
    }

    function generateQuotationPdf() {
        if (basket.length === 0) {
            alert('Please add at least one product to the quotation basket.');
            return;
        }
        const name = custName.value.trim();
        const email = custEmail.value.trim();
        const phone = custPhone.value.trim();
        const addr = custAddress.value.trim();

        if (!name || !email || !phone || !addr) {
            alert('Please fill in all customer details before generating the quotation.');
            return;
        }

        // Totals
        let subTotal = 0;
        basket.forEach(item => subTotal += item.price * item.qty);
        const mode = getGstMode();
        let gstAmount = 0;
        if (mode === 'with') {
            gstAmount = subTotal * GST_RATE;
        }
        const grandTotal = subTotal + gstAmount;

        const now = new Date();
        const dateStr = now.toLocaleDateString();
        const qNum = 'Q' +
            now.getFullYear().toString().slice(-2) +
            (now.getMonth() + 1).toString().padStart(2, '0') +
            now.getDate().toString().padStart(2, '0') +
            '-' + Math.floor(Math.random() * 9000 + 1000);

        // Woodex branding
        const companyName    = 'Woodex Furniture';
        const companyLogo    = '<?php echo $domain; ?>assets/images/header-logo.png';
        const companyPhone   = '+92 321 4000 768';
        const companyAddress = 'LG 89, Model, Zainab Tower, Link Road, Phase 3 Town, Lahore, 54700';
        const companyWebsite = 'https://woodexfurniture.pk/';

        // Build product rows HTML
        let rowsHtml = '';
        basket.forEach((item, idx) => {
            const lineTotal = item.price * item.qty;
            rowsHtml += `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${item.sku ? item.sku : ''}</td>
                    <td>${item.name}</td>
                    <td style="text-align:right;">${item.qty}</td>
                    <td style="text-align:right;">${formatPKR(lineTotal)}</td>
                </tr>
            `;
        });

        const gstText = mode === 'with'
            ? `<div class="summary-row"><span>GST (17%)</span><span>${formatPKR(gstAmount)}</span></div>`
            : '';

        const safeAddressHtml = addr.replace(/\n/g, '<br>');

        const html = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotation ${qNum}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 24px;
            font-size: 13px;
        }
        .header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            margin-bottom:20px;
        }
        .company-info {
            max-width:60%;
        }
        .company-name {
            font-size:20px;
            font-weight:bold;
            margin-bottom:4px;
        }
        .logo {
            max-height:60px;
            margin-right:12px;
        }
        .small {
            font-size:11px;
            color:#6b7280;
        }
        .quote-meta {
            text-align:right;
            font-size:12px;
        }
        .section-title {
            font-weight:bold;
            font-size:14px;
            margin:16px 0 8px;
        }
        table {
            width:100%;
            border-collapse:collapse;
        }
        th, td {
            border:1px solid #e5e7eb;
            padding:6px 8px;
        }
        th {
            background:#f3f4f6;
            font-size:12px;
            text-align:left;
        }
        .summary-box {
            margin-top:12px;
            width:250px;
            margin-left:auto;
            border:1px solid #e5e7eb;
            border-radius:4px;
            padding:8px 10px;
            font-size:12px;
        }
        .summary-row {
            display:flex;
            justify-content:space-between;
            margin-bottom:4px;
        }
        .summary-row.total {
            border-top:1px dashed #d1d5db;
            padding-top:6px;
            margin-top:4px;
            font-weight:bold;
        }
        .customer-box {
            border:1px solid #e5e7eb;
            border-radius:4px;
            padding:8px 10px;
            font-size:12px;
        }
        .footer-note {
            font-size:11px;
            color:#6b7280;
            margin-top:18px;
            border-top:1px solid #e5e7eb;
            padding-top:8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div style="display:flex;align-items:center;">
                <img src="${companyLogo}" class="logo" alt="Woodex Furniture Logo">
                <div>
                    <div class="company-name">${companyName}</div>
                    <div class="small">${companyAddress}</div>
                    <div class="small">Phone: ${companyPhone}</div>
                    <div class="small">Website: ${companyWebsite}</div>
                    <div class="small">Quotation generated from the online quotation system.</div>
                </div>
            </div>
        </div>
        <div class="quote-meta">
            <div><strong>Quotation #:</strong> ${qNum}</div>
            <div><strong>Date:</strong> ${dateStr}</div>
        </div>
    </div>

    <div class="section-title">Customer Details</div>
    <div class="customer-box">
        <div><strong>Name:</strong> ${name}</div>
        <div><strong>Email:</strong> ${email}</div>
        <div><strong>Phone:</strong> ${phone}</div>
        <div><strong>Address:</strong> ${safeAddressHtml}</div>
    </div>

    <div class="section-title">Products</div>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:15%;">SKU</th>
                <th>Product</th>
                <th style="width:10%; text-align:right;">Qty</th>
                <th style="width:20%; text-align:right;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            ${rowsHtml}
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-row">
            <span>Sub-total</span>
            <span>${formatPKR(subTotal)}</span>
        </div>
        ${gstText}
        <div class="summary-row total">
            <span>Grand Total</span>
            <span>${formatPKR(grandTotal)}</span>
        </div>
    </div>

    <div class="footer-note">
        This is a system-generated quotation from Woodex Furniture. Prices and availability are subject to change without prior notice.
    </div>
</body>
</html>`;

        const win = window.open('', '_blank');
        if (!win) {
            alert('Popup blocked. Please allow popups for this website to view the quotation.');
            return;
        }
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
    }

    if (generateBtn) {
        generateBtn.addEventListener('click', generateQuotationPdf);
    }
})();
</script>

</body>
</html>
