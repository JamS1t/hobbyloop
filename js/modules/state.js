/* ═══════════════════════════════════════════
   state.js — App State + Navigation (API)
   ═══════════════════════════════════════════ */

/* ── App State ── */
const State = {
  currentPage: 'login',
  currentTab: 'dashboard',
  cart: [],          // { product, qty, selected }
  orders: [],        // completed orders
  activeCategory: 'all',
  searchQuery: '',
  notifCount: 0,     // unread count (loaded from server)
  currentUser: null, // set on login — { id, first_name, last_name, avatar_initials, avatar_color, ... }

  // ── Cart computed helpers (work on local cache) ──

  getCartTotal() {
    return this.cart.reduce((sum, i) => sum + i.product.price * i.qty, 0);
  },
  getCartCount() {
    return this.cart.reduce((sum, i) => sum + i.qty, 0);
  },
  getSelectedItems() {
    return this.cart.filter(i => i.selected);
  },
  getSelectedTotal() {
    return this.getSelectedItems().reduce((sum, i) => sum + i.product.price * i.qty, 0);
  },
  getSelectedCount() {
    return this.getSelectedItems().reduce((sum, i) => sum + i.qty, 0);
  },

  // ── Load cart from server ──

  async loadCart() {
    const res = await API.get('/cart/get.php');
    if (res.success) {
      this.cart = res.data;
    }
    Cart.render();
    Cart.updateBadge();
  },

  // ── Cart actions (async, server-backed) ──

  async addToCart(product) {
    const res = await API.post('/cart/add.php', { product_id: product.id });
    if (!res.success) {
      Toast.show(res.error || 'Failed to add to cart', 'i');
      return;
    }
    // Update local cache
    const existing = this.cart.find(i => i.product.id === product.id);
    if (existing) {
      existing.qty++;
    } else {
      this.cart.push({ product, qty: 1, selected: true });
    }
    Cart.render();
    Cart.updateBadge();
  },

  async removeFromCart(productId) {
    const res = await API.del('/cart/remove.php', { product_id: productId });
    if (!res.success) {
      Toast.show(res.error || 'Failed to remove item', 'i');
      return;
    }
    this.cart = this.cart.filter(i => i.product.id !== productId);
    Cart.render();
    Cart.updateBadge();
  },

  async updateQty(productId, delta) {
    const item = this.cart.find(i => i.product.id === productId);
    if (!item) return;
    const newQty = Math.max(1, item.qty + delta);
    const res = await API.put('/cart/update.php', { product_id: productId, qty: newQty });
    if (!res.success) {
      Toast.show(res.error || 'Failed to update quantity', 'i');
      return;
    }
    item.qty = newQty;
    Cart.render();
    Cart.updateBadge();
  },

  inCart(productId) {
    return this.cart.some(i => i.product.id === productId);
  },

  async toggleCartItemSelected(productId) {
    const item = this.cart.find(i => i.product.id === productId);
    if (!item) return;
    const newSelected = !item.selected;
    const res = await API.put('/cart/update.php', { product_id: productId, is_selected: newSelected });
    if (!res.success) {
      Toast.show(res.error || 'Failed to update selection', 'i');
      return;
    }
    item.selected = newSelected;
    Cart.render();
  },

  async selectAllCart(checked) {
    const res = await API.put('/cart/update.php', { select_all: checked });
    if (!res.success) {
      Toast.show(res.error || 'Failed to update selection', 'i');
      return;
    }
    this.cart.forEach(i => i.selected = checked);
    Cart.render();
  },

  // ── Orders ──

  async loadOrders() {
    const res = await API.get('/orders/list.php');
    if (res.success) {
      this.orders = res.data;
    }
  }
};

/* ── Navigation ── */
const Nav = {
  goPage(page) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const el = document.getElementById('page-' + page);
    if (el) el.classList.add('active');
    State.currentPage = page;
    window.scrollTo(0, 0);

    // Update sidebar active state
    document.querySelectorAll('.nav-link').forEach(l => {
      l.classList.toggle('active', l.dataset.page === page || l.dataset.tab === page);
    });
  },

  goTab(tab) {
    State.currentTab = tab;
    // Show/hide tab content panels
    document.querySelectorAll('.tab-panel').forEach(p => {
      p.style.display = p.dataset.tab === tab ? 'block' : 'none';
    });
    // Update sidebar
    document.querySelectorAll('.nav-link').forEach(l => {
      l.classList.toggle('active', l.dataset.tab === tab);
    });
    window.scrollTo(0, 0);
  },

  setNavActive(tab) {
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const active = document.querySelector(`.nav-link[data-tab="${tab}"]`);
    if (active) active.classList.add('active');
  }
};
