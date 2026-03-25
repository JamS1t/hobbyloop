/* ═══════════════════════════════════════════
   notifications.js — Notification Panel (API)
   ═══════════════════════════════════════════ */
const Notifications = {
  open: false,
  _data: [], // local cache

  toggle() {
    const panel = document.getElementById('notif-panel');
    this.open = !this.open;
    panel.classList.toggle('open', this.open);
    if (this.open) this.load();
    if (this.open) {
      setTimeout(() => {
        document.addEventListener('click', this._outsideHandler = (e) => {
          if (!e.target.closest('#notif-panel') && !e.target.closest('#notif-btn')) {
            this.close();
          }
        });
      }, 10);
    }
  },

  close() {
    const panel = document.getElementById('notif-panel');
    if (panel) panel.classList.remove('open');
    this.open = false;
    if (this._outsideHandler) {
      document.removeEventListener('click', this._outsideHandler);
      this._outsideHandler = null;
    }
  },

  async load() {
    const list = document.getElementById('notif-list');
    if (!list) return;

    list.innerHTML = '<div style="padding:16px;text-align:center;font-size:13px;color:var(--text-muted);">Loading...</div>';

    const res = await API.get('/notifications/list.php');
    if (!res.success) {
      list.innerHTML = '<div style="padding:16px;text-align:center;font-size:13px;color:var(--text-muted);">Failed to load notifications</div>';
      return;
    }

    this._data = res.data.notifications;
    State.notifCount = res.data.unread_count;
    this.updateBadge();
    this.render();
  },

  async markAllRead() {
    const res = await API.post('/notifications/mark-read.php', { all: true });
    if (!res.success) return;

    this._data.forEach(n => n.unread = false);
    State.notifCount = 0;
    this.updateBadge();
    this.render();
    Toast.show('All notifications marked as read', '✓');
  },

  async markRead(id) {
    const n = this._data.find(x => x.id === id);
    if (!n || !n.unread) return;

    const res = await API.post('/notifications/mark-read.php', { id });
    if (!res.success) return;

    n.unread = false;
    State.notifCount = res.data.unread_count;
    this.updateBadge();
  },

  async updateBadge() {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;

    // Fetch from server only on the very first call (panel never opened yet)
    if (this._data.length === 0) {
      const res = await API.get('/notifications/list.php');
      if (res.success) {
        this._data = res.data.notifications;
        State.notifCount = res.data.unread_count;
      }
    }

    const count = State.notifCount;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
  },

  render() {
    const list = document.getElementById('notif-list');
    if (!list) return;

    if (!this._data.length) {
      list.innerHTML = '<div style="padding:24px;text-align:center;font-size:13px;color:var(--text-muted);">No notifications yet</div>';
      return;
    }

    list.innerHTML = this._data.map(n => `
      <div class="notif-item ${n.unread ? 'unread' : ''}" onclick="Notifications.markRead(${n.id}); this.classList.remove('unread');">
        <div class="notif-ico">${API.esc(n.icon || '🔔')}</div>
        <div class="notif-body">
          <div class="notif-text">${API.esc(n.text)}</div>
          <div class="notif-time">${API.esc(n.time)}</div>
        </div>
      </div>`).join('');
  },
};
