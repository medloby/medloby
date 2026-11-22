export function mountClinicBranchManagement(panel, api, onChanged) {
    const find = (part) => panel.querySelector('[data-branches-' + part + ']');
    const content = find('content');
    const list = find('list');
    const empty = find('empty');
    const form = find('form');
    const create = find('create');
    const status = find('status');
    const retry = find('retry');
    let scope;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function render(current, changedId) {
        current.branches.sort((first, second) => first.name.localeCompare(second.name, 'tr') || first.id - second.id);
        let changedAction;
        list.replaceChildren(...current.branches.map((branch) => {
            const row = find('row').content.firstElementChild.cloneNode(true);
            const active = branch.status === 'active';
            row.querySelector('[data-branches-name]').textContent = branch.name;
            row.querySelector('[data-branches-meta]').textContent = [branch.city, branch.district, active ? 'Aktif' : 'Pasif']
                .filter(Boolean).join(' · ');
            const button = row.querySelector('[data-branches-action]');
            const label = active ? 'Pasife al' : 'Etkinleştir';
            button.textContent = label;
            button.setAttribute('aria-label', branch.name + ' · ' + label);
            button.addEventListener('click', () => save(current, current.path + '/' + branch.id + '/status',
                { status: active ? 'inactive' : 'active' }, 'PUT', active ? 'Şube pasife alındı.' : 'Şube etkinleştirildi.'));
            if (branch.id === changedId) {
                changedAction = button;
            }
            return row;
        }));
        empty.hidden = current.branches.length > 0;
        changedAction?.focus();
    }

    async function load(current) {
        if (current.busy) {
            return;
        }

        current.busy = true;
        content.hidden = true;
        retry.hidden = true;
        showStatus('Şubeler yükleniyor…');
        try {
            const { branches } = await api(current.path);
            if (scope === current) {
                current.branches = branches;
                render(current);
                content.hidden = false;
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
        content.disabled = true;
        showStatus('Şube kaydediliyor…');
        try {
            const saved = await api(path, payload, method);
            if (scope === current) {
                current.branches = current.branches.filter((branch) => branch.id !== saved.id);
                current.branches.push(saved);
                if (method === 'POST') {
                    form.reset();
                    create.open = false;
                }
                content.disabled = false;
                render(current, saved.id);
                showStatus(confirmation);
                onChanged(saved);
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            current.busy = false;
            if (scope === current) {
                content.disabled = false;
            }
        }
    }

    panel.addEventListener('toggle', () => {
        if (panel.open && scope) {
            load(scope);
        }
    });
    retry.addEventListener('click', () => {
        if (scope) {
            load(scope);
        }
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (scope) {
            const payload = Object.fromEntries(new FormData(form));
            save(scope, scope.path, payload, 'POST', 'Yeni şube eklendi.');
        }
    });

    return {
        setBusiness(id) {
            const path = id ? '/businesses/' + id + '/crm/branches' : null;
            if ((scope?.path ?? null) === path) {
                return;
            }

            scope = path ? { path, branches: [], busy: false } : undefined;
            panel.open = false;
            panel.hidden = !scope;
            list.replaceChildren();
            content.hidden = true;
            content.disabled = false;
            retry.hidden = true;
            create.open = false;
            form.reset();
            showStatus();
        },
        refresh() {
            if (scope && panel.open) {
                load(scope);
            }
        },
    };
}
