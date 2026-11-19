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
    const grid = form.querySelector('.emoji-grid');
    if (grid) grid.hidden = f.get('logo') !== 'emoji';
    const custom = form.querySelector('.custom-colors');
    if (custom) {
      custom.hidden = f.get('color') !== 'custom';
      // same contrast rule as the server: at least 3:1 against white
      const lum = (hex) => { const c = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255).map((v) => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
      const tooLight = ['c1', 'c2'].some((k) => 1.05 / (lum(f.get(k) || '#000000') + 0.05) < 3);
      custom.querySelector('.contrast-hint').hidden = !tooLight;
    }
    const p = new URLSearchParams({ preview: 1, shape: f.get('shape'), color: f.get('color'), logo: f.get('logo'), eye: f.get('eye') || 'square', c1: f.get('c1') || '', c2: f.get('c2') || '', emoji: f.get('emoji') || '', t: Date.now() });
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
  form.addEventListener('change', (e) => {
    if (e.target.name === 'emoji') { const r = form.querySelector('input[name=logo][value=emoji]'); if (r) r.checked = true; }
    if (e.target !== fileInput) update();
  });
  // "My own" without a logo yet: open the file picker and point at the dropzone
  if (uploadRadio && zone) {
    uploadRadio.addEventListener('click', () => {
      if (zone.classList.contains('has-file')) return;
      zone.classList.add('nudge');
      setTimeout(() => zone.classList.remove('nudge'), 1600);
      zone.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      fileInput.click();
    });
  }
  // color pickers: update the preview while dragging, but not on every pixel
  let t; form.querySelectorAll('input[type=color]').forEach((c) => c.addEventListener('input', () => { clearTimeout(t); t = setTimeout(update, 250); }));
});

// Generic dropzones (mini-app photo/file fields, flyer upload): show a thumbnail and the file name
document.querySelectorAll('.step-drop').forEach((zone) => {
  const input = zone.querySelector('input[type=file]');
  const show = (file) => {
    if (!file) return;
    zone.classList.add('has-file');
    const thumb = zone.querySelector('.dz-thumb');
    thumb.innerHTML = /^image\//.test(file.type) ? '<img alt="" src="' + URL.createObjectURL(file) + '">' : '<b>PDF</b>';
    zone.querySelector('.dz-text b').textContent = file.name;
  };
  ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('drag'); }));
  ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('drag'); }));
  zone.addEventListener('drop', (e) => {
    const f = e.dataTransfer.files[0]; if (!f) return;
    const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files; show(f);
  });
  input.addEventListener('change', () => show(input.files[0]));
});

// Mini-app editor: type panels, presets, field rows
(() => {
  const form = document.getElementById('step-editor');
  if (!form) return;
  const el = (n) => form.elements.namedItem(n); // form.title etc. would hit built-in properties
  const panels = form.querySelectorAll('.type-panel');
  const rows = document.getElementById('field-rows');
  const showType = () => {
    const t = (form.querySelector('input[name=type]:checked') || {}).value;
    panels.forEach((p) => { p.hidden = p.dataset.type !== t; });
  };
  const renumber = () => rows.querySelectorAll('.field-row').forEach((row, i) => {
    row.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/fields\[\d+\]/, 'fields[' + i + ']'); });
  });
  const syncOpts = (row) => { row.querySelector('.opts').hidden = row.querySelector('select').value !== 'select'; };
  const addRow = (f) => {
    const tpl = rows.querySelector('.field-row');
    const row = tpl.cloneNode(true);
    row.querySelector('input[name$="[label]"]').value = f ? f.label : '';
    row.querySelector('select').value = f ? f.type : 'text';
    row.querySelector('input[type=checkbox]').checked = f ? !!f.required : false;
    row.querySelector('.opts').value = f && f.options ? f.options.join('\n') : '';
    rows.appendChild(row); syncOpts(row); renumber();
  };
  form.addEventListener('change', (e) => {
    if (e.target.name === 'type') showType();
    if (e.target.matches('.field-row select')) syncOpts(e.target.closest('.field-row'));
  });
  form.addEventListener('click', (e) => {
    if (e.target.id === 'add-field') { if (rows.children.length < 12) addRow(null); }
    if (e.target.classList.contains('rm-field')) {
      const row = e.target.closest('.field-row');
      if (rows.children.length > 1) row.remove(); else { row.querySelectorAll('input:not([type=checkbox]), textarea').forEach((i) => { i.value = ''; }); }
      renumber();
    }
    const key = e.target.dataset && e.target.dataset.preset;
    if (key && window.STEP_PRESETS[key]) {
      const p = window.STEP_PRESETS[key];
      form.querySelector('input[name=type][value=' + p.type + ']').checked = true;
      el('title').value = p.title; el('intro').value = p.intro || '';
      form.querySelector('input[name=required]').checked = !!p.required;
      const c = p.config;
      if (p.type === 'form') {
        while (rows.children.length > 1) rows.lastElementChild.remove();
        c.fields.forEach((f, i) => { if (i === 0) { const r = rows.firstElementChild; r.querySelector('input[name$="[label]"]').value = f.label; r.querySelector('select').value = f.type; r.querySelector('input[type=checkbox]').checked = !!f.required; r.querySelector('.opts').value = (f.options || []).join('\n'); syncOpts(r); } else addRow(f); });
        el('button').value = c.button || '';
      } else if (p.type === 'poll') {
        el('poll_options').value = c.options.join('\n'); el('show_results').checked = !!c.show_results;
      } else if (p.type === 'info') {
        el('button_label').value = c.button_label; el('button_url').value = c.button_url;
      } else if (p.type === 'page') {
        form.querySelectorAll('.link-row').forEach((r, i) => { const l = (c.links || [])[i]; r.children[0].value = l ? l.label : ''; r.children[1].value = l ? l.url : ''; });
      }
      showType();
      el('title').focus();
    }
  });
  rows.querySelectorAll('.field-row').forEach(syncOpts);
  showType();
})();

// New code: show the mini-app preset picker only when "Show a mini-app first" is chosen
document.querySelectorAll('.after-scan').forEach((box) => {
  const pick = box.querySelector('.after-preset');
  box.addEventListener('change', () => { pick.hidden = (box.querySelector('input[name=after]:checked') || {}).value !== 'mini'; });
});
