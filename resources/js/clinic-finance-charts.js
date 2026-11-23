const metrics = { income: 'Gelir', expense: 'Gider', net: 'Net fark' };
const minorUnits = (amount) => BigInt(amount.replace('.', ''));

function element(tag, classes, label = '') {
    const node = document.createElement(tag);
    node.className = classes;
    node.textContent = label;
    return node;
}

function currencyCard(currency) {
    const card = element('div', 'grid min-w-0 content-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700');
    card.append(element('h5', 'text-sm font-semibold', currency));
    return card;
}

function chartScale(amounts) {
    let min = 0n;
    let max = 0n;
    for (const amount of amounts) {
        const value = minorUnits(amount);
        min = value < min ? value : min;
        max = value > max ? value : max;
    }
    const range = max - min || 1n;
    return (value) => Number((minorUnits(value) - min) * 10000n / range) / 100;
}

function chartRow(label, amount, formatted, scale, previous = false) {
    const row = element('li', 'grid min-w-0 gap-1');
    const caption = element('div', 'flex flex-wrap justify-between gap-x-2 text-xs');
    caption.append(element('span', 'min-w-0 break-words', label), element('span', 'font-medium tabular-nums', formatted));
    const track = element('div', 'relative h-2 rounded bg-slate-100 dark:bg-slate-800');
    track.setAttribute('aria-hidden', 'true');
    const bar = element('span', 'absolute inset-y-0 rounded ' + (previous ? 'bg-slate-500 dark:bg-slate-400' : 'bg-teal-600 dark:bg-teal-400'));
    const zero = scale('0.00');
    const tip = scale(amount);
    bar.style.left = Math.min(zero, tip) + '%';
    bar.style.width = Math.abs(tip - zero) + '%';
    track.append(bar);
    if (zero > 0) {
        const baseline = element('span', 'absolute inset-y-0 w-px bg-slate-500 dark:bg-slate-300');
        baseline.style.left = zero + '%';
        track.append(baseline);
    }
    row.append(caption, track);
    return row;
}

function comparisonCard({ currency, selected, previous, change }, formatAmount) {
    const card = currencyCard(currency);
    const group = element('div', 'grid gap-3 sm:grid-cols-3');
    const keys = Object.keys(metrics);
    const scale = chartScale(keys.flatMap((key) => [selected[key], previous[key]]));
    for (const key of keys) {
        const compare = element('div', 'grid content-start gap-2');
        const bars = element('ul', 'grid gap-2');
        bars.append(
            chartRow('Seçilen dönem', selected[key], formatAmount(selected[key], currency), scale),
            chartRow('Önceki dönem', previous[key], formatAmount(previous[key], currency), scale, true),
        );
        const changeLabel = (minorUnits(change[key]) > 0n ? '+' : '') + formatAmount(change[key], currency);
        compare.append(
            element('h6', 'text-sm font-medium', metrics[key]),
            bars,
            element('p', 'text-xs text-slate-600 dark:text-slate-300', 'Değişim ' + changeLabel),
        );
        group.append(compare);
    }
    card.append(group);
    return card;
}

function treatmentCard({ currency, unallocated, treatments }, formatAmount) {
    const card = currencyCard(currency);
    const scale = chartScale(treatments.map(({ income }) => income));
    if (treatments.length) {
        const bars = element('ul', 'grid gap-2');
        bars.append(...treatments.map(({ name, income }) => chartRow(name, income, formatAmount(income, currency), scale)));
        card.append(bars);
    }
    if (minorUnits(unallocated) > 0n) {
        card.append(element('p', 'text-sm', 'Tedaviye bağlanmamış gelir · ' + formatAmount(unallocated, currency)));
    }
    return card;
}

export function renderFinanceCharts(find, data, formatAmount, dateRange) {
    const comparison = data.comparison;
    find('comparison').hidden = !comparison;
    if (comparison) {
        find('previous-period').textContent = 'Önceki dönem · ' + dateRange(comparison.starts_on, comparison.ends_on);
        find('comparison-empty').hidden = comparison.totals.length > 0;
    }
    find('comparison-charts').replaceChildren(...(comparison?.totals ?? []).map((total) => comparisonCard(total, formatAmount)));
    find('treatment-empty').hidden = data.treatment_income.some(({ treatments }) => treatments.length > 0);
    find('treatment-charts').replaceChildren(...data.treatment_income.map((row) => treatmentCard(row, formatAmount)));
}
