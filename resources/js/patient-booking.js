import { patientAppointmentsPath } from './patient-appointments';

export function mountPatientBooking(panel, { api, today, setOptions, onBooked }) {
    const path = patientAppointmentsPath + '/booking-branches';
    const form = panel.querySelector('form');
    const fields = form.querySelector('fieldset');
    const status = panel.querySelector('[data-status]');
    const context = panel.querySelector('[data-booking-context]');
    const clinicName = panel.querySelector('[data-booking-clinic]');
    const branchName = panel.querySelector('[data-booking-branch]');
    const submit = form.querySelector('[type="submit"]');
    const treatment = form.elements.namedItem('treatment');
    const doctor = form.elements.namedItem('doctor');
    const date = form.elements.namedItem('date');
    const time = form.elements.namedItem('time');
    const notes = form.elements.namedItem('notes');
    let branch = null;
    let treatments = [];
    let request = 0;
    let activeSave = null;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function options(select, placeholder, entries = []) {
        setOptions(select, placeholder, entries);
        select.disabled = entries.length === 0;
    }

    function resetSlots() {
        options(time, 'Saat seçin');
        submit.disabled = true;
    }

    function isCurrent(pending) {
        return branch !== null && request === pending;
    }

    async function loadSlots() {
        const pending = ++request;
        resetSlots();
        showStatus();
        if (!branch || !treatment.value || !doctor.value || !date.value || !date.checkValidity()) {
            return;
        }

        showStatus('Uygun saatler yükleniyor…');
        try {
            const query = new URLSearchParams({ treatment_id: treatment.value, doctor_id: doctor.value, date: date.value });
            const { slots } = await api(`${path}/${branch}/slots?${query}`);
            if (isCurrent(pending)) {
                options(time, 'Saat seçin', slots.map((slot) => [slot, slot.slice(11, 16)]));
                showStatus(slots.length ? '' : 'Bu gün için uygun saat yok. Başka bir gün seçin.');
            }
        } catch (error) {
            if (isCurrent(pending)) {
                showStatus(error.message);
            }
        }
    }

    async function loadTreatments(pending) {
        showStatus('Tedaviler yükleniyor…');
        try {
            const result = await api(`${path}/${branch}/treatments`);
            if (isCurrent(pending)) {
                clinicName.textContent = result.branch.clinic;
                branchName.textContent = [result.branch.name, result.branch.district, result.branch.city].filter(Boolean).join(' · ');
                context.hidden = false;
                treatments = result.treatments;
                options(treatment, 'Tedavi seçin', treatments.map(({ id, name }) => [id, name]));
                form.hidden = treatments.length === 0;
                showStatus(treatments.length ? '' : 'Bu şubede çevrimiçi randevuya açık tedavi bulunamadı.');
            }
        } catch (error) {
            if (isCurrent(pending)) {
                showStatus(error.status === 404 ? 'Seçtiğiniz şube şu anda çevrimiçi randevuya kapalı.' : error.message);
            }
        }
    }

    treatment.addEventListener('change', () => {
        const selected = treatments.find(({ id }) => String(id) === treatment.value);
        options(doctor, 'Hekim seçin', (selected?.doctors ?? []).map(({ id, name }) => [id, name]));
        date.disabled = true;
        loadSlots();
    });
    doctor.addEventListener('change', () => {
        date.disabled = !doctor.value;
        loadSlots();
    });
    date.addEventListener('change', loadSlots);
    time.addEventListener('change', () => { submit.disabled = !time.value; });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!branch || !time.value || fields.disabled) {
            return;
        }

        const pending = ++request;
        activeSave = pending;
        fields.disabled = true;
        showStatus('Randevu kaydediliyor…');
        try {
            const appointment = await api(`${path}/${branch}`, {
                treatment_id: Number(treatment.value), doctor_id: Number(doctor.value),
                starts_at: time.value, notes: notes.value.trim() || null,
            });
            if (isCurrent(pending)) {
                onBooked(appointment);
            }
        } catch (error) {
            if (isCurrent(pending)) {
                let feedbackRequest = pending;
                if (error.status === 422) {
                    feedbackRequest = request + 1;
                    await loadSlots();
                }
                if (isCurrent(feedbackRequest)) {
                    showStatus(error.message);
                }
            }
        } finally {
            if (activeSave === pending) {
                activeSave = null;
                fields.disabled = false;
            }
        }
    });

    return {
        setBranch(nextBranch) {
            if (branch === nextBranch) {
                return;
            }

            const pending = ++request;
            branch = nextBranch;
            activeSave = null;
            treatments = [];
            form.reset();
            form.hidden = true;
            context.hidden = true;
            panel.hidden = branch === null;
            fields.disabled = false;
            showStatus();
            options(treatment, 'Tedavi seçin');
            options(doctor, 'Hekim seçin');
            date.min = today();
            date.disabled = true;
            resetSlots();
            if (branch) {
                loadTreatments(pending);
            }
        },
    };
}
