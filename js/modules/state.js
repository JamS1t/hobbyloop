/* ═══════════════════════════════════════════
   state.js — App State + Navigation
   ═══════════════════════════════════════════ */

/* ── App State ── */
const State = {
  currentPage: 'login',
  currentTab: 'dashboard',
  cart: [],          // { product, qty }
  orders: [],        // completed orders
  activeCategory: 'all',
  searchQuery: '',
  notifCount: 4,     // unread count
  following: new Set(),

  getCartTotal() {
    return this.cart.reduce((sum, i) => sum + i.product.price * i.qty, 0);
  },
  getCartCount() {
    return this.cart.reduce((sum, i) => sum + i.qty, 0);
  },
  addToCart(product) {
    const existing = this.cart.find(i => i.product.id === product.id);
    if (existing) {
      existing.qty++;
    } else {
      this.cart.push({ product, qty: 1 });
    }
    Cart.render();
    Cart.updateBadge();
    Storage.saveCart(this.cart.map(i => ({ productId: i.product.id, qty: i.qty })));
  },
  removeFromCart(productId) {
    this.cart = this.cart.filter(i => i.product.id !== productId);
    Cart.render();
    Cart.updateBadge();
    Storage.saveCart(this.cart.map(i => ({ productId: i.product.id, qty: i.qty })));
  },
  updateQty(productId, delta) {
    const item = this.cart.find(i => i.product.id === productId);
    if (!item) return;
    item.qty = Math.max(1, item.qty + delta);
    Cart.render();
    Cart.updateBadge();
    Storage.saveCart(this.cart.map(i => ({ productId: i.product.id, qty: i.qty })));
  },
  inCart(productId) {
    return this.cart.some(i => i.product.id === productId);
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
