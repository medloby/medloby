const statusLabels = new Map([
    ['new', 'Yeni'],
    ['contacted', 'Görüşüldü'],
    ['offer', 'Teklif aşamasında'],
    ['converted', 'Hastaya dönüştü'],
    ['lost', 'Sonuçsuz'],
]);
const leadDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'Europe/Istanbul' });

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
    const assignment = find('assignment');
    const assignee = form.elements.namedItem('assigned_business_user_id');
    const editorTitle = find('editor-title');
    const editorSummary = find('summary');
    const save = find('save');
    let branch = null;
    let listRequest = 0;
    let page = 1;
    let activeEditor = null;

    filter.append(...Array.from(statusLabels, ([value, label]) => new Option(label, value)));

    function showStatus(node, text = '') {
        node.textContent = text;
        node.hidden = !text;
    }

    function leadName(lead) {
        return [lead.first_name, lead.last_name].filter(Boolean).join(' ') || 'İsimsiz hasta adayı';
    }

    function leadRow(lead) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        const name = leadName(lead);
        find('name', row).textContent = name;
        showStatus(find('contact', row), [lead.phone, lead.email].filter(Boolean).join(' · '));
        find('row-status', row).textContent = statusLabels.get(lead.status) ?? lead.status;
        showStatus(find('stage', row), lead.pipeline_stage?.name ?? '');
        const details = find('details', row);
        details.dataset.leadId = lead.id;
        details.setAttribute('aria-label', name + ' ayrıntısı');
        details.addEventListener('click', () => openEditor(lead));
        return row;
    }

    async function loadList(requestedPage = 1, confirmation = '', focusLeadId = null) {
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
                await loadList(result.last_page, confirmation, focusLeadId);
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
            if (focusLeadId !== null && document.activeElement === document.body) {
                list.querySelector('[data-lead-id="' + CSS.escape(String(focusLeadId)) + '"]')?.focus();
            }
        } catch (error) {
            if (pending === listRequest) {
                create.disabled = error.status === 403;
                showStatus(status, confirmation ? confirmation + ' ' + error.message : error.message);
            }
        }
    }

    function resetEditor() {
        activeEditor = null;
        editor.close();
        form.reset();
        assignee.replaceChildren();
        assignee.disabled = true;
        assignment.hidden = true;
        editorTitle.textContent = '';
        editorSummary.hidden = true;
        for (const part of ['detail-status', 'detail-stage', 'detail-date', 'note-text']) {
            find(part).textContent = '';
        }
        fields.disabled = false;
        close.disabled = false;
        showStatus(formStatus);
    }

    function formValues() {
        return Object.fromEntries(
            Array.from(new FormData(form), ([key, value]) => [key, value.trim() || null]),
        );
    }

    async function openEditor(lead = null) {
        if (!branch || editor.open) {
            return;
        }

        const current = { leadId: lead?.id ?? null, base: '/branches/' + branch + '/crm/leads', original: null, saving: false };
        activeEditor = current;
        editorTitle.textContent = lead ? leadName(lead) : 'Yeni hasta adayı';
        assignment.hidden = !lead;
        assignee.disabled = !lead;
        save.textContent = lead ? 'Değişiklikleri kaydet' : 'Hasta adayını kaydet';
        form.hidden = Boolean(lead);
        editor.showModal();

        if (!lead) {
            form.elements.namedItem('first_name').focus();
            return;
        }

        showStatus(formStatus, 'Aday ayrıntısı yükleniyor…');
        try {
            const [detail, members] = await Promise.all([
                api(current.base + '/' + current.leadId),
                api(current.base + '/assignees'),
            ]);
            if (activeEditor !== current) {
                return;
            }

            editorTitle.textContent = leadName(detail);
            find('detail-status').textContent = statusLabels.get(detail.status) ?? detail.status;
            find('detail-stage').textContent = detail.pipeline_stage?.name ?? 'Belirlenmedi';
            const created = Date.parse(detail.created_at);
            find('detail-date').textContent = Number.isNaN(created) ? '—' : leadDate.format(created);
            find('note-text').textContent = detail.notes ?? '';
            find('notes').hidden = !detail.notes;
            editorSummary.hidden = false;

            for (const name of ['first_name', 'last_name', 'phone', 'email']) {
                form.elements.namedItem(name).value = detail[name] ?? '';
            }
            assignee.replaceChildren(new Option('Atanmamış', ''), ...members.map((member) => new Option(member.name, member.id)));
            const assignedId = String(detail.assigned_business_user_id ?? '');
            assignee.value = Array.from(assignee.options).some((option) => option.value === assignedId) ? assignedId : '';
            current.original = formValues();
            form.hidden = false;
            showStatus(formStatus);
        } catch (error) {
            if (activeEditor === current) {
                showStatus(formStatus, error.message);
            }
        }
    }

    close.addEventListener('click', resetEditor);
    editor.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!activeEditor?.saving) {
            resetEditor();
        }
    });
    create.addEventListener('click', () => openEditor());
    filter.addEventListener('change', () => loadList());
    previous.addEventListener('click', () => loadList(page - 1));
    next.addEventListener('click', () => loadList(page + 1));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = activeEditor;
        if (!current || current.saving || form.hidden) {
            return;
        }

        const editing = current.leadId !== null;
        const values = formValues();
        if (!editing && !Object.values(values).some(Boolean)) {
            showStatus(formStatus, 'Ad, soyad, telefon veya e-posta alanlarından en az birini doldurun.');
            return;
        }

        const payload = editing
            ? Object.fromEntries(Object.entries(values).filter(([name, value]) => value !== current.original[name]))
            : values;
        if (editing && !Object.keys(payload).length) {
            showStatus(formStatus, 'Kaydedilecek değişiklik yok.');
            return;
        }

        current.saving = true;
        fields.disabled = true;
        close.disabled = true;
        showStatus(formStatus, 'Hasta adayı kaydediliyor…');

        try {
            await api(editing ? current.base + '/' + current.leadId : current.base, payload, editing ? 'PUT' : 'POST');
        } catch (error) {
            if (activeEditor === current) {
                showStatus(formStatus, error.message);
            }
            return;
        } finally {
            if (activeEditor === current) {
                current.saving = false;
                fields.disabled = false;
                close.disabled = false;
            }
        }

        if (activeEditor !== current) {
            return;
        }

        resetEditor();
        if (editing) {
            loadList(page, 'Hasta adayı güncellendi.', current.leadId);
            return;
        }
        filter.value = '';
        loadList(1, 'Hasta adayı kaydedildi.');
        onCreated();
    });

    return {
        setBranch(id) {
            if (branch === id) {
                return;
            }

            branch = id;
            listRequest++;
            page = 1;
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
