/* ═══════════════════════════════════════════
   auth.js — Login / Signup / Logout
   ═══════════════════════════════════════════ */
const Auth = {
  init() {
    Storage.seedDemoUser();
    const session = Storage.getSession();
    if (session) {
      this.enterApp(session);
    }
  },

  login() {
    // Clear previous errors
    document.querySelectorAll('#form-signin .form-error').forEach(e => e.remove());
    document.querySelectorAll('#form-signin .form-input.error').forEach(e => e.classList.remove('error'));

    const email = document.getElementById('signin-email').value.trim();
    const password = document.getElementById('signin-password').value.trim();

    let valid = true;
    if (!email) { this.showError('signin-email', 'Email is required'); valid = false; }
    if (!password) { this.showError('signin-password', 'Password is required'); valid = false; }
    if (!valid) return;

    const users = Storage.getUsers();
    const user = users.find(u => u.email === email && u.password === password);
    if (!user) {
      this.showError('signin-password', 'Invalid email or password');
      return;
    }

    Storage.saveSession(user);
    Toast.show(`Welcome back, ${user.name.split(' ')[0]}!`, '✓');
    setTimeout(() => this.enterApp(user), 400);
  },

  signup() {
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

    const users = Storage.getUsers();
    if (users.find(u => u.email === email)) {
      this.showError('signup-email', 'This email is already registered');
      return;
    }

    const user = { name: fname + ' ' + lname, email, password };
    users.push(user);
    Storage.saveUsers(users);
    Storage.saveSession(user);
    Toast.show(`Welcome to HobbyLoop, ${fname}!`, '🎉');
    setTimeout(() => this.enterApp(user), 400);
  },

  enterApp(user) {
    Nav.goPage('app');
    Nav.goTab('dashboard');
    Dashboard.render();

    // Update sidebar user card
    const parts = user.name.split(' ');
    const initials = parts.map(p => p[0]).join('').toUpperCase();
    const ava = document.querySelector('.user-ava');
    const name = document.querySelector('.user-name');
    if (ava) ava.textContent = initials;
    if (name) name.textContent = user.name;

    // Update hero greeting
    const firstName = parts[0];
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const heroH2 = document.querySelector('.hero-text h2');
    if (heroH2) heroH2.textContent = greeting + ', ' + firstName + ' ☀️';

    // Load persisted orders
    const savedOrders = Storage.getOrders();
    if (savedOrders.length > 0) {
      State.orders = savedOrders;
      DB.orders = savedOrders;
    }

    // Load persisted cart
    const savedCart = Storage.getCart();
    if (savedCart.length > 0) {
      State.cart = savedCart.map(item => {
        const product = DB.products.find(p => p.id === item.productId);
        return product ? { product, qty: item.qty } : null;
      }).filter(Boolean);
      Cart.updateBadge();
    }
  },

  logout() {
    Storage.clearSession();
    State.cart = [];
    State.orders = [];
    Cart.updateBadge();
    Toast.show('Signed out. See you soon!', '👋');
    setTimeout(() => Nav.goPage('login'), 500);
  },

  switchTab(tab, btn) {
    document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('form-signin').style.display = tab === 'signin' ? 'block' : 'none';
    document.getElementById('form-signup').style.display = tab === 'signup' ? 'block' : 'none';
    const greet = document.querySelector('.login-greet');
    const sub = document.querySelector('.login-greet-sub');
    if (tab === 'signin') { greet.textContent = 'Welcome back 👋'; sub.textContent = 'Sign in to explore thousands of hobby listings.'; }
    else { greet.textContent = 'Join HobbyLoop 🎉'; sub.textContent = 'Create your account and start buying or selling today.'; }
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
