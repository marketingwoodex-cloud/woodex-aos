(function(){
  const API = (window.QUOTE_API_BASE || '/api/quote/').replace(/\/?$/, '/');
  const panel = document.getElementById('cartPanel');
  const overlay = document.getElementById('overlay');
  const panelTitle = document.getElementById('cart-panel-title');
  const panelItems = document.getElementById('cart-items');
  const panelTotalLine = document.getElementById('cart-panel-total-line');
  const panelTotal = document.getElementById('cart-total');
  const primaryLink = document.getElementById('cart-panel-primary-link');
  const secondaryLink = document.getElementById('cart-panel-secondary-link');

  function setQuoteCount(count){
    const n = Number(count || 0);
    document.querySelectorAll('[data-quote-count]').forEach(el => { el.textContent = String(n); });
    const label = document.querySelector('[data-quote-label]');
    if (label) {
      const txt = label.querySelector('.quote-label-text');
      if (txt) {
        txt.textContent = 'E-Quotation';
      }
    }
  }

  function toast(message){
    const toast = document.getElementById('success-toast');
    const msg = document.getElementById('toast-message');
    const imgWrap = document.getElementById('toast-image-wrap');
    if (toast && msg) {
      if (imgWrap) imgWrap.innerHTML = '';
      msg.innerHTML = message;
      toast.style.backgroundColor = '#1f7a34';
      toast.style.color = '#fff';
      toast.classList.remove('show');
      setTimeout(() => toast.classList.add('show'), 10);
      setTimeout(() => toast.classList.remove('show'), 2800);
    }
  }

  function fetchCount(){
    fetch(API + 'count.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => { if (d && d.status) setQuoteCount(d.quote_count || 0); })
      .catch(() => {});
  }

  function safePKR(value){
    const n = Number(value || 0);
    return 'PKR ' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function escapeHtml(value){
    const text = String(value == null ? '' : value);
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getSiteBase(){
    return String(window.SITE_BASE || '/').replace(/\/?$/, '/');
  }

  function makeUrl(path){
    const raw = String(path || '').trim();
    if (!raw) return getSiteBase();
    if (/^https?:\/\//i.test(raw)) return raw;
    return getSiteBase() + raw.replace(/^\/+/, '');
  }

  function ensureQuoteMode(){
    if (panelTitle) panelTitle.textContent = 'Quotation Basket';
    if (panelTotalLine) panelTotalLine.childNodes[0].nodeValue = 'Total: ';
    if (primaryLink) {
      primaryLink.href = makeUrl('requested-quote');
      primaryLink.textContent = 'View Quote';
    }
    if (secondaryLink) {
      secondaryLink.href = makeUrl('requested-quote');
      secondaryLink.textContent = 'Submit Request';
    }
  }

  function ensureCartMode(){
    if (panelTitle) panelTitle.textContent = 'Your Cart';
    if (panelTotalLine) panelTotalLine.childNodes[0].nodeValue = 'Total: ';
    if (primaryLink) {
      primaryLink.href = makeUrl('cart');
      primaryLink.textContent = 'View Cart';
    }
    if (secondaryLink) {
      secondaryLink.href = makeUrl('checkout');
      secondaryLink.textContent = 'Checkout';
    }
  }

  function openPanel(){
    if (!panel) return;
    panel.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    if (overlay) overlay.classList.add('show');
  }

  function renderQuotePanel(items){
    if (!panelItems || !panelTotal) return;
    let total = 0;
    if (!Array.isArray(items) || items.length === 0) {
      panelItems.innerHTML = '<p class="text-muted text-center py-3">Your quotation basket is empty.</p>';
      panelTotal.textContent = safePKR(0);
      return;
    }

    let html = '';
    items.forEach(item => {
      const qty = Number(item.quantity || 0);
      const price = Number(item.current_price || 0);
      const lineTotal = qty * price;
      total += lineTotal;

      let variationInfo = '';
      if (item.attributes && typeof item.attributes === 'object') {
        const parts = [];
        Object.keys(item.attributes).forEach(key => {
          const obj = item.attributes[key] || {};
          const label = obj.label || obj.value || '-';
          parts.push(escapeHtml(key) + ': ' + escapeHtml(label));
        });
        if (parts.length > 0) {
          variationInfo = '<p class="cart-item-variation mb-1 text-muted small">' + parts.join(', ') + '</p>';
        }
      }

      html += '<div class="cart-item d-flex align-items-center mt-3">'
        + '<img src="' + escapeHtml(item.image || '') + '" alt="' + escapeHtml(item.name || 'Product') + '" class="cart-item-image me-3" style="width:60px;height:60px;object-fit:contain;">'
        + '<div class="cart-item-details flex-grow-1">'
        + '<p class="cart-item-name mb-1">' + escapeHtml(item.name || 'Product') + '</p>'
        + variationInfo
        + '<div class="d-flex justify-content-between align-items-center">'
        + '<div class="quantity-selector">'
        + '<button class="btn btn-sm btn-outline-secondary quote-update-qty" data-key="' + escapeHtml(item.quote_item_id) + '" data-action="decrease">-</button>'
        + '<span class="mx-2">' + qty + '</span>'
        + '<button class="btn btn-sm btn-outline-secondary quote-update-qty" data-key="' + escapeHtml(item.quote_item_id) + '" data-action="increase">+</button>'
        + '</div>'
        + '<p class="cart-item-price mb-0">' + safePKR(lineTotal) + '</p>'
        + '</div>'
        + '</div>'
        + '<button class="btn btn-sm btn-danger ms-2 quote-remove-item" data-key="' + escapeHtml(item.quote_item_id) + '"><i class="bi bi-x"></i></button>'
        + '</div>';
    });

    panelItems.innerHTML = html;
    panelTotal.textContent = safePKR(total);
  }

  function refreshQuotePanel(openAfter){
    fetch(API + 'get.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d || !d.status) return;
        setQuoteCount(d.quote_count || 0);
        if (openAfter || (panelTitle && panelTitle.textContent === 'Quotation Basket')) {
          ensureQuoteMode();
          renderQuotePanel(d.quote_items || []);
        }
        if (openAfter) openPanel();
      })
      .catch(() => {});
  }

  function gatherAttributes(){
    const attrs = {};
    document.querySelectorAll('#attributeSelectors [id^="selected_"]').forEach(inp => {
      const attr = inp.id.replace('selected_', '');
      attrs[attr] = inp.value;
    });
    return attrs;
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.add-to-quote-btn');
    if (!btn) return;
    e.preventDefault();

    const productId = parseInt(btn.getAttribute('data-id') || '0', 10);
    const qtyAttr = parseInt(btn.getAttribute('data-quantity') || '0', 10);
    const qtyInput = document.getElementById('pro-quantity');
    const quantity = qtyAttr > 0 ? qtyAttr : (qtyInput ? (parseInt(qtyInput.value || '1', 10) || 1) : 1);

    if (!productId) return;
    const attributes = gatherAttributes();

    const form = new URLSearchParams();
    form.set('product_id', String(productId));
    form.set('quantity', String(quantity));
    Object.keys(attributes).forEach(k => form.set(`attributes[${k}]`, attributes[k]));

    fetch(API + 'add.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      credentials: 'same-origin',
      body: form.toString()
    }).then(r => r.json())
      .then(d => {
        if (d && d.status) {
          setQuoteCount(d.quote_count || 0);
          toast(d.message || 'Added to quotation basket.');
          refreshQuotePanel(true);
        }
      })
      .catch(() => {});
  });

  document.addEventListener('click', function(e){
    const upd = e.target.closest('.quote-update-qty');
    if (upd) {
      e.preventDefault();
      const key = upd.getAttribute('data-key') || '';
      const action = upd.getAttribute('data-action') || '';
      if (!key || !action) return;
      const body = new URLSearchParams();
      body.set('quote_key', key);
      body.set('action', action);
      fetch(API + 'update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body: body.toString()
      }).then(r => r.json())
        .then(d => {
          if (d && d.status) {
            refreshQuotePanel(false);
          }
        })
        .catch(() => {});
      return;
    }

    const del = e.target.closest('.quote-remove-item');
    if (del) {
      e.preventDefault();
      const key = del.getAttribute('data-key') || '';
      if (!key) return;
      const body = new URLSearchParams();
      body.set('quote_key', key);
      fetch(API + 'remove.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body: body.toString()
      }).then(r => r.json())
        .then(d => {
          if (d && d.status) {
            refreshQuotePanel(false);
          }
        })
        .catch(() => {});
    }
  });

  document.addEventListener('woodex:quote-added', function(ev){
    const detail = ev && ev.detail ? ev.detail : {};
    if (detail && typeof detail.quote_count !== 'undefined') {
      setQuoteCount(detail.quote_count || 0);
    }
    if (detail && detail.message) {
      toast(detail.message);
    }
    refreshQuotePanel(true);
  });

  window.WoodexQuote = {
    refresh: function(){ refreshQuotePanel(false); },
    open: function(){ refreshQuotePanel(true); }
  };

  document.addEventListener('DOMContentLoaded', function(){
    const btnCartPrimary = document.getElementById('btnCartPrimary');
    const btnCartSticky = document.getElementById('btnCartSticky');
    if (btnCartPrimary) {
      btnCartPrimary.addEventListener('click', function(){ ensureCartMode(); });
    }
    if (btnCartSticky) {
      btnCartSticky.addEventListener('click', function(){ ensureCartMode(); });
    }
    fetchCount();
  });
})();
