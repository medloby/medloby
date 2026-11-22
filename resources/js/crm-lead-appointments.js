import { appointmentStatuses, dateLabel, timeLabel } from './clinic-calendar';

export function mountCrmLeadAppointments(panel, { api, openAppointment, bookForPatient }) {
    const find = (part, root = panel) => root.querySelector('[data-lead-appointment-' + part + ']');
    const list = find('list');
    const book = find('book');
    const pages = find('pages');
    const previous = find('previous');
    const next = find('next');
    let path;
    let request = 0;
    let page = 1;
    let patient;

    function showStatus(message = '') {
        find('status').textContent = message;
        find('status').hidden = !message;
    }

    function appointmentRow(appointment) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        const hours = timeLabel(appointment.starts_at, appointment.ends_at);
        find('time', row).textContent = appointment.starts_at.slice(0, 10) === appointment.ends_at.slice(0, 10)
            ? dateLabel(appointment.starts_at) + ' · ' + hours : hours;
        find('summary', row).textContent = [appointment.treatment, appointment.doctor, appointmentStatuses[appointment.status] ?? appointment.status]
            .filter(Boolean).join(' · ');
        row.querySelector('button').addEventListener('click', () => openAppointment(appointment));
        return row;
    }

    async function load(requestedPage = page) {
        if (!path || !panel.open) {
            return;
        }

        const pending = ++request;
        book.hidden = true;
        pages.hidden = true;
        list.replaceChildren();
        showStatus('Randevular yükleniyor…');
        try {
            const data = await api(path + '?page=' + requestedPage);
            if (pending !== request || !panel.open) {
                return;
            }

            patient = data.patient;
            if (!patient) {
                showStatus('Randevuları görmek için adayın hasta profilini bu şubedeki görüşmeden bağlayın.');
                return;
            }

            const result = data.appointments;
            if (result.current_page > result.last_page) {
                await load(result.last_page);
                return;
            }
            page = result.current_page;
            list.replaceChildren(...result.data.map(appointmentRow));
            book.hidden = !data.can_book;
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('page').textContent = page + ' / ' + result.last_page;
            pages.hidden = result.last_page < 2;
            showStatus(result.data.length ? '' : 'Bu şubede kayıtlı randevu bulunamadı.');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    panel.addEventListener('toggle', () => load());
    previous.addEventListener('click', () => load(page - 1));
    next.addEventListener('click', () => load(page + 1));
    book.addEventListener('click', () => bookForPatient(patient));

    return {
        refresh() {
            return load(1);
        },
        setLead(endpoint) {
            request++;
            path = endpoint;
            page = 1;
            patient = undefined;
            panel.open = false;
            panel.hidden = !path;
            book.hidden = true;
            pages.hidden = true;
            list.replaceChildren();
            showStatus();
        },
    };
}
