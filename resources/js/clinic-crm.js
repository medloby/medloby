const metricLabels = {
    total_leads: 'Toplam hasta adayı',
    new_leads: 'Yeni hasta adayı',
    open_tasks: 'Açık görev',
    pending_follow_ups: 'Bekleyen takip',
};

export function mountClinicCrm(section, api) {
    const find = (part) => section.querySelector('[data-crm-' + part + ']');
    const title = section.querySelector('#clinic-crm-name');
    const branchField = find('branch-field');
    const branch = find('branch');
    const refresh = find('refresh');
    const status = find('status');
    const summary = find('summary');
    const metrics = find('metrics');
    const pipeline = find('pipeline');
    let businessId = null;
    let request = 0;

    function showStatus(text = '') {
        status.textContent = text;
        status.hidden = !text;
    }

    function clearSummary() {
        summary.hidden = true;
        metrics.replaceChildren();
        pipeline.replaceChildren();
    }

    function setLoading(loading) {
        branch.disabled = loading;
        refresh.disabled = loading;
    }

    async function loadDashboard(branchId, pending) {
        clearSummary();
        showStatus('CRM özeti yükleniyor…');
        const dashboard = await api('/branches/' + branchId + '/crm/dashboard');
        if (pending !== request) {
            return;
        }

        metrics.replaceChildren(...Object.entries(metricLabels).map(([key, label]) => {
            const metric = find('metric').content.firstElementChild.cloneNode(true);
            metric.querySelector('dt').textContent = label;
            metric.querySelector('dd').textContent = dashboard.summary[key];
            return metric;
        }));

        const stages = dashboard.pipeline.filter((stage) => stage.is_active);
        pipeline.replaceChildren(...stages.map((stage) => {
            const row = find('stage').content.firstElementChild.cloneNode(true);
            row.querySelector('span').textContent = stage.name;
            row.querySelector('strong').textContent = stage.lead_count;
            return row;
        }));
        find('empty-pipeline').hidden = stages.length > 0;
        summary.hidden = false;
        showStatus();
    }

    async function loadBusiness(preferredBranch = '') {
        const pending = ++request;
        clearSummary();
        title.textContent = '';
        branchField.hidden = true;
        branch.replaceChildren();
        setLoading(true);
        showStatus('Klinik CRM alanı yükleniyor…');

        try {
            const clinic = await api('/businesses/' + businessId + '/crm');
            if (pending !== request) {
                return;
            }

            title.textContent = clinic.name;
            const branches = clinic.branches;
            if (!branches.length) {
                showStatus('Bu klinikte aktif şube bulunmuyor.');
                return;
            }

            branch.replaceChildren(...branches.map((item) => new Option(item.name, item.id)));
            branch.value = branches.some((item) => String(item.id) === preferredBranch)
                ? preferredBranch
                : String(branches[0].id);
            branchField.hidden = false;
            await loadDashboard(branch.value, pending);
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                setLoading(false);
            }
        }
    }

    refresh.addEventListener('click', () => loadBusiness(branch.value));
    branch.addEventListener('change', async () => {
        const pending = ++request;
        setLoading(true);
        try {
            await loadDashboard(branch.value, pending);
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                setLoading(false);
            }
        }
    });

    return {
        setBusiness(id) {
            if (businessId === id) {
                return;
            }
            businessId = id;
            section.hidden = !id;

            if (id) {
                loadBusiness();
            } else {
                request++;
                clearSummary();
                title.textContent = '';
                branch.replaceChildren();
                branchField.hidden = true;
                showStatus();
            }
        },
    };
}
