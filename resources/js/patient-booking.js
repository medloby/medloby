import { patientAppointmentsPath } from './patient-appointments';

export function mountPatientBooking(dialog, button, { api, today, setOptions, onBooked }) {
    const path = patientAppointmentsPath + '/booking-branches';
    const form = dialog.querySelector('form');
    const fields = form.querySelector('fieldset');
    const status = dialog.querySelector('[data-status]');
    const close = dialog.querySelector('[data-close]');
    const submit = form.querySelector('[type="submit"]');
    const branch = form.elements.namedItem('branch');
    const treatment = form.elements.namedItem('treatment');
    const doctor = form.elements.namedItem('doctor');
    const date = form.elements.namedItem('date');
    const time = form.elements.namedItem('time');
    const notes = form.elements.namedItem('notes');
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
        return dialog.open && request === pending;
    }

    async function loadSlots() {
        const pending = ++request;
        resetSlots();
        showStatus();
        if (!branch.value || !treatment.value || !doctor.value || !date.value || !date.checkValidity()) {
            return;
        }

        showStatus('Uygun saatler yükleniyor…');
        try {
            const query = new URLSearchParams({ treatment_id: treatment.value, doctor_id: doctor.value, date: date.value });
            const { slots } = await api(`${path}/${branch.value}/slots?${query}`);
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

    async function loadTreatments() {
        const pending = ++request;
        treatments = [];
        options(treatment, 'Tedavi seçin');
        options(doctor, 'Hekim seçin');
        date.disabled = true;
        resetSlots();
        showStatus('Tedaviler yükleniyor…');
        try {
            const result = await api(`${path}/${branch.value}/treatments`);
            if (isCurrent(pending)) {
                treatments = result.treatments;
                options(treatment, 'Tedavi seçin', treatments.map(({ id, name }) => [id, name]));
                showStatus(treatments.length ? '' : 'Bu şubede çevrimiçi randevuya açık tedavi bulunamadı.');
            }
        } catch (error) {
            if (isCurrent(pending)) {
                showStatus(error.message);
            }
        }
    }

    button.addEventListener('click', async () => {
        const pending = ++request;
        activeSave = null;
        form.reset();
        form.hidden = true;
        fields.disabled = false;
        close.disabled = false;
        options(treatment, 'Tedavi seçin');
        options(doctor, 'Hekim seçin');
        date.min = today();
        date.disabled = true;
        resetSlots();
        showStatus('Şubeler yükleniyor…');
        dialog.showModal();

        try {
            const { branches } = await api(path);
            if (isCurrent(pending)) {
                options(branch, 'Klinik ve şube seçin', branches.map(({ id, name, clinic, city }) => [id, [clinic, name, city].filter(Boolean).join(' · ')]));
                form.hidden = branches.length === 0;
                showStatus(branches.length ? '' : 'Çevrimiçi randevuya açık şube bulunamadı.');
            }
        } catch (error) {
            if (isCurrent(pending)) {
                showStatus(error.message);
            }
        }
    });

    branch.addEventListener('change', loadTreatments);
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
        if (!time.value || fields.disabled) {
            return;
        }

        const pending = ++request;
        activeSave = pending;
        fields.disabled = true;
        close.disabled = true;
        showStatus('Randevu kaydediliyor…');
        try {
            const appointment = await api(`${path}/${branch.value}`, {
                treatment_id: Number(treatment.value), doctor_id: Number(doctor.value),
                starts_at: time.value, notes: notes.value.trim() || null,
            });
            if (isCurrent(pending)) {
                dialog.close();
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
                close.disabled = false;
            }
        }
    });

    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', (event) => {
        if (fields.disabled) {
            event.preventDefault();
        }
    });
    dialog.addEventListener('close', () => { request++; });

    return { close: () => { if (dialog.open) dialog.close(); } };
}
