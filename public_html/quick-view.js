(function () {

  function byId(id) {
    return document.getElementById(id);
  }

  function safeHtml(text) {
    return String(text == null ? '' : text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getApiUrl(slug) {
    var base = String(window.SITE_BASE || '/');
    if (base.slice(-1) !== '/') base += '/';
    return base + 'api/product/quick-view.php?slug=' + encodeURIComponent(slug);
  }

  function setLoading() {
    var name = byId('wdxQvName');
    var cat = byId('wdxQvCategory');
    var desc = byId('wdxQvDescription');
    var price = byId('wdxQvPrice');
    var image = byId('wdxQvImage');
    var link = byId('wdxQvLink');
    if (name) name.textContent = 'Loading...';
    if (cat) cat.textContent = '';
    if (desc) desc.textContent = 'Please wait while we fetch product details.';
    if (price) price.innerHTML = '<span class="qv-current">PKR 0</span>';
    if (image) {
      image.src = '';
      image.alt = 'Loading product image';
    }
    if (link) link.href = '#';
  }

  function renderProduct(p) {
    var name = byId('wdxQvName');
    var cat = byId('wdxQvCategory');
    var desc = byId('wdxQvDescription');
    var price = byId('wdxQvPrice');
    var image = byId('wdxQvImage');
    var link = byId('wdxQvLink');

    if (name) name.textContent = p.name || 'Product';
    if (cat) cat.textContent = p.category || '';
    if (desc) {
      var text = String(p.description || 'No description available.')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
      if (text.length > 260) {
        text = text.slice(0, 257).trim() + '...';
      }
      desc.textContent = text;
    }
    if (image) {
      image.src = p.image || '';
      image.alt = p.name || 'Product';
      image.onerror = function () {
        var base = String(window.SITE_BASE || '/');
        if (base.slice(-1) !== '/') base += '/';
        image.onerror = null;
        image.src = base + 'assets/images/placeholder.png';
      };
    }

    if (price) {
      var oldPrice = Number(p.old_price || 0);
      var effective = Number(p.effective_price || 0);
      var activePriceText = safeHtml(p.price_text || ('PKR ' + Math.round(effective).toLocaleString()));
      if (oldPrice > 0 && oldPrice > effective) {
        price.innerHTML = '<span class="qv-old">PKR ' + Math.round(oldPrice).toLocaleString() + '</span><span class="qv-current">' + activePriceText + '</span>';
      } else {
        price.innerHTML = '<span class="qv-current">' + activePriceText + '</span>';
      }
    }

    if (link) {
      link.href = p.url || '#';
    }
  }

  function openModal() {
    var modalEl = byId('wdxQuickViewModal');
    if (!modalEl) {
      return null;
    }

    if (!window.bootstrap || !window.bootstrap.Modal) {
      modalEl.classList.add('show');
      modalEl.style.display = 'block';
      modalEl.removeAttribute('aria-hidden');
      modalEl.setAttribute('aria-modal', 'true');
      document.body.classList.add('modal-open');
      return null;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true, keyboard: true });

    modal.show();
    return modal;
  }

  function forceCloseModal() {
    var modalEl = byId('wdxQuickViewModal');
    if (!modalEl) return;

    if (window.bootstrap && window.bootstrap.Modal) {
      var instance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
      if (instance) {
        instance.hide();
      }
    }

    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }

  document.addEventListener('click', function (e) {
    var closeBtn = e.target.closest('#wdxQuickViewModal .qv-close-btn');
    if (!closeBtn) return;
    e.preventDefault();
    forceCloseModal();
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.wdx-quick-view-btn');
    if (!btn) return;

    e.preventDefault();
    var slug = (btn.getAttribute('data-slug') || '').trim();
    if (!slug) return;

    openModal();
    setLoading();

    fetch(getApiUrl(slug), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.status || !d.product) {
          throw new Error('Unable to load product details');
        }
        renderProduct(d.product);
      })
      .catch(function () {
        var name = byId('wdxQvName');
        var desc = byId('wdxQvDescription');
        if (name) name.textContent = 'Unable to load';
        if (desc) desc.textContent = 'Please try again.';
      });
  });
})();
