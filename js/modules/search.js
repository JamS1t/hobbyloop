/* ═══════════════════════════════════════════
   search.js — Live Search via API
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

  async query(q) {
    const dd = document.getElementById('search-results');
    if (!dd) return;

    const res = await API.get('/products/search.php?q=' + encodeURIComponent(q));

    if (!res.success) {
      dd.innerHTML = `<div class="search-no-results">Search failed</div>`;
      dd.classList.add('open');
      return;
    }

    const results = res.data;

    if (results.length === 0) {
      dd.innerHTML = `<div class="search-no-results">No results for "<strong>${API.esc(q)}</strong>"</div>`;
      dd.classList.add('open');
      return;
    }

    // Group by category
    const grouped = {};
    results.forEach(p => {
      const key = p.catLabel || p.cat;
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
              ? `<img src="${p.img}" alt="${p.name}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
              : p.name.charAt(0)}</div>
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
    this.close();
    this.clear();
    ProductDetail.open(productId);
  }
};
