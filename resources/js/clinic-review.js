export function mountClinicReview(dialog, trigger, { api, timeLabel }) {
    const find = (part, root = dialog) => root.querySelector('[data-clinic-' + part + ']');
    const overview = find('overview');
    const detail = find('detail');
    const information = find('information');
    const status = find('status');
    const list = find('list');
    const pages = find('pages');
    const refresh = find('refresh');
    const previous = find('previous');
    const next = find('next');
    const back = find('back');
    const title = dialog.querySelector('#clinic-application-name');
    const decision = find('decision');
    const actions = decision.querySelector('fieldset');
    const approve = find('approve');
    const rejection = find('rejection');
    const acceptances = find('acceptances');
    let page = 1;
    let request = 0;
    let selected;
    let saving = false;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function clearDetails() {
        selected = undefined;
        information.hidden = true;
        decision.hidden = true;
        decision.reset();
        rejection.open = false;
        title.textContent = '';
        acceptances.replaceChildren();
        for (const part of ['date', 'owners', 'contact', 'address', 'branches', 'description']) {
            find(part).textContent = '';
        }
    }

    function close() {
        request++;
        saving = false;
        page = 1;
        actions.disabled = false;
        back.disabled = false;
        list.replaceChildren();
        clearDetails();
        showStatus();
        dialog.close();
    }

    function clinicRow(business) {
        const row = find('row').content.firstElementChild.cloneNode(true);
        find('row-name', row).textContent = business.name;
        find('row-context', row).textContent = [business.city, business.district, business.created_at && timeLabel(business.created_at)]
            .filter(Boolean).join(' · ');
        row.querySelector('button').addEventListener('click', () => loadBusiness(business.id));
        return row;
    }

    function contractAcceptance(acceptance) {
        const card = find('acceptance').content.firstElementChild.cloneNode(true);
        const contract = acceptance.platform_contract;
        find('contract-title', card).textContent = (contract?.title ?? 'Klinik üyelik sözleşmesi')
            + ' · Sürüm ' + acceptance.contract_version;
        find('accepted-date', card).textContent = acceptance.accepted_at
            ? 'Kabul tarihi · ' + timeLabel(acceptance.accepted_at)
            : 'Kabul tarihi kayıtlı değil.';
        find('contract-content', card).textContent = contract?.content ?? 'Sözleşme metni bulunamadı.';
        return card;
    }

    async function loadList(requestedPage, confirmation = '') {
        const pending = ++request;
        const returning = !detail.hidden;
        detail.hidden = true;
        overview.hidden = false;
        clearDetails();
        list.replaceChildren();
        pages.hidden = true;
        if (returning) {
            refresh.focus();
        }
        showStatus('Başvurular yükleniyor…');

        try {
            const result = await api('/admin/businesses?page=' + requestedPage);
            if (pending !== request) {
                return;
            }
            if (!result.data.length && result.current_page > result.last_page) {
                await loadList(result.last_page, confirmation);
                return;
            }
            page = result.current_page;
            list.replaceChildren(...result.data.map(clinicRow));
            previous.disabled = page <= 1;
            next.disabled = page >= result.last_page;
            find('page').textContent = page + ' / ' + result.last_page;
            pages.hidden = result.last_page < 2;
            showStatus(confirmation || (!result.data.length ? 'Bekleyen klinik başvurusu yok.' : ''));
        } catch (error) {
            if (pending === request) {
                showStatus(confirmation ? confirmation + ' ' + error.message : error.message);
            }
        }
    }

    async function loadBusiness(id) {
        const pending = ++request;
        overview.hidden = true;
        detail.hidden = false;
        back.focus();
        showStatus('Başvuru bilgileri yükleniyor…');

        try {
            const business = await api('/admin/businesses/' + id);
            if (pending !== request) {
                return;
            }

            selected = business;
            title.textContent = business.name;
            find('date').textContent = business.created_at ? 'Başvuru tarihi · ' + timeLabel(business.created_at) : '';
            const owners = business.memberships
                .filter((membership) => membership.role === 'business_owner' && membership.user)
                .map(({ user }) => [user.name, user.email].filter(Boolean).join(' · '));
            const fields = {
                owners,
                contact: [business.email, business.phone, business.website],
                address: [business.address, [business.district, business.city].filter(Boolean).join(' / '),
                    [business.postal_code, business.country_code].filter(Boolean).join(' ')],
                branches: business.branches.map((branch) => branch.name),
            };
            for (const [part, values] of Object.entries(fields)) {
                find(part).textContent = values.filter(Boolean).join('\n') || 'Kayıtlı bilgi yok.';
            }

            const description = find('description');
            description.textContent = business.description ?? '';
            description.hidden = !business.description;
            const accepted = business.contract_acceptances.filter((acceptance) => acceptance.is_accepted);
            find('no-acceptance').hidden = Boolean(accepted.length);
            acceptances.replaceChildren(...accepted.map(contractAcceptance));
            approve.disabled = !accepted.length;
            decision.hidden = business.status !== 'pending';
            information.hidden = false;
            title.focus();
            showStatus(decision.hidden ? 'Bu başvuru daha önce sonuçlandırılmış.' : '');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    async function save(action, payload) {
        if (saving || selected?.status !== 'pending') {
            return;
        }

        const pending = request;
        const business = selected;
        saving = true;
        actions.disabled = true;
        back.disabled = true;
        showStatus('Karar kaydediliyor…');
        try {
            await api('/admin/businesses/' + business.id + '/' + action, payload);
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
            return;
        } finally {
            if (pending === request) {
                saving = false;
                actions.disabled = false;
                back.disabled = false;
            }
        }

        if (pending === request) {
            const outcome = action === 'approve' ? 'onaylandı.' : 'reddedildi.';
            await loadList(page, business.name + ' başvurusu ' + outcome);
        }
    }

    trigger.addEventListener('click', () => {
        dialog.showModal();
        loadList(1);
    });
    refresh.addEventListener('click', () => loadList(page));
    previous.addEventListener('click', () => loadList(page - 1));
    next.addEventListener('click', () => loadList(page + 1));
    back.addEventListener('click', () => loadList(page));
    approve.addEventListener('click', () => save('approve', {}));
    decision.addEventListener('submit', (event) => {
        event.preventDefault();
        const reason = new FormData(decision).get('rejection_reason').trim();
        save('reject', { rejection_reason: reason });
    });
    find('close').addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    return {
        start() {
            trigger.hidden = false;
        },
        stop() {
            trigger.hidden = true;
            close();
        },
    };
}
