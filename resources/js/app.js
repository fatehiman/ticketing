import * as bootstrap from 'bootstrap';
import '@majidh1/jalalidatepicker';
import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css';

window.bootstrap = bootstrap;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const isRtl = document.documentElement.dir === 'rtl';
const t = (key) => (window.APP_I18N || {})[key] || key;

function postJson(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(data),
    }).then((r) => {
        if (!r.ok) throw new Error(r.statusText);
        return r.json();
    });
}

/* ---------- Sidebar (mobile) ---------- */
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    document.querySelectorAll('[data-toggle-sidebar]').forEach((btn) =>
        btn.addEventListener('click', () => {
            sidebar?.classList.toggle('show');
            backdrop?.classList.toggle('show');
        }),
    );
    backdrop?.addEventListener('click', () => {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
    });
}

/* ---------- Confirm before submit ---------- */
function initConfirm() {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);
}

/* ---------- Date pickers ---------- */
function initDatePickers() {
    if (!window.jalaliDatepicker || !document.querySelector('[data-jdp]')) return;
    window.jalaliDatepicker.startWatch({
        time: false,
        hideAfterChange: true,
        autoHide: true,
        showTodayBtn: true,
        showEmptyBtn: true,
        persianDigits: true,
        zIndex: 2000,
    });
}

/* ---------- HH:MM and money inputs ---------- */
const toLatin = (s) => s.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));

function initInputs() {
    document.querySelectorAll('[data-duration]').forEach((input) => {
        input.addEventListener('blur', () => {
            let v = toLatin(input.value.trim());
            if (/^\d+$/.test(v)) v = `${v}:00`;
            const m = v.match(/^(\d{1,4})[:.](\d{1,2})$/);
            if (m) v = `${m[1].padStart(2, '0')}:${m[2].padStart(2, '0')}`;
            input.value = v;
        });
    });
    document.querySelectorAll('[data-money]').forEach(bindMoney);
}

// Money is always a whole number (all currencies): 7,000,000 — no decimal point.
function bindMoney(input) {
    const format = () => {
        const raw = toLatin(input.value).replace(/\.\d*$/, '').replace(/\D/g, '');
        input.value = raw === '' ? '' : Number(raw).toLocaleString('en-US');
    };
    input.addEventListener('input', format);
    format();
}

/* ---------- New bill: customers and tickets follow the project, manual item rows, live total ---------- */
function initBillForm() {
    const form = document.querySelector('[data-bill-form]');
    const dataEl = document.getElementById('bill-data');
    if (!form || !dataEl) return;
    const state = JSON.parse(dataEl.textContent);
    const projectSel = form.querySelector('[data-bill-project]');
    const customerSel = form.querySelector('[data-bill-customer]');
    const tbody = form.querySelector('[data-bill-tickets]');
    const itemsBox = form.querySelector('[data-bill-items]');
    const template = form.querySelector('[data-bill-item-template]');
    const totalEl = form.querySelector('[data-bill-total]');
    const checkAll = form.querySelector('[data-bill-check-all]');
    const placeholder = customerSel.options[0]?.text || '';
    const money = (n) => Number(n || 0).toLocaleString('en-US');
    const num = (s) => Number(toLatin(String(s || '')).replace(/\D/g, '')) || 0;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const project = () => state.projects.find((p) => String(p.id) === projectSel.value);
    let rowIndex = 0;

    const total = () => {
        let sum = 0;
        tbody.querySelectorAll('input[type=checkbox]:checked').forEach((c) => { sum += Number(c.dataset.cost); });
        itemsBox.querySelectorAll('[data-name="amount"]').forEach((i) => { sum += num(i.value); });
        totalEl.textContent = money(sum);
    };

    // SMS / email boxes are only possible when the customer has a mobile / an email.
    form.querySelectorAll('[data-notify]').forEach((box) => box.addEventListener('change', () => { box.dataset.touched = '1'; }));
    const notify = () => {
        const customer = project()?.customers.find((c) => String(c.id) === customerSel.value);
        form.querySelectorAll('[data-notify]').forEach((box) => {
            const ok = !customer || !!customer[box.dataset.notify];
            box.disabled = !ok;
            if (!ok) box.checked = false;
            else if (box.dataset.touched !== '1' && customer) box.checked = true;
        });
    };

    const fillProject = (first) => {
        const p = project();
        customerSel.innerHTML = '';
        customerSel.add(new Option(placeholder, ''));
        (p?.customers || []).forEach((c) => customerSel.add(new Option(c.name, c.id)));
        if (first && state.customer) customerSel.value = state.customer;
        if (!customerSel.value && p?.customers.length === 1) customerSel.value = String(p.customers[0].id);
        form.querySelector('[data-no-customer]').classList.toggle('d-none', !p || p.customers.length > 0);

        const tickets = p?.tickets || [];
        tbody.innerHTML = tickets.map((t) => `
            <tr>
                <td><input type="checkbox" class="form-check-input" name="tickets[]" value="${t.id}" data-cost="${t.cost}"
                    ${first && state.tickets.includes(String(t.id)) ? 'checked' : ''}></td>
                <td><a href="${form.dataset.ticketUrl}/${t.number}" target="_blank" class="t-number">#${t.number}</a></td>
                <td>${esc(t.title)}</td>
                <td class="text-nowrap small">${esc(t.date)}</td>
                <td class="text-nowrap fw-semibold">${money(t.cost)}</td>
            </tr>`).join('');
        form.querySelector('[data-bill-no-tickets]').classList.toggle('d-none', tickets.length > 0);
        checkAll.checked = false;
        form.querySelectorAll('[data-currency]').forEach((el) => { el.textContent = p?.currency || ''; });
        notify();
        total();
    };

    const addItem = (values = {}) => {
        const row = template.content.firstElementChild.cloneNode(true);
        const i = rowIndex++;
        row.querySelectorAll('[data-name]').forEach((input) => {
            input.name = `items[${i}][${input.dataset.name}]`;
            input.value = values[input.dataset.name] ?? '';
        });
        row.querySelectorAll('[data-currency]').forEach((el) => { el.textContent = project()?.currency || ''; });
        row.querySelector('[data-money]') && bindMoney(row.querySelector('[data-money]'));
        row.querySelector('[data-bill-remove]').addEventListener('click', () => { row.remove(); total(); });
        row.addEventListener('input', total);
        itemsBox.appendChild(row);
        return row;
    };

    projectSel.value = state.project;
    fillProject(true);
    (state.items.length ? state.items : [{}]).forEach((item) => addItem(item));
    total();

    projectSel.addEventListener('change', () => fillProject(false));
    customerSel.addEventListener('change', notify);
    tbody.addEventListener('change', total);
    checkAll.addEventListener('change', () => {
        tbody.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = checkAll.checked; });
        total();
    });
    form.querySelector('[data-bill-add]').addEventListener('click', () => addItem().querySelector('input')?.focus());
}

/* ---------- Payment form: projects follow the customer ---------- */
function initPaymentForm() {
    const customer = document.querySelector('[data-customer-select]');
    const project = document.querySelector('[data-follows-customer]');
    if (!customer || !project) return;
    const sync = () => {
        const ids = (customer.selectedOptions[0]?.dataset.projects || '').split(',');
        project.querySelectorAll('option[data-project]').forEach((opt) => {
            const ok = ids.includes(opt.dataset.project);
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (!ok && opt.selected) project.value = '';
        });
    };
    customer.addEventListener('change', sync);
    sync();
}

/* ---------- Grid column chooser (saved on the server) ---------- */
function initGrids() {
    document.querySelectorAll('.col-chooser').forEach((chooser) => {
        const key = chooser.dataset.grid;
        const table = document.querySelector(`table[data-grid="${key}"]`);
        let timer;
        chooser.addEventListener('change', (e) => {
            const box = e.target.closest('input[data-col]');
            if (!box) return;
            table?.querySelectorAll(`[data-col="${box.dataset.col}"]`).forEach((cell) => cell.classList.toggle('d-none', !box.checked));
            // The totals row is shown only while a column with a total is visible.
            const totals = table?.querySelector('.grid-totals');
            if (totals) {
                totals.classList.toggle('d-none', !totals.querySelector('[data-total]:not(.d-none)'));
            }
            clearTimeout(timer);
            timer = setTimeout(() => {
                const columns = [...chooser.querySelectorAll('input[data-col]:checked')].map((i) => i.dataset.col);
                postJson(chooser.dataset.url, { grid: key, columns }).catch(() => {});
            }, 400);
        });
        // Keep the dropdown open while ticking boxes.
        chooser.querySelector('.dropdown-menu')?.addEventListener('click', (e) => e.stopPropagation());
    });
}

/* ---------- Bulk actions on the checked tickets of one page ---------- */
function initBulk() {
    const form = document.getElementById('bulk-form');
    if (!form) return;
    const rows = [...document.querySelectorAll('[data-bulk-row]')];
    const all = document.querySelector('[data-bulk-all]');
    const action = form.querySelector('[data-bulk-action]');
    const count = form.querySelector('[data-bulk-count]');

    const refresh = () => {
        const n = rows.filter((r) => r.checked).length;
        form.classList.toggle('d-none', n === 0);
        count.textContent = n === 1 ? count.dataset.labelOne : count.dataset.labelMany.replace('#', n);
        all.checked = n > 0 && n === rows.length;
        all.indeterminate = n > 0 && n < rows.length;
        rows.forEach((r) => r.closest('tr').classList.toggle('row-checked', r.checked));
    };
    // Show only the parameter box of the chosen action; hidden boxes are disabled, so they are not sent.
    const showParams = () => {
        form.querySelectorAll('[data-bulk-for]').forEach((el) => {
            const on = el.dataset.bulkFor === action.value;
            el.classList.toggle('d-none', !on);
            el.disabled = !on;
        });
        form.dataset.confirm = action.value === 'delete' ? form.dataset.confirmDelete : form.dataset.confirmDefault;
    };
    form.dataset.confirmDefault = form.dataset.confirm;

    rows.forEach((r) => r.addEventListener('change', refresh));
    all.addEventListener('change', () => {
        rows.forEach((r) => { r.checked = all.checked; });
        refresh();
    });
    action.addEventListener('change', showParams);
    form.querySelector('[data-bulk-clear]').addEventListener('click', () => {
        rows.forEach((r) => { r.checked = false; });
        refresh();
    });
    showParams();
    refresh();
}

/* ---------- Ticket filter + "create menu" ---------- */
function formToFilters(form) {
    const out = {};
    new FormData(form).forEach((value, name) => {
        if (value === '' || ['per_page', 'sort', 'dir', 'create_menu', 'page'].includes(name)) return;
        if (name.endsWith('[]')) {
            const k = name.slice(0, -2);
            (out[k] ||= []).push(value);
        } else {
            out[name] = value;
        }
    });
    return out;
}

function initTicketFilter() {
    const form = document.getElementById('ticket-filter');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        const createMenu = form.querySelector('#create_menu');
        if (createMenu?.checked) {
            e.preventDefault();
            const name = window.prompt(t('menu_name_prompt'));
            if (!name || !name.trim()) return;
            postJson(form.dataset.menuUrl, { name: name.trim(), filters: formToFilters(form) })
                .then((res) => { window.location.href = res.url; })
                .catch(() => window.alert('Error'));
            return;
        }
        // Do not send empty fields, so the URL stays short and matches the menus.
        form.querySelectorAll('input, select').forEach((el) => {
            if (!el.name || el.type === 'checkbox' || el.type === 'radio') return;
            if (el.value === '') el.disabled = true;
        });
    });

    // Changing page size or sort submits right away.
    form.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => form.requestSubmit()));
}

/* ---------- Other filter forms: skip empty fields, auto-submit page size ---------- */
function initFilterForms() {
    document.querySelectorAll('form[data-clean-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('input, select').forEach((el) => {
                if (el.name && el.type !== 'checkbox' && el.type !== 'radio' && el.value === '') el.disabled = true;
            });
        });
        form.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => form.requestSubmit()));
    });
}

/* ---------- Edit custom menu (modal) ---------- */
function initMenuEdit() {
    const modalEl = document.getElementById('menuEditModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('.menu-edit').forEach((btn) =>
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            modalEl.querySelector('#menu-edit-form').action = btn.dataset.updateUrl;
            modalEl.querySelector('#menu-delete-form').action = btn.dataset.deleteUrl;
            modalEl.querySelector('[name="name"]').value = btn.dataset.name;
            modalEl.querySelector('[name="sort_order"]').value = btn.dataset.order;
            modal.show();
        }),
    );
}

/* ---------- Built-in folders: order + show / hide (pencil next to "Tickets") ---------- */
function initFolders() {
    const modalEl = document.getElementById('foldersModal');
    const list = modalEl?.querySelector('.folder-list');
    if (!list) return;
    const modal = new bootstrap.Modal(modalEl);
    document.querySelector('.folders-edit')?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation(); // do not open / close the folder list
        modal.show();
    });

    const mark = (li) => li.classList.toggle('is-hidden', !li.querySelector('[type="checkbox"]').checked);
    list.querySelectorAll('li').forEach(mark);
    list.addEventListener('change', (e) => mark(e.target.closest('li')));

    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.folder-up, .folder-down');
        if (!btn) return;
        const li = btn.closest('li');
        if (btn.classList.contains('folder-up') && li.previousElementSibling) {
            li.previousElementSibling.before(li);
        } else if (btn.classList.contains('folder-down') && li.nextElementSibling) {
            li.nextElementSibling.after(li);
        }
        btn.focus();
    });

    // Drag and drop with the mouse; the arrows work everywhere (also on phones).
    let dragged = null;
    list.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('li');
        dragged?.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', () => {
        dragged?.classList.remove('dragging');
        dragged = null;
    });
    list.addEventListener('dragover', (e) => {
        const over = e.target.closest('li');
        if (!dragged || !over || over === dragged) return;
        e.preventDefault();
        const box = over.getBoundingClientRect();
        if (e.clientY > box.top + box.height / 2) over.after(dragged);
        else over.before(dragged);
    });
    list.addEventListener('drop', (e) => e.preventDefault());
}

/* ---------- Ticket form: sprints and assignees follow the project ---------- */
function initTicketForm() {
    const project = document.querySelector('[data-project-select]');
    if (!project) return;
    const sync = () => {
        const id = project.value;
        document.querySelectorAll('[data-follows-project] option[data-projects]').forEach((opt) => {
            const ok = opt.dataset.projects.split(',').includes(id);
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (!ok && opt.selected) opt.closest('select').value = '';
        });
        // New ticket: the default assignee (the logged-in developer) comes back when the chosen project has them.
        document.querySelectorAll('[data-follows-project][data-default]').forEach((select) => {
            const opt = select.querySelector(`option[value="${select.dataset.default}"]`);
            if (select.value === '' && opt && !opt.disabled) select.value = select.dataset.default;
        });
    };
    // Once the user picks an assignee (or "unassigned") by hand, the default is not used again.
    document.querySelectorAll('[data-follows-project][data-default]').forEach((select) =>
        select.addEventListener('change', () => delete select.dataset.default));
    project.addEventListener('change', sync);
    sync();
}

/* ---------- Rich text editor (TinyMCE, self-hosted) ---------- */
function initEditors() {
    if (!window.tinymce || !document.querySelector('textarea[data-editor]')) return;
    const font = getComputedStyle(document.body).fontFamily;
    window.tinymce.init({
        selector: 'textarea[data-editor]',
        license_key: 'gpl',
        base_url: '/vendor/tinymce',
        suffix: '.min',
        language: document.documentElement.lang === 'fa' ? 'fa' : undefined,
        language_url: document.documentElement.lang === 'fa' ? '/vendor/tinymce/langs/fa.js' : undefined,
        directionality: isRtl ? 'rtl' : 'ltr',
        menubar: false,
        branding: false,
        promotion: false,
        min_height: 320,
        plugins: 'lists advlist link image table code autoresize directionality fullscreen charmap codesample autolink',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | ltr rtl | link image table codesample | removeformat code fullscreen',
        content_style: `body { font-family: ${font}; font-size: 15px; line-height: 1.8; } img { max-width: 100%; height: auto; }`,
        relative_urls: false,
        convert_urls: false,
        automatic_uploads: true,
        paste_data_images: true,
        images_file_types: 'jpg,jpeg,png,gif,webp',
        file_picker_types: 'image',
        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
            const data = new FormData();
            data.append('file', blobInfo.blob(), blobInfo.filename());
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/editor/upload');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => progress((e.loaded / e.total) * 100);
            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) {
                    let msg = `HTTP ${xhr.status}`;
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (_) { /* keep default */ }
                    reject({ message: msg, remove: true });
                    return;
                }
                resolve(JSON.parse(xhr.responseText).location);
            };
            xhr.onerror = () => reject({ message: 'Upload failed', remove: true });
            xhr.send(data);
        }),
        setup: (editor) => editor.on('change input', () => editor.save()),
    });
}

/* ---------- Back to the list page after saving a form (see App\Support\ReturnTo) ---------- */
// Each tab remembers its last list page (folder, filtered list, users…). Detail and form pages keep it,
// so "list → ticket → edit → save" goes back to that list. A page opened directly (no referrer from this
// site) forgets it, so the server uses its default page ("all tickets", "all users"…).
function initReturnTo() {
    const KEY = 'returnTo';
    const store = {
        get: () => { try { return sessionStorage.getItem(KEY); } catch (_) { return null; } },
        set: (v) => { try { sessionStorage.setItem(KEY, v); } catch (_) { /* storage blocked */ } },
        remove: () => { try { sessionStorage.removeItem(KEY); } catch (_) { /* storage blocked */ } },
    };
    const kind = document.body.dataset.page;
    if (kind === 'list') {
        store.set(window.location.href);
    } else if (!document.referrer.startsWith(`${window.location.origin}/`)) {
        store.remove();
    } else if (!store.get() && document.body.dataset.listReferrer) {
        store.set(document.body.dataset.listReferrer); // tab opened from a list (e.g. middle click)
    }

    const back = store.get();
    if (!back) return;
    // Every POST form sends it; only controllers that save/delete a record use it.
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.method.toLowerCase() !== 'post' || form.querySelector('input[name="_back"]')) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_back';
        input.value = back;
        form.appendChild(input);
    });
    // "Back to list" / "Cancel" links of forms.
    document.querySelectorAll('a[data-return-link]').forEach((a) => { a.href = back; });
}

/* ---------- OTP countdown: "send again" is enabled at 0:00 ---------- */
function initCountdown() {
    const el = document.querySelector('[data-countdown]');
    const button = document.querySelector('[data-countdown-button]');
    if (!el || !button) return;
    let left = parseInt(el.dataset.countdown, 10) || 0;
    const show = () => {
        el.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
        if (left <= 0) {
            button.disabled = false;
            el.closest('[data-countdown-wrap]')?.setAttribute('hidden', '');
            return false;
        }
        return true;
    };
    if (!show()) return;
    const timer = setInterval(() => {
        left -= 1;
        if (!show()) clearInterval(timer);
    }, 1000);
}

/* ---------- Copy buttons (card number, IBAN) ---------- */
function initCopy() {
    document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', () => {
        navigator.clipboard?.writeText(btn.dataset.copy).then(() => {
            const icon = btn.querySelector('i');
            icon?.classList.replace('bi-copy', 'bi-check2');
            setTimeout(() => icon?.classList.replace('bi-check2', 'bi-copy'), 1500);
        });
    }));
}

/* ---------- Tooltips ---------- */
function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    document.querySelectorAll('.toast').forEach((el) => bootstrap.Toast.getOrCreateInstance(el).show());
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initConfirm();
    initReturnTo();
    initDatePickers();
    initInputs();
    initPaymentForm();
    initBillForm();
    initGrids();
    initTicketFilter();
    initBulk();
    initFilterForms();
    initMenuEdit();
    initFolders();
    initTicketForm();
    initEditors();
    initCountdown();
    initCopy();
    initTooltips();
});
