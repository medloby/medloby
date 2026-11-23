const kindSettings = {
    tasks: {
        types: ['other', 'call', 'whatsapp', 'email', 'meeting', 'follow_up'], date: 'due_at', label: 'Son tarih', text: 'description',
        assignment: { label: 'Görev kime atansın?', unassigned: 'Atanmamış' },
    },
    'follow-ups': {
        types: ['call', 'whatsapp', 'email', 'meeting', 'other'], date: 'scheduled_at', label: 'Planlanan tarih ve saat', text: 'notes',
        assignment: { label: 'Hatırlatma kime gitsin?', unassigned: 'Klinik sahibi' },
    },
    activities: { types: ['note', 'call', 'whatsapp', 'email', 'meeting', 'follow_up', 'other'], date: 'occurred_at', label: 'Gerçekleşme tarihi', text: 'description' },
};

const typeLabels = {
    call: 'Telefon', whatsapp: 'WhatsApp', email: 'E-posta', meeting: 'Görüşme',
    follow_up: 'Takip', note: 'Not', other: 'Diğer',
};
const statusLabels = { pending: 'Bekliyor', in_progress: 'Devam ediyor', completed: 'Tamamlandı', cancelled: 'İptal edildi' };
const workDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short' });

function localDateInput(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export function mountCrmLeadWork(panel, { api, onChanged, openConversation }) {
    const find = (part, root = panel) => root.querySelector('[data-work-' + part + ']');
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const input = (name) => form.elements.namedItem(name);
    const assignee = input('assigned_business_user_id');
    const linkForm = find('link');
    const linkFields = linkForm.querySelector('fieldset');
    const conversation = linkForm.elements.namedItem('conversation');
    const content = find('content');
    const status = find('status');
    let lead = null;
    let editing = null;

    function showStatus(text = '') {
        status.textContent = text;
        status.hidden = !text;
    }

    function syncFields(assignedId = '') {
        const kind = input('kind').value;
        const { types, label, assignment } = kindSettings[kind];
        input('type').replaceChildren(...types.map((type) => new Option(typeLabels[type], type)));
        input('when').required = kind === 'follow-ups' || (kind === 'activities' && editing !== null);
        find('time-label').textContent = label + (input('when').required ? '' : ' (isteğe bağlı)');
        assignee.disabled = !assignment;
        find('assignee').hidden = !assignment;
        if (!assignment) {
            return;
        }

        find('assignee-label').textContent = assignment.label;
        const options = (lead?.members ?? []).map((member) => new Option(member.name, member.id));
        assignee.replaceChildren(new Option(assignment.unassigned, ''), ...options);
        if (assignedId && !options.some((option) => option.value === assignedId)) {
            assignee.add(new Option('Önceki atama', assignedId));
        }
        assignee.value = assignedId;
    }

    function resetForm() {
        editing = null;
        form.reset();
        input('kind').disabled = false;
        find('edit-title').textContent = 'Yeni kayıt';
        find('save').textContent = 'Kaydı ekle';
        find('discard').hidden = true;
        syncFields();
    }

    function payload() {
        const settings = kindSettings[input('kind').value];
        const data = {
            title: input('title').value.trim(),
            type: input('type').value,
            [settings.text]: input('description').value.trim() || null,
            [settings.date]: input('when').value ? new Date(input('when').value).toISOString() : null,
        };
        if (!assignee.disabled) {
            data.assigned_business_user_id = assignee.value ? Number(assignee.value) : null;
        }

        return data;
    }

    function edit(kind, record) {
        editing = { id: record.id, original: null };
        const settings = kindSettings[kind];
        input('kind').value = kind;
        input('kind').disabled = true;
        syncFields(String(record.assigned_business_user_id ?? ''));
        input('type').value = record.type;
        input('title').value = record.title;
        input('description').value = record[settings.text] ?? '';
        input('when').value = localDateInput(record[settings.date]);
        editing.original = payload();
        find('edit-title').textContent = 'Kaydı düzenle';
        find('save').textContent = 'Kaydı güncelle';
        find('discard').hidden = false;
        input('title').focus();
    }

    function action(label, handler) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'offer-button offer-button-secondary';
        button.textContent = label;
        button.addEventListener('click', handler);
        return button;
    }

    function renderRecords(current, kind, records) {
        const section = panel.querySelector('[data-work-group="' + kind + '"]');
        const settings = kindSettings[kind];
        const rows = records.map((record) => {
            const row = find('row').content.firstElementChild.cloneNode(true);
            const title = find('row-title', row);
            title.textContent = record.title;
            const date = record[settings.date] ? workDate.format(new Date(record[settings.date])) : '';
            let assigneeName = settings.assignment?.unassigned;
            if (settings.assignment && record.assigned_business_user_id) {
                assigneeName = current.members.find((member) => String(member.id) === String(record.assigned_business_user_id))?.name
                    ?? 'Önceki atama';
            }
            find('row-meta', row).textContent = [typeLabels[record.type], statusLabels[record.status], date, assigneeName].filter(Boolean).join(' · ');
            if (kind === 'follow-ups') {
                title.dataset.followUpId = record.id;
                title.tabIndex = -1;
            }
            const description = find('row-description', row);
            description.textContent = record[settings.text] ?? '';
            description.hidden = !record[settings.text];
            const buttons = find('row-actions', row);
            buttons.append(action('Düzenle', () => edit(kind, record)));
            if (record.status === 'pending' || record.status === 'in_progress') {
                const path = current.branchCrm + '/' + kind + '/' + record.id;
                buttons.append(
                    action('Tamamla', () => write(current, path + '/complete', {}, 'POST', 'Kayıt tamamlandı.')),
                    action('İptal et', () => write(current, path + '/cancel', {}, 'POST', 'Kayıt iptal edildi.')),
                );
            }
            return row;
        });
        find('list', section).replaceChildren(...rows);
        find('empty', section).hidden = rows.length > 0;
    }

    function hasUnsavedWork() {
        if (editing) {
            return Object.entries(payload()).some(([name, value]) => value !== editing.original[name]);
        }
        return Boolean(input('title').value.trim() || input('description').value.trim() || input('when').value);
    }

    async function openLinkedConversation(current, id, button) {
        if (lead !== current || current.saving || current.openingConversation) {
            return;
        }

        current.openingConversation = true;
        button.disabled = true;
        try {
            const { conversation: item } = await api('/conversations/' + id);
            if (lead === current && !current.saving) {
                await openConversation(item, hasUnsavedWork() || Boolean(conversation.value));
            }
        } catch (error) {
            if (lead === current) {
                showStatus(error.message);
            }
        } finally {
            current.openingConversation = false;
            button.disabled = false;
        }
    }

    function renderConversations(current, records) {
        const title = (record) => [record.subject || 'Görüşme #' + record.id, record.patient_name].filter(Boolean).join(' · ');
        const linked = records.filter((record) => record.attached);
        find('conversations').replaceChildren(...linked.map((record) => {
            const row = document.createElement('li');
            row.className = 'flex flex-wrap items-center justify-between gap-2';
            const label = document.createElement('span');
            label.className = 'min-w-0 break-words';
            label.textContent = title(record);
            const button = action('Görüşmeyi aç', () => openLinkedConversation(current, record.id, button));
            button.setAttribute('aria-label', button.textContent + ' · ' + label.textContent);
            row.append(label, button);
            return row;
        }));
        find('no-conversations').hidden = linked.length > 0;
        const options = records.filter((record) => !record.attached);
        const placeholder = new Option('Görüşme seçin', '', true, true);
        placeholder.disabled = true;
        conversation.replaceChildren(placeholder, ...options.map((record) => new Option(title(record), record.id)));
        linkForm.hidden = options.length === 0;
        find('no-options').hidden = options.length > 0;
        find('link-button').textContent = current.hasPatient ? 'Görüşmeyi bağla' : 'Hastayı ve görüşmeyi bağla';
    }

    async function load(current, confirmation = '') {
        current.loading = true;
        current.loaded = false;
        showStatus('Aday kayıtları yükleniyor…');
        try {
            const kinds = Object.keys(kindSettings);
            const records = await Promise.all([
                ...kinds.map((kind) => api(current.branchCrm + '/' + kind + '?crm_lead_id=' + current.id)),
                api(current.conversationPath),
            ]);
            if (lead !== current) {
                return;
            }
            kinds.forEach((kind, index) => renderRecords(current, kind, records[index]));
            renderConversations(current, records.at(-1));
            current.loaded = true;
            content.hidden = false;
            if (current.followUpId) {
                panel.querySelector('[data-follow-up-id="' + current.followUpId + '"]')?.focus();
                current.followUpId = null;
            }
            showStatus(confirmation);
        } catch (error) {
            if (lead === current) {
                showStatus([confirmation, error.message].filter(Boolean).join(' '));
            }
        } finally {
            current.loading = false;
        }
    }

    function setBusy(busy) {
        fields.disabled = busy;
        linkFields.disabled = busy;
        for (const button of panel.querySelectorAll('[data-work-row-actions] button')) {
            button.disabled = busy;
        }
    }

    async function write(current, path, data, method, confirmation, afterSave = null) {
        if (lead !== current || current.saving) {
            return;
        }
        current.saving = true;
        setBusy(true);
        showStatus('Kaydediliyor…');
        try {
            await api(path, data, method);
            if (lead === current) {
                afterSave?.();
                await load(current, confirmation);
                if (lead === current) {
                    onChanged();
                }
            }
        } catch (error) {
            if (lead === current) {
                showStatus(error.message);
            }
        } finally {
            if (lead === current) {
                current.saving = false;
                setBusy(false);
            }
        }
    }

    panel.addEventListener('toggle', () => {
        if (panel.open && lead && !lead.loaded && !lead.loading) {
            load(lead);
        }
    });
    input('kind').addEventListener('change', () => syncFields(assignee.value));
    find('discard').addEventListener('click', resetForm);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!lead || lead.saving) {
            return;
        }
        const data = payload();
        if (!data.title) {
            showStatus('Başlık girin.');
            return;
        }
        const base = lead.branchCrm + '/' + input('kind').value;
        const changes = editing
            ? Object.fromEntries(Object.entries(data).filter(([key, value]) => value !== editing.original[key]))
            : { ...data, crm_lead_id: lead.id };
        if (!Object.keys(changes).length) {
            showStatus('Kaydedilecek değişiklik yok.');
            return;
        }
        write(lead, editing ? base + '/' + editing.id : base, changes, editing ? 'PUT' : 'POST', 'Kayıt kaydedildi.', resetForm);
    });

    linkForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!lead || !conversation.value) {
            return;
        }
        const current = lead;
        write(current, current.conversationPath + '/' + conversation.value + '/attach', { link_patient: !current.hasPatient }, 'POST', 'Görüşme adaya bağlandı.', () => {
            current.hasPatient = true;
        });
    });

    return {
        setLead(detail, { branchCrm = '', members = [], followUpId = null } = {}) {
            lead = detail ? {
                id: detail.id, hasPatient: detail.patient_profile_id !== null, branchCrm, members, followUpId,
                conversationPath: branchCrm + '/leads/' + detail.id + '/conversations', loading: false, loaded: false, saving: false, openingConversation: false,
            } : null;
            panel.open = Boolean(lead?.followUpId);
            panel.hidden = !lead;
            content.hidden = true;
            resetForm();
            for (const list of panel.querySelectorAll('[data-work-list], [data-work-conversations]')) {
                list.replaceChildren();
            }
            conversation.replaceChildren();
            setBusy(false);
            showStatus();
        },
    };
}
