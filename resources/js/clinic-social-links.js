export function mountClinicSocialLinks(panel, api) {
    const find = (part) => panel.querySelector('[data-social-' + part + ']');
    const form = find('form');
    const fields = form.querySelector('fieldset');
    const record = find('record');
    const publication = find('publication');
    const status = find('status');
    const retry = find('retry');
    let scope;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function currentAccount() {
        return scope?.accounts.find((account) => String(account.id) === record.value);
    }

    function fillEditor() {
        const account = currentAccount();
        for (const field of ['platform', 'username', 'page_name', 'profile_url']) {
            form.elements.namedItem(field).value = account?.[field] ?? '';
        }
        form.elements.namedItem('sort_order').value = account?.sort_order ?? 0;
        form.elements.namedItem('publish').checked = true;
        find('new-publication').hidden = Boolean(account);
        publication.hidden = !account;
        publication.textContent = account?.is_active ? 'Yayından kaldır' : 'Yayımla';
    }

    function render(current, selected = '') {
        const options = current.accounts.map((account) => new Option(
            (account.page_name || account.platform) + (account.is_active ? ' · Yayında' : ' · Pasif'), account.id,
        ));
        record.replaceChildren(new Option('Yeni bağlantı', ''), ...options);
        record.value = selected;
        fillEditor();
    }

    async function load(current) {
        if (current.busy) {
            return;
        }

        current.busy = true;
        const selected = record.value;
        form.hidden = true;
        retry.hidden = true;
        showStatus('Bağlantılar yükleniyor…');
        try {
            const accounts = await api(current.path);
            if (scope === current) {
                current.accounts = accounts;
                render(current, accounts.some(({ id }) => String(id) === selected) ? selected : '');
                form.hidden = false;
                showStatus();
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
                retry.hidden = false;
            }
        } finally {
            current.busy = false;
        }
    }

    async function save(current, path, payload, method, confirmation) {
        if (scope !== current || current.busy) {
            return;
        }

        current.busy = true;
        fields.disabled = true;
        showStatus('Bağlantı kaydediliyor…');
        try {
            const saved = await api(path, payload, method);
            if (scope === current) {
                current.accounts = [...current.accounts.filter((account) => account.id !== saved.id), saved]
                    .sort((first, second) => first.sort_order - second.sort_order || first.id - second.id);
                render(current, saved.id);
                fields.disabled = false;
                record.focus();
                showStatus(confirmation);
            }
        } catch (error) {
            if (scope === current) {
                if (error.status === 403) {
                    form.hidden = true;
                }
                showStatus(error.message);
            }
        } finally {
            current.busy = false;
            if (scope === current) {
                fields.disabled = false;
            }
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!scope || scope.busy) {
            return;
        }

        const id = record.value;
        const values = new FormData(form);
        const payload = {
            platform: values.get('platform').trim(),
            username: values.get('username').trim() || null,
            page_name: values.get('page_name').trim() || null,
            profile_url: values.get('profile_url').trim(),
            sort_order: Number(values.get('sort_order')),
        };
        if (!id) {
            payload.is_active = values.has('publish');
        }
        save(scope, id ? scope.path + '/' + id : scope.path, payload, id ? 'PUT' : 'POST', 'Bağlantı kaydedildi.');
    });

    publication.addEventListener('click', () => {
        const account = currentAccount();
        if (account) {
            const path = scope.path + '/' + account.id;
            if (account.is_active) {
                save(scope, path, undefined, 'DELETE', 'Bağlantı yayından kaldırıldı.');
            } else {
                save(scope, path, { is_active: true }, 'PUT', 'Bağlantı yayımlandı.');
            }
        }
    });
    record.addEventListener('change', () => {
        fillEditor();
        showStatus();
    });
    retry.addEventListener('click', () => {
        if (scope) {
            load(scope);
        }
    });
    panel.addEventListener('toggle', () => {
        if (panel.open && scope) {
            load(scope);
        }
    });

    return {
        setBranch(id) {
            const path = id ? '/branches/' + id + '/operations/social-media' : null;
            if ((scope?.path ?? null) === path) {
                return;
            }

            scope = path ? { path, accounts: [], busy: false } : undefined;
            panel.open = false;
            panel.hidden = !scope;
            form.hidden = true;
            fields.disabled = false;
            retry.hidden = true;
            record.replaceChildren();
            form.reset();
            showStatus();
        },
    };
}
