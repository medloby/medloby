const endpoint = '/admin/platform-contracts';
const clinicContractType = 'clinic_membership';
const statusLabels = {
    draft: 'Taslak',
    published: 'Yayımlandı',
    inactive: 'Yayından kaldırıldı',
};
const dateLabel = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short' });

function localDateTime(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 19);
}

function contractState(contract) {
    if (contract.status === 'published' && contract.effective_at && Date.parse(contract.effective_at) > Date.now()) {
        return 'Yürürlük bekliyor';
    }
    if (contract.status === 'published' && contract.expires_at && Date.parse(contract.expires_at) <= Date.now()) {
        return 'Süresi doldu';
    }
    return statusLabels[contract.status] ?? contract.status;
}

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
    const fields = ['title', 'version', 'content', 'effective_at', 'expires_at'].map((name) => form.elements.namedItem(name));
    const [title, version, content, effectiveAt, expiresAt] = fields;
    const publish = find('publish');
    const deactivate = find('deactivate');
    const acceptances = find('acceptances');
    const acceptanceList = find('acceptance-list');
    const acceptanceStatus = find('acceptance-status');
    const acceptancePages = find('acceptance-pages');
    const previousAcceptance = find('acceptance-prev');
    const nextAcceptance = find('acceptance-next');
    let selected;
    let original = [];
    let request = 0;
    let page = 1;
    let saving = false;
    let acceptanceRequest = 0;
    let acceptancePage = 1;

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
        acceptanceRequest++;
        acceptances.open = false;
        acceptances.hidden = true;
        acceptanceList.replaceChildren();
        acceptancePages.hidden = true;
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
            || !hasChanges()
            || window.confirm('Kaydedilmemiş değişiklikler var. Bu taslaktan çıkılsın mı?')
        );
    }

    function hasChanges() {
        return fields.some((field, index) => field.value !== original[index]);
    }

    function contractRow(contract) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        find('row-title', row).textContent = contract.title;
        find('row-state', row).textContent = 'Sürüm ' + contract.version + ' · ' + contractState(contract);
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

    function showEditor(contract, confirmation = '') {
        selected = contract;
        for (const field of fields) {
            const value = contract[field.name];
            field.value = field.type === 'datetime-local' ? localDateTime(value) : (value ?? '');
        }
        original = fields.map((field) => field.value);
        const draft = contract.status === 'draft';
        title.readOnly = content.readOnly = effectiveAt.readOnly = expiresAt.readOnly = !draft;
        version.readOnly = Boolean(contract.id);
        find('save').hidden = !draft;
        find('date-help').hidden = !draft;
        publish.hidden = !draft || !contract.id;
        deactivate.hidden = contract.status !== 'published';
        acceptances.hidden = draft;
        find('metadata').hidden = !contract.id;
        find('published-at').textContent = contract.published_at ? dateLabel.format(new Date(contract.published_at)) : 'Henüz yayımlanmadı';
        find('updated-at').textContent = [contract.updated_by?.name, contract.updated_at && dateLabel.format(new Date(contract.updated_at))]
            .filter(Boolean).join(' · ');
        const state = find('state');
        state.textContent = contract.id ? contractState(contract) : 'Yeni taslak';
        listView.hidden = true;
        detail.hidden = false;
        form.hidden = false;
        state.focus();
        showStatus(confirmation);
    }

    async function loadContract(id, confirmation = '') {
        const pending = ++request;
        clearEditor();
        listView.hidden = true;
        detail.hidden = false;
        back.focus();
        showStatus('Sözleşme yükleniyor…');

        try {
            const contract = await api(endpoint + '/' + id);
            if (pending === request) {
                showEditor(contract, confirmation);
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    async function loadAcceptances(requestedPage) {
        if (!selected?.id || acceptances.hidden || !acceptances.open) {
            return;
        }

        const pending = ++acceptanceRequest;
        acceptanceList.replaceChildren();
        acceptancePages.hidden = true;
        acceptanceStatus.hidden = false;
        acceptanceStatus.textContent = 'Kabul kayıtları yükleniyor…';
        try {
            const result = await api(endpoint + '/' + selected.id + '/acceptances?page=' + requestedPage);
            if (pending !== acceptanceRequest || !acceptances.open) {
                return;
            }

            acceptancePage = result.current_page;
            acceptanceList.replaceChildren(...result.data.map((record) => {
                const row = find('acceptance-row').content.firstElementChild.cloneNode(true);
                find('acceptance-clinic', row).textContent = record.business.name;
                find('acceptance-detail', row).textContent = [
                    record.user.name, 'Sürüm ' + record.contract_version,
                    record.accepted_at && dateLabel.format(new Date(record.accepted_at)),
                ].filter(Boolean).join(' · ');
                return row;
            }));
            previousAcceptance.disabled = acceptancePage <= 1;
            nextAcceptance.disabled = acceptancePage >= result.last_page;
            find('acceptance-page').textContent = acceptancePage + ' / ' + result.last_page;
            acceptancePages.hidden = result.last_page < 2;
            acceptanceStatus.hidden = result.data.length > 0;
            acceptanceStatus.textContent = result.data.length ? '' : 'Bu sürüm için kabul kaydı bulunamadı.';
        } catch (error) {
            if (pending === acceptanceRequest) {
                acceptanceStatus.textContent = error.message;
            }
        }
    }

    async function changePublication(action) {
        if (saving || !selected?.id) {
            return;
        }

        const publishing = action === 'publish';
        if (publishing && hasChanges()) {
            showStatus('Sürümü yayımlamadan önce taslaktaki değişiklikleri kaydedin.');
            return;
        }
        const start = selected.effective_at ? dateLabel.format(new Date(selected.effective_at)) : 'yayımlandığı an';
        const question = publishing
            ? 'Sürüm ' + selected.version + ' yayımlansın mı? Yürürlük zamanı ' + start + '.'
            : 'Bu sürüm yayından kaldırılsın mı? Yürürlükte başka sürüm kalmazsa yeni klinik başvuruları durur.';
        if (!window.confirm(question)) {
            return;
        }

        const pending = request;
        const id = selected.id;
        saving = true;
        editorFields.disabled = true;
        showStatus(publishing ? 'Sürüm yayımlanıyor…' : 'Sürüm yayından kaldırılıyor…');
        try {
            await api(endpoint + '/' + id + '/' + action, publishing ? { draft_revision: selected.draft_revision } : {});
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
            await loadContract(id, publishing ? 'Sürüm yayımlandı.' : 'Sürüm yayından kaldırıldı.');
        }
    }

    publish.addEventListener('click', () => changePublication('publish'));
    deactivate.addEventListener('click', () => changePublication('deactivate'));
    acceptances.addEventListener('toggle', () => loadAcceptances(1));
    previousAcceptance.addEventListener('click', () => loadAcceptances(acceptancePage - 1));
    nextAcceptance.addEventListener('click', () => loadAcceptances(acceptancePage + 1));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving || selected?.status !== 'draft') {
            return;
        }

        const pending = request;
        const existingId = selected.id;
        const values = { title: title.value.trim(), content: content.value };
        for (const date of [effectiveAt, expiresAt]) {
            if (!existingId || date.value !== original[fields.indexOf(date)]) {
                values[date.name] = date.value ? new Date(date.value).toISOString() : null;
            }
        }
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
