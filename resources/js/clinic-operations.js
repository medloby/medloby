export function mountClinicOperations(panel, api) {
    const find = (part, root = panel) => root.querySelector('[data-operations-' + part + ']');
    const content = find('content');
    const status = find('status');
    const staffForm = find('staff-form');
    const treatmentForm = find('treatment-form');
    const doctorForm = find('doctor-form');
    const doctorSelect = find('doctors');
    const doctorBranch = find('doctor-branch');
    let scope = null;

    function showStatus(text = '') {
        status.textContent = text;
        status.hidden = !text;
    }

    function fillOptions(select, placeholder, records) {
        const prompt = new Option(placeholder, '', true, true);
        prompt.disabled = true;
        select.replaceChildren(prompt, ...records.map((item) => new Option(item.name, item.id)));
    }

    function action(label, handler, disabled = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'offer-button offer-button-secondary';
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', handler);
        return button;
    }

    function row(name, meta, ...actions) {
        const item = find('row').content.firstElementChild.cloneNode(true);
        find('name', item).textContent = name;
        find('meta', item).textContent = meta;
        find('actions', item).append(...actions);
        return item;
    }

    function selectedDoctor(current) {
        return current.data.doctors.find((item) => String(item.id) === current.doctorId);
    }

    function renderDoctor(current) {
        const doctor = selectedDoctor(current);
        find('doctor-detail').hidden = !doctor;
        find('doctor-treatments').replaceChildren();
        if (!doctor) {
            return;
        }

        find('doctor-meta').textContent = [
            doctor.specialty, doctor.license_number && 'Ruhsat ' + doctor.license_number,
            doctor.in_branch ? 'Şubeye bağlı' : 'Şubeye bağlı değil',
        ].filter(Boolean).join(' · ');
        doctorBranch.textContent = doctor.in_branch ? 'Hekimi şubeden çıkar' : 'Hekimi şubeye ekle';
        doctorBranch.disabled = !doctor.in_branch && !doctor.is_active;
        find('doctor-links').hidden = !doctor.in_branch;

        const items = current.data.branch_treatments.filter((treatment) =>
            doctor.treatment_ids.includes(treatment.id) || (treatment.is_active && treatment.catalog_active),
        );
        find('doctor-no-treatments').hidden = items.length > 0;
        find('doctor-treatments').replaceChildren(...items.map((treatment) => {
            const linked = doctor.treatment_ids.includes(treatment.id);
            const path = current.operations + '/doctors/' + doctor.id + '/treatments/' + treatment.id;
            return row(treatment.name, linked ? 'Hekime tanımlı' : 'Hekime tanımlı değil',
                action(linked ? 'Hekimden kaldır' : 'Hekime tanımla', () => write(current, path, { is_active: !linked }, 'PUT',
                    linked ? 'Tedavi hekimden kaldırıldı.' : 'Tedavi hekime tanımlandı.'), !linked && !doctor.is_active));
        }));
    }

    function render(current) {
        const data = current.data;
        find('empty-staff').hidden = data.staff.length > 0;
        find('staff').replaceChildren(...data.staff.map((member) => {
            const label = member.branch_access ? 'Şube yetkisini kaldır' : 'Şube yetkisi ver';
            const access = member.branch_access ? 'Şube yetkisi var' : 'Şube yetkisi yok';
            return row(member.name, [member.email, member.is_active ? '' : 'Pasif üyelik', access].filter(Boolean).join(' · '),
                action(label, () => write(current, current.operations + '/staff/' + member.id,
                    { is_active: !member.branch_access }, 'PUT', 'Şube yetkisi kaydedildi.'), !member.branch_access && !member.can_assign));
        }));

        const linkedIds = new Set(data.branch_treatments.map((item) => item.id));
        const available = data.catalog_treatments.filter((item) => !linkedIds.has(item.id));
        fillOptions(treatmentForm.elements.namedItem('treatment_id'), 'Tedavi seçin', available);
        treatmentForm.hidden = available.length === 0;
        find('empty-catalog').hidden = available.length > 0;
        find('empty-treatments').hidden = data.branch_treatments.length > 0;
        find('treatments').replaceChildren(...data.branch_treatments.map((item) => {
            const path = current.operations + '/treatments/' + item.id;
            const active = item.catalog_active && item.is_active;
            return row(item.name, active ? (item.is_online_bookable ? 'Çevrimiçi randevu açık' : 'Çevrimiçi randevu kapalı')
                : (item.catalog_active ? 'Şubede pasif' : 'Katalogda pasif'),
            action(item.is_active ? 'Şubede pasife al' : 'Şubede etkinleştir',
                () => write(current, path, { is_active: !item.is_active }, 'PUT', 'Şube tedavisi kaydedildi.'), !item.is_active && !item.catalog_active),
            action(item.is_online_bookable ? 'Çevrimiçi randevuyu kapat' : 'Çevrimiçi randevuyu aç',
                () => write(current, path, { is_online_bookable: !item.is_online_bookable }, 'PUT', 'Randevu ayarı kaydedildi.'), !active));
        }));

        find('doctor-picker').hidden = data.doctors.length === 0;
        fillOptions(doctorSelect, 'Hekim seçin', data.doctors);
        doctorSelect.value = selectedDoctor(current) ? current.doctorId : '';
        renderDoctor(current);
    }

    async function load(current, confirmation = '') {
        current.loading = true;
        current.loaded = false;
        showStatus('Şube operasyonları yükleniyor…');
        try {
            const data = await api(current.operations);
            if (scope !== current) {
                return;
            }
            current.data = data;
            render(current);
            content.hidden = false;
            current.loaded = true;
            showStatus(confirmation);
        } catch (error) {
            if (scope === current) {
                showStatus([confirmation, error.message].filter(Boolean).join(' '));
            }
        } finally {
            current.loading = false;
        }
    }

    async function write(current, path, data, method, confirmation, afterSave = null) {
        if (scope !== current || current.saving) {
            return;
        }
        current.saving = true;
        content.disabled = true;
        showStatus('Kaydediliyor…');
        try {
            const saved = await api(path, data, method);
            if (scope === current) {
                afterSave?.(saved);
                await load(current, confirmation);
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            if (scope === current) {
                current.saving = false;
                content.disabled = false;
            }
        }
    }

    panel.addEventListener('toggle', () => {
        if (panel.open && scope && !scope.loaded && !scope.loading) {
            load(scope);
        }
    });

    staffForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (scope) {
            write(scope, scope.operations + '/staff', { email: staffForm.elements.namedItem('email').value.trim() },
                'POST', 'Personele şube yetkisi verildi.', () => staffForm.reset());
        }
    });

    treatmentForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (scope) {
            write(scope, scope.operations + '/treatments', {
                treatment_id: Number(treatmentForm.elements.namedItem('treatment_id').value),
                is_online_bookable: treatmentForm.elements.namedItem('is_online_bookable').checked,
            }, 'POST', 'Tedavi şubeye eklendi.', () => treatmentForm.reset());
        }
    });

    doctorForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (scope) {
            const current = scope;
            const details = Object.fromEntries(new FormData(doctorForm));
            write(current, current.operations + '/doctors', details, 'POST', 'Hekim şubeye kaydedildi.', (doctor) => {
                current.doctorId = String(doctor.id);
                doctorForm.reset();
            });
        }
    });

    doctorSelect.addEventListener('change', () => {
        scope.doctorId = doctorSelect.value;
        renderDoctor(scope);
    });

    doctorBranch.addEventListener('click', () => {
        const doctor = selectedDoctor(scope);
        write(scope, scope.operations + '/doctors/' + doctor.id, { is_active: !doctor.in_branch }, 'PUT',
            doctor.in_branch ? 'Hekim şubeden çıkarıldı.' : 'Hekim şubeye eklendi.');
    });

    return {
        setBranch(id) {
            const branch = id ? '/branches/' + id : null;
            scope = branch ? { operations: branch + '/operations', doctorId: '', loaded: false, loading: false, saving: false } : null;
            panel.open = false;
            panel.hidden = !scope;
            content.hidden = true;
            content.disabled = false;
            for (const form of [staffForm, treatmentForm, doctorForm]) {
                form.reset();
            }
            for (const part of ['staff', 'treatments', 'doctors', 'doctor-treatments', 'doctor-meta']) {
                find(part).replaceChildren();
            }
            treatmentForm.elements.namedItem('treatment_id').replaceChildren();
            for (const details of content.querySelectorAll('details')) {
                details.open = false;
            }
            showStatus();
        },
    };
}
