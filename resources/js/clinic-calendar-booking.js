export function mountCalendarBooking(area, { api, onSaved, openBookingDialog, today }) {
    const find = (part) => area.querySelector('[data-booking-' + part + ']');
    const individual = find('individual');
    const packages = find('packages');
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const mode = find('patient-mode');
    const name = form.elements.namedItem('patient_name');
    const patient = form.elements.namedItem('patient_profile_id');
    const treatment = form.elements.namedItem('treatment_id');
    const doctor = form.elements.namedItem('doctor_id');
    const time = form.elements.namedItem('starts_at');
    const save = find('save');
    const packageSearch = find('package-search');
    const packageList = find('package-list');
    let scope = null;

    function status(part, message = '') {
        const output = find(part + 'status');
        output.textContent = message;
        output.hidden = !message;
    }

    function options(select, placeholder, entries = []) {
        select.replaceChildren(new Option(placeholder, ''), ...entries.map(([value, label]) => new Option(label, value)));
        select.disabled = entries.length === 0;
    }

    function updateSave() {
        save.disabled = !time.value || (mode.value === 'profile' && !patient.value);
    }

    function resetPatient() {
        const useProfile = mode.value === 'profile';
        name.disabled = useProfile;
        patient.required = useProfile;
        find('manual').hidden = useProfile;
        find('patient-search').hidden = !useProfile;
        options(patient, 'Hasta seçin');
        status('patient-');
        updateSave();
    }

    async function loadTreatments(current) {
        status('', 'Tedaviler yükleniyor…');
        try {
            const data = await api(current.path + '/appointment-options');
            if (scope === current) {
                options(treatment, 'Tedavi seçin', data.treatments.map((item) => [item.id, item.name]));
                status('', data.treatments.length ? '' : 'Bu şubede aktif tedavi bulunmuyor.');
            }
        } catch (error) {
            if (scope === current) {
                status('', error.message);
            }
        }
    }

    async function loadSlots(current) {
        const pending = ++current.slotsRequest;
        current.doctors = [];
        options(doctor, 'Hekim seçin');
        options(time, 'Saat seçin');
        updateSave();
        if (!treatment.value || current.date < today()) {
            status('slot-', current.date < today() ? 'Takvimden bugün veya ileri bir gün seçin.' : '');
            return;
        }

        const query = new URLSearchParams({ date: current.date, treatment_id: treatment.value });
        const isCurrent = () => scope === current && current.slotsRequest === pending;
        status('slot-', 'Uygun saatler yükleniyor…');
        try {
            const data = await api(current.path + '/appointment-options?' + query);
            if (isCurrent()) {
                current.doctors = data.doctors;
                options(doctor, 'Hekim seçin', data.doctors.map((item) => [item.id, item.name]));
                status('slot-', data.doctors.length ? '' : 'Bu gün için uygun randevu saati yok.');
            }
        } catch (error) {
            if (isCurrent()) {
                status('slot-', error.message);
            }
        }
    }

    async function findPatients(current) {
        const pending = ++current.patientRequest;
        const search = find('patient-query').value.trim();
        options(patient, 'Hasta seçin');
        updateSave();
        if (search.length < 2) {
            status('patient-', 'Aramak için en az iki karakter yazın.');
            return;
        }

        const isCurrent = () => scope === current && current.patientRequest === pending && mode.value === 'profile';
        status('patient-', 'Hastalar aranıyor…');
        try {
            const data = await api(current.path + '/patients?' + new URLSearchParams({ search }));
            if (isCurrent()) {
                options(patient, 'Hasta seçin', data.map((item) => [item.id, item.name + ' · #' + item.id]));
                status('patient-', data.length ? '' : 'Bu şubede eşleşen hasta bulunamadı.');
            }
        } catch (error) {
            if (isCurrent()) {
                status('patient-', error.message);
            }
        }
    }

    async function findPackages(current) {
        const pending = ++current.packageRequest;
        const search = packageSearch.elements.namedItem('search').value.trim();
        const isCurrent = () => scope === current && current.packageRequest === pending;
        packageList.replaceChildren();
        status('package-', 'Teklifler yükleniyor…');

        try {
            const data = await api(current.path + '/package-offers?' + new URLSearchParams({ search }));
            if (!isCurrent()) {
                return;
            }
            packageList.replaceChildren(...data.map((offer) => {
                const item = document.createElement('li');
                const label = document.createElement('span');
                label.textContent = offer.title + ' · ' + offer.patient;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'offer-button offer-button-secondary';
                button.textContent = 'Seansları planla';
                button.addEventListener('click', () => openBookingDialog(offer, {
                    path: current.path + '/package-offers/' + offer.id + '/appointments',
                    date: current.date,
                    async onBooked(result) {
                        if (scope === current) {
                            await onSaved(result.appointments[0].starts_at, result.appointments.length + ' seans için randevu oluşturuldu.');
                            if (scope === current) {
                                await findPackages(current);
                            }
                        }
                    },
                }));
                item.className = 'flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700';
                item.append(label, button);
                return item;
            }));
            status('package-', data.length ? '' : 'Randevu bekleyen kabul edilmiş paket teklifi bulunamadı.');
        } catch (error) {
            if (isCurrent()) {
                status('package-', error.message);
            }
        }
    }

    individual.addEventListener('toggle', () => {
        if (scope && individual.open && treatment.disabled) {
            loadTreatments(scope);
            loadSlots(scope);
        }
    });
    mode.addEventListener('change', () => {
        scope.patientRequest++;
        resetPatient();
    });
    find('find-patient').addEventListener('click', () => findPatients(scope));
    patient.addEventListener('change', updateSave);
    treatment.addEventListener('change', () => loadSlots(scope));
    doctor.addEventListener('change', () => {
        const selected = scope.doctors.find((item) => String(item.id) === doctor.value);
        options(time, 'Saat seçin', (selected?.slots ?? []).map((slot) => [slot, slot.slice(11, 16)]));
        updateSave();
    });
    time.addEventListener('change', updateSave);
    packageSearch.addEventListener('submit', (event) => {
        event.preventDefault();
        findPackages(scope);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        if (!current || fields.disabled || save.disabled) {
            return;
        }
        const payload = Object.fromEntries(new FormData(form));
        fields.disabled = true;
        status('', 'Randevu kaydediliyor…');
        try {
            const result = await api(current.path + '/appointments', payload);
            if (scope === current) {
                form.reset();
                resetPatient();
                await onSaved(result.starts_at, 'Hasta randevusu oluşturuldu.');
                status('');
            }
        } catch (error) {
            if (scope === current) {
                status('', error.message);
                loadSlots(current);
            }
        } finally {
            if (scope === current) {
                fields.disabled = false;
            }
        }
    });

    return {
        selectPatient(profile) {
            if (!scope || area.hidden || fields.disabled) {
                return;
            }
            scope.patientRequest++;
            form.reset();
            mode.value = 'profile';
            find('patient-query').value = '';
            resetPatient();
            options(patient, 'Hasta seçin', [[profile.id, profile.name + ' · #' + profile.id]]);
            patient.value = String(profile.id);
            if (individual.open && treatment.disabled) {
                loadTreatments(scope);
            }
            individual.open = true;
            loadSlots(scope);
            patient.focus();
        },
        reset() {
            scope = null;
            area.hidden = true;
            individual.open = false;
            packages.open = false;
            fields.disabled = false;
            form.reset();
            packageSearch.reset();
            resetPatient();
            options(treatment, 'Tedavi seçin');
            options(doctor, 'Hekim seçin');
            options(time, 'Saat seçin');
            updateSave();
            packageList.replaceChildren();
            status('');
            status('slot-');
            status('package-');
        },
        show(path, date, canCreate) {
            if (!canCreate) {
                this.reset();
                return;
            }
            scope ??= { path, date, doctors: [], slotsRequest: 0, patientRequest: 0, packageRequest: 0 };
            area.hidden = false;
            scope.date = date;
            if (individual.open) {
                loadSlots(scope);
            }
        },
    };
}
