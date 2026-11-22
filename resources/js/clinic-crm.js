import { mountCrmLeads } from './crm-leads';
import { mountClinicOperations } from './clinic-operations';
import { mountClinicCalendar } from './clinic-calendar';
import { mountClinicProfile } from './clinic-profile';
import { mountClinicTreatmentPrices } from './clinic-treatment-prices';
import { mountClinicBranchManagement } from './clinic-branch-management';
import { mountClinicSocialLinks } from './clinic-social-links';

const metricLabels = {
    total_leads: 'Toplam hasta adayı',
    new_leads: 'Yeni hasta adayı',
    converted_leads: 'Başarılı aday',
    lost_leads: 'Sonuçsuz aday',
    open_tasks: 'Açık görev',
    pending_follow_ups: 'Bekleyen takip',
};

export function mountClinicCrm(section, api, appointmentDialogs) {
    const find = (part) => section.querySelector('[data-crm-' + part + ']');
    const title = section.querySelector('#clinic-crm-name');
    const plan = find('plan');
    const branchField = find('branch-field');
    const branch = find('branch');
    const refresh = find('refresh');
    const status = find('status');
    const summary = find('summary');
    const metrics = find('metrics');
    const pipeline = find('pipeline');
    const stageEditor = find('stage-editor');
    const stageForm = find('stage-form');
    const stageFields = stageForm.querySelector('fieldset');
    const stageStatus = find('stage-status');
    const operations = mountClinicOperations(find('operations'), api);
    const branchManagement = mountClinicBranchManagement(find('branch-management'), api, (saved) => {
        businessLoad = loadBusiness(saved.status === 'active' ? String(saved.id) : branch.value);
    });
    const treatmentPrices = mountClinicTreatmentPrices(find('prices'), api);
    const socialLinks = mountClinicSocialLinks(find('social-links'), api);
    const calendar = mountClinicCalendar(find('calendar'), api, appointmentDialogs);
    const leads = mountCrmLeads(find('leads'), {
        api, onChanged: refreshDashboard,
        openAppointment: calendar.openAppointment, bookForPatient: calendar.bookForPatient,
    });
    const profile = mountClinicProfile(find('profile'), api, (saved) => {
        title.textContent = saved.clinic.name;
        branch.selectedOptions[0].textContent = saved.branch.name;
    });
    let businessId = null;
    let businessLoad;
    let canManageOperations = false;
    let request = 0;
    let stageRequest = 0;

    function showStatus(text = '') {
        status.textContent = text;
        status.hidden = !text;
    }

    function clearSummary() {
        summary.hidden = true;
        metrics.replaceChildren();
        pipeline.replaceChildren();
    }

    function showStageStatus(text = '') {
        stageStatus.textContent = text;
        stageStatus.hidden = !text;
    }

    function resetStageEditor() {
        stageRequest++;
        stageEditor.open = false;
        stageForm.reset();
        stageFields.disabled = false;
        showStageStatus();
    }

    function setLoading(loading) {
        branch.disabled = loading;
        refresh.disabled = loading;
    }

    function setBranchPanels(id) {
        leads.setBranch(id);
        operations.setBranch(canManageOperations ? id : null);
        treatmentPrices.setBranch(id, businessId);
        socialLinks.setBranch(null);
        calendar.setBranch(id, canManageOperations);
        profile.setBranch(canManageOperations ? businessId : null, id);
    }

    async function loadDashboard(branchId, pending) {
        clearSummary();
        showStatus('CRM özeti yükleniyor…');
        const dashboard = await api('/branches/' + branchId + '/crm/dashboard');
        if (pending !== request) {
            return;
        }

        const catalogBranch = dashboard.can_manage_branch_catalog ? branchId : null;
        operations.setBranch(catalogBranch);
        socialLinks.setBranch(catalogBranch);
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
        resetStageEditor();
        setBranchPanels(null);
        clearSummary();
        title.textContent = '';
        plan.hidden = true;
        branchField.hidden = true;
        branch.replaceChildren();
        setLoading(true);
        showStatus('Klinik CRM alanı yükleniyor…');
        let workspaceLoaded = false;

        try {
            const clinic = await api('/businesses/' + businessId + '/crm');
            if (pending !== request) {
                return;
            }

            workspaceLoaded = true;
            title.textContent = clinic.name;
            canManageOperations = clinic.can_manage_operations;
            plan.textContent = clinic.membership_plan ? 'Üyelik planı · ' + clinic.membership_plan + ' · Ücretsiz' : 'Üyelik planı seçilmedi.';
            plan.hidden = !canManageOperations;
            branchManagement.setBusiness(canManageOperations ? businessId : null);
            find('return').textContent = canManageOperations ? 'Başvurulara dön' : 'Çalışma alanına dön';
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
            setBranchPanels(branch.value);
            await loadDashboard(branch.value, pending);
        } catch (error) {
            if (pending === request) {
                if (!workspaceLoaded && [401, 403, 404].includes(error.status)) {
                    branchManagement.setBusiness(null);
                }
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                setLoading(false);
            }
        }
    }

    async function refreshDashboard() {
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
    }

    refresh.addEventListener('click', async () => {
        businessLoad = loadBusiness(branch.value);
        await businessLoad;
        branchManagement.refresh();
    });
    branch.addEventListener('change', () => {
        resetStageEditor();
        setBranchPanels(branch.value);
        refreshDashboard();
    });

    stageForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!branch.value || stageFields.disabled) {
            return;
        }

        const name = stageForm.elements.namedItem('name').value.trim();
        if (!name) {
            showStageStatus('Aşama adını girin.');
            return;
        }

        const pending = ++stageRequest;
        stageFields.disabled = true;
        showStageStatus('Aşama kaydediliyor…');
        try {
            await api('/branches/' + branch.value + '/crm/pipeline-stages', { name });
        } catch (error) {
            if (pending === stageRequest) {
                stageFields.disabled = false;
                showStageStatus(error.message);
            }
            return;
        }

        if (pending === stageRequest) {
            resetStageEditor();
            refreshDashboard();
        }
    });

    return {
        async openFollowUp(followUp) {
            await businessLoad;
            const targetBranch = String(followUp.branch_id);
            if (String(followUp.business_id) !== businessId || !Array.from(branch.options).some((option) => option.value === targetBranch)) {
                return;
            }
            if (branch.value !== targetBranch) {
                branch.value = targetBranch;
                branch.dispatchEvent(new Event('change'));
            }

            await leads.openFollowUp(followUp.crm_lead_id, followUp.id);
        },
        setBusiness(id) {
            if (businessId === id) {
                return;
            }
            businessId = id;
            section.hidden = !id;
            branchManagement.setBusiness(null);

            if (id) {
                businessLoad = loadBusiness();
            } else {
                request++;
                resetStageEditor();
                setBranchPanels(null);
                clearSummary();
                title.textContent = '';
                branch.replaceChildren();
                branchField.hidden = true;
                showStatus();
            }
        },
    };
}
