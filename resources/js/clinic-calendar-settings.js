export function mountClinicCalendarSettings(panel, api, onSaved) {
    const find = (part) => panel.querySelector('[data-appointment-settings-' + part + ']');
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const status = find('status');
    const inactive = find('inactive');
    const save = find('save');
    const online = form.elements.namedItem('online_booking_enabled');
    const sameDay = form.elements.namedItem('same_day_booking_enabled');
    const notice = form.elements.namedItem('minimum_booking_notice_minutes');
    const days = form.elements.namedItem('maximum_booking_days');
    let scope = null;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function render(settings) {
        online.checked = settings.online_booking_enabled;
        sameDay.checked = settings.same_day_booking_enabled;
        notice.value = settings.minimum_booking_notice_minutes;
        days.value = settings.maximum_booking_days;
        inactive.hidden = settings.is_active;
        save.textContent = settings.is_active ? 'Ayarları kaydet' : 'Etkinleştir ve kaydet';
        form.hidden = false;
    }

    panel.addEventListener('toggle', async () => {
        const current = scope;
        if (!panel.open || !current || current.loaded || current.loading) {
            return;
        }

        current.loading = true;
        showStatus('Randevu ayarları yükleniyor…');
        try {
            const { settings } = await api(current.path);
            if (scope === current) {
                render(settings);
                current.loaded = true;
                showStatus();
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            current.loading = false;
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const current = scope;
        if (!current?.loaded || fields.disabled) {
            return;
        }

        fields.disabled = true;
        showStatus('Randevu ayarları kaydediliyor…');
        try {
            const settings = await api(current.path, {
                online_booking_enabled: online.checked,
                same_day_booking_enabled: sameDay.checked,
                minimum_booking_notice_minutes: notice.valueAsNumber,
                maximum_booking_days: days.valueAsNumber,
                is_active: true,
            }, 'PUT');
            if (scope === current) {
                render(settings);
                showStatus('Çevrimiçi randevu ayarları kaydedildi.');
                await onSaved();
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
        setBranch(path) {
            scope = path ? { path, loading: false, loaded: false } : null;
            form.reset();
            form.hidden = true;
            fields.disabled = false;
            panel.open = false;
            panel.hidden = !scope;
            showStatus();
        },
    };
}
