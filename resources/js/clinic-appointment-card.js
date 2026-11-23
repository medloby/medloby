import { createAppointmentPdf } from './appointment-card-pdf';
import { crmLeadStatusLabels } from './crm-lead-status';

export function mountAppointmentCard(dialog, { api, dateLabel, timeLabel, appointmentStatuses, openLead }) {
    const find = (part, root = dialog) => root.querySelector('[data-card-' + part + ']');
    const fields = find('fields');
    const contents = find('content');
    const status = find('status');
    const download = find('download');
    const leadList = find('lead-list');
    const leadsSection = find('leads');
    let request = 0;
    let card;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function clear() {
        request++;
        card = undefined;
        contents.hidden = true;
        fields.replaceChildren();
        leadList.replaceChildren();
        leadsSection.hidden = true;
        for (const part of ['clinic', 'number', 'appointment-status', 'notes']) {
            find(part).textContent = '';
        }
        find('notes-section').hidden = true;
        download.disabled = false;
        showStatus();
    }

    async function openCandidate(path, leadId, current) {
        const actions = leadList.querySelectorAll('button');
        actions.forEach((button) => { button.disabled = true; });
        showStatus('Aday bağlantısı kontrol ediliyor…');
        try {
            const updated = await api(path);
            if (!dialog.open || card !== current) {
                return;
            }
            if (!(updated.crm_leads ?? []).some((lead) => lead.id === leadId)) {
                render(updated, path);
                showStatus('Bu adayın randevuyla bağlantısı değişti.');
                return;
            }
            dialog.close();
            clear();
            await openLead(leadId);
        } catch (error) {
            if (dialog.open && card === current) {
                showStatus(error.message);
            }
        } finally {
            actions.forEach((button) => { button.disabled = false; });
        }
    }

    function render(appointment, path) {
        const time = timeLabel(appointment.starts_at, appointment.ends_at);
        const sameDay = appointment.starts_at.slice(0, 10) === appointment.ends_at.slice(0, 10);
        card = {
            id: appointment.id,
            clinic: appointment.clinic === appointment.branch ? appointment.clinic : appointment.clinic + ' · ' + appointment.branch,
            status: appointmentStatuses[appointment.status] ?? appointment.status,
            fields: [
                ['Ad soyad', appointment.name],
                ['Tarih ve saat', sameDay ? dateLabel(appointment.starts_at) + ' · ' + time : time],
                ['Telefon', appointment.phone],
                ['E-posta', appointment.email],
                ['Tedavi', appointment.treatment],
                ['Hekim', appointment.doctor],
            ].map(([label, value]) => [label, value?.trim() || 'Kayıtlı değil']),
            notes: appointment.notes?.trim(),
        };
        find('clinic').textContent = card.clinic;
        find('number').textContent = 'Randevu #' + card.id;
        find('appointment-status').textContent = card.status;
        fields.replaceChildren(...card.fields.map(([label, value]) => {
            const field = find('field').content.firstElementChild.cloneNode(true);
            field.querySelector('dt').textContent = label;
            field.querySelector('dd').textContent = value;
            return field;
        }));
        find('notes').textContent = card.notes ?? '';
        find('notes-section').hidden = !card.notes;
        const current = card;
        const linkedLeads = appointment.crm_leads ?? [];
        leadList.replaceChildren(...linkedLeads.map((lead) => {
            const row = find('lead').content.firstElementChild.cloneNode(true);
            const name = lead.name || 'İsimsiz hasta adayı';
            find('lead-name', row).textContent = name;
            find('lead-meta', row).textContent = [crmLeadStatusLabels.get(lead.status), 'Aday #' + lead.id].filter(Boolean).join(' · ');
            const button = row.querySelector('button');
            button.setAttribute('aria-label', button.textContent + ' · ' + name + ' #' + lead.id);
            button.addEventListener('click', () => openCandidate(path, lead.id, current));
            return row;
        }));
        leadsSection.hidden = linkedLeads.length === 0;
        contents.hidden = false;
        showStatus();
    }

    dialog.addEventListener('close', () => {
        if (!dialog.open) {
            clear();
        }
    });

    download.addEventListener('click', async () => {
        const current = card;
        if (!current || download.disabled) {
            return;
        }
        download.disabled = true;
        showStatus('PDF hazırlanıyor…');
        try {
            const pdf = await createAppointmentPdf(current);
            if (dialog.open && current === card) {
                const url = URL.createObjectURL(pdf);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'medloby-randevu-' + current.id + '.pdf';
                link.hidden = true;
                dialog.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                showStatus();
            }
        } catch {
            if (dialog.open && current === card) {
                showStatus('Randevu PDF dosyası oluşturulamadı. Tekrar deneyin.');
            }
        } finally {
            if (current === card) {
                download.disabled = false;
            }
        }
    });

    return {
        async open(path) {
            if (dialog.open) {
                return;
            }
            clear();
            const pending = request;
            showStatus('Randevu kartı yükleniyor…');
            dialog.showModal();
            try {
                const appointment = await api(path);
                if (dialog.open && pending === request) {
                    render(appointment, path);
                }
            } catch (error) {
                if (dialog.open && pending === request) {
                    showStatus(error.message);
                }
            }
        },
        close() {
            dialog.close();
            clear();
        },
    };
}
