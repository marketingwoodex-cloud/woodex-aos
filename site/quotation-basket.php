<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

$quoteItems = isset($_SESSION['quote_basket']) && is_array($_SESSION['quote_basket']) ? $_SESSION['quote_basket'] : [];
$totalQty = 0;
$subTotal = 0;
foreach ($quoteItems as $it) {
    $q = (int)($it['quantity'] ?? 0);
    $p = (float)($it['current_price'] ?? 0);
    $totalQty += $q;
    $subTotal += ($q * $p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Requested Quote</title>
  <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
  <style>
    .quote-wrap{max-width:1240px;margin:0 auto;padding:26px 18px 34px;}
    .quote-main{display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start;}
    .quote-card{background:#fff;border:1px solid #dfe5eb;border-radius:14px;box-shadow:0 8px 24px rgba(20,34,50,.06);overflow:hidden;}
    .quote-heading{margin:0;padding:14px 18px;background:linear-gradient(90deg,var(--primary-color) 0%, #515151 100%);color:#fff;font-size:1.04rem;text-transform:uppercase;letter-spacing:.04em;font-weight:800;}
    .quote-body{padding:16px 18px;}
    .quote-item{display:grid;grid-template-columns:96px minmax(0,1fr) auto;gap:14px;align-items:center;border-bottom:1px solid #edf1f5;padding:12px 0;}
    .quote-item:last-child{border-bottom:none;}
    .quote-item img{width:96px;height:96px;object-fit:contain;border:1px solid #e4e9ee;border-radius:10px;background:#f7f9fb;padding:6px;}
    .q-name{font-weight:800;color:#1f2a36;margin-bottom:4px;}
    .q-price{color:var(--accent-color);font-weight:800;}
    .q-qty{display:flex;flex-direction:column;align-items:flex-end;gap:8px;}
    .q-box{display:inline-flex;align-items:center;border:1px solid #d9e1e8;border-radius:8px;overflow:hidden;background:#fff;}
    .q-box button{width:32px;height:34px;border:0;background:#fff;font-weight:700;}
    .q-box input{width:38px;border:0;text-align:center;font-weight:700;}
    .q-remove{border:1px solid #e2e8ef;background:#fff;color:#9aa8b7;padding:6px 10px;border-radius:8px;font-size:.8rem;font-weight:700;}
    .q-remove:hover{color:#dc3545;border-color:#f3c3cb;background:#fff8f9;}
    .q-summary p{display:flex;justify-content:space-between;margin:8px 0;color:#445362;font-weight:700;}
    .q-summary .total{font-size:1.24rem;color:var(--accent-color);}
    .q-form .form-label{font-weight:700;color:#334155;}
    .q-form .form-control{border-color:#d8e1ea;min-height:44px;}
    .q-form .form-control:focus{border-color:var(--accent-color);box-shadow:0 0 0 2px rgba(120,190,32,.15);}
    .q-submit{background:var(--accent-color);border-color:var(--accent-color);font-weight:800;min-height:46px;}
    .q-submit:hover{background:#69a81c;border-color:#69a81c;}
    @media (max-width:991.98px){.quote-main{grid-template-columns:1fr;}}
    @media (max-width:767.98px){.quote-item{grid-template-columns:84px minmax(0,1fr);} .q-qty{grid-column:1/-1;align-items:flex-start;} .quote-wrap{padding:16px 12px 24px;}}
  </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<section class="quote-wrap">
  <div class="quote-main">
    <div class="quote-card">
      <h2 class="quote-heading">Requested Quote (<?php echo (int)$totalQty; ?>)</h2>
      <div class="quote-body" id="quoteItemsWrap">
        <?php if (empty($quoteItems)): ?>
          <p class="text-muted mb-0">No products added yet. Go to product pages and click E-Quotation.</p>
        <?php else: ?>
          <?php foreach ($quoteItems as $key => $item): ?>
            <div class="quote-item" data-key="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
              <img src="<?php echo htmlspecialchars($item['image'] ?? 'assets/images/placeholder.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? 'Product', ENT_QUOTES, 'UTF-8'); ?>">
              <div>
                <div class="q-name"><?php echo htmlspecialchars($item['name'] ?? 'Product', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item['attributes'])): ?>
                  <div class="small text-muted mb-1">
                    <?php
                      $parts = [];
                      foreach ($item['attributes'] as $attrName => $attrData) {
                        $parts[] = htmlspecialchars($attrName, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($attrData['label'] ?? ($attrData['value'] ?? ''), ENT_QUOTES, 'UTF-8');
                      }
                      echo implode(', ', $parts);
                    ?>
                  </div>
                <?php endif; ?>
                <div class="q-price">PKR <?php echo number_format((float)($item['current_price'] ?? 0), 2); ?></div>
              </div>
              <div class="q-qty">
                <div class="q-box">
                  <button type="button" class="q-upd" data-action="decrease">-</button>
                  <input type="number" readonly value="<?php echo (int)($item['quantity'] ?? 1); ?>">
                  <button type="button" class="q-upd" data-action="increase">+</button>
                </div>
                <button type="button" class="q-remove q-del">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="quote-card">
      <h2 class="quote-heading">Submit Quote Request</h2>
      <div class="quote-body">
        <div class="q-summary mb-3">
          <p><span>Items</span><span id="qSummaryCount"><?php echo (int)$totalQty; ?></span></p>
          <p><span>Sub-total</span><span id="qSummarySub">PKR <?php echo number_format($subTotal, 2); ?></span></p>
          <p class="total"><span>Total</span><span id="qSummaryTotal">PKR <?php echo number_format($subTotal, 2); ?></span></p>
        </div>

        <form class="q-form" action="submit_quote_request.php" method="post">
          <div class="mb-2">
            <label class="form-label">Name *</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Phone *</label>
            <input type="text" name="phone" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Address (optional)</label>
            <input type="text" name="address" class="form-control">
          </div>
          <button type="submit" class="btn btn-primary q-submit w-100" <?php echo empty($quoteItems) ? 'disabled' : ''; ?>>Submit Request</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const API = (window.QUOTE_API_BASE || '/api/quote/').replace(/\/?$/, '/');
  function reload(){ window.location.reload(); }
  document.addEventListener('click', function(e){
    const upd = e.target.closest('.q-upd');
    if (upd){
      const wrap = upd.closest('.quote-item');
      if (!wrap) return;
      const key = wrap.getAttribute('data-key');
      const action = upd.getAttribute('data-action');
      fetch(API + 'update.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin', body:`quote_key=${encodeURIComponent(key)}&action=${encodeURIComponent(action)}`})
        .then(r=>r.json()).then(d=>{ if (d && d.status) reload(); });
      return;
    }
    const del = e.target.closest('.q-del');
    if (del){
      const wrap = del.closest('.quote-item');
      if (!wrap) return;
      const key = wrap.getAttribute('data-key');
      fetch(API + 'remove.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin', body:`quote_key=${encodeURIComponent(key)}`})
        .then(r=>r.json()).then(d=>{ if (d && d.status) reload(); });
    }
  });
})();
</script>
</body>
</html>
