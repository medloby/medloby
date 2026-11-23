import { renderFinanceCharts } from './clinic-finance-charts';

const dateLabel = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });
const monthLabel = new Intl.DateTimeFormat('tr-TR', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const countLabel = new Intl.NumberFormat('tr-TR');

function tableRow(values) {
    const row = document.createElement('tr');
    for (const value of values) {
        const cell = document.createElement('td');
        cell.className = 'px-2 py-2';
        cell.textContent = value;
        row.append(cell);
    }
    return row;
}

function dateRange(start, end) {
    const on = (value) => dateLabel.format(new Date(value + 'T00:00:00Z'));
    return on(start) + ' – ' + on(end);
}

export function mountClinicFinanceReports(panel, api, formatAmount, { clinicWide = false } = {}) {
    const find = (part, root = panel) => root.querySelector('[data-finance-' + part + ']');
    const form = find('form');
    const status = find('status');
    const result = find('result');
    const submit = find('submit');
    const month = form.elements.namedItem('month');
    const year = form.elements.namedItem('year');
    const period = form.elements.namedItem('period');
    let scope;
    let request = 0;

    function showStatus(text = '') {
        status.textContent = text;
        status.hidden = !text;
    }

    function syncPeriod() {
        find('month').hidden = period.value !== 'month';
        find('year').hidden = period.value !== 'year';
        month.disabled = period.value !== 'month';
        year.disabled = period.value !== 'year';
    }

    function amounts(total) {
        return [total.currency, ...['income', 'expense', 'net'].map((key) => formatAmount(total[key], total.currency))];
    }

    function render(data) {
        const dates = data.starts_on ? dateRange(data.starts_on, data.ends_on) : 'Tüm dönemler';
        find('period').textContent = dates + ' · ' + (data.branches.length === 1 ? data.branches[0].name : data.branches.length + ' yetkili şube');
        renderFinanceCharts(find, data, formatAmount, dateRange);
        find('cash-empty').hidden = data.totals.length > 0;
        find('cash').hidden = data.totals.length === 0;
        find('totals').replaceChildren(...data.totals.map((total) => tableRow([
            ...amounts(total), formatAmount(total.unattributed_income, total.currency),
        ])));
        find('branches').hidden = data.branches.length < 2 || data.totals.length === 0;
        find('branch-totals').replaceChildren(...data.branches.flatMap((branch) =>
            branch.totals.map((total) => tableRow([branch.name, ...amounts(total)]))));
        find('months').hidden = data.months.length === 0;
        find('month-totals').replaceChildren(...data.months.flatMap((record) => {
            const label = monthLabel.format(new Date(record.month + '-01T00:00:00Z'));
            return record.totals.map((total) => tableRow([label, ...amounts(total)]));
        }));
        find('doctors-empty').hidden = data.doctors.length > 0;
        find('doctors').replaceChildren(...data.doctors.map((doctor) => {
            const row = find('doctor').content.firstElementChild.cloneNode(true);
            find('doctor-name', row).textContent = doctor.name;
            for (const key of ['total', 'completed', 'scheduled', 'cancelled', 'no_show']) {
                find('visit-' + key, row).textContent = countLabel.format(doctor[key]);
            }
            const income = doctor.income.map(({ amount, currency }) => formatAmount(amount, currency)).join(' · ');
            find('doctor-income', row).textContent = income ? 'Bağlı tahsilat · ' + income : 'Bu dönemde bağlı tahsilat kaydı yok.';
            find('treatments', row).replaceChildren(...doctor.treatments.map((treatment) => {
                const item = document.createElement('li');
                item.textContent = (treatment.name || 'Tedavi belirtilmemiş') + ' · ' + countLabel.format(treatment.total)
                    + ' randevu, ' + countLabel.format(treatment.completed) + ' tamamlanan';
                return item;
            }));
            return row;
        }));
        result.hidden = false;
    }

    async function load() {
        if (!scope) {
            return;
        }
        const current = ++request;
        const filters = new FormData(form);
        const query = new URLSearchParams({ period: filters.get('period') });
        if (filters.get('scope') === 'branch') {
            query.set('branch_id', scope.branchId);
        }
        if (query.get('period') === 'month' && month.value) {
            query.set('month', month.value);
        }
        if (query.get('period') === 'year' && year.value) {
            query.set('year', year.value.padStart(4, '0'));
        }
        submit.disabled = true;
        result.hidden = true;
        showStatus('Finans raporu yükleniyor…');
        try {
            const data = await api(scope.path + '?' + query);
            if (current === request) {
                month.value ||= data.today.slice(0, 7);
                year.value ||= data.today.slice(0, 4);
                render(data);
                showStatus();
            }
        } catch (error) {
            if (current === request) {
                showStatus(error.message);
            }
        } finally {
            if (current === request) {
                submit.disabled = false;
            }
        }
    }

    period.addEventListener('change', syncPeriod);
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
        setScope(branchId, businessId, defaultMonth = '') {
            const path = businessId && (clinicWide || branchId) ? '/businesses/' + businessId + '/crm/finance/reports' : undefined;
            panel.hidden = !path;
            if (scope?.path === path && (!path || scope.branchId === branchId)) {
                return;
            }
            request++;
            scope = path ? { branchId, path } : undefined;
            panel.open = false;
            form.reset();
            month.value = defaultMonth;
            year.value = defaultMonth.slice(0, 4);
            syncPeriod();
            result.hidden = true;
            submit.disabled = false;
            showStatus();
        },
        refresh() {
            if (panel.open) {
                load();
            }
        },
    };
}
