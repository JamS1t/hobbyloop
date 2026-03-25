/* ═══════════════════════════════════════════
   cart.js — Cart Drawer (API-backed)
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

    // Select All checkbox
    const allSelected = items.every(i => i.selected);
    const noneSelected = items.every(i => !i.selected);
    const selectAllChecked = allSelected ? 'checked' : '';

    let html = `
      <div class="cart-select-all">
        <label>
          <input type="checkbox" class="cart-item-check" ${selectAllChecked} onchange="State.selectAllCart(this.checked)">
          <span>Select All (${items.length})</span>
        </label>
      </div>`;

    html += items.map(({ product: p, qty, selected }) => `
      <div class="cart-item">
        <input type="checkbox" class="cart-item-check" ${selected ? 'checked' : ''}
          onchange="event.stopPropagation(); State.toggleCartItemSelected(${p.id})"
          onclick="event.stopPropagation()">
        <div class="cart-item-thumb" style="overflow:hidden;">${p.img
          ? `<img src="${p.img}" alt="${API.esc(p.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
          : `<div style="background:${p.bg};width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-family:var(--font-display);font-size:20px;color:rgba(255,255,255,0.7);font-weight:700;">${p.name.charAt(0)}</div>`}</div>
        <div class="cart-item-info">
          <div class="cart-item-cat">${API.esc(p.cat_label || p.cat)}</div>
          <div class="cart-item-name">${API.esc(p.name)}</div>
          <div class="cart-item-cond">Condition: ${API.esc(p.cond)}</div>
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

    itemsEl.innerHTML = html;

    // Summary — only selected items
    const selectedItems = State.getSelectedItems();
    const sub  = selectedItems.reduce((sum, i) => sum + i.product.price * i.qty, 0);
    const disc = selectedItems.length > 1 ? Math.round(sub * 0.03) : 0;
    const ship = selectedItems.length > 0 ? 280 : 0;
    const total = sub - disc + ship;

    document.getElementById('cart-sub').textContent  = '₱' + sub.toLocaleString();
    document.getElementById('cart-disc').textContent = disc > 0 ? '−₱' + disc.toLocaleString() : '₱0';
    document.getElementById('cart-ship').textContent = '₱' + ship.toLocaleString();
    document.getElementById('cart-total').textContent = '₱' + total.toLocaleString();

    const discRow = document.getElementById('cart-disc-row');
    if (discRow) discRow.style.display = disc > 0 ? 'flex' : 'none';

    // Update select all indeterminate state
    const selectAllCb = itemsEl.querySelector('.cart-select-all input');
    if (selectAllCb) {
      selectAllCb.indeterminate = !allSelected && !noneSelected;
    }
  },

  goCheckout() {
    if (State.cart.length === 0) { Toast.show('Your cart is empty', 'i'); return; }
    if (State.getSelectedItems().length === 0) { Toast.show('Select at least one item to checkout', 'i'); return; }
    this.close();
    Checkout.open();
  }
};
