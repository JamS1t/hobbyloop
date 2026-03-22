/* ═══════════════════════════════════════════
   search.js — Live Search Across All Products
   ═══════════════════════════════════════════ */
const Search = {
  timer: null,

  init() {
    const input = document.getElementById('search-input');
    const clear = document.getElementById('search-clear');
    if (!input) return;

    input.addEventListener('input', (e) => {
      const q = e.target.value.trim();
      clear.classList.toggle('visible', q.length > 0);
      clearTimeout(this.timer);
      if (q.length < 1) { this.close(); return; }
      this.timer = setTimeout(() => this.query(q), 180);
    });

    input.addEventListener('focus', (e) => {
      if (e.target.value.trim().length > 0) this.query(e.target.value.trim());
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.search-wrap')) this.close();
    });
  },

  close() {
    const dd = document.getElementById('search-results');
    if (dd) dd.classList.remove('open');
  },

  clear() {
    const input = document.getElementById('search-input');
    const clear = document.getElementById('search-clear');
    if (input) input.value = '';
    if (clear) clear.classList.remove('visible');
    this.close();
  },

  query(q) {
    const dd = document.getElementById('search-results');
    if (!dd) return;

    const lq = q.toLowerCase();
    const results = DB.products.filter(p =>
      p.name.toLowerCase().includes(lq) ||
      p.cat.toLowerCase().includes(lq) ||
      p.desc.toLowerCase().includes(lq) ||
      p.seller.toLowerCase().includes(lq) ||
      p.cond.toLowerCase().includes(lq)
    ).slice(0, 8);

    if (results.length === 0) {
      dd.innerHTML = `<div class="search-no-results">No results for "<strong>${q}</strong>"</div>`;
      dd.classList.add('open');
      return;
    }

    // Group by category
    const grouped = {};
    results.forEach(p => {
      const cat = DB.categories.find(c => c.id === p.cat);
      const key = cat ? cat.label : p.cat;
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(p);
    });

    let html = '';
    Object.entries(grouped).forEach(([label, items]) => {
      html += `<div class="search-result-group"><div class="search-result-label">${label}</div>`;
      items.forEach(p => {
        const formatted = '₱' + p.price.toLocaleString();
        html += `
          <div class="search-result-item" onclick="Search.select(${p.id})">
            <div class="search-result-emoji" style="overflow:hidden;">${p.img
              ? `<img src="${p.img}" alt="${p.emoji}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
              : p.emoji}</div>
            <div class="search-result-info">
              <div class="res-name">${p.name}</div>
              <div class="res-meta">${p.cond} · ${p.seller} · ⭐ ${p.rating}</div>
            </div>
            <div class="search-result-price">${formatted}</div>
          </div>`;
      });
      html += '</div>';
    });

    dd.innerHTML = html;
    dd.classList.add('open');
  },

  select(productId) {
    const product = DB.products.find(p => p.id === productId);
    if (!product) return;
    this.close();
    this.clear();
    // Navigate to checkout with this product pre-loaded
    Checkout.openProduct(product);
  }
};
