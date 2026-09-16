(function () {
    'use strict';
    const panel = document.getElementById('procurementStockReview');
    if (!panel) return;
    const form = document.getElementById('divisionRequestForm');
    const lines = document.getElementById('fieldLinesJson');
    const hidden = document.getElementById('stockReviewJson');
    const status = panel.querySelector('[data-stock-status]');
    const rows = panel.querySelector('[data-stock-rows]');
    const confirmation = panel.querySelector('[data-stock-confirmation]');
    const contact = panel.querySelector('[data-stock-contact]');
    const reason = panel.querySelector('[data-stock-reason]');
    const confirmed = panel.querySelector('[data-stock-confirmed]');
    const refresh = panel.querySelector('[data-stock-refresh]');
    const verifying = panel.dataset.verify === '1';
    let current = null, lastInput = '', generation = 0, timer = null;
    function payload() {
        return {request_id:Number(panel.dataset.requestId), header:{division_id:form.elements.namedItem('division_id').value,
            destination_type:form.elements.namedItem('destination_type').value}, lines:JSON.parse(lines.value || '[]')};
    }
    function clear() {
        current = null; hidden.value = ''; generation++;
        if (confirmed) confirmed.checked = false;
        if (contact) contact.value = '';
        if (reason) reason.value = '';
        if (confirmation) confirmation.hidden = true;
        rows.replaceChildren();
    }
    function text(tag, value, className) {
        const el = document.createElement(tag); el.textContent = value;
        if (className) el.className = className;
        return el;
    }
    function quantity(stock, unit) {
        return stock.qty === null ? 'Belum diketahui' : Number(stock.qty).toLocaleString('id-ID',{maximumFractionDigits:4})+' '+unit;
    }
    function display(data) {
        rows.replaceChildren();
        data.rows.forEach(function (row) {
            const card = text('div','', 'col-12 col-lg-6');
            const content = text('div','', 'border rounded p-3 h-100');
            content.append(text('strong',row.line+'. '+row.name));
            content.append(text('div','Pengajuan: '+Number(row.requested).toLocaleString('id-ID',{maximumFractionDigits:4})+' '+row.uom));
            content.append(text('div','Stok divisi: '+quantity(row.division,row.uom),'fw-semibold'));
            content.append(text('div','Stok gudang: '+quantity(row.warehouse,row.uom),'fw-semibold'));
            content.append(text('div',row.division.message+' '+row.warehouse.message,'small text-muted'));
            content.append(text('div','Update sumber divisi: '+(row.division.latest_at || 'tidak tersedia')+' · gudang: '+(row.warehouse.latest_at || 'tidak tersedia'),'small text-muted'));
            if (row.needs_confirmation) content.append(text('div','Perlu konfirmasi kebutuhan','text-warning fw-semibold'));
            card.append(content); rows.append(card);
        });
        if (confirmation) confirmation.hidden = !data.needs_confirmation;
        status.textContent = data.has_materials ? 'Diperiksa: '+data.checked_at+'. Tinjau saldo sebelum verifikasi.' : 'Tidak ada bahan baku/material yang perlu ditinjau pada pengajuan ini.';
        if (verifying && data.has_materials && !data.ready) status.textContent += ' Penyimpanan bukti belum aktif: admin perlu memasang SQL 2026-09-16a. Verifikasi bahan baku belum dapat disimpan.';
    }
    async function load() {
        clearTimeout(timer); clear();
        let input;
        try { input = payload(); } catch (_) { status.textContent='Data baris belum siap.'; return; }
        lastInput = JSON.stringify(input);
        if (!input.lines.length || !Number(input.header.division_id) || !input.header.destination_type) {
            status.textContent='Tambahkan barang dan pilih divisi/lokasi untuk memeriksa stok.'; return;
        }
        const mine = generation; refresh.disabled = true; status.textContent='Memeriksa stok divisi dan gudang…';
        try {
            const response = await fetch(panel.dataset.url,{method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json','X-Procurement-Mutation-Csrf':panel.dataset.csrf},body:lastInput});
            const result = await response.json();
            if (mine !== generation) return;
            if (!response.ok || !result.ok || !result.data) throw new Error(result.message || 'Cek stok belum tersedia.');
            current = result.data; display(current);
        } catch (error) {
            if (mine === generation) { current = null; status.textContent=error.message || 'Stok belum diketahui. Coba perbarui cek stok.'; }
        } finally { if (mine === generation) refresh.disabled = false; }
    }
    function changed() {
        let value;
        try { value = JSON.stringify(payload()); } catch (_) { value = ''; }
        if (value === lastInput) return;
        lastInput = value; clear(); refresh.disabled = false;
        status.textContent='Pengajuan berubah. Pemeriksaan stok dan konfirmasi sebelumnya dibatalkan.';
        clearTimeout(timer); timer = setTimeout(load,450);
    }
    refresh.addEventListener('click',load);
    form.addEventListener('change',changed);
    form.addEventListener('input',changed);
    document.addEventListener('procurement-lines-changed',changed);
    form.addEventListener('submit',function (event) {
        if (!verifying) return;
        let unchanged = false;
        try { unchanged = JSON.stringify(payload()) === lastInput; } catch (_) {}
        let message = '';
        if (!current || !unchanged) message='Perbarui cek stok sebelum verifikasi.';
        else if (current.has_materials && (!current.ready || !current.token)) message='Pencatatan konfirmasi stok belum aktif atau tinjauan tidak valid.';
        else if (current.needs_confirmation && (!confirmed.checked || contact.value.trim().length<3 || reason.value.trim().length<10)) message='Konfirmasi ke divisi, isi nama dan alasan minimal 10 karakter, lalu centang pernyataan konfirmasi.';
        if (message) {
            event.preventDefault(); event.stopImmediatePropagation(); status.textContent=message;
            panel.scrollIntoView({block:'center',behavior:'smooth'}); return;
        }
        hidden.value = JSON.stringify({token:current.token,confirmed:confirmed ? confirmed.checked : false,
            confirmed_with:contact ? contact.value.trim() : '',reason:reason ? reason.value.trim() : ''});
    },true);
    load();
}());
