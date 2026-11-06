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
            window.setTimeout(() => item.remove(), 220);0
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

(function () {
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ios = () => {
        const ua = navigator.userAgent || '';
        return /iPhone|iPod|iPad/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    };
    const iosSafari = () => ios() && /Safari/i.test(navigator.userAgent || '') && !/CriOS|FxiOS|EdgiOS|OPiOS/i.test(navigator.userAgent || '');
    let deferredPrompt = null;

    const buttons = () => document.querySelectorAll('[data-pwa-install]');
    const reveal = () => {
        if (standalone()) {
            return;
        }
        buttons().forEach((button) => {
            button.hidden = false;
        });
    };
    const hide = () => {
        buttons().forEach((button) => {
            button.hidden = true;
        });
        const sheet = document.getElementById('pwaSheet');
        if (sheet) {
            sheet.hidden = true;
        }
    };
    const closeSheet = () => {
        const sheet = document.getElementById('pwaSheet');
        if (sheet) {
            sheet.hidden = true;
        }
    };
    const openIosSheet = () => {
        const sheet = document.getElementById('pwaSheet');
        const steps = document.getElementById('pwaSteps');
        if (!sheet || !steps) {
            return;
        }
        const lines = iosSafari()
            ? ['Tap the Share button in Safari.', 'Tap Add to Home Screen.', 'Tap Add.']
            : ['Open this page in Safari.', 'Tap the Share button.', 'Tap Add to Home Screen, then Add.'];
        steps.replaceChildren(...lines.map((text) => {
            const item = document.createElement('li');
            item.textContent = text;
            return item;
        }));
        sheet.hidden = false;
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        reveal();
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hide();
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-pwa-close]') || event.target.id === 'pwaSheet') {
            closeSheet();
            return;
        }
        const opener = event.target.closest('[data-pwa-install]');
        if (!opener) {
            return;
        }
        event.preventDefault();
        if (deferredPrompt) {
            const prompt = deferredPrompt;
            deferredPrompt = null;
            prompt.prompt();
            prompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    hide();
                }
            });
            return;
        }
        if (ios()) {
            openIosSheet();
            return;
        }
        if (window.toast) {
            window.toast('Use the browser menu to install HRMS.', 'info');
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        if (standalone()) {
            hide();
            return;
        }
        if (ios()) {
            reveal();
        }
    });
})();

document.addEventListener('show.bs.modal', (event) => {
    const modal = event.target;
    if (!modal || !modal.classList || !modal.classList.contains('modal')) {
        return;
    }
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a[data-back]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const ref = document.referrer;
            if (!ref || window.history.length < 2) {
                return;
            }
            try {
                const from = new URL(ref);
                if (from.origin !== window.location.origin || from.pathname === window.location.pathname) {
                    return;
                }
                event.preventDefault();
                window.history.back();
            } catch (error) {
                return;
            }
        });
    });
    const moreBtn = document.getElementById('tabMoreBtn');
    const moreSheet = document.getElementById('tabMore');
    if (moreBtn && moreSheet) {
        moreBtn.addEventListener('click', () => {
            const open = moreSheet.classList.toggle('open');
            moreSheet.hidden = !open;
            moreBtn.classList.toggle('on', open);
            moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    }

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

    window.askConfirm = function askConfirm(message, options) {
        const settings = typeof options === 'string' ? { label: options } : (options || {});
        return new Promise((resolve) => {
            pendingForm = null;
            pendingResolve = resolve;
            if (confirmButton) {
                confirmButton.textContent = settings.label || 'Continue';
                confirmButton.classList.toggle('is-danger', Boolean(settings.danger));
            }
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
        if (confirmButton) {
            confirmButton.textContent = 'Continue';
            confirmButton.classList.remove('is-danger');
        }
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

    const themeButtons = document.querySelectorAll('[data-theme-set]');
    const paintTheme = (theme) => {
        const next = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('hrms-theme', next);
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', next === 'dark' ? '#141216' : '#E10613');
        }
        themeButtons.forEach((button) => {
            button.classList.toggle('on', button.getAttribute('data-theme-set') === next);
        });
    };
    paintTheme(document.documentElement.getAttribute('data-theme') || 'light');
    themeButtons.forEach((button) => {
        button.addEventListener('click', () => paintTheme(button.getAttribute('data-theme-set')));
    });

    document.getElementById('mb')?.addEventListener('click', () => {
        document.body.classList.toggle(window.innerWidth > 900 ? 'col' : 'mo');
    });
    document.body.addEventListener('click', (event) => {
        if (event.target === document.body && document.body.classList.contains('mo')) {
            document.body.classList.remove('mo');
        }
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
                    if (select.is('[data-parent-list], [data-role-list]')) {
                        const menu = document.querySelector('.select2-container--open .select2-dropdown');
                        const holder = document.querySelector('body > .select2-container--open');
                        const field = select.next('.select2-container')[0];
                        menu?.classList.add('parent-menu');
                        menu?.classList.remove('select2-dropdown--above');
                        menu?.classList.add('select2-dropdown--below');
                        if (holder && field) {
                            const rect = field.getBoundingClientRect();
                            holder.style.top = `${rect.bottom + window.scrollY}px`;
                            holder.style.left = `${rect.left + window.scrollX}px`;
                            holder.style.width = `${rect.width}px`;
                            if (menu) {
                                menu.style.width = `${rect.width}px`;
                            }
                        }
                    }
                    const search = document.querySelector('.select2-container--open .select2-search__field');
                    search?.focus({ preventScroll: true });
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

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const badges = document.querySelectorAll('[data-notif-count]');
    const paintBadges = (count) => {
        badges.forEach((el) => {
            el.textContent = count > 99 ? '99+' : String(count);
            el.classList.toggle('show', count > 0);
        });
    };
    const refreshNotifications = async () => {
        if (!badges.length) {
            return;
        }
        try {
            const res = await fetch('/notifications/unread-count', { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                return;
            }
            const data = await res.json();
            paintBadges(Number(data.count || 0));
        } catch (_) {}
    };
    if (badges.length) {
        refreshNotifications();
        window.setInterval(refreshNotifications, 45000);
    }
});
