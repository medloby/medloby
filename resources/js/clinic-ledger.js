import { mountClinicFinanceReports } from './clinic-finance-reports';
import { mountClinicBalances } from './clinic-balances';
import { clinicRecordRow, clinicRequestKey as requestKey, clinicStatus, formatClinicAmount as amount } from './clinic-records';

const kindLabels = { income: 'Gelir', expense: 'Gider' };
const channelLabels = { cash: 'Kasa', bank: 'Banka' };
const date = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });

export function mountClinicLedger(panel, api, onChanged) {
    const find = (part, root = panel) => root.querySelector('[data-ledger-' + part + ']');
    const controls = find('controls');
    const status = find('status');
    const retry = find('retry');
    const month = find('month');
    const filter = find('filter');
    const channelFilter = find('channel-filter');
    const totals = find('totals');
    const list = find('list');
    const editor = find('editor');
    const add = find('new');
    const changeStatus = find('change-status');
    const kind = editor.elements.namedItem('kind');
    const referenceType = find('reference-type');
    const referenceId = find('reference-id');
    const referenceChoice = find('reference-choice');
    const referenceStatus = find('reference-status');
    const reports = mountClinicFinanceReports(panel.querySelector('[data-finance-reports]'), api, amount);
    const balances = mountClinicBalances(find('balances'), api, (balance) => {
        if (scope?.data?.can_manage) {
            openEditor(null, balance);
            editor.scrollIntoView({ block: 'nearest' });
        }
    });
    let scope;
    let lookup = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function closeEditor() {
        lookup++;
        editor.reset();
        editor.hidden = true;
        if (scope) {
            scope.selected = null;
            scope.balanceId = null;
        }
    }

    function syncCategory() {
        const expense = kind.value === 'expense';
        find('category').hidden = !expense;
        editor.elements.namedItem('expense_category').disabled = !expense;
    }

    function showReferenceStatus(message = '') {
        referenceStatus.textContent = message;
        referenceStatus.hidden = !message;
    }

    async function loadReferences(id = '', selected = '') {
        const current = ++lookup;
        const type = referenceType.value;
        referenceChoice.replaceChildren(new Option('Kayıt seçin', ''));
        referenceChoice.disabled = true;
        const visible = kind.value === 'income' && type !== '';
        find('reference-fields').hidden = !visible;
        find('income-reference').hidden = kind.value !== 'income';
        showReferenceStatus();
        if (!visible) {
            return;
        }

        const query = new URLSearchParams({ type });
        if (id) {
            query.set('id', id);
        }
        showReferenceStatus('Kayıtlar yükleniyor…');
        try {
            const options = await api(scope.path + '/references?' + query);
            if (current === lookup) {
                referenceChoice.append(...options.map(({ label, id: optionId }) => new Option(label, optionId)));
                referenceChoice.value = selected || (id && options.length === 1 ? String(options[0].id) : '');
                showReferenceStatus(options.length ? '' : 'Bu şubede eşleşen kayıt bulunamadı.');
            }
        } catch (error) {
            if (current === lookup) {
                showReferenceStatus(error.message);
            }
        } finally {
            if (current === lookup) {
                referenceChoice.disabled = false;
            }
        }
    }

    function openEditor(entry = null, balance = null) {
        scope.selected = entry;
        scope.balanceId = balance?.id ?? entry?.branch_balance_id ?? null;
        scope.key = entry ? null : requestKey();
        const voided = entry?.status === 'voided';
        const entryDate = scope.data.today.startsWith(month.value) ? scope.data.today : month.value + '-01';
        const values = entry ?? {
            kind: balance?.direction === 'payable' ? 'expense' : 'income', entry_date: entryDate,
            amount: balance?.remaining ?? '', currency: balance?.currency ?? 'TRY', description: balance?.description ?? '',
        };
        for (const key of ['kind', 'entry_date', 'amount', 'currency', 'description', 'payment_channel', 'expense_category']) {
            editor.elements.namedItem(key).value = values[key] ?? '';
        }
        kind.disabled = Boolean(scope.balanceId);
        editor.elements.namedItem('currency').readOnly = Boolean(scope.balanceId);
        syncCategory();
        clinicStatus(find('balance'), scope.balanceId ? 'Bağlı borç veya alacak #' + scope.balanceId : '');
        find('editor-title').textContent = !entry ? 'Yeni gelir veya gider' : voided ? 'İptal edilen kayıt' : 'Kaydı düzenle';
        find('fields').disabled = voided;
        find('save').hidden = voided;
        changeStatus.hidden = !entry;
        changeStatus.textContent = voided ? 'Yeniden etkinleştir' : 'Kaydı iptal et';
        editor.hidden = false;
        referenceType.value = values.appointment_id ? 'appointment' : values.offer_id ? 'package' : '';
        referenceId.value = values.appointment_id || values.offer_id || '';
        loadReferences(referenceId.value, referenceId.value);
        showStatus();
        (voided ? changeStatus : scope.balanceId ? editor.elements.namedItem('amount') : kind).focus();
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
        find('categories').replaceChildren(...(data.expense_categories.length ? data.expense_categories.map((item) => clinicRecordRow(
            (item.category || 'Kategorisiz') + ' · ' + amount(item.amount, item.currency),
        )) : [clinicRecordRow('Bu ay aktif gider kaydı yok.')]));
        find('accounts').replaceChildren(...(data.accounts.length ? data.accounts.map((item) => clinicRecordRow(
            channelLabels[item.channel] + ' · ' + amount(item.net, item.currency),
        )) : [clinicRecordRow('Ay sonuna kadar kanalı seçilmiş hareket yok.')]));
        list.replaceChildren(...data.entries.map((entry) => {
            const row = find('entry').content.firstElementChild.cloneNode(true);
            const stamp = date.format(new Date(entry.entry_date + 'T00:00:00Z'));
            find('description', row).textContent = entry.description;
            const reference = entry.appointment_id ? 'Randevu #' + entry.appointment_id : entry.offer_id ? 'Paket teklifi #' + entry.offer_id : '';
            find('meta', row).textContent = [stamp, kindLabels[entry.kind], amount(entry.amount, entry.currency), channelLabels[entry.payment_channel],
                entry.expense_category, reference, entry.branch_balance_id ? 'Borç/alacak #' + entry.branch_balance_id : ''].filter(Boolean).join(' · ');
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
        if (channelFilter.value) {
            query.set('channel', channelFilter.value);
        }
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
        values.kind = kind.value;
        values.currency = values.currency.toUpperCase();
        values.branch_balance_id = active.balanceId;
        values.expense_category = values.expense_category || null;
        values.payment_channel = values.payment_channel || null;
        const reference = values.kind === 'income' && referenceType.value;
        if (reference && (referenceChoice.disabled || !referenceChoice.value)) {
            showReferenceStatus('Devam etmek için bağlanacak kaydı seçin.');
            return;
        }
        values.appointment_id = reference === 'appointment' ? Number(referenceChoice.value) : null;
        values.offer_id = reference === 'package' ? Number(referenceChoice.value) : null;
        const saved = await run('Kayıt kaydediliyor…', (current) => selected
            ? api(current.path + '/' + selected.id, { ...values, revision: selected.revision }, 'PUT')
            : api(current.path, { ...values, request_key: current.key }));
        if (saved && active === scope) {
            month.value = saved.entry_date.slice(0, 7);
            filter.value = 'active';
            channelFilter.value = '';
            reports.refresh();
            balances.refresh();
            onChanged();
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
            reports.refresh();
            balances.refresh();
            onChanged();
            await load(1, activeAgain ? 'Kayıt yeniden etkinleştirildi.' : 'Kayıt iptal edildi.');
        }
    });

    add.addEventListener('click', () => openEditor());
    kind.addEventListener('change', () => {
        syncCategory();
        referenceType.value = '';
        referenceId.value = '';
        loadReferences();
    });
    referenceType.addEventListener('change', () => {
        referenceId.value = '';
        loadReferences();
    });
    find('reference-search').addEventListener('click', () => {
        if (referenceId.reportValidity()) {
            loadReferences(referenceId.value);
        }
    });
    find('close-editor').addEventListener('click', closeEditor);
    month.addEventListener('change', () => load());
    filter.addEventListener('change', () => load());
    channelFilter.addEventListener('change', () => load());
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
        setBranch(id, businessId) {
            const path = id ? '/branches/' + id + '/crm/ledger' : undefined;
            if (scope?.path === path) {
                return;
            }
            scope = path ? { path } : undefined;
            reports.setScope(id, businessId);
            balances.setBranch(id);
            panel.open = false;
            panel.hidden = !scope;
            controls.hidden = true;
            controls.disabled = false;
            retry.hidden = true;
            retry.disabled = false;
            month.value = '';
            filter.value = 'active';
            channelFilter.value = '';
            list.replaceChildren();
            totals.replaceChildren();
            closeEditor();
            showStatus();
        },
    };
}
