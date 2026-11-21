const statusLabels = {
    pending: 'İncelemede',
    approved: 'Onaylandı',
    rejected: 'Reddedildi',
};

export function mountClinicApplications(section, { api, timeLabel }) {
    const find = (part, root = section) => root.querySelector('[data-application-' + part + ']');
    const refresh = find('refresh');
    const list = find('list');
    const message = find('message');
    let visible = false;
    let request = 0;

    function updateVisibility() {
        section.hidden = !visible || (!list.childElementCount && message.hidden);
    }

    function showStatus(text = '') {
        message.textContent = text;
        message.hidden = !text;
        updateVisibility();
    }

    function applicationRow(application) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        find('name', row).textContent = application.name;

        const status = find('status', row);
        status.dataset.state = application.status;
        status.textContent = statusLabels[application.status];

        if (application.reviewed_at) {
            const date = find('date', row);
            date.dateTime = application.reviewed_at;
            date.textContent = timeLabel(application.reviewed_at);
            date.hidden = false;
        }
        if (application.applicant_rejection_reason) {
            find('reason', row).textContent = application.applicant_rejection_reason;
            find('explanation', row).hidden = false;
        }

        return row;
    }

    async function load(announce = false) {
        const pending = ++request;
        refresh.disabled = true;
        showStatus('Başvuru durumu kontrol ediliyor…');

        try {
            const applications = await api('/clinic-applications');
            if (pending === request) {
                list.replaceChildren(...applications.map(applicationRow));
                showStatus(announce && applications.length ? 'Başvuru durumu güncellendi.' : '');
            }
        } catch (error) {
            if (pending === request) {
                if (error.status === 403) {
                    list.replaceChildren();
                }
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                refresh.disabled = false;
            }
        }
    }

    refresh.addEventListener('click', () => load(true));

    return {
        setVisible(value) {
            visible = value;
            updateVisibility();
        },
        setAccount(businesses, verified) {
            request++;
            refresh.hidden = !verified;
            const known = businesses.filter((item) => item.status === 'pending' || item.status === 'rejected');
            list.replaceChildren(...known.map(applicationRow));
            showStatus();
            if (verified && businesses.length) {
                load();
            }
        },
        stop() {
            request++;
            refresh.hidden = true;
            refresh.disabled = false;
            list.replaceChildren();
            showStatus();
        },
    };
}
