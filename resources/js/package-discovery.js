export function mountPackageDiscovery(section, { api, continueInAccount }) {
    const find = (selector) => section.querySelector(selector);
    const status = find('[data-discovery-status]');
    const results = find('[data-discovery-results]');
    const previous = find('[data-discovery-previous]');
    const next = find('[data-discovery-next]');
    const template = find('[data-discovery-card]');
    const form = find('[data-discovery-search]');
    const query = form.elements.namedItem('q');
    const city = form.elements.namedItem('city');
    const clear = find('[data-discovery-clear]');
    const scope = find('[data-discovery-scope]');
    const scopeName = find('[data-discovery-scope-name]');
    const profileLink = find('[data-discovery-profile]');
    const allClinics = find('[data-discovery-all-clinics]');
    let currentPage = 1;
    let lastPage = 1;
    let request = 0;

    function showStatus(message) {
        status.textContent = message;
    }

    function cardFor(pkg) {
        const card = template.content.firstElementChild.cloneNode(true);
        card.querySelector('[data-discovery-name]').textContent = pkg.name;
        card.querySelector('[data-discovery-clinic]').textContent = [pkg.business.name, pkg.branch?.name].filter(Boolean).join(' · ');
        const location = card.querySelector('[data-discovery-location]');
        location.textContent = [pkg.location?.city, pkg.location?.district].filter(Boolean).join(' · ');
        location.hidden = !location.textContent;

        const contents = card.querySelector('[data-discovery-contents]');
        for (const [label, items] of [['Tedaviler', pkg.treatments], ['Ek hizmetler', pkg.services]]) {
            if (!items.length) {
                continue;
            }

            const group = document.createElement('section');
            const heading = document.createElement('h3');
            heading.className = 'font-semibold';
            heading.textContent = label;
            const list = document.createElement('ul');
            list.className = 'mt-1 grid gap-1 text-slate-600 dark:text-slate-300';
            for (const item of items) {
                const row = document.createElement('li');
                row.textContent = item.quantity + ' × ' + item.name;
                list.append(row);
            }
            group.append(heading, list);
            contents.append(group);
        }

        card.querySelector('[data-discovery-start]').addEventListener('click', () => continueInAccount(pkg));

        return card;
    }

    function readLocation() {
        const params = new URLSearchParams(window.location.search);
        const value = Number(params.get('page'));
        return {
            q: (params.get('q') ?? '').trim().slice(0, query.maxLength),
            city: (params.get('city') ?? '').trim().slice(0, city.maxLength),
            clinic: (params.get('clinic') ?? '').trim().slice(0, 255),
            page: Number.isSafeInteger(value) && value > 0 ? value : 1,
        };
    }

    function writeLocation(filters, method) {
        const url = new URL(window.location.href);
        for (const key of ['q', 'city', 'clinic', 'page']) {
            if (filters[key] && (key !== 'page' || filters.page > 1)) {
                url.searchParams.set(key, filters[key]);
            } else {
                url.searchParams.delete(key);
            }
        }
        history[method](null, '', url);
    }

    async function load(filters, focusResults = false) {
        const pending = ++request;
        query.value = filters.q;
        city.value = filters.city;
        clear.hidden = !filters.q && !filters.city;
        scope.hidden = !filters.clinic;
        scopeName.textContent = 'Klinik paketleri';
        profileLink.hidden = true;
        find('[data-discovery-pages]').hidden = true;
        previous.disabled = true;
        next.disabled = true;
        results.replaceChildren();
        results.setAttribute('aria-busy', 'true');
        showStatus('Paketler yükleniyor…');
        try {
            const params = new URLSearchParams({ page: filters.page });
            if (filters.q) {
                params.set('q', filters.q);
            }
            if (filters.city) {
                params.set('city', filters.city);
            }
            if (filters.clinic) {
                params.set('clinic', filters.clinic);
            }
            const response = await api('/package-catalog?' + params);
            if (pending !== request) {
                return;
            }

            if (filters.page > response.last_page) {
                const valid = { ...filters, page: response.last_page };
                writeLocation(valid, 'replaceState');
                return load(valid, focusResults);
            }
            currentPage = response.current_page;
            lastPage = response.last_page;
            if (response.clinic) {
                scopeName.textContent = response.clinic.name + ' paketleri';
                profileLink.href = response.clinic.profile_url;
                profileLink.hidden = false;
            }
            results.replaceChildren(...response.data.map(cardFor));
            find('[data-discovery-pages]').hidden = lastPage <= 1;
            find('[data-discovery-page]').textContent = currentPage + ' / ' + lastPage;
            const empty = filters.q || filters.city ? 'Aramanızla eşleşen paket bulunamadı.'
                : filters.clinic ? 'Bu kliniğin katalogda şu anda paketi yok.' : 'Katalogda şu anda paket bulunmuyor.';
            showStatus(response.total ? response.total + ' paket bulundu.' : empty);
            if (focusResults && response.data.length) {
                results.focus();
            }
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                results.removeAttribute('aria-busy');
                previous.disabled = currentPage <= 1;
                next.disabled = currentPage >= lastPage;
            }
        }
    }

    function navigate(filters) {
        writeLocation(filters, 'pushState');
        load(filters, true);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        navigate({ ...readLocation(), q: query.value.trim(), city: city.value.trim(), page: 1 });
    });
    clear.addEventListener('click', () => navigate({ ...readLocation(), q: '', city: '', page: 1 }));
    allClinics.addEventListener('click', () => navigate({ ...readLocation(), clinic: '', page: 1 }));
    previous.addEventListener('click', () => navigate({ ...readLocation(), page: currentPage - 1 }));
    next.addEventListener('click', () => navigate({ ...readLocation(), page: currentPage + 1 }));

    function open() {
        load(readLocation());
    }

    window.addEventListener('popstate', () => {
        if (!section.hidden && window.location.hash === '#katalog') {
            open();
        }
    });

    return { open };
}
