/* ═══════════════════════════════════════════
   notifications.js — Notification Panel
   ═══════════════════════════════════════════ */
const Notifications = {
  open: false,

  toggle() {
    const panel = document.getElementById('notif-panel');
    this.open = !this.open;
    panel.classList.toggle('open', this.open);
    if (this.open) this.render();
    // Close when clicking outside
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

  markAllRead() {
    DB.notifications.forEach(n => n.unread = false);
    State.notifCount = 0;
    this.updateBadge();
    this.render();
    Toast.show('All notifications marked as read', '✓');
  },

  markRead(id) {
    const n = DB.notifications.find(x => x.id === id);
    if (n && n.unread) {
      n.unread = false;
      State.notifCount = Math.max(0, State.notifCount - 1);
      this.updateBadge();
    }
  },

  updateBadge() {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    const count = DB.notifications.filter(n => n.unread).length;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
  },

  render() {
    const list = document.getElementById('notif-list');
    if (!list) return;
    list.innerHTML = DB.notifications.map(n => `
      <div class="notif-item ${n.unread ? 'unread' : ''}" onclick="Notifications.markRead(${n.id}); Notifications.renderItem(${n.id})">
        <div class="notif-ico">${n.icon}</div>
        <div class="notif-body">
          <div class="notif-text">${n.text}</div>
          <div class="notif-time">${n.time}</div>
        </div>
      </div>`).join('');
  },

  renderItem(id) {
    const item = document.querySelector(`.notif-item[onclick*="markRead(${id})"]`);
    if (item) item.classList.remove('unread');
    const badge = document.getElementById('notif-badge');
    if (badge) {
      const count = DB.notifications.filter(n => n.unread).length;
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  }
};
