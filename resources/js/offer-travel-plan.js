const dateLabel = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });
const hotelKeys = ['name', 'address', 'check_in', 'check_out'];
const transferKeys = ['starts_at', 'from', 'to'];
const maxTransfers = 12;

function day(value) {
    return dateLabel.format(new Date(value.slice(0, 10) + 'T00:00:00Z'));
}

export function mountOfferTravelPlan(dialog, api) {
    const find = (part, root = dialog) => root.querySelector('[data-travel-' + part + ']');
    const preview = find('preview');
    const form = find('form');
    const controls = find('controls');
    const status = find('status');
    const refresh = find('refresh');
    const hotelReady = form.elements.namedItem('hotel_ready');
    const hotelFields = find('hotel-fields');
    const rows = find('rows');
    const add = find('add');
    let current;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function hideHotelFields(included) {
        const ready = included && hotelReady.checked;
        hotelFields.hidden = !ready;
        hotelFields.disabled = !ready;
    }

    function transferInput(row, key) {
        return row.querySelector('[data-transfer="' + key + '"]');
    }

    function transferRow(item = {}) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        for (const key of transferKeys) {
            transferInput(row, key).value = item[key] ?? '';
        }
        find('remove', row).addEventListener('click', () => {
            row.remove();
            add.disabled = false;
            add.focus();
        });
        return row;
    }

    function readOnly(plan) {
        const hotel = plan.hotel;
        find('preview-hotel').hidden = !plan.hotel_included;
        find('hotel-text').textContent = hotel ? [
            hotel.name, hotel.address, 'Giriş · ' + day(hotel.check_in), 'Çıkış · ' + day(hotel.check_out),
        ].filter(Boolean).join('\n') : 'Klinik konaklama bilgilerini hazırlıyor.';
        find('preview-transfer').hidden = !plan.transfer_included;
        find('no-transfers').hidden = plan.transfers.length > 0;
        find('transfer-list').replaceChildren(...plan.transfers.map((transfer) => {
            const li = document.createElement('li');
            li.className = 'whitespace-pre-line break-words rounded-lg border border-slate-200 p-3 dark:border-slate-700';
            li.textContent = day(transfer.starts_at) + ', ' + transfer.starts_at.slice(11, 16) + ' · Türkiye saati'
                + '\nAlış · ' + transfer.from + '\nVarış · ' + transfer.to;
            return li;
        }));
    }

    function render(plan) {
        current.plan = plan;
        preview.hidden = plan.can_manage;
        form.hidden = !plan.can_manage;
        if (!plan.can_manage) {
            readOnly(plan);
            return;
        }

        find('edit-hotel').hidden = !plan.hotel_included;
        find('edit-transfer').hidden = !plan.transfer_included;
        hotelReady.checked = Boolean(plan.hotel);
        for (const key of hotelKeys) {
            form.elements.namedItem(key).value = plan.hotel?.[key] ?? '';
        }
        hideHotelFields(plan.hotel_included);
        rows.replaceChildren(...plan.transfers.map(transferRow));
        add.disabled = rows.childElementCount >= maxTransfers;
    }

    async function run(message, method, payload) {
        const context = current;
        if (!context || context.busy) {
            return;
        }
        context.busy = true;
        controls.disabled = true;
        refresh.disabled = true;
        showStatus(message);
        try {
            const plan = await api(context.path, payload, method);
            if (context === current) {
                render(plan);
                showStatus(method === 'PUT' ? 'Seyahat planı kaydedildi.' : '');
            }
        } catch (error) {
            if (context === current) {
                showStatus(error.message);
            }
        } finally {
            if (context === current) {
                context.busy = false;
                controls.disabled = false;
                refresh.disabled = false;
            }
        }
    }

    function load() {
        run('Seyahat planı yükleniyor…', 'GET');
    }

    function close() {
        current = undefined;
        rows.replaceChildren();
        find('transfer-list').replaceChildren();
        find('hotel-text').textContent = '';
        find('offer').textContent = '';
        form.reset();
        showStatus();
    }

    hotelReady.addEventListener('change', () => hideHotelFields(current.plan.hotel_included));
    add.addEventListener('click', () => {
        if (rows.childElementCount >= maxTransfers) {
            return;
        }
        const row = transferRow();
        rows.append(row);
        add.disabled = rows.childElementCount >= maxTransfers;
        transferInput(row, 'starts_at').focus();
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const plan = current?.plan;
        if (!plan?.can_manage) {
            return;
        }
        const hotel = plan.hotel_included && hotelReady.checked
            ? Object.fromEntries(hotelKeys.map((key) => [key, form.elements.namedItem(key).value]))
            : null;
        const transfers = Array.from(rows.children, (row) => (
            Object.fromEntries(transferKeys.map((key) => [key, transferInput(row, key).value]))
        ));
        run('Seyahat planı kaydediliyor…', 'PUT', { revision: plan.revision, hotel, transfers });
    });
    refresh.addEventListener('click', load);
    find('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', close);

    return {
        open(offer) {
            current = { path: '/offers/' + offer.id + '/travel-plan' };
            find('offer').textContent = offer.title;
            preview.hidden = true;
            form.hidden = true;
            dialog.showModal();
            load();
        },
        stop() {
            dialog.close();
            close();
        },
    };
}
