export function mountPackageDiscovery(section, { api, startConversation }) {
    const find = (selector) => section.querySelector(selector);
    const status = find('[data-discovery-status]');
    const results = find('[data-discovery-results]');
    const previous = find('[data-discovery-previous]');
    const next = find('[data-discovery-next]');
    const template = find('[data-discovery-card]');
    let currentPage = 1;
    let lastPage = 1;
    let request = 0;

    function showStatus(message) {
        status.textContent = message;
        status.hidden = !message;
    }

    function cardFor(pkg) {
        const card = template.content.firstElementChild.cloneNode(true);
        card.querySelector('[data-discovery-name]').textContent = pkg.name;
        card.querySelector('[data-discovery-clinic]').textContent = [pkg.business.name, pkg.branch?.name].filter(Boolean).join(' · ');
        const description = card.querySelector('[data-discovery-description]');
        description.textContent = pkg.description ?? '';
        description.hidden = !pkg.description;

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

        const action = card.querySelector('[data-discovery-start]');
        action.addEventListener('click', async () => {
            action.disabled = true;
            showStatus('');
            try {
                await startConversation(pkg);
            } catch (error) {
                showStatus(error.message);
            } finally {
                action.disabled = false;
            }
        });

        return card;
    }

    async function load(page) {
        const pending = ++request;
        previous.disabled = true;
        next.disabled = true;
        showStatus('Paketler yükleniyor…');
        try {
            const response = await api('/package-catalog?page=' + page);
            if (pending !== request) {
                return;
            }

            currentPage = response.current_page;
            lastPage = response.last_page;
            results.replaceChildren(...response.data.map(cardFor));
            find('[data-discovery-pages]').hidden = lastPage <= 1;
            find('[data-discovery-page]').textContent = currentPage + ' / ' + lastPage;
            showStatus(response.data.length ? '' : 'Şu anda görüşme başlatılabilecek bir paket yok.');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        } finally {
            if (pending === request) {
                previous.disabled = currentPage <= 1;
                next.disabled = currentPage >= lastPage;
            }
        }
    }

    previous.addEventListener('click', () => load(currentPage - 1));
    next.addEventListener('click', () => load(currentPage + 1));

    return { open: () => load(1) };
}
