import { clinicStatus } from './clinic-records';

export function mountCrmLeadCare(panel, api) {
    const find = (part, root = panel) => root.querySelector('[data-care-' + part + ']');
    const status = find('status');
    let scope;

    function render(current, data) {
        find('steps').replaceChildren(...data.phases.map((step) => {
            const form = find('template').content.firstElementChild.cloneNode(true);
            const fieldset = form.querySelector('fieldset');
            const select = form.elements.namedItem('appointment_id');
            find('title', form).textContent = step.label;
            form.elements.namedItem('status').value = step.status;
            form.elements.namedItem('planned_on').value = step.planned_on ?? '';
            const visits = [...data.appointments];
            if (step.appointment && !visits.some((visit) => visit.id === step.appointment.id)) {
                visits.push(step.appointment);
            }
            select.replaceChildren(new Option('Bağlı randevu yok', ''), ...visits.map((visit) => new Option(
                'Randevu #' + visit.id + ' · ' + visit.starts_at.slice(0, 16), visit.id,
            )));
            select.value = step.appointment?.id ?? '';
            const stamp = find('visit', form);
            clinicStatus(stamp, step.completed_at ? 'Tamamlanma · ' + new Date(step.completed_at).toLocaleString('tr-TR', { timeZone: 'Europe/Istanbul' }) : '');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (current !== scope || current.busy) {
                    return;
                }
                const fields = Object.fromEntries(new FormData(form));
                current.busy = true;
                fieldset.disabled = true;
                clinicStatus(status, 'Aşama kaydediliyor…');
                try {
                    const updated = await api(current.path + '/' + step.phase, {
                        ...fields, appointment_id: fields.appointment_id || null, planned_on: fields.planned_on || null, revision: step.revision,
                    }, 'PUT');
                    if (current === scope) {
                        render(current, updated);
                        clinicStatus(status, 'Aşama kaydedildi.');
                    }
                } catch (error) {
                    if (current === scope) {
                        clinicStatus(status, error.message);
                    }
                } finally {
                    current.busy = false;
                    fieldset.disabled = false;
                }
            });
            return form;
        }));
    }

    async function load() {
        const current = scope;
        if (!current || current.busy) {
            return;
        }
        find('steps').replaceChildren();
        clinicStatus(status, 'Hasta süreci yükleniyor…');
        try {
            const data = await api(current.path);
            if (current === scope) {
                render(current, data);
                clinicStatus(status);
            }
        } catch (error) {
            if (current === scope) {
                clinicStatus(status, error.message);
            }
        }
    }

    panel.addEventListener('toggle', () => {
        if (panel.open) {
            load();
        }
    });

    return {
        setLead(path) {
            scope = path ? { path } : null;
            panel.open = false;
            panel.hidden = !path;
            find('steps').replaceChildren();
            clinicStatus(status);
        },
    };
}
