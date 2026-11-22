const endpoint = '/admin/businesses';
const statusLabels = {
    pending: 'İncelemede',
    active: 'Aktif',
    suspended: 'Askıda',
    rejected: 'Reddedildi',
};
const roleLabels = { business_owner: 'Klinik sahibi', manager: 'Yönetici', staff: 'Personel' };

function pagedList(panel, fetchPage, createRow, emptyMessage) {
    const find = (part) => panel.querySelector('[data-page-' + part + ']');
    const rows = find('rows');
    const status = find('status');
    const navigation = find('navigation');
    const previous = find('prev');
    const next = find('next');
    let request = 0;
    let page = 1;

    function reset() {
        request++;
        rows.replaceChildren();
        navigation.hidden = true;
        status.hidden = true;
    }

    async function load(requestedPage = page) {
        reset();
        const pending = request;
        status.textContent = 'Yükleniyor…';
        status.hidden = false;
        try {
            const result = await fetchPage(requestedPage);
            if (pending !== request) {
                return;
            }
            if (result.current_page > result.last_page) {
                await load(result.last_page);
                return;
            }

            page = result.current_page;
            rows.replaceChildren(...result.data.map(createRow));
            status.textContent = result.data.length ? '' : emptyMessage;
            status.hidden = result.data.length > 0;
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('number').textContent = page + ' / ' + result.last_page;
            navigation.hidden = result.last_page < 2;
        } catch (error) {
            if (pending === request) {
                status.textContent = error.message;
            }
        }
    }

    previous.addEventListener('click', () => load(page - 1));
    next.addEventListener('click', () => load(page + 1));
    return { load, reset };
}

export function mountClinicAdministration(dialog, trigger, { api, timeLabel, openApplication }) {
    const find = (part) => dialog.querySelector('[data-admin-clinic-' + part + ']');
    const directoryView = find('directory');
    const clinicView = find('detail');
    const memberView = find('member');
    const filters = find('filters');
    const transition = find('transition');
    const history = find('history');
    const memberForm = find('member-role');
    const memberState = find('member-state');
    const permissionList = find('permissions');
    const status = find('status');
    const close = find('close');
    let query = new URLSearchParams();
    let selectedClinic;
    let selectedMember;
    let request = 0;
    let saving = false;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function showView(view) {
        directoryView.hidden = view !== directoryView;
        clinicView.hidden = view !== clinicView;
        memberView.hidden = view !== memberView;
    }

    function row(template) {
        return find(template).content.firstElementChild.cloneNode(true);
    }

    function selectableRow(title, context, select) {
        const entry = row('selectable-row');
        entry.querySelector('[data-row-title]').textContent = title;
        entry.querySelector('[data-row-summary]').textContent = context;
        entry.querySelector('button').addEventListener('click', select);
        return entry;
    }

    function clinicContext(clinic) {
        const plan = clinic.membership_plan ? 'Plan ' + clinic.membership_plan + ' (Ücretsiz)' : 'Plan seçilmedi';
        return [statusLabels[clinic.status] ?? clinic.status, clinic.city, clinic.district, plan].filter(Boolean).join(' · ');
    }

    const clinics = pagedList(find('directory-list'), (page) => {
        query.set('page', page);
        return api(endpoint + '/directory?' + query);
    }, (clinic) => selectableRow(clinic.name, clinicContext(clinic), () => openClinic(clinic)), 'Klinik bulunamadı.');

    const members = pagedList(find('members'), (page) => api(endpoint + '/' + selectedClinic.id + '/members?page=' + page), (member) => {
        const context = [roleLabels[member.role] ?? member.role, member.is_active ? 'Aktif' : 'Pasif', member.user.login_code ?? member.user.email]
            .filter(Boolean).join(' · ');
        return selectableRow(member.user.name, context, () => openMember(member.id));
    }, 'Bu kliniğe bağlı üye bulunamadı.');

    const historyList = pagedList(find('history-list'), (page) => api(endpoint + '/' + selectedClinic.id + '/status-history?page=' + page), (event) => {
        const entry = row('history-row');
        const previous = statusLabels[event.previous_status] ?? event.previous_status;
        const next = statusLabels[event.new_status] ?? event.new_status;
        entry.querySelector('[data-row-title]').textContent = previous + ' → ' + next;
        entry.querySelector('[data-row-summary]').textContent = [event.changed_at && timeLabel(event.changed_at), event.changed_by?.name]
            .filter(Boolean).join(' · ');
        entry.querySelector('[data-row-reason]').textContent = event.reason ?? '';
        return entry;
    }, 'Bu klinik için durum değişikliği bulunamadı.');

    function openDirectory(firstPage = false) {
        request++;
        selectedClinic = undefined;
        selectedMember = undefined;
        history.open = false;
        members.reset();
        historyList.reset();
        showView(directoryView);
        showStatus();
        clinics.load(firstPage ? 1 : undefined);
        filters.elements.namedItem('search').focus();
    }

    function renderClinic() {
        find('name').textContent = selectedClinic.name;
        find('context').textContent = clinicContext(selectedClinic);
        find('review').hidden = selectedClinic.status !== 'pending';
        transition.reset();
        transition.hidden = !['active', 'suspended'].includes(selectedClinic.status);
        transition.querySelector('button').textContent = selectedClinic.status === 'active' ? 'Kliniği askıya al' : 'Kliniği yeniden aç';
    }

    function openClinic(clinic, keepMemberPage = false) {
        if (saving) {
            return;
        }
        request++;
        selectedClinic = clinic;
        selectedMember = undefined;
        clinics.reset();
        history.open = false;
        historyList.reset();
        renderClinic();
        showView(clinicView);
        showStatus();
        find('name').focus();
        members.load(keepMemberPage ? undefined : 1);
    }

    async function mutate(action, message, refresh) {
        if (saving) {
            return;
        }

        const active = request;
        const fields = (clinicView.hidden ? memberView : clinicView).querySelectorAll('fieldset');
        saving = true;
        close.disabled = true;
        fields.forEach((fieldset) => { fieldset.disabled = true; });
        showStatus('Kaydediliyor…');
        try {
            await action();
        } catch (error) {
            if (active === request) {
                showStatus(error.message);
            }
            return;
        } finally {
            fields.forEach((fieldset) => { fieldset.disabled = false; });
            if (active === request) {
                close.disabled = false;
                saving = false;
            }
        }

        if (active === request) {
            await refresh(message);
        }
    }

    function permissionRow(permission) {
        const entry = row('permission-row');
        const form = entry.querySelector('form');
        const setting = form.elements.namedItem('setting');
        const original = permission.is_allowed === null ? 'default' : (permission.is_allowed ? 'allow' : 'deny');
        entry.querySelector('[data-permission-name]').textContent = permission.display_name + (permission.is_active ? '' : ' · Pasif');
        setting.value = original;
        if (!permission.is_active) {
            setting.querySelector('[value="allow"]').disabled = true;
            setting.querySelector('[value="deny"]').disabled = true;
        }
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (setting.value === original) {
                return;
            }

            const path = endpoint + '/' + selectedClinic.id + '/members/' + selectedMember.id + '/permissions/' + permission.id;
            const method = setting.value === 'default' ? 'DELETE' : 'PUT';
            const payload = method === 'PUT' ? { is_allowed: setting.value === 'allow' } : undefined;
            mutate(() => api(path, payload, method), 'İzin güncellendi.', (message) => openMember(selectedMember.id, message));
        });
        return entry;
    }

    function renderMember(member) {
        selectedMember = member;
        find('member-name').textContent = member.user.name;
        find('member-account').textContent = [member.user.email, member.user.login_code, member.is_active ? 'Aktif' : 'Pasif']
            .filter(Boolean).join(' · ');
        const branches = member.branches.filter((branch) => branch.status === 'active' && branch.pivot.is_active).map((branch) => branch.name);
        let branchAccess = member.role === 'business_owner' ? 'Tüm aktif şubelere erişim' : (branches.join(' · ') || 'Aktif şube erişimi yok.');
        if (!member.is_active) {
            branchAccess = 'Üyelik pasif olduğu için şube erişimi kapalı.';
        } else if (member.business.status !== 'active') {
            branchAccess = 'Şube erişimi klinik aktif olduğunda açılır.';
        }
        find('member-branches').textContent = branchAccess;
        const roles = memberForm.elements.namedItem('role');
        roles.querySelector('[value="business_owner"]').disabled = Boolean(member.user.staff_business_id);
        roles.value = member.role;
        memberState.textContent = member.is_active ? 'Üyeliği pasifleştir' : 'Üyeliği aktifleştir';
        permissionList.replaceChildren(...member.permissions.map(permissionRow));
        find('no-permissions').hidden = member.permissions.length > 0;
        find('member-content').hidden = false;
        find('member-name').focus();
    }

    async function openMember(id, confirmation = '') {
        if (saving) {
            return;
        }
        const pending = ++request;
        selectedMember = undefined;
        members.reset();
        find('member-content').hidden = true;
        showView(memberView);
        find('to-members').focus();
        showStatus('Üyelik yükleniyor…');

        try {
            const member = await api(endpoint + '/' + selectedClinic.id + '/members/' + id);
            if (pending === request) {
                renderMember(member);
                showStatus(confirmation);
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    function updateMember(segment, payload, message) {
        const memberId = selectedMember.id;
        const url = endpoint + '/' + selectedClinic.id + '/members/' + memberId + '/' + segment;
        mutate(() => api(url, payload, 'PUT'), message, (confirmation) => openMember(memberId, confirmation));
    }

    function closeDialog() {
        request++;
        selectedClinic = undefined;
        selectedMember = undefined;
        saving = false;
        close.disabled = false;
        clinics.reset();
        members.reset();
        historyList.reset();
        dialog.close();
    }

    trigger.addEventListener('click', () => {
        filters.reset();
        query = new URLSearchParams();
        dialog.showModal();
        openDirectory(true);
    });
    filters.addEventListener('submit', (event) => {
        event.preventDefault();
        const values = new FormData(filters);
        query = new URLSearchParams([...values].filter(([, value]) => value.trim()));
        clinics.load(1);
    });
    find('to-list').addEventListener('click', () => {
        if (!saving) {
            openDirectory();
        }
    });
    find('to-members').addEventListener('click', () => openClinic(selectedClinic, true));
    find('review').addEventListener('click', () => {
        const id = selectedClinic.id;
        closeDialog();
        openApplication(id);
    });
    history.addEventListener('toggle', () => {
        if (history.open && selectedClinic && !clinicView.hidden) {
            historyList.load(1);
        }
    });
    transition.addEventListener('submit', (event) => {
        event.preventDefault();
        if (saving) {
            return;
        }
        const suspending = selectedClinic.status === 'active';
        const question = suspending ? 'Klinik askıya alınsın mı? Kliniğin CRM erişimi ve yeni hasta işlemleri duracak.' : 'Klinik yeniden açılsın mı?';
        if (!window.confirm(question)) {
            return;
        }
        const reason = transition.elements.namedItem('reason').value.trim();
        const action = suspending ? 'suspend' : 'reactivate';
        mutate(async () => {
            const updated = await api(endpoint + '/' + selectedClinic.id + '/' + action, { reason });
            selectedClinic = { ...selectedClinic, ...updated };
        }, suspending ? 'Klinik askıya alındı.' : 'Klinik yeniden açıldı.', (message) => {
            renderClinic();
            showStatus(message);
            if (history.open) {
                historyList.load(1);
            }
        });
    });
    memberForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const role = memberForm.elements.namedItem('role').value;
        if (role !== selectedMember.role && window.confirm('Üye rolü değiştirilsin mi? Yeni rol ' + roleLabels[role] + '.')) {
            updateMember('role', { role }, 'Üye rolü güncellendi.');
        }
    });
    memberState.addEventListener('click', () => {
        if (saving) {
            return;
        }
        const active = !selectedMember.is_active;
        if (window.confirm(active ? 'Üyelik aktifleştirilsin mi?' : 'Üyelik pasifleştirilsin mi? Üyenin bu kliniğe erişimi kapanacak.')) {
            updateMember('status', { is_active: active }, active ? 'Üyelik aktifleştirildi.' : 'Üyelik pasifleştirildi.');
        }
    });
    close.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!saving) {
            closeDialog();
        }
    });

    return {
        start() {
            trigger.hidden = false;
        },
        stop() {
            trigger.hidden = true;
            closeDialog();
        },
    };
}
