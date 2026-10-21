(function () {
    const host = () => document.getElementById('toastHost');
    const icons = {
        ok: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        er: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
        wn: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
        info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    };

    window.toast = function toast(message, type = 'ok', duration = 4200) {
        const root = host();
        if (!root || !message) {
            return;
        }
        const kind = icons[type] ? type : 'info';
        const item = document.createElement('div');
        item.className = `toast-item ${kind}`;
        item.setAttribute('role', 'status');

        const ico = document.createElement('span');
        ico.className = 'toast-ico';
        ico.innerHTML = icons[kind];

        const copy = document.createElement('div');
        copy.className = 'toast-copy';
        const text = document.createElement('p');
        text.textContent = String(message);
        copy.appendChild(text);

        const close = document.createElement('button');
        close.className = 'toast-x';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';

        item.style.setProperty('--toast-ms', `${Math.max(1200, duration)}ms`);
        item.append(ico, copy, close);

        const dismiss = () => {
            if (item.classList.contains('out')) {
                return;
            }
            item.classList.add('out');
            window.setTimeout(() => item.remove(), 220);
        };
        close.addEventListener('click', dismiss);
        root.appendChild(item);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => item.classList.add('in'));
        });
        if (duration > 0) {
            window.setTimeout(dismiss, duration);
        }
        return item;
    };
})();

document.addEventListener('DOMContentLoaded', () => {
    (window.__TOASTS__ || []).forEach((item) => {
        window.toast(item.message, item.type || 'ok');
    });

    const confirmModal = document.getElementById('confirmModal');
    const confirmText = document.getElementById('confirmText');
    const confirmButton = document.getElementById('confirmButton');
    let pendingForm = null;
    let pendingResolve = null;

    const showConfirm = (message) => {
        if (confirmText) {
            confirmText.textContent = message;
        }
        if (confirmModal) {
            bootstrap.Modal.getOrCreateInstance(confirmModal).show();
        }
    };

    window.askConfirm = function askConfirm(message) {
        return new Promise((resolve) => {
            pendingForm = null;
            pendingResolve = resolve;
            showConfirm(message || 'Please confirm');
        });
    };

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            pendingResolve = null;
            pendingForm = form;
            showConfirm(form.dataset.confirm || 'Please confirm');
        });
    });

    confirmButton?.addEventListener('click', () => {
        if (pendingForm) {
            const form = pendingForm;
            pendingForm = null;
            if (form.dataset.confirmed === '1') {
                return;
            }
            form.dataset.confirmed = '1';
            const submitted = new Event('submit', { bubbles: true, cancelable: true });
            if (form.dispatchEvent(submitted)) {
                form.submit();
            }
            return;
        }
        if (pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            bootstrap.Modal.getOrCreateInstance(confirmModal).hide();
            resolve(true);
        }
    });

    confirmModal?.addEventListener('hidden.bs.modal', () => {
        if (pendingResolve) {
            const resolve = pendingResolve;
            pendingResolve = null;
            resolve(false);
        }
        pendingForm = null;
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button) {
                return;
            }
            window.setTimeout(() => {
                button.disabled = true;
            }, 0);
        });
    });

    const filterCompany = (select) => {
        const companyId = select.value;
        document.querySelectorAll('[data-depends-on="company"]').forEach((child) => {
            let selectedStillVisible = false;
            Array.from(child.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                const visible = !companyId || option.dataset.company === companyId;
                option.hidden = !visible;
                if (visible && option.selected) {
                    selectedStillVisible = true;
                }
            });
            if (!selectedStillVisible) {
                child.value = '';
            }
            if (window.jQuery && jQuery(child).data('select2')) {
                jQuery(child).trigger('change.select2');
            }
        });

        document.querySelectorAll('[data-company-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.companyPanel !== companyId;
        });
    };

    document.querySelectorAll('[data-company-filter]').forEach((select) => {
        select.addEventListener('change', () => filterCompany(select));
        filterCompany(select);
    });

    const categoryField = document.getElementById('designationCategory');
    const designationSelect = document.querySelector('[name="designation_id"]');
    const showCategory = () => {
        if (!categoryField || !designationSelect) {
            return;
        }
        const option = designationSelect.selectedOptions[0];
        categoryField.value = option?.dataset.category || '';
    };
    designationSelect?.addEventListener('change', showCategory);
    showCategory();

    document.getElementById('mb')?.addEventListener('click', () => {
        document.body.classList.toggle(window.innerWidth > 900 ? 'col' : 'mo');
    });

    document.querySelectorAll('.gh').forEach((button) => {
        button.addEventListener('click', () => button.classList.toggle('cl'));
    });

    document.querySelectorAll('[data-dd]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = document.getElementById(button.dataset.dd);
            document.querySelectorAll('.dw').forEach((item) => {
                if (item !== menu) {
                    item.classList.remove('open');
                }
            });
            menu?.classList.toggle('open');
        });
    });

    const search = document.getElementById('q');
    const results = document.getElementById('sres');
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        const pages = [...document.querySelectorAll('.nv .ni')];
        const matches = pages.filter((link) => (link.dataset.page || link.textContent).toLowerCase().includes(term));
        results.innerHTML = term
            ? (matches.map((link) => `<a href="${link.getAttribute('href')}">${link.querySelector('.lb')?.textContent || ''}</a>`).join('') || '<div class="nt"><small>No results</small></div>')
            : '';
        search.closest('.dw')?.classList.toggle('open', term.length > 0);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.dw')) {
            document.querySelectorAll('.dw').forEach((item) => item.classList.remove('open'));
        }
    });

    document.querySelectorAll('input[type="date"]').forEach((input) => {
        if (input.dataset.picked || typeof flatpickr !== 'function') {
            return;
        }
        input.dataset.picked = '1';
        const modal = input.closest('.modal');
        const inline = input.dataset.inlineCalendar === '1';
        flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            altInputClass: 'in form-control',
            allowInput: true,
            allowInvalidPreload: true,
            disableMobile: true,
            monthSelectorType: 'static',
            inline,
            static: inline,
            appendTo: modal || undefined,
            ignoreReadonly: true,
        });
    });

    document.querySelectorAll('input[type="time"]').forEach((input) => {
        if (input.dataset.picked || typeof flatpickr !== 'function') {
            return;
        }
        input.dataset.picked = '1';
        flatpickr(input, {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            altInput: true,
            altFormat: 'h:i K',
            altInputClass: 'in form-control',
            time_24hr: false,
            minuteIncrement: 5,
            disableMobile: true,
        });
    });

    if (window.jQuery && jQuery.fn.select2) {
        jQuery('select').not('[data-native]').each(function initSelect() {
            const select = jQuery(this);
            if (select.data('select2') || select.prop('multiple') && select.closest('.tw').length) {
                return;
            }
            const blank = select.find('option[value=""]').first();
            select.select2({
                width: '100%',
                dropdownParent: jQuery(document.body),
                minimumResultsForSearch: select.find('option').length > 8 ? 0 : Infinity,
                placeholder: blank.length ? blank.text() : undefined,
                scrollAfterSelect: false,
            });
            select.on('select2:open', () => {
                window.requestAnimationFrame(() => {
                    const field = document.querySelector('.select2-container--open .select2-search__field');
                    field?.focus({ preventScroll: true });
                });
            });
            if (select.is('[data-submit]') && ! select.closest('[data-ajax-filter]').length) {
                select.on('change', function submitFilter() {
                    this.form?.submit();
                });
            }
        });
    }

    document.querySelectorAll('input[type="file"]').forEach((input) => {
        if (input.closest('.file')) {
            return;
        }
        const wrap = document.createElement('label');
        wrap.className = 'file';
        const mark = document.createElement('i');
        mark.textContent = '+';
        const copy = document.createElement('span');
        const title = document.createElement('b');
        title.textContent = 'Choose a file';
        const hint = document.createElement('small');
        hint.textContent = 'or drop it here';
        copy.append(title, hint);
        input.parentNode.insertBefore(wrap, input);
        wrap.append(input, mark, copy);
        const show = () => {
            title.textContent = input.files?.[0]?.name || 'Choose a file';
            hint.textContent = input.files?.length ? 'Ready to upload' : 'or drop it here';
        };
        input.addEventListener('change', show);
        ['dragover', 'dragleave', 'drop'].forEach((name) => {
            wrap.addEventListener(name, (event) => {
                event.preventDefault();
                wrap.classList.toggle('over', name === 'dragover');
                if (name === 'drop' && event.dataTransfer?.files?.length) {
                    input.files = event.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    });
});
