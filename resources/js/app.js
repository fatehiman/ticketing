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
    const search = form.querySelector('[data-bill-search]');
    const pager = form.querySelector('[data-bill-pager]');
    const selectedBox = form.querySelector('[data-bill-selected]');
    const selectedCount = form.querySelector('[data-bill-selected-count]');
    const placeholder = customerSel.options[0]?.text || '';
    const sprintSel = form.querySelector('[data-bill-sprint]');
    const sprintAdd = form.querySelector('[data-bill-sprint-add]');
    const sprintMsg = form.querySelector('[data-bill-sprint-msg]');
    const sprintPlaceholder = sprintSel.options[0]?.text || '';
    const i18n = JSON.parse(document.getElementById('bill-i18n')?.textContent || '{}');
    const money = (n) => Number(n || 0).toLocaleString('en-US');
    const num = (s) => Number(toLatin(String(s || '')).replace(/\D/g, '')) || 0;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const project = () => state.projects.find((p) => String(p.id) === projectSel.value);
    const PER_PAGE = 25;
    let rowIndex = 0;
    let page = 1;
    // Ticked tickets of the project, kept across pages and searches; sent as hidden inputs.
    let selected = new Set();

    const tickets = () => project()?.tickets || [];
    const filtered = () => {
        const q = toLatin(search.value.trim().toLowerCase()).replace(/^#/, '');
        if (!q) return tickets();
        return tickets().filter((t) => String(t.number).includes(q) || String(t.title).toLowerCase().includes(q) || String(t.sprint || '').toLowerCase().includes(q));
    };

    const total = () => {
        let sum = 0;
        tickets().forEach((t) => { if (selected.has(String(t.id))) sum += Number(t.cost); });
        itemsBox.querySelectorAll('[data-name="amount"]').forEach((i) => { sum += num(i.value); });
        totalEl.textContent = money(sum);
    };

    const syncSelected = () => {
        selectedBox.innerHTML = '';
        selected.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tickets[]';
            input.value = id;
            selectedBox.appendChild(input);
        });
        selectedCount.textContent = (i18n.selected || ':count').replace(':count', selected.size);
        total();
    };

    const renderTickets = () => {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
        page = Math.min(Math.max(1, page), pages);
        const rows = list.slice((page - 1) * PER_PAGE, page * PER_PAGE);
        tbody.innerHTML = rows.map((t) => `
            <tr>
                <td><input type="checkbox" class="form-check-input" value="${t.id}" ${selected.has(String(t.id)) ? 'checked' : ''}></td>
                <td><a href="${form.dataset.ticketUrl}/${t.number}" target="_blank" class="t-number">#${t.number}</a></td>
                <td>${esc(t.title)}</td>
                <td class="text-nowrap small">${esc(t.sprint)}</td>
                <td class="text-nowrap small">${esc(t.date)}</td>
                <td class="text-nowrap ${t.cost > 0 ? 'fw-semibold' : 'text-muted small'}">${t.cost > 0 ? money(t.cost) : esc(i18n.no_cost)}</td>
            </tr>`).join('');
        checkAll.checked = rows.length > 0 && rows.every((t) => selected.has(String(t.id)));

        // Pager: « prev  page X of Y (N tickets)  next »
        pager.classList.toggle('d-none', list.length <= PER_PAGE);
        pager.querySelector('[data-bill-page-info]').textContent = (i18n.page || ':page / :pages')
            .replace(':page', page).replace(':pages', pages).replace(':count', list.length);
        pager.querySelector('[data-bill-prev]').disabled = page <= 1;
        pager.querySelector('[data-bill-next]').disabled = page >= pages;
        form.querySelector('[data-bill-no-tickets]').classList.toggle('d-none', tickets().length > 0);
        form.querySelector('[data-bill-no-match]').classList.toggle('d-none', tickets().length === 0 || list.length > 0);
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

        // A new project starts with no ticks (after a validation error: the ticks sent before).
        const ids = new Set(tickets().map((t) => String(t.id)));
        selected = new Set(first ? state.tickets.filter((id) => ids.has(String(id))).map(String) : []);
        search.value = '';
        page = 1;

        // Sprints that have billable tickets: picking one ticks all of its done tickets.
        sprintSel.innerHTML = '';
        sprintSel.add(new Option(sprintPlaceholder, ''));
        (p?.sprints || []).forEach((s) => {
            const opt = new Option(`${s.name} (${s.count})`, s.id);
            if (s.current) opt.classList.add('text-success');
            sprintSel.add(opt);
        });
        sprintAdd.disabled = true;
        sprintMsg.classList.add('d-none');
        form.querySelector('[data-bill-sprint-box]').classList.toggle('d-none', !(p?.sprints || []).length);
        form.querySelector('[data-bill-tools]').classList.toggle('d-none', tickets().length === 0);
        form.querySelectorAll('[data-currency]').forEach((el) => { el.textContent = p?.currency || ''; });
        renderTickets();
        syncSelected();
        notify();
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
    sprintSel.addEventListener('change', () => { sprintAdd.disabled = !sprintSel.value; });
    sprintAdd.addEventListener('click', () => {
        let added = 0;
        tickets().filter((t) => String(t.sprint_id) === sprintSel.value).forEach((t) => {
            if (!selected.has(String(t.id))) { selected.add(String(t.id)); added += 1; }
        });
        sprintMsg.textContent = (i18n.sprint_added || ':count').replace(':count', added);
        sprintMsg.classList.remove('d-none');
        renderTickets();
        syncSelected();
    });
    tbody.addEventListener('change', (e) => {
        if (e.target.type !== 'checkbox') return;
        if (e.target.checked) selected.add(e.target.value); else selected.delete(e.target.value);
        renderTickets();
        syncSelected();
    });
    // The header box ticks / unticks the tickets of this page only.
    checkAll.addEventListener('change', () => {
        tbody.querySelectorAll('input[type=checkbox]').forEach((c) => {
            if (checkAll.checked) selected.add(c.value); else selected.delete(c.value);
        });
        renderTickets();
        syncSelected();
    });
    form.querySelector('[data-bill-select-all]').addEventListener('click', () => {
        filtered().forEach((t) => selected.add(String(t.id)));
        renderTickets();
        syncSelected();
    });
    form.querySelector('[data-bill-select-none]').addEventListener('click', () => {
        selected.clear();
        renderTickets();
        syncSelected();
    });
    search.addEventListener('input', () => { page = 1; renderTickets(); });
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    pager.querySelector('[data-bill-prev]').addEventListener('click', () => { page -= 1; renderTickets(); });
    pager.querySelector('[data-bill-next]').addEventListener('click', () => { page += 1; renderTickets(); });
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

/* ---------- Listbox with check marks (<x-multi-select>) ---------- */
function initMultiSelects() {
    document.querySelectorAll('[data-multi-select]').forEach((box) => {
        const label = box.querySelector('[data-ms-label]');
        const items = [...box.querySelectorAll('[data-ms-item]')];
        const update = () => {
            const names = items.filter((i) => i.querySelector('input').checked).map((i) => i.textContent.trim());
            label.textContent = names.length === 0 ? box.dataset.placeholder
                : names.length <= 2 ? names.join(box.dataset.sep) : box.dataset.many.replace('#', names.length);
            label.classList.toggle('text-muted', names.length === 0);
        };
        const tick = (on) => {
            items.filter((i) => !i.classList.contains('d-none')).forEach((i) => { i.querySelector('input').checked = on; });
            update();
        };
        box.addEventListener('change', update);
        box.querySelector('[data-ms-all]')?.addEventListener('click', () => tick(true));
        box.querySelector('[data-ms-none]')?.addEventListener('click', () => tick(false));
        const search = box.querySelector('[data-ms-search]');
        search?.addEventListener('input', () => {
            const q = toLatin(search.value.trim()).toLowerCase();
            items.forEach((i) => i.classList.toggle('d-none', q !== '' && !toLatin(i.textContent).toLowerCase().includes(q)));
        });
        // Enter in the search box filters the list; it must not submit the form.
        search?.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        box.addEventListener('shown.bs.dropdown', () => search?.focus());
    });
}

/* ---------- Row actions of the tickets grid: a modal per action, saved by AJAX without a reload ---------- */
function initRowActions() {
    const modalEl = document.getElementById('row-action-modal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = modalEl.querySelector('form');
    const error = form.querySelector('[data-row-action-error]');
    const costInput = form.querySelector('[name="cost"]');
    const sprintSel = form.querySelector('[name="sprint_id"]');
    const statusSel = form.querySelector('[name="status"]');
    let row = null;
    let action = null;

    const showError = (text) => { error.textContent = text; error.classList.toggle('d-none', !text); };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-row-action]');
        if (!btn) return;
        bootstrap.Tooltip.getInstance(btn)?.hide();
        row = btn.closest('tr[data-ticket]');
        action = btn.dataset.rowAction;
        form.querySelectorAll('[data-row-action-field]').forEach((f) => f.classList.toggle('d-none', f.dataset.rowActionField !== action));
        const field = form.querySelector(`[data-row-action-field="${action}"]`);
        form.querySelector('[data-row-action-title]').textContent = field.dataset.title;
        form.querySelector('[data-row-action-number]').textContent = row.querySelector('.t-number')?.textContent || '';
        showError('');
        if (action === 'cost') {
            costInput.value = row.dataset.cost === '' ? '' : Number(row.dataset.cost).toLocaleString('en-US');
        } else if (action === 'sprint') {
            // Only the sprints of the ticket's project.
            [...sprintSel.options].forEach((o) => { o.hidden = !!o.dataset.project && o.dataset.project !== row.dataset.project; });
            sprintSel.value = row.dataset.sprint || 'none';
        } else if (action === 'status') {
            statusSel.value = row.dataset.status;
        }
        modal.show();
    });
    modalEl.addEventListener('shown.bs.modal', () => ({ cost: costInput, sprint: sprintSel, status: statusSel })[action].focus());

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = { ids: [row.dataset.ticket], action };
        if (action === 'cost') data.cost = costInput.value.trim();
        if (action === 'sprint') data.sprint_id = sprintSel.value;
        if (action === 'status') data.status = statusSel.value;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        fetch(form.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        })
            .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    showError(body.errors ? Object.values(body.errors).flat().join(' ') : body.message || 'Error');
                    return;
                }
                const tk = body.ticket;
                if (action === 'cost') {
                    // The totals row is for all filtered tickets: add the change of this ticket.
                    const total = document.querySelector('.grid-totals [data-col="cost"]');
                    if (total) {
                        const sum = Number(toLatin(total.textContent).replace(/\D/g, '')) || 0;
                        total.textContent = (sum - (Number(row.dataset.cost) || 0) + (Number(tk.cost) || 0)).toLocaleString('en-US');
                    }
                    row.dataset.cost = tk.cost ?? '';
                    row.querySelector('[data-col="cost"]').textContent = tk.cost_text;
                } else if (action === 'sprint') {
                    row.dataset.sprint = tk.sprint_id ?? '';
                    row.querySelector('[data-col="sprint"]').innerHTML = tk.sprint_html;
                } else if (action === 'status') {
                    // The page shows only some statuses (status[]= or status[0]= in the URL) and the new one is not
                    // among them: the ticket leaves this list, so reload (rows, totals, paging and badges stay right).
                    const shown = [...new URLSearchParams(location.search)]
                        .filter(([key]) => /^status\[\d*\]$/.test(key)).map(([, value]) => value);
                    if (shown.length && !shown.includes(tk.status)) {
                        location.reload();
                        return;
                    }
                    row.dataset.status = tk.status;
                    row.querySelector('[data-col="status"]').innerHTML = tk.status_html;
                }
                modal.hide();
                row.classList.add('row-saved');
                setTimeout(() => row.classList.remove('row-saved'), 1500);
                pollToasts(); // e.g. "SMS to <customer>" after a change to Done
            })
            .catch(() => showError('Error'))
            .finally(() => { submit.disabled = false; });
    });
}

/* ---------- "More filters" button: its caption says what a click does (more / fewer) ---------- */
function initFilterToggles() {
    document.querySelectorAll('[data-filter-toggle]').forEach((btn) => {
        const target = document.querySelector(btn.dataset.bsTarget);
        const label = btn.querySelector('[data-filter-toggle-label]');
        const sync = () => { label.textContent = target.classList.contains('show') ? btn.dataset.less : btn.dataset.more; };
        target?.addEventListener('shown.bs.collapse', sync);
        target?.addEventListener('hidden.bs.collapse', sync);
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
        // Only delete asks first; the other actions are plain edits (and are in the history).
        form.dataset.confirm = action.value === 'delete' ? form.dataset.confirmDelete : '';
    };

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

    // Cost: From / To are shown (and sent) only when "Range" is picked.
    const costMode = form.querySelector('[data-cost-mode]');
    costMode?.addEventListener('change', () => {
        const range = costMode.value === 'range';
        form.querySelectorAll('[data-cost-range]').forEach((el) => {
            el.classList.toggle('d-none', !range);
            if (el.tagName === 'INPUT') el.disabled = !range;
        });
        if (range) form.querySelector('input[name="cost_from"]')?.focus();
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
    // Only custom folders (they have their own URLs). The pencil of "Tickets" also has .menu-edit
    // for its look, but opens the built-in folders modal (initFolders).
    document.querySelectorAll('.menu-edit[data-update-url]').forEach((btn) =>
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

/* ---------- New sprint: number and start date follow the project ---------- */
function initSprintForm() {
    const project = document.querySelector('[data-sprint-defaults]');
    if (!project) return;
    const defaults = JSON.parse(project.dataset.sprintDefaults);
    const form = project.closest('form');
    const fields = { number: form.elements.number, start_date: form.elements.start_date };
    let current = defaults[project.value] || {};
    project.addEventListener('change', () => {
        const next = defaults[project.value] || {};
        // A value the user typed by hand stays; only the previous project's default is replaced.
        Object.entries(fields).forEach(([key, input]) => {
            if (input.value === String(current[key] ?? '')) input.value = next[key] ?? '';
        });
        current = next;
    });
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

/* ---------- Password eye: show / hide; the open eye blinks, looks around and follows the mouse ---------- */
function initPasswordEye() {
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-eye-for]').forEach((btn) => {
        const input = document.getElementById(btn.dataset.eyeFor);
        const ball = btn.querySelector('.eye-ball');
        const pupil = btn.querySelector('.eye-pupil');
        if (!input || !ball || !pupil) return;
        const MAX = 2.6; // how far the pupil may move (SVG units)
        let blinkTimer = null;
        let idleTimer = null;

        const look = (dx, dy) => { pupil.style.transform = `translate(${dx.toFixed(2)}px, ${dy.toFixed(2)}px)`; };
        const lookAt = (x, y) => {
            const box = btn.getBoundingClientRect();
            const vx = x - (box.left + box.width / 2);
            const vy = y - (box.top + box.height / 2);
            const dist = Math.hypot(vx, vy) || 1;
            const reach = Math.min(1, dist / 120) * MAX; // near the eye = small move
            look((vx / dist) * reach, (vy / dist) * reach * 0.7);
        };
        const blink = () => {
            ball.classList.add('blink');
            setTimeout(() => ball.classList.remove('blink'), 140);
            // Sometimes a double blink.
            if (Math.random() < 0.25) setTimeout(() => { ball.classList.add('blink'); setTimeout(() => ball.classList.remove('blink'), 140); }, 320);
            blinkTimer = setTimeout(blink, 2200 + Math.random() * 3200);
        };
        // When the mouse is still (or on a phone), glance around now and then.
        const wander = () => {
            look((Math.random() * 2 - 1) * MAX, (Math.random() * 2 - 1) * MAX * 0.6);
            idleTimer = setTimeout(wander, 900 + Math.random() * 1600);
        };
        const onMove = (e) => {
            clearTimeout(idleTimer);
            lookAt(e.clientX, e.clientY);
            idleTimer = setTimeout(wander, 2500);
        };

        const setOpen = (open) => {
            input.type = open ? 'text' : 'password';
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-pressed', open ? 'true' : 'false');
            const label = open ? btn.dataset.labelHide : btn.dataset.labelShow;
            btn.setAttribute('aria-label', label);
            btn.title = label;
            clearTimeout(blinkTimer);
            clearTimeout(idleTimer);
            document.removeEventListener('mousemove', onMove);
            look(0, 0);
            if (open && !still) {
                blinkTimer = setTimeout(blink, 600);
                idleTimer = setTimeout(wander, 1500);
                document.addEventListener('mousemove', onMove);
            }
        };

        btn.addEventListener('click', () => {
            setOpen(input.type === 'password');
            input.focus();
        });
        // Do not leave the password visible when the form is sent.
        input.form?.addEventListener('submit', () => setOpen(false));
    });
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
}

/* ---------- Toasts (like Filament notifications), see App\Support\Toast ----------
   window.toast({ type: 'success' | 'info' | 'warning' | 'danger', title, body, icon, url, link, duration })
   A card at the top corner; it closes by itself after `duration` ms (0 = never), the timer stops on hover. */
const TOAST_ICONS = { success: 'check-circle-fill', info: 'info-circle-fill', warning: 'exclamation-circle-fill', danger: 'exclamation-triangle-fill' };
const TOAST_POLL_MS = 15000;

function toastStack() {
    let stack = document.querySelector('.app-toasts');
    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'app-toasts';
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }
    return stack;
}

function showToast(options) {
    const o = typeof options === 'string' ? { body: options } : options || {};
    const type = TOAST_ICONS[o.type] ? o.type : 'info';
    const duration = o.duration ?? 6000;

    const el = document.createElement('div');
    el.className = `app-toast app-toast-${type}`;
    el.setAttribute('role', type === 'danger' || type === 'warning' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = `app-toast-icon bi bi-${o.icon || TOAST_ICONS[type]}`;
    const text = document.createElement('div');
    text.className = 'app-toast-text';
    if (o.title) {
        const title = document.createElement('div');
        title.className = 'app-toast-title';
        title.textContent = o.title;
        text.appendChild(title);
    }
    if (o.body) {
        const body = document.createElement('div');
        body.className = 'app-toast-body';
        body.textContent = o.body; // text only: an SMS or a message is never HTML
        text.appendChild(body);
    }
    if (o.url) {
        const link = document.createElement('a');
        link.className = 'app-toast-link';
        link.href = o.url;
        link.textContent = o.link || o.url;
        text.appendChild(link);
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close app-toast-close';
    close.setAttribute('aria-label', t('close'));
    el.append(icon, text, close);

    let bar = null;
    if (duration > 0) {
        bar = document.createElement('div');
        bar.className = 'app-toast-bar';
        bar.style.animationDuration = `${duration}ms`;
        bar.addEventListener('animationend', () => remove());
        el.appendChild(bar);
    }

    const remove = () => {
        if (el.classList.contains('app-toast-out')) return;
        el.classList.add('app-toast-out');
        setTimeout(() => el.remove(), 250);
    };
    close.addEventListener('click', remove);

    toastStack().appendChild(el);
    requestAnimationFrame(() => el.classList.add('app-toast-in'));
    return el;
}
window.toast = showToast;

let toastPolling = false;
function pollToasts() {
    const url = document.body.dataset.toastsUrl;
    if (!url || toastPolling) return;
    toastPolling = true;
    fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : { toasts: [] }))
        .then((data) => (data.toasts || []).forEach(showToast))
        .catch(() => {})
        .finally(() => { toastPolling = false; });
}

function initToasts() {
    (window.APP_TOASTS || []).forEach(showToast);
    if (!document.body.dataset.toastsUrl) return;
    // New toasts (e.g. an SMS that a developer just sent to this customer); not while the tab is hidden.
    setInterval(() => { if (!document.hidden) pollToasts(); }, TOAST_POLL_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollToasts(); });
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
    initMultiSelects();
    initRowActions();
    initFilterToggles();
    initTicketFilter();
    initBulk();
    initFilterForms();
    initMenuEdit();
    initFolders();
    initTicketForm();
    initSprintForm();
    initEditors();
    initCountdown();
    initCopy();
    initPasswordEye();
    initTooltips();
    initToasts();
});
