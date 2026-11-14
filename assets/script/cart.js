$(document).ready(function () {
    const API = (window.API_BASE || '/api/cart/').replace(/\/?$/, '/');

  let isAddingToCart = false;

  function showToast(opts) {
    const { message, image, isError=false } = opts || {};
    const toast = $('#success-toast');
    const toastMsg = $('#toast-message');
    const imgWrap = $('#toast-image-wrap');

    toast.removeClass('show');
    imgWrap.html(image ? `<img src="${image}" alt="Product" />` : '');
    toastMsg.html(message || '');

    toast.css({
      'background-color': isError ? '#dc3545' : '#28a745',
      'color': '#fff'
    });

    // reveal
    setTimeout(() => { toast.addClass('show'); }, 10);
    // hide
    setTimeout(() => { toast.removeClass('show'); }, 3200);
  }

  function openCartPanel() {
    $('#cart-panel').css('right', '0');
    $('#overlay').addClass('show');
  }
  function closeCartPanel() {
    $('#cart-panel').css('right', '-400px');
    $('#overlay').removeClass('show');
  }

  function updateCart() {
    $.ajax({
      url: API + 'get.php',
      method: 'GET',
      dataType: 'json'
    }).done(function (response) {
      const cartCount = response.cart_count || 0;
      const cartItems = response.cart_items || [];

      $('#cart-count, #footer-cart-count').text(cartCount);

      let cartHtml = '';
      let total = 0;

      if (!cartItems.length) {
        cartHtml = '<p class="text-muted text-center py-3">Your cart is empty.</p>';
      } else {
        cartItems.forEach(item => {
          const itemPrice = parseFloat(item.current_price) || 0;
          const itemQuantity = parseInt(item.quantity) || 0;
          const itemTotal = itemPrice * itemQuantity;
          total += itemTotal;

          let variationInfo = '';
          if (item.attributes && typeof item.attributes === 'object') {
            const parts = [];
            for (const [key, obj] of Object.entries(item.attributes)) {
              let display = (obj && (obj.label || obj.value)) ? (obj.label || obj.value) : '-';
              parts.push(`${key}: ${display}`);
            }
            variationInfo = `<p class="cart-item-variation mb-1 text-muted small">${parts.join(', ')}</p>`;
          }

          cartHtml += `
            <div class="cart-item d-flex align-items-center mt-3">
              <img src="${item.image}" alt="${item.name}" class="cart-item-image me-3" style="width:60px;height:60px;object-fit:contain;">
              <div class="cart-item-details flex-grow-1">
                <p class="cart-item-name mb-1">${item.name}</p>
                ${variationInfo}
                <div class="d-flex justify-content-between align-items-center">
                  <div class="quantity-selector">
                    <button class="btn btn-sm btn-outline-secondary update-qty" data-key="${item.cart_item_id}" data-action="decrease">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-outline-secondary update-qty" data-key="${item.cart_item_id}" data-action="increase">+</button>
                  </div>
                  <p class="cart-item-price mb-0">PKR ${itemTotal.toFixed(2)}</p>
                </div>
              </div>
              <button class="btn btn-sm btn-danger ms-2 remove-item" data-key="${item.cart_item_id}">
                <i class="bi bi-x"></i>
              </button>
            </div>`;
        });
      }

      $('#cart-items').html(cartHtml);
      $('#cart-total').text(`PKR ${total.toFixed(2)}`);
    });
  }

  function handleAddToCart(e) {
    e.preventDefault();
    if (isAddingToCart) return;
    isAddingToCart = true;

    const $button = $(this);
    $button.prop('disabled', true).addClass('processing');

    const productId = $button.data('id');
    const quantity = parseInt($('#pro-quantity').val()) || 1;

    let attributes = {};
    $('#attributeSelectors [id^="selected_"]').each(function() {
      const attr = this.id.replace('selected_', '');
      attributes[attr] = $(this).val();
    });

    const attrString = JSON.stringify(attributes);
    let cartKey = '';
    try {
      cartKey = CryptoJS.MD5(String(productId) + attrString).toString();
    } catch (e) {
      cartKey = '';
    }

    $.ajax({
      url: API + 'add.php',
      method: 'POST',
      // Use text + manual parse so PHP notices/warnings don't cause jQuery "parsererror".
      dataType: 'text',
      data: {
        product_id: productId,
        quantity: quantity,
        attributes: attributes,
        cart_item_id: cartKey
      }
    }).done(function (raw) {
      let response = null;
      try {
        response = (typeof raw === 'object') ? raw : JSON.parse(raw);
      } catch (err) {
        console.error('Add-to-cart: non-JSON response', raw);
        showToast({ message: 'Failed to add to cart. Please try again.', isError: true });
        return;
      }

      if (response && response.status) {
        $(document).trigger('cartUpdated');
        showToast({
          message: response.toast_html || response.message || 'Added to cart.',
          image: response.image || ''
        });
        openCartPanel();
      } else {
        showToast({ message: response.message || 'Failed to add to cart.', isError: true });
      }
    }).fail(function (xhr) {
      if (xhr && xhr.responseText) {
        console.error('Add-to-cart request failed:', xhr.status, xhr.responseText);
      }
      showToast({ message: 'Failed to add to cart. Please try again.', isError: true });
    }).always(function () {
      isAddingToCart = false;
      $button.prop('disabled', false).removeClass('processing');
    });
  }

  $(document).on('click', '#increaseQty', function () {
    const $input = $('#pro-quantity');
    const max = parseInt($input.attr('max')) || 999;
    let val = parseInt($input.val()) || 1;
    if (val < max) $input.val(val + 1);
  });

  $(document).on('click', '#decreaseQty', function () {
    const $input = $('#pro-quantity');
    let val = parseInt($input.val()) || 1;
    if (val > 1) $input.val(val - 1);
  });

  $(document).on('click', '.update-qty', function () {
    const key = $(this).data('key');
    const action = $(this).data('action');

    $.ajax({
      url: API + 'update.php',
      method: 'POST',
      dataType: 'json',
      data: { cart_key: key, action: action }
    }).done(function (response) {
      if (response.status) {
        showToast({ message: response.message });
        updateCart();
      } else {
        showToast({ message: response.message || 'Failed to update cart.', isError: true });
      }
    }).fail(function () {
      showToast({ message: 'Failed to update cart.', isError: true });
    });
  });

  $(document).on('click', '.remove-item', function () {
    const key = $(this).data('key');
    $.ajax({
      url: API + 'remove.php',
      method: 'POST',
      dataType: 'json',
      data: { cart_key: key }
    }).done(function (response) {
      if (response.status) {
        showToast({ message: response.message });
        updateCart();
      } else {
        showToast({ message: response.message || 'Unable to remove item.', isError: true });
      }
    }).fail(function () {
      showToast({ message: 'Error removing item.', isError: true });
    });
  });

  $('#cart-toggle, #footer-cart-toggle').on('click', function (e) {
    e.preventDefault();
    openCartPanel();
    updateCart();
  });
  $('#cart-close, #overlay').on('click', closeCartPanel);

  $(document).on('click', '.add-to-cart-btn', handleAddToCart);
  $(document).on('cartUpdated', updateCart);

  // initial pull
  updateCart();
});
