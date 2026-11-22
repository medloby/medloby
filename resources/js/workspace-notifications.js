const clinicNotifications = new Set([
    'patient_request',
    'new_message',
    'new_appointment',
    'offer_accepted',
    'offer_rejected',
]);

export function mountWorkspaceNotifications(dialog, button, badge, { api, openTarget, timeLabel, focusTarget }) {
    const find = (selector) => dialog.querySelector(selector);
    const previous = find('[data-notifications-previous]');
    const next = find('[data-notifications-next]');
    const status = find('[data-notifications-status]');
    const list = find('[data-notifications-list]');
    const readAll = find('[data-notifications-read-all]');
    const template = find('[data-notification-row]');
    const preferences = find('[data-notifications-preferences]');
    const business = find('[data-notifications-business]');
    const preferenceList = find('[data-notifications-preference-list]');
    const preferenceStatus = find('[data-notifications-preference-status]');
    let timer;
    let currentPage = 1;
    let lastPage = 1;
    let listRequest = 0;
    let badgeRequest = 0;
    let preferenceRequest = 0;

    function showStatus(message) {
        status.textContent = message;
        status.hidden = !message;
    }

    function setBadge(count) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.hidden = count === 0;
        button.setAttribute('aria-label', count ? 'Bildirimler, ' + count + ' okunmamış' : 'Bildirimler');
        readAll.disabled = count === 0;
    }

    async function refreshBadge() {
        if (!timer || document.hidden) {
            return;
        }

        const pending = ++badgeRequest;
        try {
            const { count } = await api('/notifications/unread-count');
            if (pending === badgeRequest) {
                setBadge(count);
            }
        } catch {
            // The notification panel remains available when a background refresh fails.
        }
    }

    async function markRead(notification, row) {
        if (!notification.read_at) {
            const updated = await api('/notifications/' + encodeURIComponent(notification.id) + '/read', {});
            notification.read_at = updated.read_at;
            row.dataset.unread = 'false';
            row.querySelector('[data-notification-read]').hidden = true;
            await refreshBadge();
        }
    }

    function render(notification) {
        const row = template.content.firstElementChild.cloneNode(true);
        const data = notification.data ?? {};
        row.dataset.unread = String(!notification.read_at);
        row.querySelector('[data-notification-title]').textContent = data.title ?? 'Bildirim';
        row.querySelector('[data-notification-message]').textContent = data.message ?? '';
        const time = row.querySelector('[data-notification-time]');
        time.dateTime = notification.created_at;
        time.textContent = timeLabel(notification.created_at);

        const open = row.querySelector('[data-notification-open]');
        const read = row.querySelector('[data-notification-read]');
        open.hidden = !data.conversation_id && !data.appointment_id && !data.clinic_application_id;
        read.hidden = Boolean(notification.read_at);

        async function act(task) {
            open.disabled = true;
            read.disabled = true;
            showStatus('');
            try {
                await task();
            } catch (error) {
                showStatus(error.message);
            } finally {
                open.disabled = false;
                read.disabled = false;
            }
        }

        read.addEventListener('click', () => act(() => markRead(notification, row)));
        open.addEventListener('click', () => act(async () => {
            await markRead(notification, row);
            const target = await openTarget(data);
            if (target) {
                dialog.close();
                focusTarget(target);
            } else {
                showStatus('Bu bildirim genel randevuya ait.');
            }
        }));

        return row;
    }

    async function load(page) {
        const pending = ++listRequest;
        previous.disabled = true;
        next.disabled = true;
        showStatus('Bildirimler yükleniyor…');
        try {
            const response = await api('/notifications?page=' + page);
            if (pending !== listRequest || !dialog.open) {
                return;
            }

            currentPage = response.current_page;
            lastPage = response.last_page;
            list.replaceChildren(...response.data.map(render));
            find('[data-notifications-pages]').hidden = lastPage <= 1;
            find('[data-notifications-page]').textContent = currentPage + ' / ' + lastPage;
            showStatus(response.data.length ? '' : 'Henüz bildirim yok.');
        } catch (error) {
            if (pending === listRequest) {
                showStatus(error.message);
            }
        } finally {
            if (pending === listRequest) {
                previous.disabled = currentPage <= 1;
                next.disabled = currentPage >= lastPage;
            }
        }
    }

    function showPreferenceStatus(message) {
        preferenceStatus.textContent = message;
        preferenceStatus.hidden = !message;
    }

    async function loadPreferences() {
        const pending = ++preferenceRequest;
        const endpoint = '/businesses/' + business.value + '/notification-preferences';
        preferenceList.replaceChildren();
        showPreferenceStatus('Bildirim tercihleri yükleniyor…');

        try {
            const settings = await api(endpoint);
            if (pending !== preferenceRequest) {
                return;
            }

            preferenceList.replaceChildren(...settings.filter((setting) => clinicNotifications.has(setting.notification_type))
                .map((setting) => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center gap-2 text-sm';
                    const enabled = document.createElement('input');
                    enabled.type = 'checkbox';
                    enabled.className = 'size-4 accent-teal-800';
                    enabled.checked = setting.in_app_enabled;
                    label.append(enabled, setting.label);

                    enabled.addEventListener('change', async () => {
                        enabled.disabled = true;
                        showPreferenceStatus('');
                        try {
                            const updated = await api(endpoint + '/' + setting.notification_type, {
                                in_app_enabled: enabled.checked,
                                email_enabled: setting.email_enabled,
                            }, 'PUT');
                            setting.in_app_enabled = updated.in_app_enabled;
                            setting.email_enabled = updated.email_enabled;
                            if (pending === preferenceRequest) {
                                showPreferenceStatus('Bildirim tercihi kaydedildi.');
                            }
                        } catch (error) {
                            enabled.checked = setting.in_app_enabled;
                            if (pending === preferenceRequest) {
                                showPreferenceStatus(error.message);
                            }
                        } finally {
                            enabled.disabled = false;
                        }
                    });

                    return label;
                }));
            showPreferenceStatus('');
        } catch (error) {
            if (pending === preferenceRequest) {
                showPreferenceStatus(error.message);
            }
        }
    }

    preferences.addEventListener('toggle', () => {
        if (preferences.open && business.value) {
            loadPreferences();
        }
    });
    business.addEventListener('change', loadPreferences);

    button.addEventListener('click', () => {
        list.replaceChildren();
        dialog.showModal();
        load(1);
        refreshBadge();
    });
    previous.addEventListener('click', () => load(currentPage - 1));
    next.addEventListener('click', () => load(currentPage + 1));
    find('[data-notifications-refresh]').addEventListener('click', () => {
        refreshBadge();
        load(1);
    });
    find('[data-notifications-close]').addEventListener('click', () => dialog.close());
    readAll.addEventListener('click', async () => {
        readAll.disabled = true;
        try {
            await api('/notifications/read-all', {});
            await load(1);
        } catch (error) {
            showStatus(error.message);
        } finally {
            await refreshBadge();
        }
    });

    document.addEventListener('visibilitychange', refreshBadge);

    return {
        start(businesses) {
            preferences.hidden = !businesses.length;
            business.replaceChildren(...businesses.map((item) => new Option(item.name, item.id)));
            if (!timer) {
                timer = setInterval(refreshBadge, 30000);
            }
            button.hidden = false;
            setBadge(0);
            refreshBadge();
        },
        stop() {
            clearInterval(timer);
            timer = undefined;
            listRequest++;
            badgeRequest++;
            preferenceRequest++;
            preferences.open = false;
            preferences.hidden = true;
            business.replaceChildren();
            preferenceList.replaceChildren();
            showPreferenceStatus('');
            button.hidden = true;
            dialog.close();
            list.replaceChildren();
            showStatus('');
            setBadge(0);
        },
    };
}
