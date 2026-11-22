export function mountClinicProfile(panel, api, onSaved) {
    const form = panel.querySelector('form');
    const fields = form.firstElementChild;
    const status = panel.querySelector('[data-profile-status]');
    let scope;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function populate(profile) {
        form.elements.namedItem('owner_name').value = profile.owner_name;
        for (const section of ['clinic', 'branch']) {
            for (const [field, value] of Object.entries(profile[section])) {
                form.elements.namedItem(section + '.' + field).value = value ?? '';
            }
        }
    }

    async function load(current) {
        current.loading = true;
        showStatus('Klinik bilgileri yükleniyor…');
        try {
            const profile = await api(current.path + '?branch_id=' + current.branchId);
            if (scope === current) {
                populate(profile);
                current.loaded = true;
                form.hidden = false;
                showStatus();
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            current.loading = false;
        }
    }

    panel.addEventListener('toggle', () => {
        if (panel.open && scope && !scope.loading && !scope.loaded) {
            load(scope);
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        if (!current || fields.disabled) {
            return;
        }

        const data = { branch_id: Number(current.branchId), clinic: {}, branch: {} };
        for (const [name, value] of new FormData(form)) {
            const [section, field] = name.split('.');
            if (field) {
                data[section][field] = field === 'country_code' ? value.trim().toUpperCase() : value.trim();
            } else {
                data[name] = value.trim();
            }
        }

        fields.disabled = true;
        showStatus('Kaydediliyor…');
        try {
            const profile = await api(current.path, data, 'PUT');
            if (scope === current) {
                populate(profile);
                onSaved(profile);
                showStatus('Klinik ve şube bilgileri kaydedildi.');
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            if (scope === current) {
                fields.disabled = false;
            }
        }
    });

    return {
        setBranch(businessId, branchId) {
            scope = businessId && branchId ? { path: '/businesses/' + businessId + '/crm/profile', branchId } : null;
            panel.hidden = !scope;
            form.hidden = true;
            fields.disabled = false;
            form.reset();
            showStatus();
            if (panel.open && scope) {
                load(scope);
            }
        },
    };
}
