const categoryUrl = '/treatment-categories';
const treatmentUrl = '/treatments';

function optionFor(record) {
    return new Option(record.name + (record.is_active ? '' : ' · Pasif'), record.id);
}

function fillRecords(select, records, placeholder, id) {
    select.replaceChildren(new Option(placeholder, ''), ...records.map(optionFor));
    select.value = id ?? '';
}

function fillDefinition(form, record) {
    form.reset();
    form.elements.namedItem('name').value = record?.name ?? '';
    form.elements.namedItem('description').value = record?.description ?? '';
    form.elements.namedItem('is_active').checked = record?.is_active ?? false;
    form.querySelector('[data-treatment-new-slug]').hidden = Boolean(record);
}

function saveRecord(records, saved) {
    return [...records.filter(({ id }) => id !== saved.id), saved]
        .sort((first, second) => first.sort_order - second.sort_order || first.name.localeCompare(second.name, 'tr'));
}

export function mountTreatmentAdministration(dialog, trigger, { api }) {
    const find = (part) => dialog.querySelector('[data-treatment-' + part + ']');
    const controls = find('controls');
    const categoryForm = find('category-form');
    const treatmentForm = find('definition-form');
    const categoryRecord = find('category-record');
    const treatmentRecord = find('definition-record');
    const treatmentCategory = treatmentForm.elements.namedItem('treatment_category_id');
    const treatmentSave = treatmentForm.querySelector('[type="submit"]');
    const status = find('status');
    const retry = find('retry');
    const closeButton = find('close');
    let categories = [];
    let treatments = [];
    let request = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function setCategoryOptions(value, savedCategory) {
        const choices = categories.filter((category) => category.is_active || category.id === savedCategory);
        const placeholder = choices.length ? 'Kategori seçin' : 'Önce aktif bir kategori oluşturun';
        const selected = choices.some(({ id }) => String(id) === String(value)) ? value : '';
        fillRecords(treatmentCategory, choices, placeholder, selected);
        treatmentSave.disabled = choices.length === 0;
    }

    function showCategory(id) {
        fillDefinition(categoryForm, categories.find((category) => String(category.id) === String(id)));
        fillRecords(categoryRecord, categories, 'Yeni kategori', id);
    }

    function showTreatment(id) {
        const record = treatments.find((treatment) => String(treatment.id) === String(id));
        fillDefinition(treatmentForm, record);
        fillRecords(treatmentRecord, treatments, 'Yeni tedavi', id);
        treatmentForm.elements.namedItem('duration_minutes').value = record?.duration_minutes ?? '';
        setCategoryOptions(record?.treatment_category_id, record?.treatment_category_id);
    }

    function close() {
        request++;
        categories = [];
        treatments = [];
        showCategory('');
        showTreatment('');
        controls.hidden = true;
        controls.disabled = false;
        retry.hidden = true;
        closeButton.disabled = false;
        showStatus();
        dialog.close();
    }

    async function load() {
        const pending = ++request;
        retry.hidden = true;
        showStatus('Katalog yükleniyor…');
        try {
            const [categoryData, treatmentData] = await Promise.all([api(categoryUrl), api(treatmentUrl)]);
            if (pending === request) {
                categories = categoryData.categories;
                treatments = treatmentData.treatments;
                showCategory('');
                showTreatment('');
                controls.hidden = false;
                showStatus();
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
                retry.hidden = false;
            }
        }
    }

    function onSave(form, selector, url, additionalFields, onSaved, confirmation) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (controls.disabled) {
                return;
            }

            const id = selector.value;
            const fields = new FormData(form);
            const payload = {
                name: fields.get('name').trim(),
                description: fields.get('description').trim() || null,
                is_active: fields.has('is_active'),
                ...additionalFields(fields, id),
            };
            if (!id) {
                payload.slug = fields.get('slug').trim() || null;
            }

            const pending = ++request;
            controls.disabled = true;
            closeButton.disabled = true;
            showStatus();
            try {
                const saved = await api(id ? url + '/' + id : url, payload, id ? 'PUT' : 'POST');
                if (pending === request) {
                    onSaved(saved);
                    showStatus(confirmation);
                }
            } catch (error) {
                if (pending === request) {
                    showStatus(error.message);
                }
            } finally {
                if (pending === request) {
                    controls.disabled = false;
                    closeButton.disabled = false;
                    form.querySelector('[type="submit"]').focus();
                }
            }
        });
    }

    function savedTreatmentCategory(id) {
        return treatments.find((treatment) => String(treatment.id) === id)?.treatment_category_id;
    }

    onSave(categoryForm, categoryRecord, categoryUrl, () => ({}), (saved) => {
        categories = saveRecord(categories, saved);
        showCategory(saved.id);
        const savedCategory = savedTreatmentCategory(treatmentRecord.value);
        setCategoryOptions(treatmentCategory.value, savedCategory);
    }, 'Kategori kaydedildi.');
    onSave(treatmentForm, treatmentRecord, treatmentUrl, (fields, id) => {
        const data = { duration_minutes: fields.get('duration_minutes') || null };
        const selectedCategory = Number(fields.get('treatment_category_id'));
        if (selectedCategory !== savedTreatmentCategory(id)) {
            data.treatment_category_id = selectedCategory;
        }
        return data;
    }, (saved) => {
        treatments = saveRecord(treatments, saved);
        showTreatment(saved.id);
    }, 'Tedavi kaydedildi.');

    categoryRecord.addEventListener('change', () => showCategory(categoryRecord.value));
    treatmentRecord.addEventListener('change', () => showTreatment(treatmentRecord.value));
    trigger.addEventListener('click', () => {
        dialog.showModal();
        load();
    });
    retry.addEventListener('click', load);
    closeButton.addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!closeButton.disabled) {
            close();
        }
    });

    return {
        start() {
            trigger.hidden = false;
        },
        stop() {
            trigger.hidden = true;
            close();
        },
    };
}
