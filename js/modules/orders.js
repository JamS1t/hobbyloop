/* ═══════════════════════════════════════════
   orders.js — Order History
   ═══════════════════════════════════════════ */
const Orders = {
  render() {
    const list = document.getElementById('orders-list');
    if (!list) return;
    if (State.orders.length === 0) {
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">📦</div>
          <div class="empty-title">No orders yet</div>
          <div class="empty-sub">Items you purchase will appear here.</div>
          <button class="btn btn-primary-sm" style="margin-top:16px;" onclick="Nav.goTab('dashboard'); Dashboard.render();">Start Shopping</button>
        </div>`;
      return;
    }
    const statusColor = { 'Processing': 'var(--gold)', 'Shipped': 'var(--teal)', 'Delivered': 'var(--sage)', 'Cancelled': 'var(--coral)' };
    list.innerHTML = State.orders.map(o => `
      <div class="order-row">
        <div class="order-row-hd">
          <span class="order-id">${o.id}</span>
          <span class="order-date">${o.date}</span>
          <span class="order-status">
            <span class="pill" style="background:${statusColor[o.status] || 'var(--text-muted)'}22; color:${statusColor[o.status] || 'var(--text-muted)'};">${o.status}</span>
          </span>
        </div>
        <div class="order-items-row">
          ${o.items.slice(0, 4).map(item => {
            const p = item.product || item;
            const name = p.name;
            const emoji = p.emoji;
            const img = p.img;
            const bg = p.bg;
            return `
            <div class="order-thumb" style="overflow:hidden;" title="${name}">${img
              ? `<img src="${img}" alt="${emoji}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
              : `<div style="background:${bg};width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;">${emoji}</div>`}</div>`;
          }).join('')}
          ${o.items.length > 4 ? `<div class="order-thumb" style="background:var(--paper-warm);font-size:13px;font-weight:700;color:var(--text-muted);">+${o.items.length-4}</div>` : ''}
          <div style="flex:1;padding-left:8px;">
            <div style="font-size:13px;font-weight:600;">${(o.items[0].product || o.items[0]).name}${o.items.length > 1 ? ` + ${o.items.length - 1} more` : ''}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">${o.items.reduce((s,i)=>s+i.qty,0)} item(s)</div>
          </div>
        </div>
        <div class="order-row-ft">
          <span style="font-size:13px;color:var(--text-muted);">Total</span>
          <span class="order-total" style="color:var(--teal);">₱${o.total.toLocaleString()}</span>
        </div>
      </div>`).join('');
  }
};
