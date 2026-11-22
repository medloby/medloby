const endpoint = '/treatment-prices';
const types = [['fixed', 'Sabit tutar'], ['range', 'Fiyat aralığı']];
const amountFields = ['price', 'min_price', 'max_price'];

function options(select, choices, value = '') {
    select.replaceChildren(...choices.map(([id, label]) => new Option(label, id)));
    select.value = value;
}

function doctorName(doctor) {
    return doctor?.person ? [doctor.person.first_name, doctor.person.last_name].filter(Boolean).join(' ') : '';
}

function amountLabel(price) {
    const values = (price.price_type === 'fixed' ? [price.price] : [price.min_price, price.max_price, price.price])
        .filter((value) => value !== null);
    if (!values.length) {
        return 'Tutar girilmemiş';
    }
    const formatted = values.map((value) => Number(value).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    return formatted.join(' – ') + ' ' + (price.currency ?? '');
}

export function mountClinicTreatmentPrices(panel, api) {
    const find = (part) => panel.querySelector('[data-prices-' + part + ']');
    const scope = find('scope');
    const status = find('status');
    const retry = find('retry');
    const content = find('content');
    const record = find('record');
    const empty = find('empty');
    const form = find('form');
    const controls = form.querySelector('fieldset');
    const field = (name) => form.elements.namedItem(name);
    const actions = find('actions');
    const toggle = find('toggle');
    let branch;
    let data;
    let operation = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function selectedPrice() {
        return data?.prices.find((price) => String(price.id) === record.value);
    }

    function showAmounts() {
        const type = field('price_type').value;
        for (const name of amountFields) {
            const visible = type === 'fixed' ? name === 'price' : type === 'range' ? name !== 'price' : true;
            field(name).closest('label').hidden = !visible;
            field(name).disabled = !visible;
        }
    }

    function showRecord() {
        const price = selectedPrice();
        const canCreate = data.can_manage && data.treatments.length > 0;
        form.hidden = !price && !canCreate;
        if (form.hidden) {
            return;
        }

        const treatments = data.treatments.map(({ id, name }) => [id, name]);
        if (price && !data.treatments.some(({ id }) => id === price.treatment_id)) {
            treatments.push([price.treatment_id, price.treatment.name]);
        }
        const doctors = data.doctors.map((doctor) => [doctor.id, doctorName(doctor)]);
        if (price?.doctor_id && !data.doctors.some(({ id }) => id === price.doctor_id)) {
            doctors.push([price.doctor_id, doctorName(price.doctor)]);
        }

        options(field('treatment_id'), [['', 'Tedavi seçin'], ...treatments], price?.treatment_id);
        options(field('doctor_id'), [['', 'Tüm hekimler'], ...doctors], price?.doctor_id ?? '');
        const currentType = price?.price_type ?? 'fixed';
        const availableTypes = types.some(([type]) => type === currentType) ? types : [...types, [currentType, currentType]];
        options(field('price_type'), availableTypes, currentType);
        field('currency').value = price?.currency ?? 'TRY';
        for (const name of amountFields) {
            field(name).value = price?.[name] ?? '';
        }
        for (const name of ['valid_from', 'valid_until']) {
            field(name).value = price?.[name]?.slice(0, 10) ?? '';
        }

        controls.disabled = !data.can_manage;
        actions.hidden = !data.can_manage;
        toggle.hidden = !price;
        toggle.textContent = price?.is_active ? 'Fiyatı pasife al' : 'Fiyatı etkinleştir';
        showAmounts();
    }

    function render(id) {
        const entries = data.prices.map((price) => [price.id, [
            price.treatment.name, doctorName(price.doctor), amountLabel(price), price.is_active ? '' : 'Pasif',
        ].filter(Boolean).join(' · ')]);
        const canCreate = data.can_manage && data.treatments.length > 0;
        options(record, [['', canCreate ? 'Yeni fiyat kaydı' : 'Fiyat kaydı seçin'], ...entries], id);
        record.closest('label').hidden = !canCreate && entries.length === 0;
        empty.hidden = canCreate || entries.length > 0;
        empty.textContent = data.can_manage ? 'Fiyat tanımlamak için aktif bir tedavi gerekiyor.' : 'Bu kapsamda fiyat kaydı yok.';
        showRecord();
        content.hidden = false;
    }

    async function load(id = '', confirmation = '') {
        const pending = ++operation;
        data = undefined;
        content.hidden = true;
        retry.hidden = true;
        showStatus('Tedavi fiyatları yükleniyor…');
        try {
            const response = await api('/branches/' + branch.id + '/crm/treatment-prices?' + new URLSearchParams({ scope: scope.value }));
            if (pending === operation) {
                data = response;
                render(id);
                showStatus(confirmation);
                if (confirmation) {
                    record.focus();
                }
            }
        } catch (error) {
            if (pending === operation) {
                showStatus([confirmation, error.message].filter(Boolean).join(' '));
                retry.hidden = false;
            }
        }
    }

    async function write(path, payload, method, confirmation) {
        const pending = ++operation;
        controls.disabled = true;
        record.disabled = true;
        scope.disabled = true;
        showStatus('Tedavi fiyatı kaydediliyor…');
        let saved;
        try {
            saved = await api(path, payload, method);
        } catch (error) {
            if (pending === operation) {
                showStatus(error.message);
            }
        } finally {
            if (pending === operation) {
                controls.disabled = false;
                record.disabled = false;
                scope.disabled = false;
            }
        }
        if (saved && pending === operation) {
            await load(saved.id, confirmation);
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!data?.can_manage || controls.disabled) {
            return;
        }

        const price = selectedPrice();
        const payload = Object.fromEntries(new FormData(form));
        for (const name of ['doctor_id', 'valid_from', 'valid_until']) {
            payload[name] ||= null;
        }
        for (const name of amountFields) {
            payload[name] = field(name).disabled && price?.price_type === payload.price_type ? price[name] : (payload[name] || null);
        }
        payload.currency = payload.currency.toUpperCase();
        if (!price) {
            payload.business_id = Number(branch.businessId);
            payload.branch_id = scope.value === 'branch' ? Number(branch.id) : null;
        }
        write(price ? endpoint + '/' + price.id : endpoint, payload, price ? 'PUT' : 'POST', 'Tedavi fiyatı kaydedildi.');
    });

    toggle.addEventListener('click', () => {
        const price = selectedPrice();
        if (!price || !data.can_manage || controls.disabled) {
            return;
        }
        const path = endpoint + '/' + price.id;
        if (price.is_active) {
            write(path, undefined, 'DELETE', 'Tedavi fiyatı pasife alındı.');
        } else {
            write(path, { is_active: true }, 'PUT', 'Tedavi fiyatı etkinleştirildi.');
        }
    });

    record.addEventListener('change', () => {
        showStatus();
        showRecord();
    });
    field('price_type').addEventListener('change', showAmounts);
    scope.addEventListener('change', () => load());
    retry.addEventListener('click', () => load());
    panel.addEventListener('toggle', () => {
        if (panel.open && branch && !data && content.hidden && retry.hidden && !status.textContent) {
            load();
        }
    });

    return {
        setBranch(id, businessId) {
            operation++;
            branch = id ? { id, businessId } : undefined;
            data = undefined;
            panel.hidden = !branch;
            content.hidden = true;
            form.hidden = true;
            form.reset();
            record.replaceChildren();
            record.disabled = false;
            scope.disabled = false;
            scope.value = 'branch';
            retry.hidden = true;
            showStatus();
            if (!branch) {
                panel.open = false;
            } else if (panel.open) {
                load();
            }
        },
    };
}
