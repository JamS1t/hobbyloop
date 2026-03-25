/* ═══════════════════════════════════════════
   orders.js — Order History (API)
   ═══════════════════════════════════════════ */
const Orders = {
  async render() {
    const list = document.getElementById('orders-list');
    if (!list) return;

    list.innerHTML = '<div class="loading-text">Loading orders...</div>';

    await State.loadOrders();

    if (State.orders.length === 0) {
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon" style="font-size:48px;opacity:0.3;">📦</div>
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
          <span class="order-id">${API.esc(o.id)}</span>
          <span class="order-date">${API.esc(o.date)}</span>
          <span class="order-status">
            <span class="pill" style="background:${statusColor[o.status] || 'var(--text-muted)'}22; color:${statusColor[o.status] || 'var(--text-muted)'};">${API.esc(o.status)}</span>
          </span>
        </div>
        <div class="order-items-row">
          ${o.items.slice(0, 4).map(item => `
            <div class="order-thumb" style="overflow:hidden;" title="${API.esc(item.name)}">${item.img
              ? `<img src="${item.img}" alt="${API.esc(item.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`
              : `<div style="background:${item.bg || 'var(--teal)'};width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:inherit;font-weight:700;color:rgba(255,255,255,0.7);">${item.name.charAt(0)}</div>`}</div>`
          ).join('')}
          ${o.items.length > 4 ? `<div class="order-thumb" style="background:var(--paper-warm);font-size:13px;font-weight:700;color:var(--text-muted);">+${o.items.length-4}</div>` : ''}
          <div style="flex:1;padding-left:8px;">
            <div style="font-size:13px;font-weight:600;">${API.esc(o.items[0].name)}${o.items.length > 1 ? ` + ${o.items.length - 1} more` : ''}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">${o.items.reduce((s,i)=>s+i.qty,0)} item(s)</div>
          </div>
        </div>
        ${o.tracking ? `<div style="font-size:12px;color:var(--text-muted);margin-top:8px;">📦 Tracking: <strong>${API.esc(o.tracking)}</strong> via ${API.esc(o.courier)}</div>` : ''}
        ${o.estimated_delivery ? `<div style="font-size:12px;color:var(--text-muted);margin-top:4px;">🚚 Estimated delivery: <strong>${new Date(o.estimated_delivery).toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'})}</strong></div>` : ''}
        <div class="order-row-ft">
          <span style="font-size:13px;color:var(--text-muted);">Total</span>
          <span class="order-total" style="color:var(--teal);">₱${o.total.toLocaleString()}</span>
        </div>
      </div>`).join('');
  }
};
