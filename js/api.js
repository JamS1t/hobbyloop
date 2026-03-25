/* ═══════════════════════════════════════════
   api.js — Fetch wrapper for HobbyLoop PHP API
   ═══════════════════════════════════════════ */

const API = {

  // Base URL — points to XAMPP Apache (PHP backend)
  // Access the app via http://localhost/hobbyloop/ for best results
  BASE: 'http://localhost/hobbyloop/api',

  // ── Token management ──

  getToken() {
    return localStorage.getItem('hl_token');
  },

  setToken(token) {
    localStorage.setItem('hl_token', token);
  },

  clearToken() {
    localStorage.removeItem('hl_token');
  },

  // ── Core request method ──

  async request(method, path, body = null) {
    const url = this.BASE + path;
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };

    const token = this.getToken();
    if (token) {
      opts.headers['Authorization'] = 'Bearer ' + token;
    }

    if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
      opts.body = JSON.stringify(body);
    }

    try {
      const res = await fetch(url, opts);
      const json = await res.json();

      if (!json.success) {
        return { success: false, error: json.error || 'Unknown error', status: res.status };
      }

      return { success: true, data: json.data, status: res.status };
    } catch (err) {
      return { success: false, error: 'Network error — please check your connection', status: 0 };
    }
  },

  // ── Convenience methods ──

  get(path)           { return this.request('GET', path); },
  post(path, body)    { return this.request('POST', path, body); },
  put(path, body)     { return this.request('PUT', path, body); },
  del(path, body)     { return this.request('DELETE', path, body); },

  // ── HTML escape for safe innerHTML insertion ──
  esc(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  },
};
