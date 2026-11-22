const preparedDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Istanbul' });

export function mountClinicConsentTemplates(panel, api) {
    const find = (part) => panel.querySelector('[data-consents-' + part + ']');
    const status = find('status');
    const retry = find('retry');
    const controls = find('controls');
    const picker = find('picker');
    const form = find('form');
    const reload = find('reload');
    const previous = find('previous');
    const next = find('next');
    const prepare = find('prepare');
    const history = find('history');
    const versionPicker = find('version-picker');
    const preview = find('version-preview');
    const title = form.elements.namedItem('title');
    const content = form.elements.namedItem('content');
    let scope;

    function showStatus(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    function edit(draft = null) {
        scope.draft = draft;
        picker.value = draft?.id ?? '';
        title.value = draft?.title ?? '';
        content.value = draft?.content ?? '';
        reload.hidden = !draft;
        resetHistory();
        updatePreparation();
    }

    function resetHistory() {
        history.open = false;
        versionPicker.replaceChildren();
        find('version-field').hidden = true;
        find('version-pages').hidden = true;
        preview.hidden = true;
        find('version-title').textContent = '';
        find('version-content').textContent = '';
    }

    function updatePreparation() {
        const draft = scope?.draft;
        find('preparation').hidden = !draft;
        history.hidden = !draft?.prepared_revision;
        if (!draft) {
            return;
        }

        const dirty = title.value !== draft.title || content.value !== draft.content.replace(/\r\n?/g, '\n');
        prepare.disabled = dirty;
        prepare.hidden = draft.prepared_revision === draft.revision;
        const prepared = draft.prepared_revision
            ? 'Son hazırlanan sürüm ' + draft.prepared_revision + '.'
            : 'Bu metin için ilk sürümü hazırlayabilirsiniz.';
        find('prepared-label').textContent = dirty ? 'Sürüm hazırlamak için değişiklikleri kaydedin.' : prepared;
    }

    function versionsPath(current) {
        return current.path + '/' + current.draft.id + '/versions';
    }

    function loadVersions(page) {
        return run(async (current) => {
            const result = await api(versionsPath(current) + '?page=' + page);
            if (scope !== current) {
                return;
            }

            current.versionPage = result.current_page;
            preview.hidden = true;
            versionPicker.replaceChildren(new Option('Sürüm seçin', ''), ...result.data.map((version) => (
                new Option('Sürüm ' + version.revision + ' · ' + preparedDate.format(new Date(version.created_at)), version.id)
            )));
            find('version-field').hidden = false;
            find('version-pages').hidden = result.last_page <= 1;
            find('version-page').textContent = result.current_page + ' / ' + result.last_page;
            find('version-previous').disabled = result.current_page <= 1;
            find('version-next').disabled = result.current_page >= result.last_page;
            showStatus();
        }, 'Hazırlanan sürümler yükleniyor…');
    }

    async function run(action, message) {
        const current = scope;
        if (!current || controls.disabled) {
            return;
        }

        controls.disabled = true;
        showStatus(message);
        try {
            await action(current);
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            if (scope === current) {
                controls.disabled = false;
            }
        }
    }

    async function renderPage(current, page, selected = null) {
        const result = await api(current.path + '?page=' + page);
        if (scope !== current) {
            return;
        }

        current.page = result.current_page;
        picker.replaceChildren(new Option('Yeni taslak', ''), ...result.data.map((draft) => new Option(draft.title, draft.id)));
        find('pages').hidden = result.last_page <= 1;
        find('page').textContent = result.current_page + ' / ' + result.last_page;
        previous.disabled = result.current_page <= 1;
        next.disabled = result.current_page >= result.last_page;
        controls.hidden = false;
        if (selected && !result.data.some((draft) => draft.id === selected.id)) {
            picker.add(new Option(selected.title, selected.id), 1);
        }
        edit(selected);
    }

    function load(page) {
        return run(async (current) => {
            retry.hidden = true;
            try {
                await renderPage(current, page);
                if (scope === current) {
                    showStatus();
                }
            } catch (error) {
                if (scope === current) {
                    retry.hidden = false;
                }
                throw error;
            }
        }, 'Onam taslakları yükleniyor…');
    }

    function loadDraft(id) {
        if (!id) {
            edit();
            showStatus();
            return;
        }

        return run(async (current) => {
            picker.value = current.draft?.id ?? '';
            const draft = await api(current.path + '/' + id);
            if (scope === current) {
                edit(draft);
                showStatus();
            }
        }, 'Onam taslağı yükleniyor…');
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const fields = Object.fromEntries(new FormData(form));
        run(async (current) => {
            const existing = current.draft;
            const saved = existing
                ? await api(current.path + '/' + existing.id, { ...fields, revision: existing.revision }, 'PUT')
                : await api(current.path, fields);
            if (scope !== current) {
                return;
            }

            if (existing) {
                picker.selectedOptions[0].textContent = saved.title;
            } else {
                picker.add(new Option(saved.title, saved.id), 1);
            }
            edit(saved);

            if (!existing) {
                try {
                    await renderPage(current, 1, saved);
                } catch (error) {
                    if (scope === current) {
                        retry.hidden = false;
                        showStatus('Onam taslağı kaydedildi. ' + error.message);
                    }
                    return;
                }
            }
            if (scope === current) {
                showStatus('Onam taslağı kaydedildi.');
            }
        }, 'Onam taslağı kaydediliyor…');
    });

    form.addEventListener('input', updatePreparation);
    prepare.addEventListener('click', () => run(async (current) => {
        const version = await api(versionsPath(current), { revision: current.draft.revision });
        if (scope === current) {
            current.draft.prepared_revision = version.revision;
            resetHistory();
            updatePreparation();
            showStatus('Onam metni sürüm ' + version.revision + ' olarak hazırlandı.');
        }
    }, 'Onam sürümü hazırlanıyor…'));
    history.addEventListener('toggle', () => {
        if (history.open && !versionPicker.options.length) {
            if (controls.disabled) {
                history.open = false;
                return;
            }
            loadVersions(1);
        }
    });
    versionPicker.addEventListener('change', () => {
        const id = versionPicker.value;
        preview.hidden = true;
        if (!id) {
            return;
        }

        run(async (current) => {
            const version = await api(versionsPath(current) + '/' + id);
            if (scope === current) {
                find('version-title').textContent = version.title;
                find('version-content').textContent = version.content;
                preview.hidden = false;
                showStatus();
            }
        }, 'Onam sürümü yükleniyor…');
    });
    find('version-previous').addEventListener('click', () => loadVersions(scope.versionPage - 1));
    find('version-next').addEventListener('click', () => loadVersions(scope.versionPage + 1));
    picker.addEventListener('change', () => loadDraft(picker.value));
    reload.addEventListener('click', () => loadDraft(scope.draft.id));
    previous.addEventListener('click', () => load(scope.page - 1));
    next.addEventListener('click', () => load(scope.page + 1));
    retry.addEventListener('click', () => load(scope.page));
    panel.addEventListener('toggle', () => {
        if (panel.open && scope && controls.hidden) {
            load(1);
        }
    });

    return {
        setBusiness(id) {
            const path = id ? '/businesses/' + id + '/crm/consent-templates' : undefined;
            if (scope?.path === path) {
                return;
            }

            scope = path ? { path, page: 1, draft: null } : undefined;
            panel.open = false;
            panel.hidden = !scope;
            controls.hidden = true;
            controls.disabled = false;
            picker.replaceChildren();
            form.reset();
            resetHistory();
            updatePreparation();
            retry.hidden = true;
            showStatus();
        },
    };
}
