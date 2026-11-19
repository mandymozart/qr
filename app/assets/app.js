// qr — tiny progressive enhancements, no framework.

// Confirm destructive actions: <form data-confirm="Really?">
document.addEventListener('submit', (ev) => {
  const msg = ev.target.dataset && ev.target.dataset.confirm;
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Copy to clipboard: <button data-copy="text">
function copyText(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
  }
  return Promise.resolve(legacyCopy(text));
}
function legacyCopy(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); } finally { ta.remove(); }
}
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('[data-copy]');
  if (!btn) return;
  ev.preventDefault();
  copyText(btn.dataset.copy).then(() => {
    const old = btn.dataset.label || btn.textContent;
    btn.dataset.label = old;
    btn.textContent = document.documentElement.lang === 'de' ? 'Kopiert ✓' : 'Copied ✓';
    setTimeout(() => (btn.textContent = old), 1200);
  });
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

// QR style editor: live preview, corner/shape/color/logo choices, logo dropzone
document.querySelectorAll('.style-form').forEach((form) => {
  const img = document.querySelector(form.dataset.preview);
  const zone = form.querySelector('.dropzone');
  const fileInput = zone && zone.querySelector('input[type=file]');
  const uploadRadio = form.querySelector('input[name=logo][value=upload]');
  let overlay = null;

  const showOverlay = (src) => {
    // instant local preview of a new logo on top of the QR (the real one is rendered after saving)
    if (!img) return;
    const box = img.parentElement;
    box.style.position = 'relative';
    if (!overlay) { overlay = document.createElement('img'); overlay.className = 'logo-overlay'; overlay.alt = ''; box.appendChild(overlay); }
    overlay.src = src;
    overlay.hidden = !src;
  };

  const update = () => {
    const f = new FormData(form);
    if (overlay) overlay.hidden = f.get('logo') !== 'upload' || !(fileInput && fileInput.files.length);
    if (!img) return;
    const custom = form.querySelector('.custom-colors');
    if (custom) {
      custom.hidden = f.get('color') !== 'custom';
      // same contrast rule as the server: at least 3:1 against white
      const lum = (hex) => { const c = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255).map((v) => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
      const tooLight = ['c1', 'c2'].some((k) => 1.05 / (lum(f.get(k) || '#000000') + 0.05) < 3);
      custom.querySelector('.contrast-hint').hidden = !tooLight;
    }
    const p = new URLSearchParams({ preview: 1, shape: f.get('shape'), color: f.get('color'), logo: f.get('logo'), eye: f.get('eye') || 'square', c1: f.get('c1') || '', c2: f.get('c2') || '', t: Date.now() });
    img.src = form.dataset.base + '&' + p.toString();
  };

  const pick = (file) => {
    if (!file || !/^image\//.test(file.type)) return;
    const url = URL.createObjectURL(file);
    zone.classList.add('has-file');
    zone.querySelector('.dz-thumb').innerHTML = '<img alt="" src="' + url + '">';
    zone.querySelector('.dz-text b').textContent = file.name;
    if (uploadRadio) uploadRadio.checked = true;
    update();
    showOverlay(url);
  };

  if (zone) {
    ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('drag'); }));
    ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('drag'); }));
    zone.addEventListener('drop', (e) => {
      const file = e.dataTransfer.files[0];
      if (!file) return;
      const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
      pick(file);
    });
    fileInput.addEventListener('change', () => pick(fileInput.files[0]));
  }
  form.addEventListener('change', (e) => { if (e.target !== fileInput) update(); });
  // color pickers: update the preview while dragging, but not on every pixel
  let t; form.querySelectorAll('input[type=color]').forEach((c) => c.addEventListener('input', () => { clearTimeout(t); t = setTimeout(update, 250); }));
});
