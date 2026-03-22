/* ═══════════════════════════════════════════
   cart.js — Cart Drawer (Fully Functional)
   ═══════════════════════════════════════════ */
const Cart = {
  open: false,

  toggle() {
    this.open = !this.open;
    document.getElementById('cart-overlay').classList.toggle('open', this.open);
    document.getElementById('cart-drawer').classList.toggle('open', this.open);
    if (this.open) this.render();
  },

  close() {
    this.open = false;
    document.getElementById('cart-overlay').classList.remove('open');
    document.getElementById('cart-drawer').classList.remove('open');
  },

  updateBadge() {
    const badge = document.getElementById('cart-badge');
    const count = State.getCartCount();
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
    // Update cart button in topbar
    const btn = document.getElementById('cart-count-lbl');
    if (btn) btn.textContent = count > 0 ? count : '';
  },

  render() {
    const itemsEl = document.getElementById('cart-items');
    const emptyEl = document.getElementById('cart-empty');
    const ftEl    = document.getElementById('cart-footer');
    if (!itemsEl) return;

    const items = State.cart;
    const hasItems = items.length > 0;

    emptyEl.style.display  = hasItems ? 'none' : 'flex';
    ftEl.style.display     = hasItems ? 'block' : 'none';
    itemsEl.style.display  = hasItems ? 'block' : 'none';

    const hd = document.getElementById('cart-hd-count');
    if (hd) hd.textContent = `${State.getCartCount()} item${State.getCartCount() !== 1 ? 's' : ''}`;

    if (!hasItems) return;

    itemsEl.innerHTML = items.map(({ product: p, qty }) => `
      <div class="cart-item">
        <div class="cart-item-thumb" style="overflow:hidden;">${p.img
          ? `<img src="${p.img}" alt="${p.emoji}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
          : `<div style="background:${p.bg};width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;">${p.emoji}</div>`}</div>
        <div class="cart-item-info">
          <div class="cart-item-cat">${DB.categories.find(c => c.id === p.cat)?.label || p.cat}</div>
          <div class="cart-item-name">${p.name}</div>
          <div class="cart-item-cond">Condition: ${p.cond}</div>
          <div class="cart-item-row">
            <div class="cart-qty-ctrl">
              <button class="qty-btn" onclick="State.updateQty(${p.id}, -1)">−</button>
              <span class="qty-num">${qty}</span>
              <button class="qty-btn" onclick="State.updateQty(${p.id}, 1)">+</button>
            </div>
            <span class="cart-item-price">₱${(p.price * qty).toLocaleString()}</span>
            <button class="cart-remove" onclick="State.removeFromCart(${p.id})" title="Remove">🗑</button>
          </div>
        </div>
      </div>`).join('');

    // Summary
    const sub  = State.getCartTotal();
    const disc = State.cart.length > 1 ? Math.round(sub * 0.03) : 0;
    const ship = 280;
    const total = sub - disc + ship;

    document.getElementById('cart-sub').textContent  = '₱' + sub.toLocaleString();
    document.getElementById('cart-disc').textContent = disc > 0 ? '−₱' + disc.toLocaleString() : '₱0';
    document.getElementById('cart-ship').textContent = '₱' + ship.toLocaleString();
    document.getElementById('cart-total').textContent = '₱' + total.toLocaleString();

    const discRow = document.getElementById('cart-disc-row');
    if (discRow) discRow.style.display = disc > 0 ? 'flex' : 'none';
  },

  goCheckout() {
    if (State.cart.length === 0) { Toast.show('Your cart is empty', 'ℹ️'); return; }
    this.close();
    Checkout.open();
  }
};
