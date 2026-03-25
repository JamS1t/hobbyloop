/* ═══════════════════════════════════════════
   product-detail.js — Product Detail Page (API)
   ═══════════════════════════════════════════ */
const ProductDetail = {
  currentProduct: null,

  async open(productId) {
    Nav.goTab('product-detail');
    const container = document.getElementById('product-detail-page');
    if (container) container.innerHTML = '<div class="loading-text">Loading product...</div>';

    const res = await API.get('/products/detail.php?id=' + productId);
    if (!res.success) {
      if (container) container.innerHTML = '<div class="empty-state">Product not found</div>';
      return;
    }
    this.currentProduct = res.data;
    this.render();
  },

  render() {
    const p = this.currentProduct;
    if (!p) return;
    const container = document.getElementById('product-detail-page');
    if (!container) return;

    const inCart = State.inCart(p.id);
    const discount = p.orig ? Math.round((1 - p.price / p.orig) * 100) : 0;
    const badgeHTML = p.badge === 'hot' ? '<span class="pill pill-coral">Hot</span>'
      : p.badge === 'top' ? '<span class="pill pill-gold">Top Rated</span>'
      : p.badge === 'new' ? '<span class="pill pill-teal">New</span>' : '';

    const stars = (rating) => '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

    // ── Reviews section ──
    let reviewsHTML = '';
    if (p.reviewsList && p.reviewsList.length > 0) {
      const reviewCards = p.reviewsList.map(r => {
        const date = new Date(r.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        return `
          <div class="review-card">
            <div class="review-header">
              <div class="review-avatar" style="background:${r.avatar_color};">${r.avatar_initials}</div>
              <div class="review-meta">
                <div class="review-name">${r.reviewer_name}</div>
                <div class="review-date">${date}</div>
              </div>
              <div class="review-stars">${stars(r.rating)}</div>
            </div>
            ${r.comment ? `<div class="review-comment">${r.comment}</div>` : ''}
          </div>`;
      }).join('');

      reviewsHTML = `
        <div class="pd-reviews-section">
          <div class="pd-desc-title">Reviews (${p.reviews})</div>
          <div class="pd-reviews-avg">
            <span class="pd-stars">${stars(p.rating)}</span>
            <span>${p.rating} out of 5</span>
          </div>
          <div class="reviews-list">${reviewCards}</div>
        </div>`;
    } else {
      reviewsHTML = `
        <div class="pd-reviews-section">
          <div class="pd-desc-title">Reviews</div>
          <div class="reviews-empty">No reviews yet. Be the first to review this product!</div>
        </div>`;
    }

    container.innerHTML = `
      <button class="btn btn-ghost pd-back-btn" onclick="Nav.goTab('dashboard'); Dashboard.render();">
        ← Back to Dashboard
      </button>

      <div class="pd-layout">
        <div class="pd-image-section">
          <div class="pd-image" style="background:${p.bg};">
            ${p.img
              ? '<img src="' + p.img + '" alt="' + p.name + '" class="pd-photo">'
              : '<div class="pd-initials">' + p.name.charAt(0) + '</div>'}
          </div>
        </div>

        <div class="pd-info-section">
          <div class="pd-category">${p.catLabel || p.cat}</div>
          ${badgeHTML ? '<div style="margin-bottom:4px;">' + badgeHTML + '</div>' : ''}
          <h1 class="pd-name">${p.name}</h1>

          <div class="pd-price-row">
            <span class="pd-price">₱${p.price.toLocaleString()}</span>
            ${p.orig ? '<span class="pd-orig">₱' + p.orig.toLocaleString() + '</span>' : ''}
            ${discount > 0 ? '<span class="pd-discount">-' + discount + '%</span>' : ''}
          </div>

          <div class="pd-condition">Condition: <strong>${p.cond}</strong></div>

          <div class="pd-rating">
            <span class="pd-stars">${stars(p.rating)}</span>
            <span>${p.rating}</span>
            <span class="pd-reviews">(${p.reviews} reviews)</span>
          </div>

          <div class="pd-desc-title">Description</div>
          <div class="pd-desc">${p.desc}</div>

          <div class="pd-seller">
            <div class="pd-seller-ava" style="background:${p.sellerColor};">${p.sellerInitials}</div>
            <div class="pd-seller-info">
              <div class="pd-seller-name">${p.seller}</div>
              <div class="pd-seller-meta">${p.sellerBadge} · ${p.sellerSales} sales · ${p.sellerCity}</div>
              <div class="pd-seller-rating">★ ${p.sellerRating} seller rating</div>
            </div>
          </div>

          <div class="pd-actions">
            <button class="btn btn-ghost pd-add-btn ${inCart ? 'in-cart' : ''}"
              onclick="ProductDetail.addToCart()">
              ${inCart ? '✓ In Cart' : 'Add to Cart'}
            </button>
            <button class="btn btn-primary pd-buy-btn" onclick="ProductDetail.buyNow()">
              Buy Now
            </button>
          </div>

          ${reviewsHTML}
        </div>
      </div>`;
  },

  async addToCart() {
    if (!this.currentProduct) return;
    if (!State.inCart(this.currentProduct.id)) {
      await State.addToCart(this.currentProduct);
      Toast.show(this.currentProduct.name + ' added to cart', '✓');
    }
    this.render();
  },

  async buyNow() {
    if (!this.currentProduct) return;
    if (!State.inCart(this.currentProduct.id)) {
      await State.addToCart(this.currentProduct);
    }
    Checkout.open();
  }
};
