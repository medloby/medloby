export const patientAppointmentsPath = '/patient/appointments';

export function mountPatientAppointments(panel, { api, renderAppointment }) {
    const views = panel.querySelector('[data-appointment-views]');
    const buttons = Array.from(views.querySelectorAll('[data-appointment-view]'));
    const back = panel.querySelector('[data-appointment-back]');
    const list = panel.querySelector('[data-patient-appointment-entries]');
    const status = panel.querySelector('[data-appointment-list-status]');
    const pages = panel.querySelector('[data-appointment-pages]');
    const pageNumber = panel.querySelector('[data-appointment-page]');
    const previous = panel.querySelector('[data-appointment-previous]');
    const next = panel.querySelector('[data-appointment-next]');
    let view = 'upcoming';
    let appointmentId = null;
    let page = 1;
    let request = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    async function load(targetPage = 1, preparedAppointment = null) {
        const pending = ++request;
        const single = appointmentId !== null;
        list.replaceChildren();
        pages.hidden = true;
        showStatus('Randevular yükleniyor…');

        try {
            if (single) {
                const appointment = preparedAppointment ?? await api(patientAppointmentsPath + '/' + appointmentId);
                if (pending === request) {
                    list.append(renderAppointment(appointment));
                    showStatus();
                }
                return;
            }

            const result = await api(patientAppointmentsPath + '?' + new URLSearchParams({ view, page: targetPage }));
            if (pending !== request) {
                return;
            }

            list.replaceChildren(...result.appointments.map(renderAppointment));
            page = result.current_page;
            const lastPage = result.last_page;
            pages.hidden = lastPage <= 1;
            previous.disabled = page <= 1;
            next.disabled = page >= lastPage;
            pageNumber.textContent = `${page} / ${lastPage}`;
            showStatus(result.appointments.length ? '' : (view === 'upcoming' ? 'Yaklaşan randevunuz yok.' : 'Geçmiş veya iptal edilmiş randevunuz yok.'));
        } catch (error) {
            if (pending === request) {
                showStatus(single && error.status === 404 ? 'Randevu bulunamadı.' : error.message);
            }
        }
    }

    function selectView(value) {
        view = value;
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.appointmentView === view)));
    }

    for (const button of buttons) {
        button.addEventListener('click', () => {
            selectView(button.dataset.appointmentView);
            load();
        });
    }
    previous.addEventListener('click', () => load(page - 1));
    next.addEventListener('click', () => load(page + 1));

    return {
        setView(visible, id, preparedAppointment) {
            if (!visible) {
                if (!panel.hidden) {
                    request++;
                    panel.hidden = true;
                    list.replaceChildren();
                    selectView('upcoming');
                }
                return;
            }

            if (!panel.hidden && appointmentId === id && !preparedAppointment) {
                return;
            }

            panel.hidden = false;
            appointmentId = id;
            views.hidden = id !== null;
            back.hidden = id === null;
            load(1, preparedAppointment);
        },
        reload() {
            return load(appointmentId === null ? page : 1);
        },
    };
}
