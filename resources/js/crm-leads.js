import { mountCrmLeadAppointments } from './crm-lead-appointments';
import { crmLeadStatusLabels as statusLabels } from './crm-lead-status';
import { mountCrmLeadWork } from './crm-lead-work';
import { mountCrmLeadCare } from './crm-lead-care';

const leadDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'Europe/Istanbul' });
const historyDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Istanbul' });

export function mountCrmLeads(section, { api, onChanged, openAppointment, bookForPatient, openConversation }) {
    const find = (part, root = section) => root.querySelector('[data-leads-' + part + ']');
    const create = find('new');
    const filter = find('filter');
    const list = find('list');
    const pages = find('pages');
    const previous = find('previous');
    const next = find('next');
    const status = find('status');
    const editor = find('editor');
    const care = mountCrmLeadCare(find('care'), api);
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const formStatus = find('form-status');
    const close = find('close');
    const assignment = find('assignment');
    const assignee = form.elements.namedItem('assigned_business_user_id');
    const editorTitle = find('editor-title');
    const editorSummary = find('summary');
    const save = find('save');
    const stageField = find('stage-field');
    const stage = form.elements.namedItem('pipeline_stage_id');
    const stageNoteField = find('stage-note-field');
    const stageNote = form.elements.namedItem('pipeline_stage_note');
    const history = find('history');
    const historyList = find('history-list');
    const outcome = find('outcome');
    const outcomeForm = find('outcome-form');
    const outcomeFields = outcomeForm.querySelector('fieldset');
    const outcomeStatus = find('outcome-status');
    const outcomeChoice = outcomeForm.elements.namedItem('outcome');
    const lostReason = outcomeForm.elements.namedItem('lost_reason');
    const leadAppointments = mountCrmLeadAppointments(find('appointments'), {
        api,
        openAppointment: (appointment) => goToCalendar(() => openAppointment(appointment)),
        bookForPatient: (patient) => goToCalendar(() => bookForPatient(patient)),
        linkPatient: () => leadWork.openPatientSearch(),
    });
    const leadWork = mountCrmLeadWork(find('work'), {
        api,
        openConversation(item, hasWorkChanges) {
            if (canLeaveEditor('Kaydedilmemiş değişiklikler var. Görüşme açılsın mı?', hasWorkChanges)) {
                return openConversation(item, activeEditor.leadId);
            }
        },
        onChanged() {
            onChanged();
            leadAppointments.refresh();
        },
    });
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
        leadWork.setLead(null);
        leadAppointments.setLead(null);
        care.setLead(null);
        editor.close();
        form.reset();
        assignee.replaceChildren();
        assignee.disabled = true;
        assignment.hidden = true;
        editorTitle.textContent = '';
        editorSummary.hidden = true;
        for (const part of ['detail-status', 'detail-stage', 'detail-date', 'note-text', 'result-text']) {
            find(part).textContent = '';
        }
        stage.replaceChildren();
        stage.disabled = true;
        stageField.hidden = true;
        syncStageNote();
        historyList.replaceChildren();
        history.open = false;
        history.hidden = true;
        outcomeForm.reset();
        syncLostReason();
        outcome.open = false;
        outcome.hidden = true;
        showStatus(outcomeStatus);
        fields.disabled = false;
        outcomeFields.disabled = false;
        close.disabled = false;
        showStatus(formStatus);
    }

    function canLeaveEditor(question, hasWorkChanges = false) {
        if (!activeEditor || activeEditor.saving) {
            return false;
        }
        const hasLeadChanges = activeEditor.original && Object.keys(changedValues(activeEditor)).length > 0;
        return (!hasLeadChanges && !hasWorkChanges) || window.confirm(question);
    }

    function goToCalendar(open) {
        if (!canLeaveEditor('Aday bilgilerinde kaydedilmemiş değişiklikler var. Takvime geçilsin mi?')) {
            return;
        }

        resetEditor();
        open();
    }

    function formValues() {
        return Object.fromEntries(
            Array.from(new FormData(form), ([key, value]) => [key, value.trim() || null]),
        );
    }

    function syncStageNote() {
        const enabled = !stage.disabled && stage.value !== '' && stage.value !== activeEditor?.stageId;
        stageNote.disabled = !enabled;
        stageNoteField.hidden = !enabled;
        if (!enabled) {
            stageNote.value = '';
        }
    }

    function syncLostReason() {
        const enabled = outcomeChoice.value === 'lost';
        lostReason.disabled = !enabled;
        lostReason.required = enabled;
        find('lost-reason-field').hidden = !enabled;
    }

    function renderHistory(records) {
        historyList.replaceChildren(...Array.from(records).reverse().map((record) => {
            const row = find('history-row').content.firstElementChild.cloneNode(true);
            const from = record.from_stage?.name ?? (record.from_stage_id ? 'Önceki aşama' : 'Başlangıç');
            const to = record.to_stage?.name ?? 'Aşama bilgisi yok';
            find('history-transition', row).textContent = from + ' → ' + to;
            const changedAt = Date.parse(record.changed_at);
            const date = Number.isNaN(changedAt) ? '' : historyDate.format(changedAt);
            find('history-by', row).textContent = [date, record.changed_by_business_user?.user?.name].filter(Boolean).join(' · ');
            showStatus(find('history-note', row), record.notes ?? '');
            return row;
        }));
        find('no-history').hidden = records.length > 0;
        history.hidden = false;
    }

    async function openEditor(lead = null, { followUpId = null, openWork = false, preparedLead = null } = {}) {
        if (!branch || editor.open) {
            return;
        }

        const branchCrm = '/branches/' + branch + '/crm';
        const current = { leadId: lead?.id ?? null, base: branchCrm + '/leads', original: null, stageId: '', saving: false };
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
            const [detail, members, stages] = await Promise.all([
                preparedLead ?? api(current.base + '/' + current.leadId),
                api(current.base + '/assignees'),
                api(branchCrm + '/pipeline-stages'),
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
            const finalized = detail.status === 'converted' || detail.status === 'lost';
            const resolved = Date.parse(detail.status === 'lost' ? detail.lost_at : detail.converted_at);
            const outcomeText = [Number.isNaN(resolved) ? '' : leadDate.format(resolved), detail.lost_reason].filter(Boolean).join(' · ');
            find('result-text').textContent = outcomeText;
            find('result').hidden = !finalized || !outcomeText;
            editorSummary.hidden = false;
            renderHistory(detail.pipeline_stage_histories ?? []);

            for (const name of ['first_name', 'last_name', 'phone', 'email']) {
                form.elements.namedItem(name).value = detail[name] ?? '';
            }
            assignee.replaceChildren(new Option('Atanmamış', ''), ...members.map((member) => new Option(member.name, member.id)));
            const assignedId = String(detail.assigned_business_user_id ?? '');
            assignee.value = Array.from(assignee.options).some((option) => option.value === assignedId) ? assignedId : '';

            current.stageId = String(detail.pipeline_stage_id ?? '');
            const placeholder = new Option(stages.length ? 'Aşama seçin' : 'Bu klinikte aktif aşama yok', '');
            placeholder.disabled = true;
            stage.replaceChildren(placeholder, ...stages.map((item) => new Option(item.name, item.id)));
            if (current.stageId && !stages.some((item) => String(item.id) === current.stageId)) {
                stage.add(new Option((detail.pipeline_stage?.name ?? 'Önceki aşama') + ' (pasif)', current.stageId));
            }
            stage.value = current.stageId;
            stageField.hidden = finalized;
            stage.disabled = finalized || !stages.length;
            syncStageNote();
            outcome.hidden = finalized;
            leadWork.setLead(detail, { branchCrm, members, followUpId, openWork });
            leadAppointments.setLead(current.base + '/' + current.leadId + '/appointments');
            care.setLead(current.base + '/' + current.leadId + '/care-steps');

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
    stage.addEventListener('change', syncStageNote);
    outcomeChoice.addEventListener('change', syncLostReason);

    function changedValues(current) {
        return Object.fromEntries(Object.entries(formValues()).filter(([name, value]) => value !== current.original[name]));
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = activeEditor;
        if (!current || current.saving || form.hidden) {
            return;
        }

        const editing = current.leadId !== null;
        const payload = editing ? changedValues(current) : formValues();
        if (!editing && !Object.values(payload).some(Boolean)) {
            showStatus(formStatus, 'Ad, soyad, telefon veya e-posta alanlarından en az birini doldurun.');
            return;
        }

        if (editing && !Object.keys(payload).length) {
            showStatus(formStatus, 'Kaydedilecek değişiklik yok.');
            return;
        }

        await persist(current, {
            url: editing ? current.base + '/' + current.leadId : current.base,
            payload,
            method: editing ? 'PUT' : 'POST',
            message: editing ? 'Hasta adayı güncellendi.' : 'Hasta adayı kaydedildi.',
            feedback: formStatus,
            refreshSummary: !editing || 'pipeline_stage_id' in payload,
        });
    });

    outcomeForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = activeEditor;
        if (!current || current.saving || outcome.hidden) {
            return;
        }
        if (Object.keys(changedValues(current)).length) {
            showStatus(outcomeStatus, 'Önce aday bilgilerindeki değişiklikleri kaydedin.');
            return;
        }

        const lost = outcomeChoice.value === 'lost';
        await persist(current, {
            url: current.base + '/' + current.leadId + (lost ? '/lost' : '/convert'),
            payload: lost ? { lost_reason: lostReason.value.trim() } : {},
            method: 'POST',
            message: lost ? 'Aday sonuçsuz kapatıldı.' : 'Aday başarıyla sonuçlandırıldı.',
            feedback: outcomeStatus,
            refreshSummary: true,
        });
    });

    async function persist(current, { url, payload, method, message, feedback, refreshSummary }) {
        current.saving = true;
        fields.disabled = true;
        outcomeFields.disabled = true;
        close.disabled = true;
        showStatus(feedback, 'Kaydediliyor…');

        try {
            await api(url, payload, method);
        } catch (error) {
            if (activeEditor === current) {
                showStatus(feedback, error.message);
            }
            return;
        } finally {
            if (activeEditor === current) {
                current.saving = false;
                fields.disabled = false;
                outcomeFields.disabled = false;
                close.disabled = false;
            }
        }

        if (activeEditor !== current) {
            return;
        }

        resetEditor();
        if (current.leadId === null) {
            filter.value = '';
        }
        loadList(current.leadId === null ? 1 : page, message, current.leadId);
        if (refreshSummary) {
            onChanged();
        }
    }

    return {
        openLead(leadId, options) {
            return openEditor({ id: leadId }, options);
        },
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
