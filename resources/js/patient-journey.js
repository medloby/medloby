const occurredDate = new Intl.DateTimeFormat('tr-TR', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Istanbul',
});
const appointmentDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });

const appointmentUpdates = {
    confirmed: 'Randevu onaylandı',
    cancelled: 'Randevu iptal edildi',
    completed: 'Randevu tamamlandı',
    no_show: 'Randevuya katılım olmadı',
};

const appointmentStates = {
    pending: 'Onay bekliyor',
    confirmed: 'Onaylı',
    cancelled: 'İptal edildi',
    completed: 'Tamamlandı',
    no_show: 'Katılım olmadı',
    rescheduled: 'Saati değişti',
};

function eventLabel(item) {
    switch (item.kind) {
    case 'conversation':
        return 'Klinikle görüşme başladı';
    case 'offer_received':
        return 'Teklif geldi';
    case 'offer_responded':
        return item.status === 'accepted' ? 'Teklif kabul edildi' : 'Teklif reddedildi';
    case 'appointment_booked':
        return 'Randevu oluşturuldu';
    case 'appointment_moved':
        return 'Randevu saati değişti';
    case 'appointment_status':
        return appointmentUpdates[item.status] ?? 'Randevu durumu güncellendi';
    }
}

export function mountPatientJourney(panel, { api, openActivity }) {
    const find = (part, root = panel) => root.querySelector('[data-journey-' + part + ']');
    const content = find('content');
    const category = find('category');
    const list = find('events');
    const status = find('status');
    const previous = find('previous');
    const next = find('next');
    let page = 1;
    let request = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    async function run(message, task) {
        if (panel.hidden || content.disabled) {
            return;
        }

        const active = ++request;
        content.disabled = true;
        showStatus(message);
        try {
            const result = await task();
            if (active === request) {
                showStatus();
                return result;
            }
        } catch (error) {
            if (active === request) {
                showStatus(error.message);
            }
        } finally {
            if (active === request) {
                content.disabled = false;
            }
        }
    }

    function render(item) {
        const row = find('event').content.firstElementChild.cloneNode(true);
        find('label', row).textContent = eventLabel(item);
        const title = find('title', row);
        title.textContent = item.title ?? '';
        title.hidden = !item.title;
        const date = item.occurred_at ? occurredDate.format(new Date(item.occurred_at)) : null;
        const expired = item.kind === 'offer_received' && item.status === 'expired' ? 'Teklifin süresi doldu' : null;
        find('meta', row).textContent = [item.business_name, date, expired].filter(Boolean).join(' · ');
        if (item.starts_at) {
            const starts = find('starts', row);
            const day = appointmentDate.format(new Date(item.starts_at.slice(0, 10) + 'T00:00:00Z'));
            starts.textContent = [day + ', ' + item.starts_at.slice(11, 16), appointmentStates[item.status]].filter(Boolean).join(' · ');
            starts.hidden = false;
        }
        const action = find('open', row);
        action.textContent = !item.conversation_id ? 'Randevuyu görüntüle'
            : item.kind.startsWith('offer_') && item.target_id ? 'Teklifi görüntüle' : 'Görüşmeyi görüntüle';
        action.addEventListener('click', () => run('Kayıt açılıyor…', () => openActivity(item)));
        return row;
    }

    async function load(target = 1) {
        const query = new URLSearchParams({ page: target });
        if (category.value) {
            query.set('category', category.value);
        }

        const result = await run('Tedavi süreci yükleniyor…', () => api('/patient/journey?' + query));
        if (!result) {
            return;
        }
        page = result.current_page;
        list.replaceChildren(...result.events.map(render));
        find('pages').hidden = page <= 1 && !result.has_more;
        find('page').textContent = 'Sayfa ' + page;
        previous.disabled = page <= 1;
        next.disabled = !result.has_more;
        showStatus(result.events.length ? '' : 'Bu türde kayıt bulunamadı.');
    }

    category.addEventListener('change', () => {
        list.replaceChildren();
        find('pages').hidden = true;
        load();
    });
    find('refresh').addEventListener('click', () => load());
    previous.addEventListener('click', () => load(page - 1));
    next.addEventListener('click', () => load(page + 1));

    return {
        setVisible(visible) {
            if (visible === !panel.hidden) {
                return;
            }
            panel.hidden = !visible;
            if (visible) {
                load();
                return;
            }
            request++;
            content.disabled = false;
            category.value = '';
            list.replaceChildren();
            find('pages').hidden = true;
            showStatus();
        },
    };
}
