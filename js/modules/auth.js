/* ═══════════════════════════════════════════
   auth.js — Login / Signup / Logout
   Uses PHP API (api.js) for authentication.
   ═══════════════════════════════════════════ */
const Auth = {

  async init() {
    const token = API.getToken();
    if (!token) return;

    // Validate existing token with server
    const res = await API.get('/auth/session.php');
    if (res.success) {
      this.enterApp(res.data.user);
    } else {
      // Token expired or invalid — clear it
      API.clearToken();
      Storage.clearSession();
    }
  },

  async login() {
    // Clear previous errors
    document.querySelectorAll('#form-signin .form-error').forEach(e => e.remove());
    document.querySelectorAll('#form-signin .form-input.error').forEach(e => e.classList.remove('error'));

    const email = document.getElementById('signin-email').value.trim();
    const password = document.getElementById('signin-password').value.trim();

    let valid = true;
    if (!email) { this.showError('signin-email', 'Email is required'); valid = false; }
    if (!password) { this.showError('signin-password', 'Password is required'); valid = false; }
    if (!valid) return;

    const res = await API.post('/auth/login.php', { email, password });
    if (!res.success) {
      this.showError('signin-password', res.error || 'Invalid email or password');
      return;
    }

    API.setToken(res.data.token);
    Storage.saveSession(res.data.user);
    Toast.show(`Welcome back, ${res.data.user.first_name}!`, '✓');
    setTimeout(() => this.enterApp(res.data.user), 400);
  },

  async signup() {
    // Clear previous errors
    document.querySelectorAll('#form-signup .form-error').forEach(e => e.remove());
    document.querySelectorAll('#form-signup .form-input.error').forEach(e => e.classList.remove('error'));

    const fname = document.getElementById('signup-fname').value.trim();
    const lname = document.getElementById('signup-lname').value.trim();
    const email = document.getElementById('signup-email').value.trim();
    const password = document.getElementById('signup-password').value.trim();

    let valid = true;
    if (!fname) { this.showError('signup-fname', 'First name is required'); valid = false; }
    if (!lname) { this.showError('signup-lname', 'Last name is required'); valid = false; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { this.showError('signup-email', 'Valid email is required'); valid = false; }
    if (!password || password.length < 8) { this.showError('signup-password', 'Password must be at least 8 characters'); valid = false; }
    if (!valid) return;

    const res = await API.post('/auth/register.php', {
      first_name: fname,
      last_name: lname,
      email,
      password
    });

    if (!res.success) {
      this.showError('signup-email', res.error || 'Registration failed');
      return;
    }

    API.setToken(res.data.token);
    Storage.saveSession(res.data.user);
    Toast.show(`Welcome to HobbyLoop, ${fname}!`, '✓');
    setTimeout(() => this.enterApp(res.data.user), 400);
  },

  async enterApp(user) {
    Nav.goPage('app');
    Nav.goTab('dashboard');
    Dashboard.render();

    // Update sidebar user card
    const initials = user.avatar_initials || (user.name || '').split(' ').map(p => p[0]).join('').toUpperCase();
    const displayName = user.name || (user.first_name + ' ' + user.last_name);
    const ava = document.querySelector('.user-ava');
    const nameEl = document.querySelector('.user-name');
    if (ava) ava.textContent = initials;
    if (nameEl) nameEl.textContent = displayName;

    // Update hero greeting
    const firstName = user.first_name || displayName.split(' ')[0];
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const heroH2 = document.querySelector('.hero-text h2');
    if (heroH2) heroH2.textContent = greeting + ', ' + firstName;

    // Load cart and orders from server
    await Promise.all([State.loadCart(), State.loadOrders()]);
  },

  async logout() {
    await API.post('/auth/logout.php');
    API.clearToken();
    Storage.clearSession();
    State.cart = [];
    State.orders = [];
    Cart.updateBadge();
    Toast.show('Signed out. See you soon!', '✓');
    setTimeout(() => Nav.goPage('login'), 500);
  },

  switchTab(tab, btn) {
    document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('form-signin').style.display = tab === 'signin' ? 'block' : 'none';
    document.getElementById('form-signup').style.display = tab === 'signup' ? 'block' : 'none';
    const greet = document.querySelector('.login-greet');
    const sub = document.querySelector('.login-greet-sub');
    if (tab === 'signin') { greet.textContent = 'Welcome back'; sub.textContent = 'Sign in to explore thousands of hobby listings.'; }
    else { greet.textContent = 'Join HobbyLoop'; sub.textContent = 'Create your account and start buying or selling today.'; }
    // Clear errors when switching tabs
    document.querySelectorAll('.form-error').forEach(e => e.remove());
    document.querySelectorAll('.form-input.error').forEach(e => e.classList.remove('error'));
  },

  showError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('error');
    const err = document.createElement('div');
    err.className = 'form-error';
    err.textContent = message;
    input.closest('.form-group').appendChild(err);
  }
};
