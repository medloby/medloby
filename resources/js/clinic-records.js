const integer = new Intl.NumberFormat('tr-TR');

export const clinicBusinessStatusLabels = {
    pending: 'İncelemede',
    active: 'Aktif',
    suspended: 'Askıda',
    rejected: 'Reddedildi',
    inactive: 'Pasif',
};

export function formatClinicAmount(value, currency) {
    const [whole, fraction] = value.replace(/^-/, '').split('.');
    return (value.startsWith('-') ? '−' : '') + integer.format(BigInt(whole)) + ',' + fraction + ' ' + currency;
}

export function clinicIncomeReference(entry) {
    if (entry.appointment_id) {
        return 'Randevu #' + entry.appointment_id;
    }
    return entry.offer_id ? 'Paket teklifi #' + entry.offer_id : '';
}

export function clinicRequestKey() {
    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
}

export function clinicStatus(element, message = '') {
    element.textContent = message;
    element.hidden = !message;
}

export function clinicAction(label, handler) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'offer-button offer-button-secondary';
    button.textContent = label;
    button.addEventListener('click', handler);
    return button;
}

export function clinicRecordRow(text, buttons = []) {
    const row = document.createElement('li');
    row.className = 'flex min-w-0 flex-wrap items-center justify-between gap-2 py-2 text-sm';
    const content = document.createElement('span');
    content.className = 'min-w-0 break-words';
    content.textContent = text;
    row.append(content);
    if (buttons.length) {
        const actions = document.createElement('div');
        actions.className = 'flex flex-wrap gap-2';
        actions.append(...buttons);
        row.append(actions);
    }
    return row;
}

export function clinicPager(nav, page, navigate) {
    nav.hidden = !page || page.last_page <= 1 && page.current_page <= 1;
    const prev = nav.querySelector('[data-page-previous]');
    const next = nav.querySelector('[data-page-next]');
    nav.querySelector('[data-page-number]').textContent = page ? page.current_page + ' / ' + page.last_page : '';
    prev.disabled = !page || page.current_page <= 1;
    next.disabled = !page || page.current_page >= page.last_page;
    prev.onclick = () => navigate(page.current_page - 1);
    next.onclick = () => navigate(page.current_page + 1);
}
