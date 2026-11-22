const endpoint = '/package-services';

export function mountPackageServiceAdministration(dialog, api) {
    const find = (part) => dialog.querySelector('[data-service-' + part + ']');
    const form = find('form');
    const controls = form.querySelector('fieldset');
    const records = find('record');
    const scope = find('scope');
    const status = find('status');
    const retry = find('retry');
    const closeButton = find('close');
    let clinic;
    let onSaved;
    let services = [];
    let operation = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function render(id = '') {
        const service = services.find((item) => String(item.id) === String(id));
        form.reset();
        records.replaceChildren(new Option('Yeni ek hizmet', ''), ...services.map((item) =>
            new Option(item.name + (item.is_active ? '' : ' · Pasif'), item.id)));
        records.value = service?.id ?? '';

        for (const field of ['name', 'service_type', 'unit', 'description']) {
            form.elements.namedItem(field).value = service?.[field] ?? '';
        }
        form.elements.namedItem('is_active').checked = service?.is_active ?? true;
    }

    function close() {
        operation++;
        clinic = undefined;
        onSaved = undefined;
        services = [];
        render();
        scope.textContent = '';
        form.hidden = true;
        controls.disabled = false;
        closeButton.disabled = false;
        retry.hidden = true;
        showStatus();
        dialog.close();
    }

    async function load() {
        const pending = ++operation;
        retry.hidden = true;
        showStatus('Ek hizmetler yükleniyor…');
        try {
            const path = endpoint + '/manage' + (clinic ? '/' + clinic.id : '');
            const result = await api(path);
            if (pending === operation) {
                services = result.services;
                render();
                form.hidden = false;
                showStatus();
                records.focus();
            }
        } catch (error) {
            if (pending === operation) {
                showStatus(error.message);
                retry.hidden = false;
            }
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (controls.disabled) {
            return;
        }

        const id = records.value;
        const fields = new FormData(form);
        const payload = {
            name: fields.get('name').trim(),
            service_type: fields.get('service_type').trim(),
            unit: fields.get('unit').trim() || null,
            description: fields.get('description').trim() || null,
            is_active: fields.has('is_active'),
        };
        if (!id) {
            payload.business_id = clinic?.id ?? null;
        }

        const pending = ++operation;
        controls.disabled = true;
        closeButton.disabled = true;
        showStatus();
        try {
            const service = await api(id ? endpoint + '/' + id : endpoint, payload, id ? 'PUT' : 'POST');
            if (pending === operation) {
                services = [...services.filter((item) => item.id !== service.id), service]
                    .sort((first, second) => first.sort_order - second.sort_order || first.name.localeCompare(second.name, 'tr'));
                render(service.id);
                onSaved?.(service);
                showStatus('Ek hizmet kaydedildi.');
            }
        } catch (error) {
            if (pending === operation) {
                showStatus(error.message);
            }
        } finally {
            if (pending === operation) {
                controls.disabled = false;
                closeButton.disabled = false;
                form.querySelector('[type="submit"]').focus();
            }
        }
    });

    records.addEventListener('change', () => {
        render(records.value);
        showStatus();
        records.focus();
    });
    closeButton.addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!closeButton.disabled) {
            close();
        }
    });
    retry.addEventListener('click', load);

    return {
        open(business, afterSave) {
            clinic = business;
            onSaved = afterSave;
            scope.textContent = clinic?.name ?? 'Ortak hizmetler';
            dialog.showModal();
            load();
        },
        close,
    };
}
