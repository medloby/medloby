import { clinicAction, clinicPager, clinicRecordRow, clinicStatus } from './clinic-records';

const date = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'Europe/Istanbul' });
const profileLabels = { active: 'Aktif profil', inactive: 'Pasif profil' };

export function mountAdminPatients(dialog, trigger, api, openSupport) {
    const find = (name) => dialog.querySelector('[data-patients-' + name + ']');
    const form = find('filters');
    const status = find('status');
    const list = find('list');
    const pages = find('pages');
    let filters;
    let request = 0;

    async function load(page) {
        const current = ++request;
        const query = new URLSearchParams(filters);
        query.set('page', page);
        list.replaceChildren();
        clinicPager(pages, null);
        clinicStatus(status, 'Hasta hesapları yükleniyor…');
        try {
            const result = await api('/admin/patient-accounts?' + query);
            if (current !== request || !dialog.open) {
                return;
            }
            list.replaceChildren(...result.data.map((account) => {
                const details = [
                    'Hesap #' + account.id, account.name,
                    account.verified ? 'E-posta doğrulandı' : 'E-posta doğrulama bekliyor',
                    profileLabels[account.profile_status] ?? 'Profil durumu bilinmiyor',
                    account.created_at && 'Kayıt ' + date.format(new Date(account.created_at)),
                ].filter(Boolean).join(' · ');
                return clinicRecordRow(details, [clinicAction('Destek talepleri', () => openSupport(account))]);
            }));
            clinicPager(pages, result, load);
            clinicStatus(status, result.data.length ? '' : 'Eşleşen hasta hesabı bulunamadı.');
        } catch (error) {
            if (request === current && dialog.open) {
                clinicStatus(status, error.message);
            }
        }
    }

    function search() {
        filters = [...new FormData(form)].filter(([, value]) => value.trim());
        load(1);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        search();
    });
    trigger.addEventListener('click', () => {
        form.reset();
        dialog.showModal();
        search();
    });
    find('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { request++; });

    return { stop: () => dialog.close() };
}
