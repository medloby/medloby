import { mountClinicAdministration } from './clinic-administration';
import { mountClinicContracts } from './clinic-contracts';
import { mountClinicReview } from './clinic-review';
import { mountPackageServiceAdministration } from './package-service-administration';
import { mountSupportTickets } from './support-tickets';
import { mountTreatmentAdministration } from './treatment-administration';
import { mountAdminPatients } from './platform-admin-patients';
import { createWorkspaceApi } from './workspace-api';
import { clinicPager } from './clinic-records';
import { mountWorkspaceNotifications, platformNotificationTarget } from './workspace-notifications';

const number = new Intl.NumberFormat('tr-TR');
const time = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Istanbul' });
const day = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });
const dateLabel = (date) => day.format(new Date(date + 'T00:00:00Z'));

function textElement(tag, text, classes = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    element.className = classes;
    return element;
}

function status(element, message = '') {
    element.textContent = message;
    element.hidden = !message;
}

function showTrend(chart, values) {
    const maximum = Math.max(...values.map(({ count }) => count), 0);
    chart.querySelector('[data-chart-period]').textContent = dateLabel(values[0].date) + ' – ' + dateLabel(values.at(-1).date);
    const bars = chart.querySelector('[data-chart-bars]');
    const table = chart.querySelector('[data-chart-table]');
    chart.querySelector('[data-chart-empty]').hidden = maximum > 0;
    bars.hidden = maximum === 0;
    table.hidden = maximum === 0;
    bars.replaceChildren(...values.map(({ date, count }) => {
        const bar = textElement('div', '', 'rounded-t-sm bg-teal-600 dark:bg-teal-400');
        bar.style.height = (maximum ? count / maximum * 100 : 0) + '%';
        bar.title = dateLabel(date) + ' · ' + number.format(count);
        return bar;
    }));
    chart.querySelector('[data-chart-rows]').replaceChildren(...values.map(({ date, count }) => {
        const row = document.createElement('tr');
        row.append(textElement('td', dateLabel(date), 'py-1'), textElement('td', number.format(count), 'py-1 text-right tabular-nums'));
        return row;
    }));
}

function showDistribution(chart, values) {
    const total = values.reduce((sum, { count }) => sum + count, 0);
    const bars = chart.querySelector('[data-chart-bars]');
    if (!values.length) {
        bars.replaceChildren(textElement('li', 'Gösterilecek kayıt yok.'));
        return;
    }
    bars.replaceChildren(...values.map(({ label, count }) => {
        const row = document.createElement('li');
        row.className = 'grid gap-1';
        const caption = textElement('div', '', 'flex flex-wrap justify-between gap-x-2');
        caption.append(textElement('span', label), textElement('span', number.format(count), 'font-medium tabular-nums'));
        const track = textElement('div', '', 'h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800');
        track.setAttribute('aria-hidden', 'true');
        const bar = textElement('div', '', 'h-full rounded-full bg-teal-600 dark:bg-teal-400');
        bar.style.width = (total ? count / total * 100 : 0) + '%';
        track.append(bar);
        row.append(caption, track);
        return row;
    }));
}

function mountDetailsReport(panel, link, fetchReport, render, endSession) {
    const message = panel.querySelector('[data-report-status]');
    const refresh = panel.querySelector('[data-report-refresh]');
    const result = panel.querySelector('[data-report-result]');
    let request = 0;
    async function load() {
        const current = ++request;
        refresh.disabled = true;
        status(message, 'Rapor yükleniyor…');
        try {
            const data = await fetchReport();
            if (current === request && panel.isConnected) {
                render(panel, data);
                result.hidden = false;
                status(message);
            }
        } catch (error) {
            if (current === request && error.status === 403) {
                endSession(error.message);
            } else if (current === request && panel.isConnected) {
                status(message, error.message);
            }
        } finally {
            if (current === request) {
                refresh.disabled = false;
            }
        }
    }
    link.addEventListener('click', () => {
        panel.open = true;
        panel.querySelector('summary').focus();
    });
    panel.addEventListener('toggle', () => {
        if (panel.open && !refresh.disabled) {
            load();
        }
    });
    refresh.addEventListener('click', load);
    return load;
}

function renderAnalytics(panel, data) {
    for (const field of panel.querySelectorAll('[data-analytics-metric]')) {
        field.textContent = number.format(data[field.dataset.analyticsMetric]);
    }
    panel.querySelector('[data-analytics-period]').textContent =
        dateLabel(data.starts_on) + ' – ' + dateLabel(data.ends_on) + ' · İl sayımları tüm klinik kayıtlarını kapsar.';
    showDistribution(panel.querySelector('[data-analytics-outcomes]'), data.outcomes);
    showDistribution(panel.querySelector('[data-analytics-cities]'), data.cities);
}

const operationLabels = {
    reachable: 'Erişilebilir', ready: 'Hazır', unavailable: 'Erişilemiyor',
    needs_attention: 'Kontrol gerekiyor', not_configured: 'Yapılandırılmamış', unknown: 'Bilinmiyor',
};

function renderOperations(panel, data) {
    for (const field of panel.querySelectorAll('[data-operation]')) {
        const key = field.dataset.operation;
        const observed = data.last_observed_at?.[key];
        field.textContent = observed ? time.format(new Date(observed))
            : operationLabels[data[key]] ?? data[key] ?? operationLabels.unknown;
    }
}

const auditActions = {
    approved: 'Klinik başvurusu onaylandı', rejected: 'Klinik başvurusu reddedildi',
    suspended: 'Klinik askıya alındı', active: 'Klinik yeniden açıldı',
    published: 'Sözleşme yayımlandı', deactivated: 'Sözleşme yayından kaldırıldı',
    replied: 'Destek talebine yanıt verildi',
    assigned: 'Destek talebine sorumlu atandı', unassigned: 'Destek talebinin sorumlusu kaldırıldı',
    support_granted: 'Destek görevlisi yetkisi verildi', support_revoked: 'Destek görevlisi yetkisi kaldırıldı',
    member_role_changed: 'Klinik üyesinin rolü değiştirildi', member_status_changed: 'Klinik üyesinin durumu değiştirildi',
    member_permission_changed: 'Klinik üyesinin özel yetkisi değiştirildi',
};
const auditTargets = {
    clinic_application: 'Klinik', clinic_status: 'Klinik', contract: 'Sözleşme', support: 'Talep',
    platform_role: 'Hesap', clinic_membership: 'Üyelik',
};
const membershipAuditValues = {
    business_owner: 'Klinik sahibi', manager: 'Yönetici', staff: 'Personel',
    active: 'Aktif', inactive: 'Pasif', allowed: 'İzinli', denied: 'Engelli',
};

function mountAudit(panel, link, api, endSession) {
    const source = panel.querySelector('[data-audit-source]');
    const rows = panel.querySelector('[data-audit-rows]');
    const pages = panel.querySelector('[data-audit-pages]');
    let page = 1;
    const load = mountDetailsReport(panel, link, () => {
        const query = new URLSearchParams({ page });
        if (source.value) {
            query.set('source', source.value);
        }
        return api('/admin/audit?' + query);
    }, (_, result) => {
        rows.replaceChildren(...result.data.map((event) => {
            const item = textElement('li', '', 'grid gap-1 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800');
            const target = auditTargets[event.source] + ' #' + event.target_id;
            const details = [time.format(new Date(event.occurred_at)), event.actor_name ?? 'İşlemi yapan hesap kayıtlı değil', target, event.target_name];
            if (event.change) {
                const { business_id, permission_name, previous_value, new_value } = event.change;
                const before = previous_value === null ? 'Varsayılan' : membershipAuditValues[previous_value] ?? previous_value;
                const after = new_value === null ? 'Varsayılan' : membershipAuditValues[new_value] ?? new_value;
                details.push('Klinik #' + business_id, permission_name, before + ' → ' + after);
            }
            item.append(textElement('strong', auditActions[event.action] ?? 'Klinik durumu güncellendi', 'break-words'));
            item.append(textElement('span', details.filter(Boolean).join(' · '), 'break-words text-slate-700 dark:text-slate-300'));
            return item;
        }));
        if (!result.data.length) {
            rows.append(textElement('li', 'Bu türde kayıtlı işlem yok.', 'text-sm'));
        }
        clinicPager(pages, result, (next) => { page = next; load(); });
    }, endSession);
    source.addEventListener('change', () => {
        page = 1;
        rows.replaceChildren();
        clinicPager(pages, null);
        load();
    });
}

function bindWorkspaceLogout(button, message, api, endSession) {
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            await api('/logout', {});
            endSession();
        } catch (error) {
            if (button.isConnected) {
                button.disabled = false;
                status(message, error.message);
            }
        }
    });
}

function mountAssignedSupport(root, api, account, endSession) {
    const find = (part) => root.querySelector('[data-admin-' + part + ']');
    const timeLabel = (value) => time.format(new Date(value));
    const support = mountSupportTickets(root.querySelector('#support-tickets-dialog'), find('support'), { api, timeLabel });
    const supportTarget = (data) => {
        const target = platformNotificationTarget(data);
        return target?.section === 'destek' ? target : null;
    };
    const notifications = mountWorkspaceNotifications(root.querySelector('#notifications-dialog'), find('notifications'), find('notification-count'), {
        api, timeLabel, openTarget: supportTarget, canOpenTarget: supportTarget,
        focusTarget: ({ id }) => support.openTicket(id),
    });
    find('account').textContent = account.name;
    bindWorkspaceLogout(find('logout'), find('dashboard-status'), api, endSession);
    support.start({ assignedOnly: true });
    notifications.start([]);
    const target = /^#destek\/([1-9]\d*)$/.exec(window.location.hash);
    if (target && Number.isSafeInteger(Number(target[1]))) {
        support.openTicket(Number(target[1]));
    }

    return () => {
        support.stop();
        notifications.stop();
    };
}

function mountDashboard(root, api, account, endSession) {
    const find = (part) => root.querySelector('[data-admin-' + part + ']');
    const action = (name) => root.querySelector('[data-admin-action="' + name + '"]');
    const chart = (name) => root.querySelector('[data-admin-chart="' + name + '"]');
    const formatTime = (value) => time.format(new Date(value));
    const review = mountClinicReview(root.querySelector('#clinic-review-dialog'), action('clinic-review'), { api, timeLabel: formatTime });
    const clinics = mountClinicAdministration(root.querySelector('#clinic-administration-dialog'), action('clinic-administration'), {
        api, timeLabel: formatTime, openApplication: (id) => review.open(id),
    });
    const contracts = mountClinicContracts(root.querySelector('#clinic-contracts-dialog'), action('clinic-contracts'), { api });
    const treatments = mountTreatmentAdministration(root.querySelector('#treatment-administration-dialog'), action('treatment-administration'), { api });
    const services = mountPackageServiceAdministration(root.querySelector('#package-service-dialog'), api);
    const support = mountSupportTickets(root.querySelector('#support-tickets-dialog'), action('support-tickets'), { api, timeLabel: formatTime });
    const patients = mountAdminPatients(find('patients'), action('patient-accounts'), api, support.openForAccount);
    const notifications = mountWorkspaceNotifications(root.querySelector('#notifications-dialog'), find('notifications'), find('notification-count'), {
        api, timeLabel: formatTime, openTarget: platformNotificationTarget, focusTarget: openTarget, canOpenTarget: platformNotificationTarget,
    });
    const main = root.querySelector('main');
    const refresh = find('refresh');
    const results = find('dashboard-results');
    mountDetailsReport(find('analytics'), find('analytics-link'), () => api('/admin/analytics'), renderAnalytics, endSession);
    mountDetailsReport(find('operations'), find('operations-link'), () => api('/admin/operations'), renderOperations, endSession);
    mountAudit(find('audit'), find('audit-link'), api, endSession);

    function openTarget({ section, id }) {
        if (section === 'basvuru') {
            review.open(id);
        } else {
            support.openTicket(id);
        }
    }

    function showAlerts(data) {
        const alerts = [];
        const add = (label, target, open = () => action(target).click()) => {
            const item = document.createElement('li');
            const message = textElement(target ? 'button' : 'span', label,
                target ? 'text-left text-sm font-medium underline decoration-teal-600 underline-offset-4' : 'text-sm');
            if (target) {
                message.type = 'button';
                message.addEventListener('click', open);
            }
            item.append(message);
            alerts.push(item);
        };
        if (data.summary.pending_applications > 0) {
            add(number.format(data.summary.pending_applications) + ' klinik başvurusu inceleme bekliyor', 'clinic-review');
        }
        if (data.summary.awaiting_support_reply > 0) {
            add(number.format(data.summary.awaiting_support_reply) + ' destek talebi yanıt bekliyor', 'support-tickets', support.openAwaitingReply);
        } else if (data.summary.open_tickets > 0) {
            add(number.format(data.summary.open_tickets) + ' destek talebi açık', 'support-tickets');
        }
        if (data.alerts.membership_contract_missing) {
            add('Yürürlükte zorunlu klinik üyelik sözleşmesi bulunmuyor', 'clinic-contracts');
        }
        if (data.alerts.membership_contract_expires_at) {
            add('Klinik üyelik sözleşmesi bitişi · ' + formatTime(data.alerts.membership_contract_expires_at), 'clinic-contracts');
        }
        if (data.alerts.offers_awaiting_expiry > 0) {
            add(number.format(data.alerts.offers_awaiting_expiry) + ' teklifin süresi doldu, durum kaydı güncelleme bekliyor');
        }
        if (data.alerts.failed_jobs > 0) {
            add(number.format(data.alerts.failed_jobs) + ' başarısız arka plan işi kayıtlı');
        }
        if (data.alerts.failed_jobs === null) {
            add('Başarısız arka plan işi sayısı doğrulanamadı');
        }
        if (data.alerts.active_treatments_in_inactive_categories > 0) {
            add(number.format(data.alerts.active_treatments_in_inactive_categories) + ' aktif tedavi pasif bir kategoride', 'treatment-administration');
        }
        find('alerts').replaceChildren(...(alerts.length ? alerts : [textElement('li', 'Gösterilecek açık uyarı yok.', 'text-sm')]));
    }

    async function load() {
        if (!main.isConnected || refresh.disabled) {
            return;
        }
        refresh.disabled = true;
        status(find('dashboard-status'), 'Platform sayımları yükleniyor…');
        try {
            const data = await api('/admin/dashboard');
            if (!main.isConnected) {
                return;
            }
            const values = {
                ...data.summary,
                new_today: data.summary.new_users.today, new_month: data.summary.new_users.month,
                visits_today: data.summary.appointments.today, visits_week: data.summary.appointments.week,
                visits_month: data.summary.appointments.month,
            };
            for (const field of root.querySelectorAll('[data-admin-metric]')) {
                field.textContent = number.format(values[field.dataset.adminMetric]);
            }
            showTrend(chart('clinic-trend'), data.trends.clinics);
            showTrend(chart('visit-trend'), data.trends.appointments);
            showDistribution(chart('clinic-statuses'), data.clinic_statuses);
            showDistribution(chart('appointment-statuses'), data.appointment_statuses);
            showDistribution(chart('plans'), data.plans);
            showDistribution(chart('support'), data.support);
            showAlerts(data);
            status(find('updated'), 'Son ölçüm · ' + formatTime(data.as_of) + ' (Türkiye saati)');
            status(find('dashboard-status'));
            results.hidden = false;
        } catch (error) {
            if (error.status === 403) {
                endSession(error.message);
            } else if (main.isConnected) {
                status(find('dashboard-status'), error.message);
            }
        } finally {
            refresh.disabled = false;
        }
    }

    for (const module of [review, clinics, contracts, treatments]) {
        module.start();
    }
    support.start({ administrator: true });
    notifications.start([]);
    action('shared-services').addEventListener('click', () => services.open(null));
    refresh.addEventListener('click', load);
    for (const dialog of root.querySelectorAll('dialog')) {
        dialog.addEventListener('close', load);
    }
    find('account').textContent = account.name;
    bindWorkspaceLogout(find('logout'), find('dashboard-status'), api, endSession);
    const roleForm = find('support-role');
    roleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fields = new FormData(roleForm);
        const id = Number(fields.get('user_id'));
        const submitRole = roleForm.querySelector('button[type=submit]');
        if (!Number.isSafeInteger(id) || id < 1 || submitRole.disabled) {
            return;
        }
        submitRole.disabled = true;
        try {
            const result = await api('/admin/support-agents/' + id, { active: fields.get('active') === '1' }, 'PUT');
            status(find('support-role-status'), result.active ? 'Destek görevlisi yetkisi verildi.' : 'Destek görevlisi yetkisi kaldırıldı.');
            roleForm.reset();
        } catch (error) {
            status(find('support-role-status'), error.message);
        } finally {
            submitRole.disabled = false;
        }
    });
    main.focus();
    load();
    const target = /^#(basvuru|destek)\/([1-9]\d*)$/.exec(window.location.hash);
    if (target && Number.isSafeInteger(Number(target[2]))) {
        openTarget({ section: target[1], id: Number(target[2]) });
    }

    return () => {
        for (const module of [review, clinics, contracts, treatments, patients, support, notifications]) {
            module.stop();
        }
        services.close();
    };
}

export function mountPlatformAdmin(root) {
    const signIn = root.querySelector('[data-admin-sign-in]');
    const form = root.querySelector('[data-admin-login]');
    const loginStatus = root.querySelector('[data-admin-login-status]');
    const workspace = root.querySelector('[data-admin-workspace]');
    const newPassword = root.querySelector('[data-admin-new-password]');
    const submit = form.querySelector('button[type=submit]');
    let token;
    let stop;

    function endSession(message = '') {
        token = undefined;
        stop?.();
        stop = undefined;
        workspace.replaceChildren();
        workspace.hidden = true;
        signIn.hidden = false;
        form.reset();
        newPassword.disabled = true;
        newPassword.hidden = true;
        form.elements.email.focus();
        status(loginStatus, message);
    }

    const api = createWorkspaceApi(() => token, () => endSession('Oturum sona erdi. Yeniden giriş yapın.'));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) {
            return;
        }
        submit.disabled = true;
        status(loginStatus, 'Giriş yapılıyor…');
        let sessionToken;
        try {
            const fields = Object.fromEntries(new FormData(form));
            fields.email = fields.email.trim().toLowerCase();
            const result = await api('/admin/login', fields);
            if (result.password_change_required) {
                newPassword.hidden = false;
                newPassword.disabled = false;
                newPassword.querySelector('input').focus();
                status(loginStatus);
                return;
            }
            sessionToken = result.token;
            token = sessionToken;
            const html = await api('/admin/workspace', undefined, 'GET', true);
            const markup = await html.text();
            if (token !== sessionToken) {
                return;
            }
            workspace.innerHTML = markup;
            workspace.hidden = false;
            signIn.hidden = true;
            form.reset();
            const mount = workspace.querySelector('[data-platform-support-workspace]') ? mountAssignedSupport : mountDashboard;
            stop = mount(workspace, api, result.user, endSession);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            if (sessionToken && token === sessionToken) {
                await api('/logout', {}).catch(() => {});
            }
            if (!token || token === sessionToken) {
                endSession(error.message);
            }
        } finally {
            submit.disabled = false;
        }
    });
}
