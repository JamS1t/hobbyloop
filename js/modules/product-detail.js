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
    API.track('product_view', { target_id: productId });
  },

  render() {
    const p = this.currentProduct;
    if (!p) return;
    const container = document.getElementById('product-detail-page');
    if (!container) return;

    const inCart     = State.inCart(p.id);
    const isWished   = State.isWishlisted(p.id);
    const discount   = p.orig ? Math.round((1 - p.price / p.orig) * 100) : 0;
    const lowStock   = p.stock_qty > 0 && p.stock_qty <= 5;
    const outOfStock = p.stock_qty === 0;

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
          <div class="reviews-list" id="reviews-list-${p.id}">${reviewCards}</div>
        </div>`;
    } else {
      reviewsHTML = `
        <div class="pd-reviews-section">
          <div class="pd-desc-title">Reviews</div>
          <div class="reviews-empty" id="reviews-list-${p.id}">No reviews yet. Be the first to review this product!</div>
        </div>`;
    }

    // ── Review form (purchasers only, one review per user) ──
    const hasPurchased = State.orders.some(o =>
      o.items && o.items.some(i => i.product_id === p.id)
    );
    const alreadyReviewed = p.reviewsList && State.currentUser &&
      p.reviewsList.some(r => r.reviewer_name === (State.currentUser.first_name + ' ' + State.currentUser.last_name));

    let reviewFormHTML = '';
    if (hasPurchased && !alreadyReviewed) {
      reviewFormHTML = `
        <div class="review-form-wrap" id="review-form-${p.id}">
          <div class="pd-desc-title">Write a Review</div>
          <div class="review-form">
            <div class="star-rating-row">
              <span class="review-form-label">Your Rating</span>
              <div class="star-rating" id="star-rating-${p.id}">
                ${[1,2,3,4,5].map(n => `<span class="star" data-val="${n}" onclick="ProductDetail.setRating(${n})">☆</span>`).join('')}
              </div>
              <input type="hidden" id="review-rating-${p.id}" value="0">
            </div>
            <textarea class="form-input review-textarea" id="review-comment-${p.id}"
              placeholder="Share your experience with this product..." rows="3"></textarea>
            <button class="btn btn-primary-sm" onclick="ProductDetail.submitReview(${p.id})">Submit Review</button>
          </div>
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
          ${lowStock ? `<div style="text-align:center;margin-top:8px;"><span class="pill pill-low-stock">Only ${p.stock_qty} left!</span></div>` : ''}
          ${outOfStock ? `<div style="text-align:center;margin-top:8px;"><span class="pill pill-stock-out">Out of Stock</span></div>` : ''}
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
            <button class="btn btn-ghost pd-add-btn ${inCart ? 'in-cart' : ''} ${outOfStock ? 'disabled' : ''}"
              ${outOfStock ? 'disabled' : ''}
              onclick="ProductDetail.addToCart()">
              ${inCart ? '✓ In Cart' : outOfStock ? 'Out of Stock' : 'Add to Cart'}
            </button>
            <button class="btn btn-primary pd-buy-btn" ${outOfStock ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''} onclick="ProductDetail.buyNow()">
              Buy Now
            </button>
            <button class="pd-wishlist-btn ${isWished ? 'wishlisted' : ''}"
              title="${isWished ? 'Remove from Wishlist' : 'Save to Wishlist'}"
              onclick="ProductDetail.toggleWishlist(this)">
              ${isWished ? '♥' : '♡'}
            </button>
          </div>

          ${reviewsHTML}
          ${reviewFormHTML}
        </div>
      </div>`;
  },

  setRating(val) {
    const p = this.currentProduct;
    if (!p) return;
    document.getElementById('review-rating-' + p.id).value = val;
    const stars = document.querySelectorAll('#star-rating-' + p.id + ' .star');
    stars.forEach((s, i) => {
      s.textContent = i < val ? '★' : '☆';
      s.classList.toggle('active', i < val);
    });
  },

  async submitReview(productId) {
    const ratingInput = document.getElementById('review-rating-' + productId);
    const commentInput = document.getElementById('review-comment-' + productId);
    const rating = parseInt(ratingInput ? ratingInput.value : 0);
    const comment = commentInput ? commentInput.value.trim() : '';

    if (!rating) { Toast.show('Please select a star rating', 'i'); return; }

    const res = await API.post('/reviews/submit.php', { product_id: productId, rating, comment });
    if (!res.success) {
      Toast.show(res.error || 'Failed to submit review', 'i');
      return;
    }

    Toast.show('Review submitted! Thank you.', '✓');

    // Add new review to the list immediately
    const review = res.data.review;
    const stars = (r) => '★'.repeat(r) + '☆'.repeat(5 - r);
    const date = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    const newCard = `
      <div class="review-card">
        <div class="review-header">
          <div class="review-avatar" style="background:${review.avatar_color};">${review.avatar_initials}</div>
          <div class="review-meta">
            <div class="review-name">${review.reviewer_name}</div>
            <div class="review-date">${date}</div>
          </div>
          <div class="review-stars">${stars(review.rating)}</div>
        </div>
        ${review.comment ? `<div class="review-comment">${review.comment}</div>` : ''}
      </div>`;

    const listEl = document.getElementById('reviews-list-' + productId);
    if (listEl) {
      if (listEl.classList.contains('reviews-empty')) {
        listEl.classList.remove('reviews-empty');
        listEl.innerHTML = newCard;
      } else {
        listEl.insertAdjacentHTML('afterbegin', newCard);
      }
    }

    // Hide the review form
    const formEl = document.getElementById('review-form-' + productId);
    if (formEl) formEl.remove();
  },

  async toggleWishlist(btn) {
    if (!this.currentProduct) return;
    const productId = this.currentProduct.id;
    if (btn) btn.disabled = true;
    const nowWishlisted = await State.toggleWishlist(productId);
    if (btn) {
      btn.disabled = false;
      btn.textContent = nowWishlisted ? '♥' : '♡';
      btn.classList.toggle('wishlisted', nowWishlisted);
      btn.title = nowWishlisted ? 'Remove from Wishlist' : 'Save to Wishlist';
    }
    Toast.show(nowWishlisted ? 'Added to Wishlist' : 'Removed from Wishlist', nowWishlisted ? '♥' : '♡');
  },

  async addToCart() {
    if (!this.currentProduct) return;
    if (!State.inCart(this.currentProduct.id)) {
      await State.addToCart(this.currentProduct);
      Toast.show(this.currentProduct.name + ' added to cart', '✓');
      API.track('add_to_cart', { target_id: this.currentProduct.id });
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
