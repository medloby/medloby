const kindLabels = { income: 'Gelir', expense: 'Gider' };
const wholeNumber = new Intl.NumberFormat('tr-TR');
const date = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });

function amount(value, currency) {
    const [whole, fraction] = value.replace(/^-/, '').split('.');
    const sign = value.startsWith('-') ? '−' : '';
    return sign + wholeNumber.format(BigInt(whole)) + ',' + fraction + ' ' + currency;
}

function requestKey() {
    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

export function mountClinicLedger(panel, api) {
    const find = (part, root = panel) => root.querySelector('[data-ledger-' + part + ']');
    const controls = find('controls');
    const status = find('status');
    const retry = find('retry');
    const month = find('month');
    const filter = find('filter');
    const totals = find('totals');
    const list = find('list');
    const editor = find('editor');
    const add = find('new');
    const changeStatus = find('change-status');
    let scope;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function closeEditor() {
        editor.reset();
        editor.hidden = true;
        if (scope) {
            scope.selected = null;
        }
    }

    function openEditor(entry = null) {
        scope.selected = entry;
        scope.key = entry ? null : requestKey();
        const voided = entry?.status === 'voided';
        const entryDate = scope.data.today.startsWith(month.value) ? scope.data.today : month.value + '-01';
        const values = entry ?? { kind: 'income', entry_date: entryDate, amount: '', currency: 'TRY', description: '' };
        for (const key of ['kind', 'entry_date', 'amount', 'currency', 'description']) {
            editor.elements.namedItem(key).value = values[key];
        }
        find('editor-title').textContent = !entry ? 'Yeni gelir veya gider' : voided ? 'İptal edilen kayıt' : 'Kaydı düzenle';
        find('fields').disabled = voided;
        find('save').hidden = voided;
        changeStatus.hidden = !entry;
        changeStatus.textContent = voided ? 'Yeniden etkinleştir' : 'Kaydı iptal et';
        editor.hidden = false;
        showStatus();
        (voided ? changeStatus : editor.elements.namedItem('kind')).focus();
    }

    async function run(message, task) {
        const active = scope;
        if (!active || active.busy) {
            return;
        }
        active.busy = true;
        controls.disabled = true;
        retry.disabled = true;
        showStatus(message);
        try {
            const response = await task(active);
            return active === scope ? response : undefined;
        } catch (error) {
            if (active === scope) {
                showStatus(error.message);
            }
            return undefined;
        } finally {
            if (active === scope) {
                active.busy = false;
                controls.disabled = false;
                retry.disabled = false;
            }
        }
    }

    function render(data) {
        scope.data = data;
        month.value = data.month;
        add.hidden = !data.can_manage;
        totals.replaceChildren(...data.totals.map((total) => {
            const row = find('total').content.firstElementChild.cloneNode(true);
            find('currency', row).textContent = total.currency;
            for (const key of ['income', 'expense', 'net']) {
                find(key, row).textContent = amount(total[key], total.currency);
            }
            return row;
        }));
        list.replaceChildren(...data.entries.map((entry) => {
            const row = find('entry').content.firstElementChild.cloneNode(true);
            const stamp = date.format(new Date(entry.entry_date + 'T00:00:00Z'));
            find('description', row).textContent = entry.description;
            find('meta', row).textContent = [stamp, kindLabels[entry.kind], amount(entry.amount, entry.currency)].join(' · ');
            const open = find('open', row);
            open.hidden = !data.can_manage;
            open.setAttribute('aria-label', 'Kaydı aç · ' + entry.description);
            open.addEventListener('click', () => openEditor(entry));
            return row;
        }));
        find('empty').hidden = data.entries.length > 0;
        find('pages').hidden = data.last_page <= 1 && data.current_page <= 1;
        find('page').textContent = data.current_page + ' / ' + data.last_page;
        find('previous').disabled = data.current_page <= 1;
        find('next').disabled = data.current_page >= data.last_page;
        controls.hidden = false;
    }

    async function load(page = 1, confirmation = '') {
        const active = scope;
        if (!active || active.busy) {
            return;
        }
        closeEditor();
        retry.hidden = true;
        const query = new URLSearchParams({ page, status: filter.value });
        if (month.value) {
            query.set('month', month.value);
        }
        const result = await run('Gelir ve gider kayıtları yükleniyor…', (current) => api(current.path + '?' + query));
        if (active !== scope) {
            return;
        }
        if (result) {
            render(result);
            showStatus(confirmation);
        } else {
            controls.hidden = true;
            retry.hidden = false;
            if (confirmation) {
                showStatus(confirmation + ' ' + status.textContent);
            }
        }
    }

    editor.addEventListener('submit', async (event) => {
        event.preventDefault();
        const active = scope;
        const selected = active.selected;
        const values = Object.fromEntries(new FormData(editor));
        values.currency = values.currency.toUpperCase();
        const saved = await run('Kayıt kaydediliyor…', (current) => selected
            ? api(current.path + '/' + selected.id, { ...values, revision: selected.revision }, 'PUT')
            : api(current.path, { ...values, request_key: current.key }));
        if (saved && active === scope) {
            month.value = saved.entry_date.slice(0, 7);
            filter.value = 'active';
            await load(1, 'Kayıt kaydedildi.');
        }
    });

    changeStatus.addEventListener('click', async () => {
        const active = scope;
        const selected = active.selected;
        const activeAgain = selected.status === 'voided';
        const saved = await run('Kayıt durumu güncelleniyor…', (current) => api(current.path + '/' + selected.id + '/status',
            { status: activeAgain ? 'active' : 'voided', revision: selected.revision }, 'PUT'));
        if (saved && active === scope) {
            await load(1, activeAgain ? 'Kayıt yeniden etkinleştirildi.' : 'Kayıt iptal edildi.');
        }
    });

    add.addEventListener('click', () => openEditor());
    find('close-editor').addEventListener('click', closeEditor);
    month.addEventListener('change', () => load());
    filter.addEventListener('change', () => load());
    find('refresh').addEventListener('click', () => load());
    retry.addEventListener('click', () => load());
    find('previous').addEventListener('click', () => load(scope.data.current_page - 1));
    find('next').addEventListener('click', () => load(scope.data.current_page + 1));
    panel.addEventListener('toggle', () => {
        if (panel.open && scope && !scope.data) {
            load();
        }
    });

    return {
        setBranch(id) {
            const path = id ? '/branches/' + id + '/crm/ledger' : undefined;
            if (scope?.path === path) {
                return;
            }
            scope = path ? { path } : undefined;
            panel.open = false;
            panel.hidden = !scope;
            controls.hidden = true;
            controls.disabled = false;
            retry.hidden = true;
            retry.disabled = false;
            month.value = '';
            filter.value = 'active';
            list.replaceChildren();
            totals.replaceChildren();
            closeEditor();
            showStatus();
        },
    };
}
