(() => {
    'use strict';
    if (window.bancayVariantMatrixLoaded) return;
    window.bancayVariantMatrixLoaded = true;
    const states = new WeakMap();
    const fields = ['sku', 'pricing_type', 'price', 'sale_price', 'image_id', 'quantity', 'status'];
    const rows = form => [...form.querySelectorAll('[data-matrix-row]')];
    const state = form => {
        if (!states.has(form)) states.set(form, new Map(JSON.parse(form.dataset.rows).map(row => [row.id, row])));
        return states.get(form);
    };
    const values = row => Object.fromEntries(fields.map(field => [field, row.querySelector(`[data-field="${field}"]`).value.trim()]));
    const changed = (form, row) => fields.some(field => values(row)[field] !== String(state(form).get(row.dataset.matrixRow)[field] ?? ''));
    const dirty = form => rows(form).filter(row => changed(form, row));
    function update(form) {
        const selected = rows(form).filter(row => row.querySelector('[data-matrix-select]').checked).length;
        const count = dirty(form).length;
        form.querySelector('[data-bulk-apply]').textContent = `Áp dụng cho ${selected} dòng đã chọn`;
        form.querySelector('[data-bulk-apply]').disabled = selected === 0;
        form.querySelector('[data-matrix-save]').textContent = `Lưu ${count} dòng thay đổi`;
        form.querySelector('[data-matrix-save]').disabled = count === 0;
        form.querySelector('[data-matrix-reset]').disabled = count === 0;
        form.querySelector('[data-matrix-select-all]').checked = selected === rows(form).length;
        form.querySelector('[data-matrix-select-all]').indeterminate = selected > 0 && selected < rows(form).length;
        rows(form).forEach(row => row.classList.toggle('matrix-changed', changed(form, row)));
    }
    function status(form, message, error = false) {
        const target = form.querySelector('[data-matrix-status]');
        target.textContent = message;
        target.className = error ? 'alert alert-danger' : 'alert alert-info';
    }
    function normalize(row, field) {
        if (field === 'pricing_type' && row.querySelector('[data-field=pricing_type]').value === 'CONTACT') {
            row.querySelector('[data-field=price]').value = '';
            row.querySelector('[data-field=sale_price]').value = '';
        } else if (field === 'price' && row.querySelector('[data-field=price]').value.trim() !== '') {
            row.querySelector('[data-field=pricing_type]').value = 'FIXED';
        }
    }
    function clearErrors(form) {
        form.querySelectorAll('[data-row-errors]').forEach(el => { el.textContent = ''; });
        form.querySelectorAll('[aria-invalid=true]').forEach(el => el.removeAttribute('aria-invalid'));
    }
    function applyRows(form, data) {
        states.set(form, new Map(data.map(row => [row.id, row])));
        rows(form).forEach(row => {
            const saved = state(form).get(row.dataset.matrixRow);
            if (!saved) return;
            fields.forEach(field => { row.querySelector(`[data-field="${field}"]`).value = saved[field] ?? ''; });
            row.querySelector('[data-reserved]').textContent = saved.reserved;
            row.querySelector('[data-available]').textContent = saved.available;
        });
        update(form);
    }
    document.addEventListener('input', event => {
        const form = event.target.closest('[data-variant-matrix]');
        const row = event.target.closest('[data-matrix-row]');
        if (!form || !row) return;
        normalize(row, event.target.dataset.field);
        update(form);
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('[data-variant-matrix]');
        if (!form) return;
        if (event.target.matches('[data-matrix-select-all]')) {
            rows(form).forEach(row => { row.querySelector('[data-matrix-select]').checked = event.target.checked; });
        }
        if (event.target.matches('[data-bulk-field]')) {
            const field = event.target.value;
            const select = form.querySelector('[data-bulk-choice]');
            const choices = field === 'status' ? [['ACTIVE', 'Hoạt động'], ['INACTIVE', 'Tạm ngưng']]
                : field === 'pricing_type' ? [['FIXED', 'Giá cố định'], ['CONTACT', 'Liên hệ']] : [];
            select.replaceChildren(...choices.map(([value, label]) => new Option(label, value)));
            select.hidden = choices.length === 0;
            form.querySelector('[data-bulk-value]').hidden = choices.length > 0;
        }
        const row = event.target.closest('[data-matrix-row]');
        if (row) normalize(row, event.target.dataset.field);
        update(form);
    });
    document.addEventListener('click', event => {
        const form = event.target.closest('[data-variant-matrix]');
        if (!form) return;
        if (event.target.closest('[data-matrix-reset]')) {
            clearErrors(form);
            applyRows(form, [...state(form).values()]);
            status(form, 'Đã hoàn tác các thay đổi chưa lưu.');
        }
        if (event.target.closest('[data-bulk-apply]')) {
            const selected = rows(form).filter(row => row.querySelector('[data-matrix-select]').checked);
            const field = form.querySelector('[data-bulk-field]').value;
            const value = ['status', 'pricing_type'].includes(field) ? form.querySelector('[data-bulk-choice]').value : form.querySelector('[data-bulk-value]').value.trim();
            if (!selected.length) return;
            selected.forEach(row => { row.querySelector(`[data-field="${field}"]`).value = value; normalize(row, field); });
            update(form);
            status(form, `Đã điền ${selected.length} dòng đã chọn. Kiểm tra bảng và nhấn Lưu để ghi nhận.`);
        }
    });
    document.addEventListener('submit', async event => {
        const form = event.target.closest('[data-variant-matrix]');
        if (!form) return;
        event.preventDefault();
        if (form.dataset.saving === 'true') return;
        const submitted = dirty(form);
        if (!submitted.length) return;
        if (submitted.length > 256) { status(form, 'Chỉ lưu tối đa 256 dòng mỗi lần.', true); return; }
        const payload = submitted.map(row => {
            const initial = state(form).get(row.dataset.matrixRow);
            const current = values(row);
            return {...current, id: initial.id, version: initial.version, expected_quantity: initial.quantity,
                price: current.price || null, sale_price: current.sale_price || null, image_id: current.image_id || null};
        });
        const controls = form.querySelector('[data-matrix-controls]');
        clearErrors(form);
        form.dataset.saving = 'true';
        controls.disabled = true;
        status(form, 'Đang lưu các SKU…');
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value},
                body: JSON.stringify({variants: payload, stock_reason: form.querySelector('[data-stock-reason]').value.trim() || null}),
            });
            if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                status(form, 'Không nhận được phản hồi hợp lệ. Kiểm tra phiên đăng nhập và tải lại trang trước khi lưu lại.', true);
                return;
            }
            const result = await response.json();
            if (!response.ok) {
                const errors = result.errors || {request: [response.status === 419 ? 'Phiên đã hết hạn. Tải lại trang.' : 'Không lưu được dữ liệu. Kiểm tra quyền và tải lại trang.']};
                const messages = [];
                Object.entries(errors).forEach(([key, list]) => {
                    const match = key.match(/^variants\.(\d+)\.(.+)$/);
                    const row = match ? submitted[Number(match[1])] : null;
                    const text = (Array.isArray(list) ? list : [String(list)]).join(' ');
                    messages.push((row ? state(form).get(row.dataset.matrixRow).label + ': ' : '') + text);
                    if (row) {
                        row.querySelector('[data-row-errors]').textContent += text + ' ';
                        const field = fields.includes(match[2]) ? match[2] : 'sku';
                        row.querySelector(`[data-field="${field}"]`).setAttribute('aria-invalid', 'true');
                    }
                    if (key === 'stock_reason') form.querySelector('[data-stock-reason]').setAttribute('aria-invalid', 'true');
                });
                status(form, messages.join(' '), true);
                form.querySelector('[data-matrix-status]').focus();
                return;
            }
            applyRows(form, result.rows);
            form.querySelector('[data-stock-reason]').value = '';
            status(form, result.message);
        } catch (_) {
            status(form, 'Chưa xác định được kết quả do kết nối bị gián đoạn. Tải lại trang để kiểm tra trước khi lưu lại.', true);
        } finally {
            controls.disabled = false;
            form.dataset.saving = 'false';
            update(form);
        }
    });
    window.addEventListener('beforeunload', event => {
        if ([...document.querySelectorAll('[data-variant-matrix]')].some(form => dirty(form).length)) {
            event.preventDefault(); event.returnValue = '';
        }
    });
})();
