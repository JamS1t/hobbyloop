/* ═══════════════════════════════════════════
   toast.js — Toast Notifications
   ═══════════════════════════════════════════ */
const Toast = (() => {
  let timer = null;
  function show(msg, icon = '✓') {
    const el = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    document.getElementById('toast-icon').textContent = icon;
    el.classList.add('show');
    clearTimeout(timer);
    timer = setTimeout(() => el.classList.remove('show'), 2800);
  }
  return { show };
})();
