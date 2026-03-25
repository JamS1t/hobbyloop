/* ═══════════════════════════════════════════
   community.js — Community Feed (API-backed)
   ═══════════════════════════════════════════ */
const Community = {

  posts: [],
  suggested: [],

  async render() {
    await this.loadPosts();
    this.renderSidebar();
  },

  async loadPosts() {
    const feed = document.getElementById('community-feed');
    if (!feed) return;

    feed.innerHTML = this.composerHTML() + '<div class="loading-text">Loading posts...</div>';

    const res = await API.get('/community/posts.php');
    if (!res.success) {
      feed.innerHTML = this.composerHTML() + '<div class="empty-state"><div class="empty-title">Failed to load posts</div></div>';
      return;
    }

    this.posts = res.data.posts;
    this.suggested = res.data.suggested;

    const postsHTML = this.posts.map(p => this.postHTML(p)).join('');
    feed.innerHTML = this.composerHTML() + (postsHTML || '<div class="empty-state"><div class="empty-title">No posts yet</div><div class="empty-sub">Be the first to share something!</div></div>');

    this.renderSidebar();
  },

  composerHTML() {
    const u = State.currentUser;
    const initials = u ? (u.avatar_initials || ((u.first_name || '')[0] + (u.last_name || '')[0]).toUpperCase()) : '?';
    const color = u ? (u.avatar_color || '#0D7C6E') : '#0D7C6E';
    return `
      <div class="post-card post-composer">
        <div class="composer-row">
          <div class="composer-ava" style="background:${color};">${API.esc(initials)}</div>
          <textarea class="composer-input" id="composer-text" placeholder="Share a find, tip, or recommendation with the community..."></textarea>
        </div>
        <div class="composer-actions">
          <button class="composer-action-btn btn" onclick="Toast.show('Tag an item coming soon!', 'i')">📦 Tag Item</button>
          <button class="composer-action-btn btn" onclick="Toast.show('Photo upload coming soon!', 'i')">📷 Photo</button>
          <button class="btn btn-primary-sm composer-post-btn" onclick="Community.submitPost()">Post</button>
        </div>
      </div>`;
  },

  postHTML(post) {
    const productHTML = post.product ? `
      <div class="post-product-card" onclick="ProductDetail.open(${post.product.id})">
        <div class="post-prod-thumb" style="overflow:hidden;">${post.product.img
          ? `<img src="${post.product.img}" alt="${API.esc(post.product.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
          : `<div style="background:${post.product.bg};width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-weight:700;color:rgba(255,255,255,0.7);">${post.product.name.charAt(0)}</div>`}</div>
        <div class="post-prod-info">
          <div class="post-prod-name">${API.esc(post.product.name)}</div>
          <div class="post-prod-price">₱${post.product.price.toLocaleString()}</div>
          <div class="post-prod-cond">${API.esc(post.product.cond)} · ★${post.product.rating}</div>
        </div>
        <button class="post-prod-btn" onclick="event.stopPropagation(); Dashboard.addToCart(${post.product.id}, this)">
          ${State.inCart(post.product.id) ? '✓ In Cart' : 'Add to Cart'}
        </button>
      </div>` : '';

    return `
      <div class="post-card" id="post-${post.id}">
        <div class="post-hd">
          <div class="post-ava" style="background:${post.color};">${API.esc(post.initials)}</div>
          <div class="post-meta">
            <div class="post-author">${API.esc(post.author)}</div>
            <div class="post-handle">${API.esc(post.handle)}</div>
          </div>
          <div class="post-time">${API.esc(post.time)}</div>
        </div>
        <div class="post-body">${API.esc(post.text)}</div>
        ${productHTML}
        <div class="post-actions">
          <button class="post-action-btn ${post.liked ? 'liked' : ''}" id="like-btn-${post.id}"
            onclick="Community.toggleLike(${post.id}, this)">
            ${post.liked ? '❤️ Liked' : '🤍 Like'} <span id="like-count-${post.id}">${post.likes}</span>
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
    // Trending topics — derive from post hashtags, fall back to static list
    const trending = document.getElementById('trending-topics');
    if (trending) {
      const hashtagCounts = {};
      this.posts.forEach(p => {
        const matches = p.text.match(/#\w+/g) || [];
        matches.forEach(tag => {
          hashtagCounts[tag] = (hashtagCounts[tag] || 0) + 1;
        });
      });

      let topics;
      if (Object.keys(hashtagCounts).length >= 3) {
        topics = Object.entries(hashtagCounts)
          .sort((a, b) => b[1] - a[1])
          .slice(0, 7)
          .map(([tag, count]) => ({ tag, count: count * 312 + 104 }));
      } else {
        // Static fallback when posts have no hashtags
        topics = [
          { tag: '#FilmPhotography', count: 2841 },
          { tag: '#CarbonSteelCooking', count: 1923 },
          { tag: '#StargazingPH', count: 1204 },
          { tag: '#BoardGaming', count: 987 },
          { tag: '#VintageCameras', count: 876 },
          { tag: '#OutdoorGear', count: 654 },
          { tag: '#CollectingTips', count: 432 },
        ];
      }

      trending.innerHTML = topics.map(t => `
        <div class="trending-tag">
          <span class="tt-name">${API.esc(t.tag)}</span>
          <span class="tt-count">${t.count.toLocaleString()} posts</span>
        </div>`).join('');
    }

    // Suggested sellers
    const suggested = document.getElementById('suggested-users');
    if (suggested) {
      if (!this.suggested.length) {
        suggested.innerHTML = '<div style="font-size:13px;color:var(--text-muted);padding:8px 0;">You\'re following everyone!</div>';
        return;
      }
      suggested.innerHTML = this.suggested.map(s => `
        <div class="suggested-user">
          <div class="su-ava" style="background:${s.color};">${API.esc(s.initials)}</div>
          <div class="su-info">
            <div class="su-name">${API.esc(s.name)}</div>
            <div class="su-desc">${API.esc(s.badge || 'Seller')} · ${s.sales} sales</div>
          </div>
          <button class="su-follow" id="follow-btn-${s.id}"
            onclick="Community.toggleFollow(${s.id}, this)">Follow</button>
        </div>`).join('');
    }
  },

  async toggleLike(postId, btn) {
    const res = await API.post('/community/like.php', { post_id: postId });
    if (!res.success) { Toast.show(res.error || 'Failed to like post', 'i'); return; }

    const { liked, likes_count } = res.data;
    btn.className = 'post-action-btn' + (liked ? ' liked' : '');
    btn.innerHTML = `${liked ? '❤️ Liked' : '🤍 Like'} <span id="like-count-${postId}">${likes_count}</span>`;

    // Update local cache
    const post = this.posts.find(p => p.id === postId);
    if (post) { post.liked = liked; post.likes = likes_count; }
  },

  async toggleFollow(userId, btn) {
    const res = await API.post('/community/follow.php', { user_id: userId });
    if (!res.success) { Toast.show(res.error || 'Failed to follow user', 'i'); return; }

    const { following, name } = res.data;
    btn.textContent = following ? 'Following' : 'Follow';
    btn.classList.toggle('following', following);
    if (following) Toast.show(`You're now following ${name}!`, '✓');

    // Remove from suggested list if now following, then re-render sidebar
    if (following) {
      this.suggested = this.suggested.filter(s => s.id !== userId);
      this.renderSidebar();
    }
  },

  async submitPost() {
    const textarea = document.getElementById('composer-text');
    const text = textarea ? textarea.value.trim() : '';
    if (!text) { Toast.show('Write something before posting!', 'i'); return; }

    const res = await API.post('/community/posts.php', { text });
    if (!res.success) { Toast.show(res.error || 'Failed to post', 'i'); return; }

    if (textarea) textarea.value = '';
    this.posts.unshift(res.data);

    // Re-render feed with new post at top
    const feed = document.getElementById('community-feed');
    if (feed) {
      const postsHTML = this.posts.map(p => this.postHTML(p)).join('');
      feed.innerHTML = this.composerHTML() + postsHTML;
    }

    Toast.show('Post shared with the community!', '✓');
  },
};
