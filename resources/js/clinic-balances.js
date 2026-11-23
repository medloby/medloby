import { clinicAction, clinicPager, clinicRecordRow, clinicRequestKey, clinicStatus, formatClinicAmount } from './clinic-records';

const channels = { cash: 'Kasa', bank: 'Banka' };

export function mountClinicBalances(panel, api, settle, onChanged) {
    const find = (part) => panel.querySelector('[data-balances-' + part + ']');
    const editor = find('editor');
    const field = (name) => editor.elements.namedItem(name);
    const people = field('patient_profile_id');
    const filter = find('filter');
    const status = find('status');
    let scope;

    function closeEditor() {
        editor.reset();
        editor.hidden = true;
        if (scope) {
            scope.editor = null;
        }
    }

    function syncParty() {
        const party = find('party');
        party.disabled = field('direction').value === 'payable';
        if (party.disabled) {
            party.value = 'named';
        }
        const patient = party.value === 'patient';
        find('counterparty').hidden = patient;
        field('counterparty').disabled = patient;
        field('counterparty').required = !patient;
        people.disabled = !patient;
        people.required = patient;
        find('patient').hidden = !patient;
    }

    function openEditor(record = null) {
        editor.reset();
        scope.editor = { record, key: clinicRequestKey() };
        people.replaceChildren(new Option('Hasta seçin', ''));
        if (record?.patient) {
            people.add(new Option(record.patient.name, record.patient.id, true, true));
        }
        find('party').value = record?.patient ? 'patient' : 'named';
        for (const name of ['direction', 'counterparty', 'description', 'amount', 'currency', 'due_on']) {
            if (record) {
                field(name).value = record[name] ?? '';
            }
        }
        syncParty();
        find('editor-title').textContent = record ? 'Borç veya alacağı düzenle' : 'Yeni borç veya alacak';
        editor.hidden = false;
        clinicStatus(status);
        field('direction').focus();
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

    async function history(record, page = 1) {
        const current = scope;
        if (!current) {
            return;
        }
        const request = ++current.historyRequest;
        find('history').hidden = true;
        try {
            const data = await api(current.path + '/' + record.id + '?page=' + page);
            if (scope !== current || request !== current.historyRequest) {
                return;
            }
            find('history-title').textContent = record.description + ' · Ödeme hareketleri';
            find('history-list').replaceChildren(...(data.movements.data.length ? data.movements.data.map((entry) => clinicRecordRow([
                entry.entry_date, entry.description, formatClinicAmount(entry.amount, data.currency),
                channels[entry.channel], entry.voided ? 'İptal' : '',
            ].filter(Boolean).join(' · '))) : [clinicRecordRow('Henüz hareket yok.')]));
            clinicPager(find('history-pages'), data.movements, (next) => history(record, next));
            find('history').hidden = false;
        } catch (error) {
            if (scope === current && request === current.historyRequest) {
                clinicStatus(status, error.message);
            }
        }
    }

    function render(data) {
        scope.loaded = true;
        find('new').hidden = !data.can_manage;
        find('list').replaceChildren(...(data.balances.data.length ? data.balances.data.map((record) => {
            const label = record.direction === 'receivable' ? 'Alacak' : 'Borç';
            const buttons = [clinicAction('Ödeme geçmişi', () => history(record))];
            if (data.can_manage) {
                if (record.status === 'open') {
                    buttons.push(clinicAction(record.direction === 'receivable' ? 'Tahsilat ekle' : 'Ödeme ekle', () => settle(record)));
                }
                if (record.status !== 'voided') {
                    buttons.push(clinicAction('Düzenle', () => openEditor(record)));
                }
                buttons.push(clinicAction(record.status === 'voided' ? 'Yeniden etkinleştir' : 'İptal et', () => updateStatus(record)));
            }
            return clinicRecordRow([
                '#' + record.id, label, record.patient?.name || record.counterparty, record.description,
                'Tutar ' + formatClinicAmount(record.amount, record.currency),
                'Ödenen ' + formatClinicAmount(record.settled, record.currency),
                'Kalan ' + formatClinicAmount(record.remaining, record.currency),
                record.due_on ? 'Vade ' + record.due_on : '', record.status === 'voided' ? 'İptal' : '',
            ].filter(Boolean).join(' · '), buttons);
        }) : [clinicRecordRow('Bu durumda borç veya alacak kaydı yok.')]));
        clinicPager(find('pages'), data.balances, load);
    }

    function load(page = 1) {
        const current = scope;
        if (current?.busy) {
            current.nextPage = page;
            return;
        }
        return run(current, 'Borç ve alacaklar yükleniyor…', () => api(current.path + '?page=' + page + '&status=' + filter.value), (data) => {
            if (current.nextPage === undefined) {
                render(data);
            }
        });
    }

    async function updateStatus(record) {
        const current = scope;
        const voiding = record.status !== 'voided';
        if (voiding && !window.confirm('Bu borç veya alacak kaydı iptal edilsin mi?')) {
            return;
        }
        const saved = await run(current, 'Kayıt güncelleniyor…', () => api(current.path + '/' + record.id + '/status', {
            revision: record.revision, status: voiding ? 'voided' : 'active',
        }, 'PUT'), closeEditor);
        if (saved && current === scope) {
            onChanged();
            load();
        }
    }

    editor.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        const editing = current.editor;
        const values = Object.fromEntries(new FormData(editor));
        values.currency = values.currency.toUpperCase();
        const saved = await run(current, 'Kayıt kaydediliyor…', () => editing.record
            ? api(current.path + '/' + editing.record.id, { ...values, revision: editing.record.revision }, 'PUT')
            : api(current.path, { ...values, request_key: editing.key }), closeEditor);
        if (saved && current === scope) {
            onChanged();
            load();
        }
    });

    find('search').addEventListener('click', () => {
        const current = scope;
        const query = find('query').value.trim();
        if (query.length < 2) {
            clinicStatus(status, 'Hasta adı için en az iki karakter girin.');
            return;
        }
        const activeEditor = current.editor;
        run(current, 'Hasta aranıyor…', () => api(current.path + '/patients?' + new URLSearchParams({ q: query })), (patients) => {
            if (current.editor !== activeEditor) {
                return;
            }
            people.replaceChildren(new Option('Hasta seçin', ''), ...patients.map((patient) => new Option(patient.name, patient.id)));
            clinicStatus(status, patients.length ? '' : 'Bu şubede eşleşen hasta bulunamadı.');
        });
    });
    find('party').addEventListener('change', syncParty);
    field('direction').addEventListener('change', syncParty);
    find('new').addEventListener('click', () => openEditor());
    find('close').addEventListener('click', closeEditor);
    find('refresh').addEventListener('click', () => load());
    filter.addEventListener('change', () => load());
    panel.addEventListener('toggle', () => {
        if (panel.open && !scope?.loaded) {
            load();
        }
    });

    return {
        setBranch(id) {
            scope = id ? { path: '/branches/' + id + '/crm/balances', historyRequest: 0 } : null;
            panel.open = false;
            filter.value = 'active';
            closeEditor();
            find('new').hidden = true;
            find('list').replaceChildren();
            find('history').hidden = true;
            clinicPager(find('pages'), null);
            clinicStatus(status);
        },
        openOverdue() {
            if (!scope) {
                return;
            }
            filter.value = 'overdue';
            panel.open = true;
            panel.querySelector('summary').focus();
            find('history').hidden = true;
            scope.historyRequest++;
            find('list').replaceChildren();
            clinicPager(find('pages'), null);
            load();
        },
        refresh() {
            if (scope && panel.open) {
                find('history').hidden = true;
                scope.historyRequest++;
                load();
            }
        },
    };
}
