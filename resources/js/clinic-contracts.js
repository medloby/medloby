const endpoint = '/admin/platform-contracts';
const clinicContractType = 'clinic_membership';
const statusLabels = {
    draft: 'Taslak',
    published: 'Yayımlandı',
    inactive: 'Yayından kaldırıldı',
};

export function mountClinicContracts(dialog, trigger, { api }) {
    const find = (part, root = dialog) => root.querySelector('[data-contract-' + part + ']');
    const listView = find('list-view');
    const detail = find('detail');
    const status = find('status');
    const list = find('list');
    const pages = find('pages');
    const previous = find('prev');
    const next = find('next');
    const create = find('new');
    const back = find('back');
    const form = find('form');
    const editorFields = form.querySelector('fieldset');
    const fields = ['title', 'version', 'content'].map((name) => form.elements.namedItem(name));
    const [title, version, content] = fields;
    let selected;
    let original = [];
    let request = 0;
    let page = 1;
    let saving = false;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function clearEditor() {
        selected = undefined;
        original = [];
        form.hidden = true;
        editorFields.disabled = false;
        form.reset();
    }

    function clearDialog() {
        request++;
        page = 1;
        saving = false;
        list.replaceChildren();
        clearEditor();
        showStatus();
        dialog.close();
    }

    function allowLeavingEditor() {
        return !saving && (
            selected?.status !== 'draft'
            || fields.every((field, index) => field.value === original[index])
            || window.confirm('Kaydedilmemiş değişiklikler var. Bu taslaktan çıkılsın mı?')
        );
    }

    function contractRow(contract) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        find('row-title', row).textContent = contract.title;
        find('row-state', row).textContent = 'Sürüm ' + contract.version + ' · ' + (statusLabels[contract.status] ?? contract.status);
        row.querySelector('button').addEventListener('click', () => loadContract(contract.id));
        return row;
    }

    async function loadList(requestedPage, confirmation = '') {
        const pending = ++request;
        const returning = !detail.hidden;
        clearEditor();
        detail.hidden = true;
        listView.hidden = false;
        list.replaceChildren();
        pages.hidden = true;
        if (returning) {
            create.focus();
        }
        showStatus('Sözleşmeler yükleniyor…');

        try {
            const result = await api(endpoint + '?contract_type=' + clinicContractType + '&page=' + requestedPage);
            if (pending !== request) {
                return;
            }
            if (result.current_page > result.last_page) {
                await loadList(result.last_page, confirmation);
                return;
            }

            page = result.current_page;
            list.replaceChildren(...result.data.map(contractRow));
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('page').textContent = page + ' / ' + result.last_page;
            pages.hidden = result.last_page < 2;
            showStatus(confirmation || (result.data.length ? '' : 'Klinik üyelik sözleşmesi bulunamadı.'));
        } catch (error) {
            if (pending === request) {
                showStatus(confirmation ? confirmation + ' ' + error.message : error.message);
            }
        }
    }

    function showEditor(contract) {
        selected = contract;
        for (const field of fields) {
            field.value = contract[field.name] ?? '';
        }
        original = fields.map((field) => field.value);
        title.readOnly = content.readOnly = contract.status !== 'draft';
        version.readOnly = Boolean(contract.id);
        find('save').hidden = contract.status !== 'draft';
        const state = find('state');
        state.textContent = contract.id ? (statusLabels[contract.status] ?? contract.status) : 'Yeni taslak';
        listView.hidden = true;
        detail.hidden = false;
        form.hidden = false;
        state.focus();
        showStatus();
    }

    async function loadContract(id) {
        const pending = ++request;
        listView.hidden = true;
        detail.hidden = false;
        back.focus();
        showStatus('Sözleşme yükleniyor…');

        try {
            const contract = await api(endpoint + '/' + id);
            if (pending === request) {
                showEditor(contract);
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving || selected?.status !== 'draft') {
            return;
        }

        const pending = request;
        const existingId = selected.id;
        const values = { title: title.value.trim(), content: content.value };
        saving = true;
        editorFields.disabled = true;
        showStatus('Taslak kaydediliyor…');

        try {
            if (existingId) {
                await api(endpoint + '/' + existingId, { ...values, draft_revision: selected.draft_revision }, 'PUT');
            } else {
                await api(endpoint, { ...values, version: version.value.trim(), contract_type: clinicContractType });
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
            return;
        } finally {
            if (pending === request) {
                saving = false;
                editorFields.disabled = false;
            }
        }

        if (pending === request) {
            await loadList(existingId ? page : 1, 'Taslak kaydedildi.');
        }
    });

    trigger.addEventListener('click', () => {
        dialog.showModal();
        loadList(1);
    });
    create.addEventListener('click', () => {
        request++;
        showEditor({ status: 'draft' });
    });
    find('refresh').addEventListener('click', () => loadList(page));
    previous.addEventListener('click', () => loadList(page - 1));
    next.addEventListener('click', () => loadList(page + 1));
    back.addEventListener('click', () => {
        if (allowLeavingEditor()) {
            loadList(page);
        }
    });
    find('close').addEventListener('click', () => {
        if (allowLeavingEditor()) {
            clearDialog();
        }
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (allowLeavingEditor()) {
            clearDialog();
        }
    });

    return {
        start() {
            trigger.hidden = false;
        },
        stop() {
            trigger.hidden = true;
            clearDialog();
        },
    };
}
