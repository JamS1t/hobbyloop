/* ═══════════════════════════════════════════
   community.js — Community Feed (Twitter-like)
   ═══════════════════════════════════════════ */
const Community = {

  render() {
    this.renderFeed();
    this.renderSidebar();
  },

  renderFeed() {
    const feed = document.getElementById('community-feed');
    if (!feed) return;

    const composer = `
      <div class="post-card post-composer">
        <div class="composer-row">
          <div class="composer-ava">AK</div>
          <textarea class="composer-input" id="composer-text" placeholder="Share a find, tip, or recommendation with the community..."></textarea>
        </div>
        <div class="composer-actions">
          <button class="composer-action-btn btn" onclick="Community.attachProduct()">📦 Tag Item</button>
          <button class="composer-action-btn btn" onclick="Toast.show('Photo upload coming soon!', 'i')">📷 Photo</button>
          <button class="btn btn-primary-sm composer-post-btn" onclick="Community.submitPost()">Post</button>
        </div>
      </div>`;

    const posts = DB.communityPosts.map(post => this.postHTML(post)).join('');
    feed.innerHTML = composer + posts;
  },

  postHTML(post) {
    const product = post.productId ? DB.products.find(p => p.id === post.productId) : null;
    const productHTML = product ? `
      <div class="post-product-card" onclick="ProductDetail.open(${product.id})">
        <div class="post-prod-thumb" style="overflow:hidden;">${product.img
          ? `<img src="${product.img}" alt="${product.name}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
          : product.name.charAt(0)}</div>
        <div class="post-prod-info">
          <div class="post-prod-name">${product.name}</div>
          <div class="post-prod-price">₱${product.price.toLocaleString()}</div>
          <div class="post-prod-cond">${product.cond} · ${product.rating}</div>
        </div>
        <button class="post-prod-btn" onclick="event.stopPropagation(); Dashboard.addToCart(${product.id}, this)">
          ${State.inCart(product.id) ? '✓ In Cart' : 'Add to Cart'}
        </button>
      </div>` : '';

    return `
      <div class="post-card" id="post-${post.id}">
        <div class="post-hd">
          <div class="post-ava" style="background:${post.color};">${post.initials}</div>
          <div class="post-meta">
            <div class="post-author">${post.author}</div>
            <div class="post-handle">${post.handle}</div>
          </div>
          <div class="post-time">${post.time}</div>
        </div>
        <div class="post-body">${post.text}</div>
        ${productHTML}
        <div class="post-actions">
          <button class="post-action-btn ${post.liked ? 'liked' : ''}" onclick="Community.toggleLike(${post.id}, this)">
            ${post.liked ? '❤️ Liked' : '🤍 Like'} <span>${post.likes}</span>
          </button>
          <button class="post-action-btn" onclick="Toast.show('Comments coming soon!', 'i')">
            💬 Comments <span>${post.comments}</span>
          </button>
          <button class="post-action-btn" onclick="Toast.show('Shared to clipboard!', '✓')">
            🔗 Share
          </button>
        </div>
      </div>`;
  },

  renderSidebar() {
    // Trending topics
    const trending = document.getElementById('trending-topics');
    if (trending) {
      const topics = ['#FilmPhotography','#CarbonSteelCooking','#StargazingPH','#BoardGaming','#VintageCameras','#OutdoorGear','#CollectingTips'];
      const counts = [2841, 1923, 1204, 987, 876, 654, 432];
      trending.innerHTML = topics.map((t,i) => `
        <div class="trending-tag">
          <span class="tt-name">${t}</span>
          <span class="tt-count">${counts[i].toLocaleString()} posts</span>
        </div>`).join('');
    }

    // Suggested users
    const suggested = document.getElementById('suggested-users');
    if (suggested) {
      const others = DB.sellers.filter(s => s.id !== 0).slice(0, 4);
      suggested.innerHTML = others.map(s => `
        <div class="suggested-user">
          <div class="su-ava" style="background:${s.color};">${s.initials}</div>
          <div class="su-info">
            <div class="su-name">${s.name}</div>
            <div class="su-desc">${s.badge} · ${s.sales} sales</div>
          </div>
          <button class="su-follow ${State.following.has(s.id) ? 'following' : ''}"
            onclick="Community.toggleFollow(${s.id}, this)">
            ${State.following.has(s.id) ? 'Following' : 'Follow'}
          </button>
        </div>`).join('');
    }
  },

  toggleLike(postId, btn) {
    const post = DB.communityPosts.find(p => p.id === postId);
    if (!post) return;
    post.liked = !post.liked;
    post.likes += post.liked ? 1 : -1;
    btn.classList.toggle('liked', post.liked);
    btn.innerHTML = `${post.liked ? '❤️ Liked' : '🤍 Like'} <span>${post.likes}</span>`;
  },

  toggleFollow(userId, btn) {
    if (State.following.has(userId)) {
      State.following.delete(userId);
      btn.textContent = 'Follow';
      btn.classList.remove('following');
    } else {
      State.following.add(userId);
      btn.textContent = 'Following';
      btn.classList.add('following');
      const seller = DB.sellers.find(s => s.id === userId);
      if (seller) Toast.show(`You're now following ${seller.name}!`, '✓');
    }
  },

  attachProduct() {
    Toast.show('Select any item and tag it to your post', 'i');
  },

  submitPost() {
    const textarea = document.getElementById('composer-text');
    const text = textarea ? textarea.value.trim() : '';
    if (!text) { Toast.show('Write something before posting!', 'i'); return; }
    const newPost = {
      id: Date.now(),
      userId: 0,
      author: 'Alex Kim',
      handle: '@alexkim',
      initials: 'AK',
      color: '#0D7C6E',
      time: 'Just now',
      text: text,
      productId: null,
      likes: 0,
      comments: 0,
      liked: false
    };
    DB.communityPosts.unshift(newPost);
    if (textarea) textarea.value = '';
    this.render();
    Toast.show('Post shared with the community!', '✓');
  }
};
