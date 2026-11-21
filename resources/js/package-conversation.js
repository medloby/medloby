export function mountPackageConversation(thread, { api, downloadAttachment, timeLabel }) {
    const find = (selector) => thread.querySelector(selector);
    const form = find('[data-thread-form]');
    const submit = form.querySelector('[type=submit]');
    const status = find('[data-thread-status]');
    const list = find('[data-thread-messages]');
    const newer = find('[data-thread-newer]');
    const older = find('[data-thread-older]');
    const template = find('[data-thread-message]');
    let current;
    let page = 1;
    let lastPage = 1;
    let request = 0;

    function showStatus(message) {
        status.textContent = message;
        status.hidden = !message;
    }

    function messageRow(message) {
        const row = template.content.firstElementChild.cloneNode(true);
        const body = row.querySelector('[data-thread-body]');
        body.textContent = message.body ?? '';
        body.hidden = !message.body;
        row.querySelector('[data-thread-sender]').textContent = message.sender?.name ?? 'Gönderen';
        const time = row.querySelector('[data-thread-time]');
        time.dateTime = message.created_at;
        time.textContent = timeLabel(message.created_at);

        const files = row.querySelector('[data-thread-files]');
        for (const attachment of message.attachments) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'offer-button offer-button-secondary max-w-full break-all text-left';
            button.textContent = attachment.original_name;
            button.addEventListener('click', async () => {
                button.disabled = true;
                showStatus('');
                try {
                    await downloadAttachment(attachment);
                } catch (error) {
                    showStatus(error.message);
                } finally {
                    button.disabled = false;
                }
            });
            files.append(button);
        }

        return row;
    }

    async function load(targetPage) {
        const pending = ++request;
        newer.disabled = true;
        older.disabled = true;
        showStatus('Mesajlar yükleniyor…');

        try {
            const { messages, conversation } = await api('/conversations/' + current.id + '?page=' + targetPage);
            if (pending !== request) {
                return;
            }

            page = messages.current_page;
            lastPage = messages.last_page;
            list.replaceChildren(...messages.data.slice().reverse().map(messageRow));
            form.hidden = conversation.status !== 'open';
            find('[data-thread-pages]').hidden = lastPage <= 1;
            showStatus(messages.data.length ? '' : 'Henüz mesaj yok.');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                newer.disabled = page <= 1;
                older.disabled = page >= lastPage;
            }
        }
    }

    function select(conversation) {
        if (current?.id === conversation?.id) {
            return;
        }

        current = conversation;
        request++;
        page = 1;
        lastPage = 1;
        thread.hidden = !current;
        form.reset();
        form.hidden = true;
        list.replaceChildren();
        find('[data-thread-pages]').hidden = true;
        showStatus('');

        if (current && thread.open) {
            load(1);
        }
    }

    thread.addEventListener('toggle', () => {
        if (current && thread.open) {
            load(1);
        }
    });
    newer.addEventListener('click', () => load(page - 1));
    older.addEventListener('click', () => load(page + 1));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!current || submit.disabled) {
            return;
        }

        const id = current.id;
        const fields = new FormData(form);
        const body = fields.get('body').trim();
        const file = fields.get('attachment');
        if (!body && !file.name) {
            showStatus('Mesaj yazın veya dosya seçin.');
            return;
        }

        fields.set('body', body);
        if (!file.name) {
            fields.delete('attachment');
        }

        submit.disabled = true;
        try {
            await api('/conversations/' + id + '/messages', fields);
            if (current?.id === id) {
                form.reset();
                await load(1);
            }
        } catch (error) {
            if (current?.id === id) {
                showStatus(error.message);
            }
        } finally {
            submit.disabled = false;
        }
    });

    return { select };
}
