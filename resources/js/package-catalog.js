function options(select, choices, selected = '') {
    select.replaceChildren(...choices.map(([id, name]) => new Option(name, id)));
    select.value = selected;
}

export function mountPackageCatalog(dialog, api, openServices) {
    const find = (selector) => dialog.querySelector(selector);
    const controls = find('[data-catalog-controls]');
    const status = find('[data-catalog-status]');
    const business = find('[data-catalog-business]');
    const packageSelect = find('[data-catalog-package]');
    const metadata = find('[data-catalog-metadata]');
    const publish = find('[data-catalog-publish]');
    const contents = find('[data-catalog-contents]');
    const itemSections = dialog.querySelectorAll('[data-catalog-items]');
    const manageServices = find('[data-catalog-manage-services]');
    let catalog;
    let selectedPackage;
    let operation = 0;

    function showStatus(message) {
        status.hidden = !message;
        status.textContent = message;
    }

    async function run(work) {
        if (controls.disabled) {
            return;
        }
        controls.disabled = true;
        showStatus('');
        const request = ++operation;
        try {
            await work(() => request === operation);
        } catch (error) {
            if (request === operation) {
                showStatus(error.message);
            }
        } finally {
            if (request === operation) {
                controls.disabled = false;
            }
        }
    }

    function submit(form, work) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const fields = Object.fromEntries(new FormData(form));
            run((current) => work(fields, current));
        });
    }

    function close() {
        operation++;
        controls.disabled = false;
        catalog = undefined;
        selectedPackage = undefined;
        dialog.close();
        options(business, []);
        options(packageSelect, []);
        metadata.reset();
        metadata.hidden = true;
        contents.hidden = true;
        manageServices.hidden = true;
        showStatus('');
    }

    function renderItems(section, group, items, available) {
        const rows = section.querySelector('[data-catalog-rows]');
        const template = find('[data-catalog-row]');
        const itemUrl = '/treatment-packages/' + selectedPackage.id + '/' + group;
        rows.replaceChildren(...items.map((item) => {
            const row = template.content.firstElementChild.cloneNode(true);
            row.querySelector('[data-item-name]').textContent = item.name + (item.is_active ? '' : ' · Pasif');
            const form = row.querySelector('form');
            form.elements.namedItem('quantity').value = item.pivot.quantity;
            form.elements.namedItem('sort_order').value = item.pivot.sort_order;
            submit(form, async (fields, current) => {
                await api(itemUrl + '/' + item.pivot.id, fields, 'PUT');
                await reload(current, selectedPackage.id, 'Kalem güncellendi.');
            });

            row.querySelector('[data-item-remove]').addEventListener('click', () => {
                if (window.confirm('Bu kalem paketten kaldırılsın mı?')) {
                    run(async (current) => {
                        await api(itemUrl + '/' + item.pivot.id, undefined, 'DELETE');
                        await reload(current, selectedPackage.id, 'Kalem kaldırıldı.');
                    });
                }
            });
            return row;
        }));

        const eligible = available.filter((option) => !items.some((item) => item.id === option.id));
        const form = section.querySelector('[data-catalog-add-item]');
        options(form.elements.namedItem('item_id'), [
            ['', eligible.length ? 'Seçin' : 'Eklenebilecek kalem yok'],
            ...eligible.map((item) => [item.id, item.name]),
        ]);
        form.querySelector('[type="submit"]').disabled = !eligible.length;
        form.elements.namedItem('quantity').value = 1;
    }

    function render(packageId) {
        selectedPackage = catalog.packages.find((item) => String(item.id) === String(packageId));
        options(packageSelect, [
            ['', 'Yeni paket oluştur'],
            ...catalog.packages.map((pkg) => [pkg.id, pkg.name + (pkg.is_active ? ' · Yayında' : ' · Pasif')]),
        ], selectedPackage?.id ?? '');

        metadata.reset();
        options(metadata.elements.namedItem('branch_id'), [
            ['', 'Tüm şubeler'],
            ...catalog.branches.map((branch) => [branch.id, branch.name]),
        ], selectedPackage?.branch_id ?? '');
        metadata.elements.namedItem('name').value = selectedPackage?.name ?? '';
        metadata.elements.namedItem('price').value = selectedPackage?.price ?? '';
        metadata.elements.namedItem('currency').value = selectedPackage?.currency ?? 'TRY';
        metadata.elements.namedItem('description').value = selectedPackage?.description ?? '';
        metadata.elements.namedItem('valid_from').value = selectedPackage?.valid_from?.slice(0, 10) ?? '';
        metadata.elements.namedItem('valid_until').value = selectedPackage?.valid_until?.slice(0, 10) ?? '';
        metadata.elements.namedItem('is_offer_enabled').checked = selectedPackage?.is_offer_enabled ?? true;
        metadata.elements.namedItem('includes_hotel').checked = selectedPackage?.includes_hotel ?? false;
        metadata.elements.namedItem('includes_transfer').checked = selectedPackage?.includes_transfer ?? false;
        find('[data-catalog-save]').textContent = selectedPackage ? 'Paket bilgilerini kaydet' : 'Taslak oluştur';
        publish.hidden = !selectedPackage;
        publish.textContent = selectedPackage?.is_active ? 'Pasife al' : 'Yayımla';
        metadata.hidden = false;
        manageServices.hidden = false;
        contents.hidden = !selectedPackage;

        if (!selectedPackage) {
            return;
        }

        const treatments = catalog.treatments.filter((item) => !selectedPackage.branch_id
            || item.branches.some((branch) => branch.id === selectedPackage.branch_id));
        renderItems(itemSections[0], 'items', selectedPackage.treatments, treatments);
        renderItems(itemSections[1], 'service-items', selectedPackage.services, catalog.services);
    }

    async function reload(current, packageId = '', message = '') {
        if (!current()) {
            return;
        }

        const data = await api('/treatment-packages/manage/' + business.value);
        if (current()) {
            catalog = data;
            render(packageId);
            showStatus(message);
        }
    }

    function open(businesses) {
        options(business, businesses.map((item) => [item.id, item.name]), businesses[0].id);
        options(packageSelect, []);
        metadata.hidden = true;
        contents.hidden = true;
        manageServices.hidden = true;
        dialog.showModal();
        run((current) => reload(current));
    }

    business.addEventListener('change', () => {
        metadata.hidden = true;
        contents.hidden = true;
        manageServices.hidden = true;
        options(packageSelect, []);
        run((current) => reload(current));
    });
    packageSelect.addEventListener('change', () => {
        showStatus('');
        render(packageSelect.value);
    });

    submit(metadata, async (fields, current) => {
        const payload = {
            name: fields.name.trim(),
            branch_id: fields.branch_id || null,
            price: fields.price || null,
            currency: fields.currency.toUpperCase(),
            description: fields.description.trim() || null,
            valid_from: fields.valid_from || null,
            valid_until: fields.valid_until || null,
            is_offer_enabled: fields.is_offer_enabled === 'on',
            includes_hotel: fields.includes_hotel === 'on',
            includes_transfer: fields.includes_transfer === 'on',
        };
        const packageData = selectedPackage
            ? await api('/treatment-packages/' + selectedPackage.id, payload, 'PUT')
            : await api('/treatment-packages', { ...payload, business_id: Number(business.value), is_active: false });

        await reload(current, packageData.id, selectedPackage ? 'Paket bilgileri kaydedildi.' : 'Taslak oluşturuldu. Tedavileri ve ek hizmetleri ekleyebilirsiniz.');
    });

    publish.addEventListener('click', () => run(async (current) => {
        const { id, is_active: active } = selectedPackage;
        await api('/treatment-packages/' + id, { is_active: !active }, 'PUT');
        await reload(current, id, active ? 'Paket pasife alındı.' : 'Paket yayımlandı.');
    }));

    for (const section of itemSections) {
        const group = section.dataset.catalogItems;
        submit(section.querySelector('[data-catalog-add-item]'), async (fields, current) => {
            const key = group === 'items' ? 'treatment_id' : 'package_service_id';
            await api('/treatment-packages/' + selectedPackage.id + '/' + group, {
                [key]: Number(fields.item_id),
                quantity: Number(fields.quantity),
            });
            await reload(current, selectedPackage.id, 'Kalem eklendi.');
        });
    }

    manageServices.addEventListener('click', () => {
        openServices({ id: Number(business.value), name: business.selectedOptions[0].text }, (service) => {
            catalog.services = catalog.services.filter((item) => item.id !== service.id);
            if (service.is_active) {
                catalog.services.push({ id: service.id, name: service.name });
                catalog.services.sort((first, second) => first.name.localeCompare(second.name, 'tr'));
            }
            if (selectedPackage) {
                const linked = selectedPackage.services.find((item) => item.id === service.id);
                if (linked) {
                    linked.name = service.name;
                    linked.is_active = service.is_active;
                }
                renderItems(itemSections[1], 'service-items', selectedPackage.services, catalog.services);
            }
        });
    });

    find('[data-catalog-close]').addEventListener('click', () => {
        if (!controls.disabled) {
            close();
        }
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!controls.disabled) {
            close();
        }
    });

    return { open, close };
}
