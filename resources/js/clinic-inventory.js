import { clinicAction, clinicPager, clinicRecordRow, clinicRequestKey, clinicStatus } from './clinic-records';

const dateTime = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Istanbul' });

export function mountClinicInventory(panel, api, onChanged) {
    const find = (part) => panel.querySelector('[data-inventory-' + part + ']');
    const filter = find('filter');
    const editor = find('editor');
    const movement = find('movement');
    const status = find('status');
    let scope;

    function closeForms() {
        for (const form of [editor, movement]) {
            form.reset();
            form.hidden = true;
        }
    }

    function openEditor(item = null) {
        closeForms();
        scope.item = item;
        if (item) {
            for (const name of ['code', 'name', 'unit', 'minimum_stock']) {
                editor.elements.namedItem(name).value = item[name];
            }
        }
        find('editor-title').textContent = item ? 'Malzemeyi düzenle' : 'Yeni malzeme';
        editor.hidden = false;
        editor.elements.namedItem('code').focus();
    }

    function openMovement(item) {
        closeForms();
        scope.item = item;
        scope.key = clinicRequestKey();
        find('movement-title').textContent = item.name + ' · ' + item.on_hand + ' ' + item.unit;
        movement.hidden = false;
        movement.elements.namedItem('direction').focus();
    }

    async function run(current, message, task, apply) {
        if (!current || current.busy) {
            return;
        }
        current.busy = true;
        clinicStatus(status, message);
        try {
            const data = await task();
            if (current === scope) {
                clinicStatus(status);
                apply(data);
                return true;
            }
        } catch (error) {
            if (current === scope) {
                clinicStatus(status, error.message);
            }
        } finally {
            current.busy = false;
            if (current === scope && current.nextPage !== undefined) {
                const nextPage = current.nextPage;
                delete current.nextPage;
                load(nextPage);
            }
        }
    }

    async function history(item, page = 1) {
        const current = scope;
        const request = ++current.historyRequest;
        try {
            const data = await api(current.path + '/' + item.id + '/movements?page=' + page);
            if (current !== scope || request !== current.historyRequest) {
                return;
            }
            find('history-title').textContent = item.name + ' · Stok hareketleri';
            find('history-list').replaceChildren(...(data.movements.data.length ? data.movements.data.map((row) => clinicRecordRow([
                row.created_at && dateTime.format(new Date(row.created_at)), row.direction === 'in' ? 'Giriş' : 'Çıkış',
                row.quantity + ' ' + item.unit, 'Kalan ' + row.balance_after, row.description,
            ].filter(Boolean).join(' · '))) : [clinicRecordRow('Bu malzeme için henüz hareket yok.')]));
            clinicPager(find('history-pages'), data.movements, (next) => history(item, next));
            find('history').hidden = false;
        } catch (error) {
            if (current === scope && request === current.historyRequest) {
                clinicStatus(status, error.message);
            }
        }
    }

    function render(data) {
        scope.loaded = true;
        find('new').hidden = !data.can_manage;
        clinicStatus(find('warning'), data.low_stock_count ? data.low_stock_count + ' malzeme uyarı eşiğinde veya altında.' : '');
        find('list').replaceChildren(...(data.items.data.length ? data.items.data.map((item) => {
            const actions = [clinicAction('Hareketler', () => history(item))];
            if (data.can_manage) {
                actions.push(clinicAction('Stok işlemi', () => openMovement(item)), clinicAction('Düzenle', () => openEditor(item)));
            }
            return clinicRecordRow([
                item.name, item.code, item.on_hand + ' ' + item.unit, 'Eşik ' + item.minimum_stock,
                item.low_stock ? 'Düşük stok' : '',
            ].filter(Boolean).join(' · '), actions);
        }) : [clinicRecordRow('Bu şubede eşleşen malzeme yok.')]));
        clinicPager(find('pages'), data.items, load);
    }

    function load(page = 1) {
        const current = scope;
        if (current?.busy) {
            current.nextPage = page;
            return;
        }
        const query = new URLSearchParams(new FormData(filter));
        query.set('page', page);
        return run(current, 'Stok listesi yükleniyor…', () => api(current.path + '?' + query), (data) => {
            if (current.nextPage === undefined) {
                render(data);
            }
        });
    }

    filter.addEventListener('submit', (event) => {
        event.preventDefault();
        load();
    });
    editor.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        const item = current.item;
        const values = Object.fromEntries(new FormData(editor));
        const saved = await run(current, 'Malzeme kaydediliyor…', () => item
            ? api(current.path + '/' + item.id, { ...values, revision: item.revision }, 'PUT')
            : api(current.path, values), closeForms);
        if (saved && current === scope) {
            onChanged();
            load();
        }
    });
    movement.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        const item = current.item;
        const values = Object.fromEntries(new FormData(movement));
        const saved = await run(current, 'Stok hareketi kaydediliyor…', () => api(current.path + '/' + item.id + '/movements', {
            ...values, request_key: current.key,
        }), closeForms);
        if (saved && current === scope) {
            onChanged();
            load();
            history(item);
        }
    });
    find('new').addEventListener('click', () => openEditor());
    find('close-editor').addEventListener('click', closeForms);
    find('close-movement').addEventListener('click', closeForms);
    panel.addEventListener('toggle', () => {
        if (panel.open && !scope?.loaded) {
            load();
        }
    });

    return {
        openLowStock() {
            if (!scope) {
                return false;
            }
            filter.reset();
            filter.elements.namedItem('low').checked = true;
            panel.open = true;
            panel.querySelector('summary').focus();
            find('history').hidden = true;
            scope.historyRequest++;
            find('list').replaceChildren();
            clinicPager(find('pages'), null);
            load();
            return true;
        },
        setBranch(id) {
            const path = id ? '/branches/' + id + '/crm/inventory' : null;
            if ((scope?.path ?? null) === path) {
                return;
            }
            scope = path ? { path, historyRequest: 0 } : null;
            panel.open = false;
            panel.hidden = !path;
            filter.reset();
            closeForms();
            find('new').hidden = true;
            find('history').hidden = true;
            find('list').replaceChildren();
            clinicPager(find('pages'), null);
            clinicStatus(find('warning'));
            clinicStatus(status);
        },
    };
}
