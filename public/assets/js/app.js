document.addEventListener('DOMContentLoaded', () => {
    const confirmModal = document.getElementById('confirmModal');
    const confirmText = document.getElementById('confirmText');
    const confirmButton = document.getElementById('confirmButton');
    let pendingForm = null;

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            pendingForm = form;
            confirmText.textContent = form.dataset.confirm;
            bootstrap.Modal.getOrCreateInstance(confirmModal).show();
        });
    });

    confirmButton?.addEventListener('click', () => {
        if (!pendingForm) {
            return;
        }
        pendingForm.dataset.confirmed = '1';
        pendingForm.submit();
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
        flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            altInputClass: 'in form-control',
            allowInput: true,
            disableMobile: true,
            monthSelectorType: 'static',
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
            });
            if (select.is('[data-submit]')) {
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
