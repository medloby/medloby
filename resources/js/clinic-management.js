import { clinicAction, clinicPager, clinicRecordRow, clinicStatus, formatClinicAmount } from './clinic-records';
import { mountClinicFinanceReports } from './clinic-finance-reports';

const visitStates = { pending: 'Bekliyor', confirmed: 'Onaylı', completed: 'Tamamlandı', cancelled: 'İptal', no_show: 'Hasta gelmedi' };
const taskStates = { pending: 'Bekliyor', open: 'Açık', in_progress: 'Devam ediyor', completed: 'Tamamlandı', cancelled: 'İptal' };
const dateTime = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Istanbul' });
const dateOnly = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });
const number = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 });
const percentage = (value) => value === null ? '—' : '%' + number.format(value);

export function mountClinicManagement(panel, api, { openAppointment, openOverdue, openLowStock }) {
    const find = (part) => panel.querySelector('[data-management-' + part + ']');
    const form = find('form');
    const month = form.elements.namedItem('month');
    const status = find('status');
    const reports = mountClinicFinanceReports(panel.querySelector('[data-finance-reports]'), api, formatClinicAmount, { clinicWide: true });
    let business;
    let pending = 0;

    function cashLabel(total) {
        return [
            'Gelir ' + formatClinicAmount(total.income, total.currency),
            'Gider ' + formatClinicAmount(total.expense, total.currency),
            'Net ' + formatClinicAmount(total.net, total.currency),
        ].join(' · ');
    }

    function summaryCard(branch) {
        const card = document.createElement('li');
        card.className = 'grid min-w-0 gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700';
        const title = document.createElement('h4');
        title.className = 'break-words font-semibold';
        title.textContent = branch.name;
        card.append(title);
        const details = [
            branch.appointments.total + ' randevu · ' + branch.open_tasks + ' açık görev',
            branch.lead_count + ' aday · ' + branch.offered_count + ' teklif alan · ' + branch.booked_count + ' randevu oluşan',
        ];
        if (branch.cash) {
            details.push(...(branch.cash.length ? branch.cash.map(cashLabel) : ['Bu ay gelir veya gider kaydı yok.']));
        }
        for (const text of details) {
            const line = document.createElement('p');
            line.className = 'break-words';
            line.textContent = text;
            card.append(line);
        }
        const target = { branch_id: branch.id, business_id: business };
        const alerts = [];
        if (branch.overdue_balances?.length) {
            const overdue = branch.overdue_balances.map((total) => [
                total.count + (total.direction === 'receivable' ? ' gecikmiş alacak' : ' gecikmiş borç'),
                formatClinicAmount(total.remaining, total.currency),
            ].join(' · '));
            alerts.push(clinicRecordRow(overdue.join(' / '), [clinicAction('Vadeleri aç', () => openOverdue(target))]));
        }
        if (branch.low_stock_count > 0) {
            alerts.push(clinicRecordRow(branch.low_stock_count + ' malzeme düşük stokta', [clinicAction('Düşük stoğu aç', () => openLowStock(target))]));
        }
        if (alerts.length) {
            const list = document.createElement('ul');
            list.className = 'divide-y divide-amber-200 rounded-lg bg-amber-50 px-2 text-amber-950 dark:divide-amber-800 dark:bg-amber-950 dark:text-amber-100';
            list.append(...alerts);
            card.append(list);
        }
        return card;
    }

    function render(data) {
        month.value = data.month;
        const branchNames = new Map(data.branches.map((branch) => [branch.id, branch.name]));
        const summary = data.summary;
        reports.setScope(null, summary.finance_branch_count > 0 ? business : null, data.month);
        reports.refresh();
        const hasAlerts = data.branches.some((branch) => branch.overdue_balances?.length || branch.low_stock_count > 0);
        clinicStatus(find('alert-date'), hasAlerts ? 'Vade ve stok uyarıları · ' + dateOnly.format(new Date(data.alerts_on + 'T00:00:00Z')) : '');
        find('summary').replaceChildren(clinicRecordRow(
            data.branches.length + ' şube · ' + summary.open_tasks + ' açık görev · ' + summary.lead_count + ' aday · '
            + summary.offered_count + ' teklif alan · ' + summary.booked_count + ' randevu oluşan',
        ), ...summary.cash.map((total) => clinicRecordRow(summary.finance_branch_count + ' finans yetkili şube · ' + cashLabel(total))));
        find('branches').replaceChildren(...data.branches.map(summaryCard));
        const totals = data.performance;
        find('performance').textContent = totals.total + ' randevu · ' + totals.completed + ' tamamlanan · '
            + totals.cancelled + ' iptal (' + percentage(totals.cancellation_percent) + ') · '
            + totals.no_show + ' gelmeyen (' + percentage(totals.no_show_percent) + ')';
        find('doctors-empty').hidden = data.doctors.length > 0;
        find('doctors-table').hidden = !data.doctors.length;
        find('doctors').replaceChildren(...data.doctors.map((doctor) => {
            const row = document.createElement('tr');
            const fields = [
                branchNames.get(doctor.branch_id) + ' · ' + doctor.name, number.format(doctor.available_minutes / 60) + ' sa.',
                number.format(doctor.booked_minutes / 60) + ' sa.', percentage(doctor.occupancy_percent),
                percentage(doctor.cancellation_percent), percentage(doctor.no_show_percent),
            ];
            for (const value of fields) {
                const cell = document.createElement('td');
                cell.className = 'px-2 py-2';
                cell.textContent = value;
                row.append(cell);
            }
            return row;
        }));

        find('visits').replaceChildren(...(data.appointments.data.length ? data.appointments.data.map((visit) => clinicRecordRow(
            [branchNames.get(visit.branch_id), visit.starts_at.slice(0, 16), visit.doctor, visit.treatment, visitStates[visit.status]].filter(Boolean).join(' · '),
            [clinicAction('Randevu kartı', () => openAppointment({ ...visit, business_id: business }))],
        )) : [clinicRecordRow('Bu ay randevu yok.')]));
        clinicPager(find('visit-pages'), data.appointments, (page) => load(page, data.tasks.current_page));
        find('tasks').replaceChildren(...(data.tasks.data.length ? data.tasks.data.map((task) => clinicRecordRow(
            [branchNames.get(task.branch_id), task.title, taskStates[task.status] || task.status,
                task.due_at && dateTime.format(new Date(task.due_at))].filter(Boolean).join(' · '),
        )) : [clinicRecordRow('Bu şubelerde görev kaydı yok.')]));
        clinicPager(find('task-pages'), data.tasks, (page) => load(data.appointments.current_page, page));
        find('results').hidden = false;
    }

    async function load(visits = 1, tasks = 1) {
        if (!business) {
            return;
        }
        const request = ++pending;
        const query = new URLSearchParams({ visit_page: visits, task_page: tasks });
        if (month.value) {
            query.set('month', month.value);
        }
        find('results').hidden = true;
        clinicStatus(status, 'Yönetim özeti yükleniyor…');
        try {
            const data = await api('/businesses/' + business + '/crm/management?' + query);
            if (request === pending) {
                render(data);
                clinicStatus(status);
            }
        } catch (error) {
            if (request === pending) {
                clinicStatus(status, error.message);
            }
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load();
    });
    panel.addEventListener('toggle', () => {
        if (panel.open) {
            load();
        }
    });

    return {
        setBusiness(id) {
            if (id === business) {
                return;
            }
            pending++;
            business = id;
            reports.setScope(null, null);
            panel.open = false;
            panel.hidden = !id;
            form.reset();
            find('results').hidden = true;
            clinicStatus(status);
        },
        refresh() {
            if (panel.open) {
                load();
            }
        },
    };
}
