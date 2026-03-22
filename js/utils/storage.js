/* ═══════════════════════════════════════════
   storage.js — localStorage Helpers
   ═══════════════════════════════════════════ */
const Storage = {
  // ── Users ──
  getUsers()        { return JSON.parse(localStorage.getItem('hl_users') || '[]'); },
  saveUsers(users)  { localStorage.setItem('hl_users', JSON.stringify(users)); },

  // ── Session ──
  getSession()      { return JSON.parse(localStorage.getItem('hl_session') || 'null'); },
  saveSession(user) { localStorage.setItem('hl_session', JSON.stringify(user)); },
  clearSession()    { localStorage.removeItem('hl_session'); },

  // ── Orders ──
  getOrders()         { return JSON.parse(localStorage.getItem('hl_orders') || '[]'); },
  saveOrders(orders)  { localStorage.setItem('hl_orders', JSON.stringify(orders)); },

  // ── Cart ──
  getCart()          { return JSON.parse(localStorage.getItem('hl_cart') || '[]'); },
  saveCart(cart)     { localStorage.setItem('hl_cart', JSON.stringify(cart)); },

  // ── Demo Seed ──
  seedDemoUser() {
    const users = this.getUsers();
    if (!users.find(u => u.email === 'alex.kim@hobbyloop.ph')) {
      users.push({ name: 'Alex Kim', email: 'alex.kim@hobbyloop.ph', password: 'password123' });
      this.saveUsers(users);
    }
  }
};
