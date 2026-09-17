/* PharmaERP progressive-enhancement JS: confirm dialogs, sidebar, dynamic rows, product autocomplete + batch loading, permission matrix toggles. */
(function () {
  'use strict';
  const base = document.querySelector('meta[name="base-url"]')?.content || '/';
  const url = (p) => base.replace(/\/$/, '') + '/' + p.replace(/^\//, '');

  // Sidebar
  document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('show'));
  document.querySelectorAll('.erp-nav-toggle').forEach(b => b.addEventListener('click', () => b.parentElement.classList.toggle('open')));

  // Confirm for destructive forms
  const modalEl = document.getElementById('confirmModal');
  let pendingForm = null;
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!f.classList.contains('needs-confirm') || f.dataset.confirmed === '1' || !modalEl) return;
    e.preventDefault();
    pendingForm = f;
    document.getElementById('confirmModalBody').textContent = f.dataset.confirm || 'Lanjutkan?';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
  document.getElementById('confirmModalOk')?.addEventListener('click', () => {
    if (!pendingForm) return;
    pendingForm.dataset.confirmed = '1';
    bootstrap.Modal.getInstance(modalEl).hide();
    pendingForm.requestSubmit();
  });

  // Dynamic rows (clone last row, reindex names)
  document.querySelectorAll('[data-add-row]').forEach(btn => btn.addEventListener('click', () => {
    const tbody = document.querySelector(btn.dataset.addRow + ' tbody');
    const rows = tbody.querySelectorAll('tr');
    const clone = rows[rows.length - 1].cloneNode(true);
    const idx = rows.length;
    clone.querySelectorAll('input,select').forEach(el => {
      el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
      if (el.type === 'checkbox') el.checked = false; else if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
      if (el.dataset.testid) el.dataset.testid = el.dataset.testid.replace(/-\d+$/, '-' + idx);
    });
    clone.querySelectorAll('.js-batch').forEach(s => s.innerHTML = s.options[0].outerHTML);
    tbody.appendChild(clone);
    clone.querySelector('input:not([type=hidden])')?.focus();
  }));
  document.addEventListener('click', (e) => {
    const rm = e.target.closest('.row-remove');
    if (!rm) return;
    const tbody = rm.closest('tbody');
    if (tbody.querySelectorAll('tr').length > 1) rm.closest('tr').remove(); else rm.closest('tr').querySelectorAll('input:not([type=hidden])').forEach(i => i.value = '');
  });

  // Product autocomplete (name / SKU / barcode scan → Enter)
  let acBox = null, acTimer = null;
  const closeAc = () => { acBox?.remove(); acBox = null; };
  document.addEventListener('input', (e) => {
    const inp = e.target;
    if (!inp.classList.contains('js-product-search')) return;
    const row = inp.closest('tr');
    row.querySelector('.js-product-id').value = '';
    clearTimeout(acTimer);
    const q = inp.value.trim();
    if (q.length < 2) return closeAc();
    acTimer = setTimeout(async () => {
      const r = await fetch(url('master/products/search?q=' + encodeURIComponent(q)), { headers: { 'Accept': 'application/json' } });
      const { data } = await r.json();
      closeAc();
      if (!data?.length) return;
      acBox = document.createElement('div');
      acBox.className = 'erp-autocomplete';
      data.forEach((p, i) => {
        const d = document.createElement('div');
        d.innerHTML = '<strong>' + esc(p.name) + '</strong> <span class="text-muted">' + esc(p.sku) + (p.strength ? ' · ' + esc(p.strength) : '') + '</span>';
        d.addEventListener('mousedown', () => pick(row, inp, p));
        if (i === 0) d.classList.add('active');
        acBox.appendChild(d);
      });
      const rect = inp.getBoundingClientRect();
      acBox.style.left = (rect.left + window.scrollX) + 'px'; acBox.style.top = (rect.bottom + window.scrollY) + 'px';
      document.body.appendChild(acBox);
      if (data.length === 1 && data[0].barcode === q) pick(row, inp, data[0]); // barcode exact hit
    }, 180);
  });
  document.addEventListener('keydown', (e) => {
    if (!acBox) return;
    const items = [...acBox.children]; let i = items.findIndex(x => x.classList.contains('active'));
    if (e.key === 'ArrowDown') { e.preventDefault(); items[i]?.classList.remove('active'); items[Math.min(i + 1, items.length - 1)].classList.add('active'); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[i]?.classList.remove('active'); items[Math.max(i - 1, 0)].classList.add('active'); }
    else if (e.key === 'Enter') { e.preventDefault(); items[i]?.dispatchEvent(new Event('mousedown')); }
    else if (e.key === 'Escape') closeAc();
  });
  document.addEventListener('click', (e) => { if (acBox && !acBox.contains(e.target)) closeAc(); });

  async function pick(row, inp, p) {
    row.querySelector('.js-product-id').value = p.id;
    inp.value = p.sku + ' — ' + p.name;
    row.querySelector('.js-product-label').value = inp.value;
    closeAc();
    const sel = row.querySelector('.js-batch');
    if (sel && p.is_batch_tracked == 1) {
      const wh = document.getElementById('warehouseSelect')?.value;
      const r = await fetch(url('inventory/stock/fefo?product_id=' + p.id + '&warehouse_id=' + (wh || 0) + '&qty=999999999'), { headers: { 'Accept': 'application/json' } });
      const { data } = await r.json();
      sel.innerHTML = sel.options[0].outerHTML;
      (data?.allocations || []).forEach(a => {
        const o = document.createElement('option');
        o.value = a.batch_id; o.textContent = a.batch_no + ' · ED ' + (a.expiry_date || '-') + ' · tersedia ' + a.qty;
        sel.appendChild(o);
      });
    }
    row.querySelector('input[name*="qty"]')?.focus();
  }
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Permission matrix column/row toggles
  document.querySelectorAll('.perm-col').forEach(th => th.addEventListener('click', () => {
    const boxes = document.querySelectorAll('input[data-action="' + th.dataset.action + '"]');
    const all = [...boxes].every(b => b.checked); boxes.forEach(b => b.checked = !all);
  }));
  document.querySelectorAll('.perm-row').forEach(td => td.addEventListener('click', () => {
    const boxes = td.parentElement.querySelectorAll('input[type=checkbox]');
    const all = [...boxes].every(b => b.checked); boxes.forEach(b => b.checked = !all);
  }));

  // Inline master CRUD edit
  document.querySelectorAll('.js-edit-row').forEach(b => b.addEventListener('click', () => {
    const row = JSON.parse(b.dataset.row); const f = document.getElementById('masterForm'); if (!f) return;
    document.getElementById('formTitle').textContent = 'Ubah #' + row.id;
    Object.entries(row).forEach(([k, v]) => { const el = f.elements[k]; if (!el) return; if (el.type === 'checkbox') el.checked = !!Number(v); else el.value = v ?? ''; });
    f.querySelector('input,select')?.focus();
  }));
  document.getElementById('formReset')?.addEventListener('click', () => { document.getElementById('formTitle').textContent = 'Tambah'; document.getElementById('masterForm').elements['id'].value = ''; });

  // Keyboard: Alt+N = primary "new" button, "/" = focus first filter input
  document.addEventListener('keydown', (e) => {
    if (e.altKey && e.key.toLowerCase() === 'n') document.querySelector('[data-testid$="-create-btn"]')?.click();
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); document.querySelector('.erp-filter input')?.focus(); }
  });
})();
