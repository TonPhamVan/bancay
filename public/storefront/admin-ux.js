(() => {
    'use strict';
    if (window.bancayAdminUxLoaded) return;
    window.bancayAdminUxLoaded = true;
    const originals = new WeakMap();
    let consentUntil = 0;
    let pjaxConsent = false;
    let allowedLink = null;
    let allowedForm = null;
    let lastSafeUrl = location.href;
    let lastSafeState = history.state;
    const fingerprint = form => JSON.stringify([...new FormData(form)].filter(([key]) => key !== '_token')
        .map(([key, value]) => [key, value instanceof File ? (value.name ? `${value.name}:${value.size}:${value.lastModified}` : '') : value]));
    const dirtyForms = () => [...document.querySelectorAll('[data-watch-unsaved]')]
        .filter(form => form.dataset.unsavedInitial === 'true' || (originals.has(form) && originals.get(form) !== fingerprint(form)));
    const matrixDirty = () => !!document.querySelector('[data-variant-matrix][data-dirty="true"]');
    const hasChanges = () => dirtyForms().length > 0 || matrixDirty();
    function askLeave(accept) {
        if (document.querySelector('#bancay-unsaved-dialog')) return;
        const previousFocus = document.activeElement;
        const dialog = document.createElement('dialog');
        dialog.id = 'bancay-unsaved-dialog';
        dialog.setAttribute('aria-labelledby', 'bancay-unsaved-title');
        dialog.style.cssText = 'max-width:90vw;width:460px;border:1px solid #bacbbf;border-radius:8px;padding:24px;color:#24332b;background:white';
        const title = document.createElement('h3'); title.id = 'bancay-unsaved-title'; title.textContent = 'Có thay đổi chưa lưu';
        const message = document.createElement('p'); message.textContent = 'Rời trang sẽ bỏ các thay đổi chưa lưu. Bạn muốn tiếp tục hay ở lại chỉnh sửa?';
        const stay = document.createElement('button'); stay.type = 'button'; stay.className = 'btn btn-default'; stay.textContent = 'Ở lại chỉnh sửa';
        const leave = document.createElement('button'); leave.type = 'button'; leave.className = 'btn btn-danger'; leave.textContent = 'Tiếp tục rời trang'; leave.style.marginLeft = '8px';
        stay.addEventListener('click', () => dialog.close());
        leave.addEventListener('click', () => { dialog.close(); consentUntil = Date.now() + 1500; accept(); });
        dialog.addEventListener('close', () => { dialog.remove(); previousFocus?.focus(); });
        dialog.append(title, message, stay, leave); document.body.append(dialog); dialog.showModal(); stay.focus();
    }

    function combinationPreview() {
        const target = document.querySelector('[data-combination-preview]');
        if (!target) return;
        let combinations = 1;
        document.querySelectorAll('[data-option-editor]').forEach(editor => {
            const form = editor.closest('form');
            const text = editor.querySelector('[data-option-source]').value;
            if (!form.matches('[data-option-group]') && !text.trim() && !form.querySelector('[name=name]').value.trim()) return;
            const count = text.split(/\r\n|\r|\n/).filter(value => value.trim()).length;
            combinations = Math.min(257, combinations * count);
        });
        target.textContent = `${combinations > 256 ? 'Vượt giới hạn 256' : combinations} tổ hợp dự kiến từ các nhóm trên màn hình. Chỉ nhóm đã Lưu mới được dùng khi sinh SKU.`;
    }

    function row(editor, value) {
        const container = document.createElement('div');
        container.className = 'store-option-row';
        const input = document.createElement('input');
        input.type = 'text'; input.value = value; input.maxLength = 100; input.required = true;
        input.setAttribute('data-option-value', '');
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn btn-default'; remove.textContent = '×';
        remove.setAttribute('data-option-remove', '');
        container.append(input, remove);
        editor.querySelector('[data-option-rows]').append(container);
    }

    function syncEditor(editor, fromRows = true) {
        const source = editor.querySelector('[data-option-source]');
        const inputs = [...editor.querySelectorAll('[data-option-value]')];
        if (fromRows) source.value = inputs.map(input => input.value).join('\n');
        const linesMode = !source.hidden;
        editor.querySelector('[data-option-toggle]').textContent = linesMode ? 'Nhập từng giá trị' : 'Nhập nhiều dòng';
        inputs.forEach((input, index) => {
            input.disabled = linesMode;
            const label = `Giá trị ${index + 1} · ${editor.closest('form').querySelector('[name=name]').value || 'nhóm mới'}`;
            input.setAttribute('aria-label', label);
            input.parentElement.querySelector('button').setAttribute('aria-label', `Xóa ${label}`);
        });
        editor.querySelector('[data-option-count]').textContent = `${inputs.length} dòng giá trị · tối đa100`;
        editor.querySelector('[data-option-add]').disabled = inputs.length >= 100;
        combinationPreview();
    }

    function renderEditor(editor) {
        const source = editor.querySelector('[data-option-source]');
        editor.querySelector('[data-option-rows]').replaceChildren();
        const values = source.value === '' ? [''] : source.value.split(/\r\n|\r|\n/);
        const overflow = values.length > 100;
        editor.querySelector('[data-option-toggle]').disabled = overflow;
        if (overflow) {
            // Preserve the complete attempted text, but do not create an unbounded DOM from invalid input.
            source.hidden = false;
            editor.querySelector('[data-option-toggle]').textContent = 'Nhập từng giá trị';
            source.labels?.forEach(label => { label.hidden = false; });
            editor.querySelector('[data-option-rows]').hidden = true;
            editor.querySelector('[data-option-add]').hidden = true;
            editor.querySelector('[data-option-count]').textContent = `${values.length} dòng: vượt giới hạn100. Giảm số dòng trước khi chuyển sang nhập từng giá trị.`;
            combinationPreview(); return;
        }
        values.forEach(value => row(editor, value));
        syncEditor(editor, false);
    }

    function init() {
        lastSafeUrl = location.href; lastSafeState = history.state;
        document.querySelectorAll('[data-option-editor]:not([data-ready])').forEach(editor => {
            editor.dataset.ready = 'true';
            const source = editor.querySelector('[data-option-source]');
            const focus = source.hasAttribute('autofocus');
            source.required = false; source.hidden = true;
            source.labels?.forEach(label => { label.hidden = true; });
            editor.querySelector('[data-option-rows]').hidden = false;
            editor.querySelector('[data-option-add]').hidden = false;
            editor.querySelector('[data-option-toggle]').hidden = false;
            renderEditor(editor);
            if (focus) {
                const input = editor.querySelector('[data-option-value]') || source;
                input.setAttribute('aria-invalid', 'true'); input.focus();
            }
        });
        document.querySelectorAll('[data-image-order]').forEach(element => { element.hidden = false; });
        document.querySelectorAll('[data-watch-unsaved]').forEach(form => {
            if (!originals.has(form)) originals.set(form, fingerprint(form));
        });
        combinationPreview();
    }

    document.addEventListener('input', event => {
        const editor = event.target.closest('[data-option-editor]');
        if (editor) event.target.matches('[data-option-source]') ? renderEditor(editor) : syncEditor(editor);
        if (event.target.matches('[name=name]')) combinationPreview();
    });
    document.addEventListener('click', event => {
        const editor = event.target.closest('[data-option-editor]');
        if (editor && event.target.closest('[data-option-add]')) {
            if (editor.querySelectorAll('[data-option-value]').length >= 100) return;
            row(editor, ''); syncEditor(editor); editor.querySelector('[data-option-rows]').lastElementChild.querySelector('input').focus();
        }
        if (editor && event.target.closest('[data-option-remove]')) {
            event.target.closest('.store-option-row').remove();
            if (!editor.querySelector('[data-option-value]')) row(editor, '');
            syncEditor(editor); editor.querySelector('[data-option-value]').focus();
        }
        if (editor && event.target.closest('[data-option-toggle]')) {
            const source = editor.querySelector('[data-option-source]');
            source.hidden = !source.hidden;
            editor.querySelector('[data-option-rows]').hidden = !source.hidden;
            editor.querySelector('[data-option-add]').hidden = !source.hidden;
            source.labels?.forEach(label => { label.hidden = source.hidden; });
            event.target.textContent = source.hidden ? 'Nhập nhiều dòng' : 'Nhập từng giá trị';
            syncEditor(editor, false);
        }
        const move = event.target.closest('[data-image-up], [data-image-down]');
        if (move) {
            const item = move.closest('[data-image-row]');
            const sibling = move.matches('[data-image-up]') ? item.previousElementSibling : item.nextElementSibling;
            if (sibling) {
                move.matches('[data-image-up]') ? item.parentElement.insertBefore(item, sibling) : item.parentElement.insertBefore(sibling, item);
                [...item.parentElement.children].forEach((image, index) => { image.querySelector('[data-image-sort]').value = String(index); });
                move.focus();
            }
        }
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank'
            || link.hasAttribute('download') || link.hasAttribute('data-no-unsaved-guard')) return;
        if (link.getAttribute('href').startsWith('#')) return;
        const url = new URL(link.href, location.href);
        if (!['http:', 'https:'].includes(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        if (allowedLink === link) { allowedLink = null; return; }
        if (hasChanges()) {
            event.preventDefault(); event.stopImmediatePropagation();
            askLeave(() => { allowedLink = link; pjaxConsent = true; link.click(); });
        }
    }, true);
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.matches('[data-variant-matrix]')) return; // AJAX save leaves other forms in place.
        if (allowedForm === form) { allowedForm = null; return; }
        const othersDirty = dirtyForms().some(other => other !== form) || matrixDirty();
        if (othersDirty) {
            event.preventDefault(); event.stopImmediatePropagation();
            askLeave(() => { allowedForm = form; form.requestSubmit(event.submitter || undefined); }); return;
        }
        // Only a real submit can suppress the browser unload prompt; button clicks alone cannot.
        consentUntil = Date.now() + 1500;
    }, true);
    window.addEventListener('beforeunload', event => {
        if (Date.now() >= consentUntil && hasChanges()) { event.preventDefault(); event.returnValue = ''; }
    });
    if (window.jQuery) {
        window.jQuery(document).on('pjax:beforeSend.bancayAdminUx', (event, xhr, options) => {
            if (pjaxConsent) { pjaxConsent = false; return; }
            if (hasChanges()) {
                event.preventDefault();
                // popstate can update the address before the request; keep URL and visible form together on Cancel.
                if (location.href !== lastSafeUrl) history.replaceState(lastSafeState, '', lastSafeUrl);
                askLeave(() => { if (options?.url) location.assign(options.url); }); return false;
            }
        }).on('pjax:end.bancayAdminUx', init);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
