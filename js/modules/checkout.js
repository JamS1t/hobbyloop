/* ═══════════════════════════════════════════
   checkout.js — 4-Step Checkout Wizard
   ═══════════════════════════════════════════ */
const Checkout = {
  currentStep: 1,
  selectedPayment: 'card',
  shippingData: {},

  open() {
    this.currentStep = 1;
    Nav.goTab('checkout');
    Nav.setNavActive('checkout');
    this.goStep(1);
  },

  openProduct(product) {
    if (!product) return;
    if (!State.inCart(product.id)) {
      State.addToCart(product);
      Toast.show(product.name + ' added to cart!', '✓');
    }
    this.open();
  },

  goStep(step) {
    // Validate before advancing
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
      if (State.cart.length === 0) { Toast.show('Your cart is empty!', 'ℹ️'); return false; }
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

  renderCartReview() {
    const itemsEl = document.getElementById('wiz-cart-items');
    const emptyEl = document.getElementById('wiz-cart-empty');
    const summaryEl = document.getElementById('wiz-cart-summary');
    const nextBtn = document.getElementById('wiz-next-1');
    if (!itemsEl) return;

    const hasItems = State.cart.length > 0;
    emptyEl.style.display = hasItems ? 'none' : 'block';
    summaryEl.style.display = hasItems ? 'block' : 'none';
    if (nextBtn) nextBtn.disabled = !hasItems;

    if (!hasItems) { itemsEl.innerHTML = ''; return; }

    itemsEl.innerHTML = State.cart.map(({ product: p, qty }) => `
      <div class="op-item">
        <div class="op-thumb" style="overflow:hidden;">${p.img
          ? '<img src="' + p.img + '" alt="' + p.emoji + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">'
          : '<div style="background:' + p.bg + ';width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;">' + p.emoji + '</div>'}</div>
        <div class="op-info">
          <div class="op-name">${p.name}</div>
          <div class="op-meta">${p.cond} · Seller: ${p.seller}</div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
            <div class="cart-qty-ctrl" style="border-color:var(--ink-12);">
              <button class="qty-btn" onclick="State.updateQty(${p.id}, -1); Checkout.renderCartReview();">−</button>
              <span class="qty-num">${qty}</span>
              <button class="qty-btn" onclick="State.updateQty(${p.id}, 1); Checkout.renderCartReview();">+</button>
            </div>
            <button onclick="State.removeFromCart(${p.id}); Checkout.renderCartReview();" style="background:none;border:none;cursor:pointer;color:var(--coral);font-size:12px;font-weight:600;font-family:var(--font-body);">Remove</button>
          </div>
          <div class="op-price" style="margin-top:6px;">₱${(p.price * qty).toLocaleString()}</div>
        </div>
      </div>`).join('');

    // Summary
    const sub = State.getCartTotal();
    const disc = State.cart.length > 1 ? Math.round(sub * 0.03) : 0;
    const ship = 280;
    const total = sub - disc + ship;

    summaryEl.innerHTML = `
      <div class="op-row"><span>Subtotal</span><span class="val">₱${sub.toLocaleString()}</span></div>
      ${disc > 0 ? '<div class="op-row disc"><span>🏷️ Multi-item discount (3%)</span><span class="val">−₱' + disc.toLocaleString() + '</span></div>' : ''}
      <div class="op-row"><span>Shipping (J&T)</span><span class="val">₱${ship.toLocaleString()}</span></div>
      <div class="op-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>`;
  },

  renderConfirm() {
    const itemsEl = document.getElementById('wiz-confirm-items');
    const shippingEl = document.getElementById('wiz-confirm-shipping');
    const paymentEl = document.getElementById('wiz-confirm-payment');
    const totalsEl = document.getElementById('wiz-confirm-totals');

    // Items summary
    itemsEl.innerHTML = '<div style="font-weight:600;margin-bottom:12px;">Items (' + State.getCartCount() + ')</div>' +
      State.cart.map(({ product: p, qty }) => `
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--ink-06);">
          <div class="op-thumb" style="width:40px;height:40px;overflow:hidden;">${p.img
            ? '<img src="' + p.img + '" alt="' + p.emoji + '" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">'
            : '<div style="background:' + p.bg + ';width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-size:16px;">' + p.emoji + '</div>'}</div>
          <div style="flex:1;font-size:13px;">${p.name} <span style="color:var(--text-muted);">× ${qty}</span></div>
          <div style="font-weight:600;font-size:13px;">₱${(p.price * qty).toLocaleString()}</div>
        </div>`).join('');

    // Shipping summary
    const s = this.shippingData;
    shippingEl.innerHTML = `
      <div style="font-weight:600;margin-bottom:8px;">📦 Shipping To</div>
      <div style="font-size:13px;color:var(--text-secondary);line-height:1.7;">
        ${s.name}<br>${s.address}<br>${s.city}, ${s.zip}<br>${s.phone}<br>${s.email}
      </div>`;

    // Payment summary
    const payLabels = { card: '💳 Credit / Debit Card', gcash: '📱 GCash', bank: '🏦 Bank Transfer', cod: '🚚 Cash on Delivery' };
    paymentEl.innerHTML = `
      <div style="font-weight:600;margin-bottom:8px;">💳 Payment Method</div>
      <div style="font-size:13px;color:var(--text-secondary);">${payLabels[this.selectedPayment] || this.selectedPayment}</div>`;

    // Totals
    const sub = State.getCartTotal();
    const disc = State.cart.length > 1 ? Math.round(sub * 0.03) : 0;
    const ship = 280;
    const total = sub - disc + ship;

    totalsEl.innerHTML = `
      <div class="op-row"><span>Subtotal</span><span class="val">₱${sub.toLocaleString()}</span></div>
      ${disc > 0 ? '<div class="op-row disc"><span>🏷️ Multi-item discount (3%)</span><span class="val">−₱' + disc.toLocaleString() + '</span></div>' : ''}
      <div class="op-row"><span>Shipping (J&T)</span><span class="val">₱${ship.toLocaleString()}</span></div>
      <div class="op-row total"><span>Total</span><span>₱${total.toLocaleString()}</span></div>`;
  },

  selectPayment(label) {
    document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
    label.classList.add('selected');
    const radio = label.querySelector('input[type=radio]');
    if (radio) { radio.checked = true; this.selectedPayment = radio.value; }

    // Show/hide payment-specific fields
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

  placeOrder() {
    if (State.cart.length === 0) { Toast.show('Cart is empty!', 'ℹ️'); return; }

    const orderNum = '#HL-' + new Date().getFullYear() + '-' + String(Math.floor(Math.random() * 90000) + 10000);
    const order = {
      id: orderNum,
      items: State.cart.map(i => ({ productId: i.product.id, qty: i.qty, name: i.product.name, price: i.product.price, emoji: i.product.emoji, img: i.product.img, bg: i.product.bg })),
      total: State.getCartTotal() - (State.cart.length > 1 ? Math.round(State.getCartTotal() * 0.03) : 0) + 280,
      date: new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }),
      status: 'Processing',
      shipping: this.shippingData,
      payment: this.selectedPayment,
    };

    State.orders.unshift(order);
    Storage.saveOrders(State.orders);

    // Clear cart
    State.cart = [];
    Storage.saveCart([]);
    Cart.updateBadge();

    // Show success
    document.getElementById('success-order-num').textContent = orderNum;

    // Update success email
    const session = Storage.getSession();
    const emailNote = document.querySelector('.success-email-note');
    if (emailNote && session) emailNote.textContent = '📧 Confirmation sent to ' + session.email;

    Nav.goTab('success');

    // Add notification
    DB.notifications.unshift({
      id: Date.now(),
      icon: '🎉',
      text: '<strong>Order ' + orderNum + ' confirmed!</strong> Your items are being prepared for shipment.',
      time: 'Just now',
      unread: true
    });
    State.notifCount++;
    Notifications.updateBadge();
  }
};
