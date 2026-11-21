const statusLabels = new Map([
    ['new', 'Yeni'],
    ['contacted', 'Görüşüldü'],
    ['offer', 'Teklif aşamasında'],
    ['converted', 'Hastaya dönüştü'],
    ['lost', 'Sonuçsuz'],
]);

export function mountCrmLeads(section, { api, onCreated }) {
    const find = (part, root = section) => root.querySelector('[data-leads-' + part + ']');
    const create = find('new');
    const filter = find('filter');
    const list = find('list');
    const pages = find('pages');
    const previous = find('previous');
    const next = find('next');
    const status = find('status');
    const editor = find('editor');
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const formStatus = find('form-status');
    const close = find('close');
    let branch = null;
    let branchVersion = 0;
    let listRequest = 0;
    let page = 1;
    let saving = false;

    filter.append(...Array.from(statusLabels, ([value, label]) => new Option(label, value)));

    function showStatus(node, text = '') {
        node.textContent = text;
        node.hidden = !text;
    }

    function leadRow(lead) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        find('name', row).textContent = [lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'İsimsiz hasta adayı';
        showStatus(find('contact', row), [lead.phone, lead.email].filter(Boolean).join(' · '));
        find('row-status', row).textContent = statusLabels.get(lead.status) ?? lead.status;
        showStatus(find('stage', row), lead.pipeline_stage?.name ?? '');
        return row;
    }

    async function loadList(requestedPage = 1, confirmation = '') {
        const pending = ++listRequest;
        const params = new URLSearchParams({ per_page: '12', page: requestedPage });
        if (filter.value) {
            params.set('status', filter.value);
        }

        list.replaceChildren();
        pages.hidden = true;
        showStatus(status, 'Hasta adayları yükleniyor…');

        try {
            const result = await api('/branches/' + branch + '/crm/leads?' + params);
            if (pending !== listRequest) {
                return;
            }
            if (result.current_page > result.last_page) {
                await loadList(result.last_page, confirmation);
                return;
            }

            list.replaceChildren(...result.data.map(leadRow));
            page = result.current_page;
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('page').textContent = page + ' / ' + result.last_page;
            pages.hidden = result.last_page < 2;
            create.disabled = false;
            showStatus(status, confirmation || (result.data.length ? '' : 'Bu şubede seçilen duruma uygun hasta adayı yok.'));
        } catch (error) {
            if (pending === listRequest) {
                create.disabled = error.status === 403;
                showStatus(status, confirmation ? confirmation + ' ' + error.message : error.message);
            }
        }
    }

    function resetEditor() {
        editor.close();
        form.reset();
        fields.disabled = false;
        close.disabled = false;
        showStatus(formStatus);
    }

    close.addEventListener('click', resetEditor);
    editor.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!saving) {
            resetEditor();
        }
    });
    create.addEventListener('click', () => editor.showModal());
    filter.addEventListener('change', () => loadList());
    previous.addEventListener('click', () => loadList(page - 1));
    next.addEventListener('click', () => loadList(page + 1));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving || !branch) {
            return;
        }

        const payload = Object.fromEntries(
            Array.from(new FormData(form), ([key, value]) => [key, value.trim() || null]),
        );
        if (!Object.values(payload).some(Boolean)) {
            showStatus(formStatus, 'Ad, soyad, telefon veya e-posta alanlarından en az birini doldurun.');
            return;
        }

        const pending = branchVersion;
        saving = true;
        fields.disabled = true;
        close.disabled = true;
        showStatus(formStatus, 'Hasta adayı kaydediliyor…');

        try {
            await api('/branches/' + branch + '/crm/leads', payload);
        } catch (error) {
            if (pending === branchVersion) {
                showStatus(formStatus, error.message);
            }
            return;
        } finally {
            if (pending === branchVersion) {
                saving = false;
                fields.disabled = false;
                close.disabled = false;
            }
        }

        if (pending === branchVersion) {
            resetEditor();
            filter.value = '';
            loadList(1, 'Hasta adayı kaydedildi.');
            onCreated();
        }
    });

    return {
        setBranch(id) {
            if (branch === id) {
                return;
            }

            branch = id;
            branchVersion++;
            listRequest++;
            page = 1;
            saving = false;
            filter.value = '';
            list.replaceChildren();
            pages.hidden = true;
            create.disabled = false;
            resetEditor();
            showStatus(status);
            section.hidden = !id;
            if (id) {
                loadList();
            }
        },
    };
}
