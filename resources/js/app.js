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
    document.querySelectorAll('[data-money]').forEach((input) => {
        const format = () => {
            const raw = toLatin(input.value).replace(/[^\d.]/g, '');
            if (raw === '') { input.value = ''; return; }
            const [int, dec] = raw.split('.');
            // data-money="int": whole numbers only (no decimal point).
            const keepDec = dec !== undefined && input.dataset.money !== 'int';
            input.value = Number(int || 0).toLocaleString('en-US') + (keepDec ? `.${dec.slice(0, 2)}` : '');
        };
        input.addEventListener('input', format);
        format();
    });
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
    };
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

/* ---------- Tooltips ---------- */
function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initConfirm();
    initDatePickers();
    initInputs();
    initPaymentForm();
    initGrids();
    initTicketFilter();
    initFilterForms();
    initMenuEdit();
    initTicketForm();
    initEditors();
    initTooltips();
});
