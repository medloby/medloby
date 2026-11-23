export function mountPackageConversation(thread, { api, downloadAttachment, timeLabel }) {
    const find = (selector) => thread.querySelector(selector);
    const form = find('[data-thread-form]');
    const maxAttachmentSize = Number(form.elements.attachment.dataset.maxMegabytes);
    const submit = form.querySelector('[type=submit]');
    const summary = find('summary');
    const status = find('[data-thread-status]');
    const list = find('[data-thread-messages]');
    const pages = find('[data-thread-pages]');
    const newer = find('[data-thread-newer]');
    const older = find('[data-thread-older]');
    const template = find('[data-thread-message]');
    let current;
    let newerCursor;
    let olderCursor;
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
                    if (button.isConnected && error.name !== 'AbortError') {
                        showStatus(error.message);
                    }
                } finally {
                    button.disabled = false;
                }
            });
            files.append(button);
        }

        return row;
    }

    async function load(cursor = '') {
        const pending = ++request;
        newer.disabled = true;
        older.disabled = true;
        showStatus('Mesajlar yükleniyor…');

        try {
            const { messages, conversation } = await api('/conversations/' + current.id + '?message_cursor=' + encodeURIComponent(cursor));
            if (pending !== request) {
                return;
            }

            newerCursor = messages.prev_cursor;
            olderCursor = messages.next_cursor;
            list.replaceChildren(...messages.data.slice().reverse().map(messageRow));
            form.hidden = conversation.status !== 'open';
            pages.hidden = !newerCursor && !olderCursor;
            showStatus(messages.data.length ? '' : 'Henüz mesaj yok.');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                newer.disabled = !newerCursor;
                older.disabled = !olderCursor;
            }
        }
    }

    function select(conversation) {
        if (current?.id === conversation?.id) {
            return;
        }

        current = conversation;
        request++;
        newerCursor = null;
        olderCursor = null;
        thread.hidden = !current;
        form.reset();
        form.hidden = true;
        list.replaceChildren();
        pages.hidden = true;
        showStatus('');

        if (current && thread.open) {
            load();
        }
    }

    function openLatest() {
        if (!current) {
            return;
        }

        if (thread.open) {
            load();
        } else {
            thread.open = true;
        }

        summary.focus();
    }

    thread.addEventListener('toggle', () => {
        if (current && thread.open) {
            load();
        }
    });
    newer.addEventListener('click', () => load(newerCursor));
    older.addEventListener('click', () => load(olderCursor));

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

        if (file.size > maxAttachmentSize * 1024 * 1024) {
            showStatus(`Ek dosya en fazla ${maxAttachmentSize} MB olabilir.`);
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
                await load();
            }
        } catch (error) {
            if (current?.id === id) {
                showStatus(error.status === 413 ? 'Dosya sunucunun yükleme sınırını aşıyor. Daha küçük bir dosya seçin.' : error.message);
            }
        } finally {
            submit.disabled = false;
        }
    });

    return { select, openLatest };
}
