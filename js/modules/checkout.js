/* ═══════════════════════════════════════════
   checkout.js — 4-Step Checkout Wizard (API)
   ═══════════════════════════════════════════ */
const Checkout = {
  currentStep: 1,
  selectedPayment: 'card',
  shippingData: {},
  appliedPromo: null,   // { code, discount_type, discount_value, discount }

  open() {
    this.currentStep = 1;
    this.appliedPromo = null;
    Nav.goTab('checkout');
    Nav.setNavActive('checkout');
    this.goStep(1);
  },

  async openProduct(product) {
    if (!product) return;
    if (!State.inCart(product.id)) {
      await State.addToCart(product);
      Toast.show(product.name + ' added to cart!', '✓');
    }
    this.open();
  },

  goStep(step) {
    if (step > this.currentStep && !this.validateStep(this.currentStep)) return;

    this.currentStep = step;
    for (let i = 1; i <= 4; i++) {
      const el = document.getElementById('checkout-step-' + i);
      if (el) el.style.display = i === step ? 'block' : 'none';
    }
    this.updateStepBar(step);
    this.renderStep(step);
    window.scrollTo(0, 0);
  },

  updateStepBar(step) {
    for (let i = 1; i <= 4; i++) {
      const circle = document.getElementById('cs-circle-' + i);
      const label = document.getElementById('cs-lbl-' + i);
      if (!circle || !label) continue;
      if (i < step) {
        circle.className = 'cstep-circle done'; circle.textContent = '✓';
        label.className = 'cstep-lbl done';
      } else if (i === step) {
        circle.className = 'cstep-circle active'; circle.textContent = i;
        label.className = 'cstep-lbl active';
      } else {
        circle.className = 'cstep-circle pending'; circle.textContent = i;
        label.className = 'cstep-lbl pending';
      }
    }
    for (let i = 1; i <= 3; i++) {
      const line = document.getElementById('cs-line-' + i);
      if (line) line.className = i < step ? 'cstep-line done' : 'cstep-line';
    }
  },

  validateStep(step) {
    if (step === 1) {
      if (State.getSelectedItems().length === 0) { Toast.show('Select at least one item!', 'i'); return false; }
      return true;
    }
    if (step === 2) return this.validateShipping();
    if (step === 3) return this.validatePayment();
    return true;
  },

  validateShipping() {
    document.querySelectorAll('#checkout-step-2 .form-error').forEach(e => e.remove());
    document.querySelectorAll('#checkout-step-2 .form-input.error').forEach(e => e.classList.remove('error'));

    let valid = true;
    const fields = [
      { id: 'ship-fname', msg: 'First name is required' },
      { id: 'ship-lname', msg: 'Last name is required' },
      { id: 'ship-email', msg: 'Valid email is required', pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
      { id: 'ship-phone', msg: 'Phone number is required' },
      { id: 'ship-address', msg: 'Address is required' },
      { id: 'ship-city', msg: 'City is required' },
      { id: 'ship-zip', msg: 'ZIP code is required' },
    ];

    fields.forEach(f => {
      const input = document.getElementById(f.id);
      if (!input) return;
      const val = input.value.trim();
      if (!val || (f.pattern && !f.pattern.test(val))) {
        input.classList.add('error');
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = f.msg;
        input.closest('.form-group').appendChild(err);
        valid = false;
      }
    });

    if (valid) {
      this.shippingData = {
        name: document.getElementById('ship-fname').value.trim() + ' ' + document.getElementById('ship-lname').value.trim(),
        email: document.getElementById('ship-email').value.trim(),
        phone: document.getElementById('ship-phone').value.trim(),
        address: document.getElementById('ship-address').value.trim(),
        city: document.getElementById('ship-city').value.trim(),
        zip: document.getElementById('ship-zip').value.trim(),
      };
    }
    return valid;
  },

  validatePayment() {
    if (this.selectedPayment === 'card') {
      document.querySelectorAll('#pay-card-fields .form-error').forEach(e => e.remove());
      document.querySelectorAll('#pay-card-fields .form-input.error').forEach(e => e.classList.remove('error'));

      let valid = true;
      const fields = [
        { id: 'pay-card-num', msg: 'Card number is required' },
        { id: 'pay-card-exp', msg: 'Expiry date is required' },
        { id: 'pay-card-cvv', msg: 'CVV is required' },
        { id: 'pay-card-name', msg: 'Name on card is required' },
      ];
      fields.forEach(f => {
        const input = document.getElementById(f.id);
        if (!input) return;
        if (!input.value.trim()) {
          input.classList.add('error');
          const err = document.createElement('div');
          err.className = 'form-error';
          err.textContent = f.msg;
          input.closest('.form-group').appendChild(err);
          valid = false;
        }
      });
      return valid;
    }
    return true;
  },

  renderStep(step) {
    if (step === 1) this.renderCartReview();
    if (step === 4) this.renderConfirm();
  },

  // ── Promo code handling ──

  async applyPromo() {
    const input = document.getElementById('promo-code-input');
    const msgEl = document.getElementById('promo-msg');
    if (!input || !msgEl) return;

    const code = input.value.trim();
    if (!code) {
      msgEl.textContent = 'Please enter a promo code';
      msgEl.className = 'promo-msg error';
      return;
    }

    const subtotal = State.getSelectedTotal();
    msgEl.textContent = 'Validating...';
    msgEl.className = 'promo-msg';

    const res = await API.post('/checkout/validate-promo.php', { code, subtotal });

    if (res.success) {
      this.appliedPromo = res.data;
      msgEl.textContent = res.data.message;
      msgEl.className = 'promo-msg success';
      input.disabled = true;
      const btn = document.getElementById('promo-apply-btn');
      if (btn) {
        btn.textContent = 'Remove';
        btn.onclick = () => this.removePromo();
      }
    } else {
      this.appliedPromo = null;
      msgEl.textContent = res.error;
      msgEl.className = 'promo-msg error';
    }

    // Re-render summary with promo
    this.renderCartReviewSummary();
  },

  removePromo() {
    this.appliedPromo = null;
    const input = document.getElementById('promo-code-input');
    const msgEl = document.getElementById('promo-msg');
    const btn = document.getElementById('promo-apply-btn');
    if (input) { input.disabled = false; input.value = ''; }
    if (msgEl) { msgEl.textContent = ''; msgEl.className = 'promo-msg'; }
    if (btn) {
      btn.textContent = 'Apply';
      btn.onclick = () => this.applyPromo();
    }
    this.renderCartReviewSummary();
  },

  renderCartReview() {
    const itemsEl = document.getElementById('wiz-cart-items');
    const emptyEl = document.getElementById('wiz-cart-empty');
    const summaryEl = document.getElementById('wiz-cart-summary');
    const nextBtn = document.getElementById('wiz-next-1');
    if (!itemsEl) return;

    const selectedItems = State.getSelectedItems();
    const hasItems = selectedItems.length > 0;
    emptyEl.style.display = hasItems ? 'none' : 'block';
    summaryEl.style.display = hasItems ? 'block' : 'none';
    if (nextBtn) nextBtn.disabled = !hasItems;

    if (!hasItems) { itemsEl.innerHTML = ''; return; }

    itemsEl.innerHTML = selectedItems.map(({ product: p, qty }) => `
      <div class="op-item">
        <div class="op-thumb" style="overflow:hidden;">${p.img
          ? '<img src="' + p.img + '" alt="' + API.esc(p.name) + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">'
          : '<div style="background:' + p.bg + ';width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-family:var(--font-display);font-size:18px;color:rgba(255,255,255,0.7);font-weight:700;">' + p.name.charAt(0) + '</div>'}</div>
        <div class="op-info">
          <div class="op-name">${API.esc(p.name)}</div>
          <div class="op-meta">${API.esc(p.cond)} · Seller: ${API.esc(p.seller)}</div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
            <div class="cart-qty-ctrl" style="border-color:var(--ink-12);">
              <button class="qty-btn" onclick="State.updateQty(${p.id}, -1).then(() => Checkout.renderCartReview());">−</button>
              <span class="qty-num">${qty}</span>
              <button class="qty-btn" onclick="State.updateQty(${p.id}, 1).then(() => Checkout.renderCartReview());">+</button>
            </div>
            <button onclick="State.removeFromCart(${p.id}).then(() => Checkout.renderCartReview());" style="background:none;border:none;cursor:pointer;color:var(--coral);font-size:12px;font-weight:600;font-family:var(--font-body);">Remove</button>
          </div>
          <div class="op-price" style="margin-top:6px;">₱${(p.price * qty).toLocaleString()}</div>
        </div>
      </div>`).join('');

    this.renderCartReviewSummary();
  },

  renderCartReviewSummary() {
    const summaryEl = document.getElementById('wiz-cart-summary');
    if (!summaryEl) return;

    const selectedItems = State.getSelectedItems();
    const sub = State.getSelectedTotal();
    const multiDisc = selectedItems.length > 1 ? Math.round(sub * 0.03) : 0;
    const promoDisc = this.appliedPromo ? this.appliedPromo.discount : 0;
    const totalDisc = multiDisc + promoDisc;
    const ship = 280;
    const total = sub - totalDisc + ship;

    let promoHTML = '';
    if (this.appliedPromo) {
      const p = this.appliedPromo;
      const label = p.discount_type === 'percent' ? p.code + ' (' + p.discount_value + '%)' : p.code;
      promoHTML = `<div class="op-row disc"><span>Promo: ${API.esc(label)}</span><span class="val">−₱${promoDisc.toLocaleString()}</span></div>`;
    }

    summaryEl.innerHTML = `
      <div class="op-row"><span>Subtotal</span><span class="val">₱${sub.toLocaleString()}</span></div>
      ${multiDisc > 0 ? '<div class="op-row disc"><span>Multi-item discount (3%)</span><span class="val">−₱' + multiDisc.toLocaleString() + '</span></div>' : ''}
      ${promoHTML}
      <div class="op-row"><span>Shipping (J&T)</span><span class="val">₱${ship.toLocaleString()}</span></div>
      <div class="op-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>`;
  },

  renderConfirm() {
    const itemsEl = document.getElementById('wiz-confirm-items');
    const shippingEl = document.getElementById('wiz-confirm-shipping');
    const paymentEl = document.getElementById('wiz-confirm-payment');
    const totalsEl = document.getElementById('wiz-confirm-totals');

    const selectedItems = State.getSelectedItems();

    // Items summary
    itemsEl.innerHTML = '<div style="font-weight:600;margin-bottom:12px;">Items (' + State.getSelectedCount() + ')</div>' +
      selectedItems.map(({ product: p, qty }) => `
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--ink-06);">
          <div class="op-thumb" style="width:40px;height:40px;overflow:hidden;">${p.img
            ? '<img src="' + p.img + '" alt="' + API.esc(p.name) + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">'
            : '<div style="background:' + p.bg + ';width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-size:14px;font-weight:700;color:rgba(255,255,255,0.7);">' + p.name.charAt(0) + '</div>'}</div>
          <div style="flex:1;font-size:13px;">${API.esc(p.name)} <span style="color:var(--text-muted);">× ${qty}</span></div>
          <div style="font-weight:600;font-size:13px;">₱${(p.price * qty).toLocaleString()}</div>
        </div>`).join('');

    // Shipping summary
    const s = this.shippingData;
    shippingEl.innerHTML = `
      <div style="font-weight:600;margin-bottom:8px;">Shipping To</div>
      <div style="font-size:13px;color:var(--text-secondary);line-height:1.7;">
        ${API.esc(s.name)}<br>${API.esc(s.address)}<br>${API.esc(s.city)}, ${API.esc(s.zip)}<br>${API.esc(s.phone)}<br>${API.esc(s.email)}
      </div>`;

    // Payment summary
    const payLabels = { card: 'Credit / Debit Card', gcash: 'GCash', bank: 'Bank Transfer', cod: 'Cash on Delivery' };
    paymentEl.innerHTML = `
      <div style="font-weight:600;margin-bottom:8px;">Payment Method</div>
      <div style="font-size:13px;color:var(--text-secondary);">${payLabels[this.selectedPayment] || this.selectedPayment}</div>`;

    // Totals
    const sub = State.getSelectedTotal();
    const multiDisc = selectedItems.length > 1 ? Math.round(sub * 0.03) : 0;
    const promoDisc = this.appliedPromo ? this.appliedPromo.discount : 0;
    const totalDisc = multiDisc + promoDisc;
    const ship = 280;
    const total = sub - totalDisc + ship;

    let promoHTML = '';
    if (this.appliedPromo) {
      const p = this.appliedPromo;
      const label = p.discount_type === 'percent' ? p.code + ' (' + p.discount_value + '%)' : p.code;
      promoHTML = `<div class="op-row disc"><span>Promo: ${API.esc(label)}</span><span class="val">−₱${promoDisc.toLocaleString()}</span></div>`;
    }

    totalsEl.innerHTML = `
      <div class="op-row"><span>Subtotal</span><span class="val">₱${sub.toLocaleString()}</span></div>
      ${multiDisc > 0 ? '<div class="op-row disc"><span>Multi-item discount (3%)</span><span class="val">−₱' + multiDisc.toLocaleString() + '</span></div>' : ''}
      ${promoHTML}
      <div class="op-row"><span>Shipping (J&T)</span><span class="val">₱${ship.toLocaleString()}</span></div>
      <div class="op-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>`;
  },

  selectPayment(label) {
    document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
    label.classList.add('selected');
    const radio = label.querySelector('input[type=radio]');
    if (radio) { radio.checked = true; this.selectedPayment = radio.value; }

    const card = document.getElementById('pay-card-fields');
    const gcash = document.getElementById('pay-gcash-fields');
    const bank = document.getElementById('pay-bank-fields');
    if (card) card.style.display = this.selectedPayment === 'card' ? 'flex' : 'none';
    if (gcash) gcash.style.display = this.selectedPayment === 'gcash' ? 'block' : 'none';
    if (bank) bank.style.display = this.selectedPayment === 'bank' ? 'block' : 'none';
  },

  formatCard(input) {
    input.value = input.value.replace(/\D/g, '').substring(0, 16).replace(/(.{4})/g, '$1 ').trim();
  },

  formatExpiry(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + ' / ' + v.substring(2);
    input.value = v;
  },

  async placeOrder() {
    const selectedItems = State.getSelectedItems();
    if (selectedItems.length === 0) { Toast.show('No items selected!', 'i'); return; }

    const s = this.shippingData;
    const orderBtn = document.querySelector('#checkout-step-4 .btn-primary');
    if (orderBtn) { orderBtn.disabled = true; orderBtn.textContent = 'Placing order...'; }

    const res = await API.post('/checkout/place-order.php', {
      shipping_name: s.name,
      shipping_email: s.email,
      shipping_phone: s.phone,
      shipping_address: s.address,
      shipping_city: s.city,
      shipping_zip: s.zip,
      payment_method: this.selectedPayment,
      promo_code: this.appliedPromo ? this.appliedPromo.code : null,
    });

    if (!res.success) {
      Toast.show(res.error || 'Failed to place order', 'i');
      if (orderBtn) { orderBtn.disabled = false; orderBtn.textContent = '🔒 Place Order'; }
      return;
    }

    // Clear local cart of selected items
    State.cart = State.cart.filter(i => !i.selected);
    Cart.updateBadge();

    // Show success
    document.getElementById('success-order-num').textContent = res.data.order_number;
    const emailNote = document.querySelector('.success-email-note');
    if (emailNote) emailNote.textContent = 'Confirmation sent to ' + s.email;

    // Reset promo
    this.appliedPromo = null;

    // Re-enable button for next time
    if (orderBtn) { orderBtn.disabled = false; orderBtn.textContent = '🔒 Place Order'; }

    Nav.goTab('success');

    // Update notification badge
    State.notifCount++;
    if (typeof Notifications !== 'undefined') Notifications.updateBadge();

    Toast.show('Order placed successfully!', '✓');
  }
};
