/* ═══════════════════════════════════════════
   storage.js — localStorage Helpers
   Token + session storage only. Cart/orders
   are now server-side (Phase 3).
   ═══════════════════════════════════════════ */
const Storage = {
  // ── Auth Token ──
  getToken()        { return localStorage.getItem('hl_token'); },
  setToken(token)   { localStorage.setItem('hl_token', token); },
  clearToken()      { localStorage.removeItem('hl_token'); },

  // ── Session (local cache of user info for UI) ──
  getSession()      { return JSON.parse(localStorage.getItem('hl_session') || 'null'); },
  saveSession(user) { localStorage.setItem('hl_session', JSON.stringify(user)); },
  clearSession()    {
    localStorage.removeItem('hl_session');
    this.clearToken();
  },
};
