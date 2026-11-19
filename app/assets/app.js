// qr — tiny progressive enhancements, no framework.

// Confirm destructive actions: <form data-confirm="Really?">
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset && ev.target.dataset.confirm;
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Copy to clipboard: <button data-copy="text">
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('[data-copy]');
  if (!btn) return;
  ev.preventDefault();
  try {
    await navigator.clipboard.writeText(btn.dataset.copy);
    const old = btn.textContent;
    btn.textContent = 'Copied';
    setTimeout(() => (btn.textContent = old), 1200);
  } catch (e) { prompt('Copy:', btn.dataset.copy); }
});

// Live filter for lists: <input data-filter="#listId">
document.querySelectorAll('[data-filter]').forEach((input) => {
  const list = document.querySelector(input.dataset.filter);
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    list.querySelectorAll('[data-search]').forEach((el) => {
      el.style.display = el.dataset.search.toLowerCase().includes(q) ? '' : 'none';
    });
  });
});
