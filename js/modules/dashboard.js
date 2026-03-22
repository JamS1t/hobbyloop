/* ═══════════════════════════════════════════
   dashboard.js — Dashboard Rendering
   ═══════════════════════════════════════════ */
const Dashboard = {

  render() {
    this.renderCategoryFilter();
    this.renderFiltered(State.activeCategory);
  },

  renderCategoryFilter() {
    const bar = document.getElementById('cat-filter-bar');
    if (!bar) return;
    bar.innerHTML = DB.categories.map(c => `
      <button class="cat-chip ${c.id === State.activeCategory ? 'active' : ''}"
        onclick="Dashboard.filterBy('${c.id}', this)">
        ${c.emoji} ${c.label}
      </button>`).join('');
  },

  filterBy(catId, btn) {
    State.activeCategory = catId;
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    this.renderFiltered(catId);
  },

  renderFiltered(catId) {
    const grid = document.getElementById('products-grid');
    if (!grid) return;
    const products = catId === 'all'
      ? DB.products
      : DB.products.filter(p => p.cat === catId);

    grid.innerHTML = products.map(p => this.productCardHTML(p)).join('');
  },

  addToCart(productId, btn) {
    const product = DB.products.find(p => p.id === productId);
    if (!product) return;
    State.addToCart(product);
    if (btn) { btn.textContent = '✓ In Cart'; btn.classList.add('in-cart'); }
    Toast.show(`${product.name} added to cart 🛒`, '✓');
  },

  productCardHTML(p) {
    const inCart = State.inCart(p.id);
    const badge = p.badge === 'hot' ? '<span class="pill pill-coral">🔥 Hot</span>'
      : p.badge === 'top' ? '<span class="pill pill-gold">🏆 Top</span>'
      : p.badge === 'new' ? '<span class="pill pill-teal">✨ New</span>' : '';
    return `
      <div class="product-card" onclick="Checkout.openProduct(DB.products.find(x=>x.id===${p.id}))">
        <div class="pc-img">
          ${p.img
            ? `<img src="${p.img}" alt="${p.name}" class="pc-photo" loading="lazy"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
               <div class="pc-img-fallback" style="display:none;"><div class="pc-img-bg" style="background:${p.bg};"></div><span class="pc-emoji">${p.emoji}</span></div>`
            : `<div class="pc-img-bg" style="background:${p.bg};"></div><span class="pc-emoji">${p.emoji}</span>`}
          ${badge ? `<div class="pc-badges">${badge}</div>` : ''}
        </div>
        <div class="pc-body">
          <div class="pc-name">${p.name}</div>
          <div class="pc-cond">${p.cond}</div>
          <div class="pc-foot">
            <div>
              <span class="pc-price">₱${p.price.toLocaleString()}</span>
              ${p.orig ? `<span class="pc-price-orig">₱${p.orig.toLocaleString()}</span>` : ''}
            </div>
            <button class="pc-add ${inCart ? 'in-cart' : ''}"
              onclick="event.stopPropagation(); Dashboard.addToCart(${p.id}, this)">
              ${inCart ? '✓ In Cart' : '🛒 Add'}
            </button>
          </div>
        </div>
      </div>`;
  }
};
