const statusLabels = { open: 'Açık', closed: 'Kapalı' };
const elapsedNumber = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 1 });

function elapsed(seconds) {
    if (seconds >= 3600) {
        return elapsedNumber.format(seconds / 3600) + ' saat';
    }
    return elapsedNumber.format(seconds / 60) + ' dk.';
}

export function mountSupportTickets(dialog, trigger, { api, timeLabel }) {
    const find = (part, root = dialog) => root.querySelector('[data-support-' + part + ']');
    const workspace = find('workspace');
    const overview = find('overview');
    const compose = find('compose');
    const thread = find('thread');
    const filter = find('filter');
    const assignmentFilter = find('assignment-filter');
    const searchForm = find('search-form');
    const awaitingOption = find('awaiting-option');
    const list = find('list');
    const messages = find('messages');
    const subject = find('subject');
    const status = find('status');
    const reply = find('reply');
    const assignmentForm = find('assignment-form');
    const assignee = assignmentForm.elements.namedItem('assigned_to_user_id');
    const changeStatus = find('set-status');
    let endpoint;
    let admin;
    let canManage;
    let ticket;
    let requester;
    let search = '';
    let listPage = 1;
    let messagePage = 1;
    let request = 0;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function showView(view) {
        for (const item of [overview, compose, thread]) {
            item.hidden = item !== view;
        }
    }

    async function run(message, task) {
        if (!endpoint || !dialog.open || workspace.disabled) {
            return;
        }

        const active = ++request;
        workspace.disabled = true;
        showStatus(message);
        try {
            const result = await task();
            return active === request && dialog.open ? result : undefined;
        } catch (error) {
            if (active === request && dialog.open) {
                showStatus(error.message);
            }
        } finally {
            if (active === request) {
                workspace.disabled = false;
            }
        }
    }

    function setPages(prefix, page) {
        find(prefix + '-pages').hidden = page.last_page <= 1 && page.current_page <= 1;
        find(prefix + '-page').textContent = page.current_page + ' / ' + page.last_page;
        find(prefix + '-previous').disabled = page.current_page <= 1;
        find(prefix + '-next').disabled = page.current_page >= page.last_page;
    }

    function ticketSummary(item) {
        const response = Number.isFinite(item.first_response_seconds) ? 'İlk yanıt ' + elapsed(item.first_response_seconds) : '';
        const resolution = Number.isFinite(item.resolution_seconds) ? 'Son kapanışa kadar ' + elapsed(item.resolution_seconds) : '';
        const owner = item.assignee ? 'Sorumlu ' + item.assignee.name : item.assignment_needs_attention ? 'Sorumlu seçilmeli' : 'Sorumlusuz';
        return [
            admin && 'Talep #' + item.id, item.awaiting_reply ? 'Yanıt bekliyor' : statusLabels[item.status],
            admin && owner, item.requester_name, timeLabel(item.updated_at), response, resolution,
        ].filter(Boolean).join(' · ');
    }

    function ticketRow(item) {
        const row = find('ticket-row').content.firstElementChild.cloneNode(true);
        find('row-subject', row).textContent = item.subject;
        find('row-summary', row).textContent = ticketSummary(item);
        row.querySelector('button').addEventListener('click', () => loadTicket(item.id));
        return row;
    }

    function messageRow(message) {
        const row = find('message-row').content.firstElementChild.cloneNode(true);
        const name = message.from_support ? 'Medloby destek' : admin ? ticket.requester_name : 'Siz';
        find('message-by', row).textContent = name + ' · ' + timeLabel(message.created_at);
        find('message-body', row).textContent = message.body;
        return row;
    }

    async function loadList(page = 1, focus = false) {
        const query = new URLSearchParams({ page });
        if (filter.value) {
            query.set('status', filter.value);
        }
        if (admin && assignmentFilter.value) {
            query.set('assignment', assignmentFilter.value);
        }
        if (requester) {
            query.set('requester_id', requester.id);
        }
        if (search) {
            query.set('search', search);
        }
        showView(overview);
        const result = await run('Destek talepleri yükleniyor…', () => api(endpoint + '?' + query));
        if (!result) {
            return;
        }

        listPage = result.current_page;
        list.replaceChildren(...result.data.map(ticketRow));
        setPages('list', result);
        showStatus(result.data.length ? '' : 'Bu durumda destek talebi bulunamadı.');
        if (focus) {
            filter.focus();
        }
    }

    function renderTicket(item, message = '') {
        const changed = ticket?.id !== item.id || thread.hidden;
        if (changed) {
            reply.reset();
        }
        ticket = item;
        messagePage = item.messages.current_page;
        subject.textContent = item.subject;
        find('summary').textContent = ticketSummary(item);
        messages.replaceChildren(...item.messages.data.toReversed().map(messageRow));
        setPages('thread', item.messages);
        reply.hidden = item.status === 'closed';
        assignmentForm.hidden = !canManage || item.status === 'closed';
        if (!assignmentForm.hidden) {
            assignee.replaceChildren(new Option('Sorumlusuz', ''), ...item.assignment_candidates.map(
                (candidate) => new Option(candidate.name + ' · #' + candidate.id, candidate.id),
            ));
            assignee.value = item.assignee?.id ?? '';
        }
        changeStatus.hidden = !canManage;
        changeStatus.textContent = item.status === 'closed' ? 'Yeniden aç' : 'Talebi kapat';
        showView(thread);
        showStatus(message);
        if (changed) {
            subject.focus();
        }
    }

    function fetchTicket(id, page = 1) {
        return api(endpoint + '/' + id + '?message_page=' + page);
    }

    async function loadTicket(id, page = 1) {
        const item = await run('Destek talebi yükleniyor…', () => fetchTicket(id, page));
        if (item) {
            renderTicket(item);
        }
    }

    function reset() {
        request++;
        ticket = undefined;
        requester = undefined;
        search = '';
        find('requester').textContent = '';
        find('requester').hidden = true;
        listPage = 1;
        messagePage = 1;
        workspace.disabled = false;
        list.replaceChildren();
        messages.replaceChildren();
        find('list-pages').hidden = true;
        find('thread-pages').hidden = true;
        subject.textContent = '';
        find('summary').textContent = '';
        compose.reset();
        searchForm.reset();
        reply.reset();
        assignmentFilter.value = '';
        assignmentForm.hidden = true;
        assignee.replaceChildren();
        showStatus();
        showView(overview);
    }

    function open(item = null, { relatedAccount = null, ticketId = null, status: initialStatus } = {}) {
        if (!endpoint || dialog.open) {
            return;
        }

        requester = admin ? relatedAccount : null;
        find('requester').textContent = requester ? requester.name + ' · Hesap #' + requester.id : '';
        find('requester').hidden = !requester;
        filter.value = initialStatus ?? (admin && !requester ? 'open' : '');
        dialog.showModal();
        if (item) {
            renderTicket(item);
        } else if (ticketId) {
            loadTicket(ticketId);
        } else {
            loadList(1, true);
        }
    }

    trigger.addEventListener('click', () => open());
    find('close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', reset);
    find('create').addEventListener('click', () => {
        showStatus();
        showView(compose);
        compose.elements.namedItem('subject').focus();
    });
    dialog.querySelectorAll('[data-support-back]').forEach((button) => {
        button.addEventListener('click', () => loadList(listPage, true));
    });

    compose.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fields = Object.fromEntries(new FormData(compose));
        const item = await run('Destek talebi gönderiliyor…', () => api(endpoint, fields));
        if (item) {
            compose.reset();
            renderTicket(item, 'Destek talebiniz alındı.');
        }
    });

    reply.addEventListener('submit', async (event) => {
        event.preventDefault();
        const fields = Object.fromEntries(new FormData(reply));
        const item = await run('Yanıt gönderiliyor…', () => api(endpoint + '/' + ticket.id + '/messages', fields));
        if (item) {
            reply.reset();
            renderTicket(item, 'Yanıt gönderildi.');
        }
    });

    assignmentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const path = endpoint + '/' + ticket.id + '/assignment?message_page=' + messagePage;
        const fields = { assigned_to_user_id: assignee.value || null, assignment_id: ticket.assignment_id };
        const item = await run('Sorumlu güncelleniyor…', () => api(path, fields, 'PUT'));
        if (item) {
            renderTicket(item, 'Sorumlu güncellendi.');
        }
    });

    changeStatus.addEventListener('click', async () => {
        const closing = ticket.status === 'open';
        const path = endpoint + '/' + ticket.id + '/status';
        const item = await run('Talep durumu güncelleniyor…', () => api(path, { status: closing ? 'closed' : 'open' }, 'PUT'));
        if (item) {
            renderTicket(item, closing ? 'Destek talebi kapatıldı.' : 'Destek talebi yeniden açıldı.');
        }
    });

    function reloadList() {
        list.replaceChildren();
        find('list-pages').hidden = true;
        loadList();
    }

    filter.addEventListener('change', reloadList);
    assignmentFilter.addEventListener('change', reloadList);
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (admin) {
            search = searchForm.elements.namedItem('search').value.trim();
            reloadList();
        }
    });
    find('refresh-list').addEventListener('click', () => loadList(listPage));
    find('list-previous').addEventListener('click', () => loadList(listPage - 1));
    find('list-next').addEventListener('click', () => loadList(listPage + 1));
    find('refresh-thread').addEventListener('click', () => loadTicket(ticket.id));
    find('thread-previous').addEventListener('click', () => loadTicket(ticket.id, messagePage - 1));
    find('thread-next').addEventListener('click', () => loadTicket(ticket.id, messagePage + 1));

    return {
        open,
        openForAccount: (account) => open(null, { relatedAccount: account }),
        openTicket: (ticketId) => open(null, { ticketId }),
        openAwaitingReply: () => open(null, { status: 'awaiting_reply' }),
        fetchTicket,
        start({ administrator = false, assignedOnly = false } = {}) {
            admin = administrator || assignedOnly;
            canManage = administrator;
            endpoint = admin ? '/admin/support-tickets' : '/support-tickets';
            trigger.textContent = assignedOnly ? 'Bana atanan talepler' : admin ? 'Destek talepleri' : 'Destek taleplerim';
            find('heading').textContent = trigger.textContent;
            find('create').hidden = admin;
            searchForm.hidden = !admin;
            find('assignment-filter-field').hidden = !canManage;
            awaitingOption.hidden = !admin;
            awaitingOption.disabled = !admin;
            trigger.hidden = false;
        },
        stop() {
            endpoint = undefined;
            trigger.hidden = true;
            dialog.close();
            reset();
        },
    };
}
