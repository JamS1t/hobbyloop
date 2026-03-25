/* ═══════════════════════════════════════════
   dashboard.js — Dashboard Rendering (API)
   ═══════════════════════════════════════════ */
const Dashboard = {

  categories: [],
  products: [],

  async render() {
    await this.loadCategories();
    this.renderCategoryFilter();
    await this.renderFiltered(State.activeCategory);
  },

  async loadCategories() {
    if (this.categories.length > 0) return;
    const res = await API.get('/products/categories.php');
    if (res.success) {
      // Prepend "All Items" option (not in DB)
      this.categories = [{ id: 'all', label: 'All Items' }, ...res.data];
    }
  },

  renderCategoryFilter() {
    const bar = document.getElementById('cat-filter-bar');
    if (!bar) return;
    bar.innerHTML = this.categories.map(c => `
      <button class="cat-chip ${c.id === State.activeCategory ? 'active' : ''}"
        onclick="Dashboard.filterBy('${c.id}', this)">
        ${c.label}
      </button>`).join('');
  },

  async filterBy(catId, btn) {
    State.activeCategory = catId;
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    await this.renderFiltered(catId);
  },

  async renderFiltered(catId) {
    const grid = document.getElementById('products-grid');
    if (!grid) return;

    grid.innerHTML = '<div class="loading-text">Loading products...</div>';

    const path = catId && catId !== 'all'
      ? '/products/list.php?cat=' + encodeURIComponent(catId)
      : '/products/list.php';
    const res = await API.get(path);

    if (res.success) {
      this.products = res.data;
      grid.innerHTML = this.products.map(p => this.productCardHTML(p)).join('');
    } else {
      grid.innerHTML = '<div class="empty-state">Failed to load products</div>';
    }
  },

  async addToCart(productId, btn) {
    const product = this.products.find(p => p.id === productId);
    if (!product) return;
    if (btn) { btn.textContent = 'Adding...'; btn.disabled = true; }
    await State.addToCart(product);
    if (btn) { btn.textContent = '✓ In Cart'; btn.classList.add('in-cart'); btn.disabled = false; }
    Toast.show(`${product.name} added to cart`, '✓');
  },

  productCardHTML(p) {
    const inCart = State.inCart(p.id);
    const badge = p.badge === 'hot' ? '<span class="pill pill-coral">Hot</span>'
      : p.badge === 'top' ? '<span class="pill pill-gold">Top</span>'
      : p.badge === 'new' ? '<span class="pill pill-teal">New</span>' : '';
    return `
      <div class="product-card" onclick="ProductDetail.open(${p.id})">
        <div class="pc-img">
          ${p.img
            ? `<img src="${p.img}" alt="${p.name}" class="pc-photo" loading="lazy"
                   onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
               <div class="pc-img-fallback" style="display:none;"><div class="pc-img-bg" style="background:${p.bg};"></div><span class="pc-emoji">${p.name.charAt(0)}</span></div>`
            : `<div class="pc-img-bg" style="background:${p.bg};"></div><span class="pc-emoji">${p.name.charAt(0)}</span>`}
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
              ${inCart ? '✓ In Cart' : '+ Add'}
            </button>
          </div>
        </div>
      </div>`;
  }
};
