import { mountPackageCatalog } from './package-catalog';
import { mountPackageDiscovery } from './package-discovery';
import { mountPackageConversation } from './package-conversation';

const statusLabels = {
    pending: 'Yanıt bekliyor',
    accepted: 'Kabul edildi',
    rejected: 'Reddedildi',
    expired: 'Süresi doldu',
};

const appointmentStatusLabels = {
    pending: 'Onay bekliyor',
    confirmed: 'Onaylandı',
    cancelled: 'İptal edildi',
    completed: 'Tamamlandı',
    no_show: 'Hasta gelmedi',
    rescheduled: 'Tarihi değiştirildi',
};

const appointmentActionLabels = {
    confirm: 'Randevuyu onayla',
    cancel: 'Randevuyu iptal et',
    reschedule: 'Tarihi değiştir',
    complete: 'Tamamlandı olarak işaretle',
    'no-show': 'Gelmedi olarak işaretle',
};

const offerDeadline = new Intl.DateTimeFormat('tr-TR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const appointmentDate = new Intl.DateTimeFormat('tr-TR', {
    dateStyle: 'long',
    timeZone: 'UTC',
});

function appointmentDateLabel(start, end) {
    const day = appointmentDate.format(new Date(start.replace(' ', 'T') + 'Z'));
    const endDay = start.slice(0, 10) === end.slice(0, 10)
        ? ''
        : appointmentDate.format(new Date(end.replace(' ', 'T') + 'Z')) + ' ';

    return day + ' · ' + start.slice(11, 16) + ' – ' + endDay + end.slice(11, 16);
}

function element(tag, value, className = '') {
    const node = document.createElement(tag);
    node.textContent = value;
    node.className = className;
    return node;
}

function showStatus(node, message = '') {
    node.textContent = message;
    node.hidden = !message;
}

function setOptions(select, placeholder, entries) {
    const empty = new Option(placeholder, '');
    empty.disabled = true;
    empty.selected = true;
    select.replaceChildren(empty, ...entries.map(([id, label]) => new Option(label, id)));
}

function priceLabel(amount, currency) {
    if (!/^[a-z]{3}$/i.test(currency)) {
        return amount + ' ' + currency;
    }

    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency }).format(amount);
}

export function mountPackageOffers(root) {
    const get = (id) => root.querySelector('#' + id);
    const notice = get('page-message');
    const signIn = get('sign-in-form');
    const offerList = get('offer-list');
    const newOfferButton = get('new-offer');
    const offerDialog = get('offer-dialog');
    const offerForm = get('offer-form');
    const rejectDialog = get('reject-dialog');
    const rejectForm = get('reject-form');
    const rejectStatus = rejectDialog.querySelector('[data-status]');
    const bookingDialog = get('booking-dialog');
    const bookingForm = get('booking-form');
    const appointmentDialog = get('appointment-action-dialog');
    const appointmentForm = get('appointment-action-form');
    const appointmentNotice = appointmentDialog.querySelector('[data-status]');
    const sessionList = get('booking-sessions');
    const conversationList = get('conversation-list');
    const branchField = bookingForm.elements.namedItem('branch');
    const catalogButton = get('catalog-button');
    const packageCatalog = mountPackageCatalog(get('catalog-dialog'), api);
    const discoverySection = get('package-discovery');
    const discovery = mountPackageDiscovery(discoverySection, { api, priceLabel, startConversation });
    const thread = mountPackageConversation(get('package-conversation'), {
        api,
        downloadAttachment,
        timeLabel: (value) => offerDeadline.format(new Date(value)),
    });

    let token;
    let user;
    let conversation;
    let pendingPackage;
    let currentPage = 1;
    let selectedPackageById = new Map();
    let bookingOffer;
    let rejection;
    let appointmentAction;
    let sessions = [];
    let offerRequest = 0;
    let pageRequest = 0;
    let dialogRequest = 0;

    async function api(path, body, method = body === undefined ? 'GET' : 'POST', download = false) {
        const headers = { Accept: 'application/json' };
        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }
        const multipart = body instanceof FormData;
        if (body !== undefined && !multipart) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch('/api' + path, {
            method,
            headers,
            body: multipart || body === undefined ? body : JSON.stringify(body),
            cache: 'no-store',
        });
        if (download && response.ok) {
            return response.blob();
        }

        const result = await response.json().catch(() => null);

        if (!response.ok || !result?.success) {
            if (response.status === 401 && token) {
                endSession();
            }

            throw new Error(
                Object.values(result?.errors ?? {}).flat()[0]
                    ?? result?.message
                    ?? 'İşlem tamamlanamadı. Lütfen tekrar deneyin.',
            );
        }

        return result.data;
    }

    async function downloadAttachment(attachment) {
        const blob = await api('/attachments/' + attachment.id + '/view', undefined, 'GET', true);
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = attachment.original_name;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function syncPage() {
        const browsing = window.location.hash === '#katalog';
        const openingCatalogue = browsing && discoverySection.hidden;

        discoverySection.hidden = !browsing;
        get('page-title').textContent = browsing ? 'Tedavi paketleri' : 'Paket teklifleri';
        document.title = get('page-title').textContent + ' | Medloby';
        get('discovery-link').setAttribute('aria-current', browsing ? 'page' : 'false');
        get('offers-link').setAttribute('aria-current', browsing ? 'false' : 'page');
        get('sign-in').hidden = browsing || Boolean(user);
        get('workspace').hidden = browsing || !user;

        if (openingCatalogue) {
            discovery.open();
        }
    }

    window.addEventListener('hashchange', syncPage);

    async function startConversation(pkg) {
        if (!user) {
            pendingPackage = pkg;
            window.location.hash = 'gorusmeler';
            syncPage();
            showStatus(notice, pkg.name + ' için görüşme başlatmak üzere hasta hesabınızla giriş yapın.');
            signIn.elements.namedItem('email').focus();
            return;
        }
        if (!user.patient_profile_exists) {
            throw new Error('Görüşme başlatmak için hasta hesabınızla giriş yapın.');
        }

        const item = await api('/conversations', { treatment_package_id: pkg.id });
        window.location.hash = 'gorusmeler';
        syncPage();
        await loadConversations(1, item);
        showStatus(notice, 'Paket görüşmesi açıldı.');
    }

    function endSession() {
        token = undefined;
        user = undefined;
        conversation = undefined;
        pendingPackage = undefined;
        bookingOffer = undefined;
        rejection = undefined;
        appointmentAction = undefined;
        sessions = [];
        selectedPackageById.clear();
        offerRequest++;
        pageRequest++;
        dialogRequest++;
        offerDialog.close();
        rejectDialog.close();
        bookingDialog.close();
        appointmentDialog.close();
        packageCatalog.close();
        catalogButton.hidden = true;
        appointmentForm.reset();
        offerForm.reset();
        rejectForm.reset();
        bookingForm.reset();
        sessionList.replaceChildren();
        offerList.replaceChildren();
        conversationList.replaceChildren();
        thread.select(undefined);
        get('account-name').textContent = '';
        get('conversation-title').textContent = 'Görüşme seçin';
        get('conversation-context').textContent = '';
        get('account').hidden = true;
        syncPage();
        signIn.reset();
    }

    function ownedBusinesses() {
        return user.business_memberships
            .filter((membership) => membership.is_active && membership.role === 'business_owner' && membership.business)
            .map((membership) => membership.business);
    }

    catalogButton.addEventListener('click', () => packageCatalog.open(ownedBusinesses()));

    function canSendOffer(item) {
        return item.status === 'open' && user.business_memberships.some(
            (membership) => membership.is_active && membership.business_id === item.business_id,
        );
    }

    function isPatient(item) {
        return item.patient_profile?.user_id === user.id;
    }

    function conversationContext(item) {
        const patient = item.patient_profile;
        return [
            item.business?.name,
            item.branch?.name,
            item.treatment_package ? 'İstenen paket: ' + item.treatment_package.name : null,
            !isPatient(item) && patient ? patient.first_name + ' ' + patient.last_name : null,
        ].filter(Boolean).join(' · ');
    }

    async function loadConversations(page, preferred) {
        const request = ++pageRequest;
        showStatus(notice, 'Görüşmeler yükleniyor…');
        const result = await api('/conversations?page=' + page);
        if (request !== pageRequest) {
            return;
        }

        currentPage = result.current_page;
        get('conversation-pages').hidden = result.last_page <= 1;
        get('previous-page').disabled = currentPage <= 1;
        get('next-page').disabled = currentPage >= result.last_page;
        const items = preferred && !result.data.some((item) => item.id === preferred.id)
            ? [preferred, ...result.data]
            : result.data;
        conversationList.replaceChildren(...items.map((item) => {
            const button = get('conversation-template').content.firstElementChild.cloneNode(true);
            button.dataset.conversation = item.id;
            button.querySelector('[data-subject]').textContent = item.subject || 'Görüşme #' + item.id;
            button.querySelector('[data-context]').textContent = conversationContext(item);
            button.addEventListener('click', () => {
                showStatus(notice);
                showConversation(item).catch((error) => showStatus(notice, error.message));
            });
            return button;
        }));

        showStatus(notice);
        if (items.length) {
            await showConversation(items.find((item) => item.id === preferred?.id) ?? items[0]);
        } else {
            conversation = undefined;
            thread.select(undefined);
            offerRequest++;
            newOfferButton.hidden = true;
            get('conversation-title').textContent = 'Görüşme bulunamadı';
            get('conversation-context').textContent = '';
            offerList.replaceChildren();
        }
    }

    async function showConversation(item) {
        conversation = item;
        thread.select(item);
        for (const button of conversationList.children) {
            button.setAttribute('aria-current', String(Number(button.dataset.conversation) === item.id));
        }
        get('conversation-title').textContent = item.subject || 'Görüşme #' + item.id;
        get('conversation-context').textContent = conversationContext(item);
        newOfferButton.hidden = !canSendOffer(item);
        await loadOffers(item);
    }

    async function loadOffers(item) {
        const request = ++offerRequest;
        offerList.replaceChildren(element('p', 'Teklifler yükleniyor…', 'text-sm'));
        const result = await api('/conversations/' + item.id + '/offers');

        if (request !== offerRequest) {
            return;
        }

        const packages = result.filter((offer) => offer.package_snapshot);
        offerList.replaceChildren(...(packages.length
            ? packages.map((offer) => renderOffer(offer, item))
            : [element('p', 'Bu görüşmede henüz paket teklifi yok.', 'text-sm text-slate-600 dark:text-slate-300')]));
    }

    function renderOffer(offer, item) {
        const card = get('offer-template').content.firstElementChild.cloneNode(true);
        const snapshot = offer.package_snapshot;
        const booked = offer.appointments_exists;
        card.querySelector('[data-title]').textContent = offer.title;
        card.querySelector('[data-price]').textContent = priceLabel(offer.amount, offer.currency);
        const deadline = offer.valid_until ? new Date(offer.valid_until) : null;
        const pastDeadline = deadline && deadline.getTime() < Date.now();
        showStatus(card.querySelector('[data-valid-until]'), deadline ? 'Geçerlilik sonu: ' + offerDeadline.format(deadline) : '');
        card.querySelector('[data-offer-status]').textContent = booked
            ? 'Randevular oluşturuldu'
            : statusLabels[pastDeadline && offer.status === 'pending' ? 'expired' : offer.status] ?? offer.status;
        showStatus(card.querySelector('[data-description]'), offer.description ?? '');

        if (offer.status === 'rejected' && offer.rejection_reason) {
            card.querySelector('[data-rejection]').hidden = false;
            card.querySelector('[data-rejection-reason]').textContent = offer.rejection_reason;
        }

        const contents = card.querySelector('[data-contents]');
        for (const [heading, entries] of [['Tedaviler', snapshot.treatments], ['Ek hizmetler', snapshot.services]]) {
            if (!entries?.length) {
                continue;
            }

            const group = element('section', '', 'grid gap-1');
            const list = element('ul', '', 'grid gap-1 text-slate-600 dark:text-slate-300');
            for (const entry of entries) {
                list.append(element('li', entry.quantity + ' × ' + entry.name));
            }
            group.append(element('h4', heading, 'font-semibold'), list);
            contents.append(group);
        }

        const actions = card.querySelector('[data-actions]');
        if (isPatient(item) && offer.status === 'pending' && !pastDeadline) {
            const accept = element('button', 'Teklifi kabul et', 'offer-button');
            accept.type = 'button';
            accept.addEventListener('click', async () => {
                accept.disabled = true;
                showStatus(notice);
                try {
                    await api('/offers/' + offer.id + '/accept', {});
                    if (conversation === item) {
                        showStatus(notice, 'Teklif kabul edildi. Seansları planlayabilirsiniz.');
                        await loadOffers(item);
                    }
                } catch (error) {
                    showStatus(notice, error.message);
                } finally {
                    accept.disabled = false;
                }
            });
            const reject = element('button', 'Teklifi reddet', 'offer-button offer-button-secondary');
            reject.type = 'button';
            reject.addEventListener('click', () => {
                rejection = { offer, item };
                rejectForm.reset();
                showStatus(rejectStatus);
                get('reject-offer-title').textContent = offer.title;
                rejectDialog.showModal();
            });
            actions.append(accept, reject);
        } else if (isPatient(item) && offer.status === 'accepted' && !booked && !pastDeadline) {
            const plan = element('button', 'Seansları planla', 'offer-button');
            plan.type = 'button';
            plan.addEventListener('click', () => openBookingDialog(offer));
            actions.append(plan);
        }
        actions.hidden = !actions.childElementCount;

        if (booked) {
            connectSchedule(card.querySelector('[data-schedule]'), offer);
        }

        return card;
    }

    function connectSchedule(details, offer) {
        details.hidden = false;
        const list = details.querySelector('[data-schedule-entries]');
        const status = details.querySelector('[data-schedule-status]');
        let loading = false;

        async function refresh() {
            if (!details.open || loading) {
                return;
            }

            loading = true;
            list.replaceChildren();
            showStatus(status, 'Randevular yükleniyor…');

            try {
                const appointments = await api('/offers/' + offer.id + '/appointments');
                list.replaceChildren(...appointments.map((appointment) => {
                    const row = element('li', '', 'grid gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900');
                    const date = element('time', appointmentDateLabel(appointment.starts_at, appointment.ends_at));
                    date.dateTime = appointment.starts_at.replace(' ', 'T');
                    const context = [appointment.doctor_name, appointment.branch_name].filter(Boolean).join(' · ');
                    row.append(
                        element('strong', appointment.treatment_name ?? 'Tedavi seansı'),
                        date,
                        element('span', context, 'text-slate-600 dark:text-slate-300'),
                        element('span', appointmentStatusLabels[appointment.status] ?? appointment.status, 'text-xs font-semibold text-slate-600 dark:text-slate-300'),
                    );

                    if (appointment.actions?.length) {
                        const controls = element('div', '', 'mt-2 flex flex-wrap gap-2');
                        for (const action of appointment.actions) {
                            const button = element('button', appointmentActionLabels[action], 'offer-button offer-button-secondary');
                            button.type = 'button';
                            button.addEventListener('click', () => openAppointmentAction(appointment, action, refresh));
                            controls.append(button);
                        }
                        row.append(controls);
                    }

                    return row;
                }));
                showStatus(status, appointments.length ? '' : 'Bu teklife ait randevu bulunamadı.');
            } catch (error) {
                showStatus(status, error.message);
            } finally {
                loading = false;
            }
        }

        details.addEventListener('toggle', refresh);
    }

    function openAppointmentAction(appointment, action, refresh) {
        appointmentAction = { appointment, action, refresh };
        appointmentForm.reset();
        showStatus(appointmentNotice);
        get('appointment-action-title').textContent = appointmentActionLabels[action];
        get('appointment-action-context').textContent = [
            appointment.treatment_name,
            appointmentDateLabel(appointment.starts_at, appointment.ends_at),
        ].filter(Boolean).join(' · ');

        const needsDate = action === 'reschedule';
        const needsReason = action === 'cancel';
        const date = appointmentForm.elements.namedItem('starts_at');
        const reason = appointmentForm.elements.namedItem('reason');
        date.disabled = !needsDate;
        date.required = needsDate;
        date.value = needsDate ? appointment.starts_at.replace(' ', 'T').slice(0, 16) : '';
        reason.disabled = !needsDate && !needsReason;
        reason.required = needsReason;
        appointmentForm.querySelector('[data-action-start]').hidden = !needsDate;
        appointmentForm.querySelector('[data-action-reason]').hidden = reason.disabled;
        appointmentForm.querySelector('[data-action-reason-label]').textContent = needsReason ? 'İptal nedeni' : 'Neden (isteğe bağlı)';
        appointmentDialog.showModal();
    }

    async function openOfferDialog(item) {
        const request = ++dialogRequest;
        offerForm.reset();
        offerForm.hidden = true;
        const status = offerDialog.querySelector('[data-status]');
        showStatus(status, 'Paketler yükleniyor…');
        offerDialog.showModal();

        try {
            const data = await api('/treatment-packages?offer_enabled=1&business_id=' + item.business_id);
            if (request !== dialogRequest) {
                return;
            }

            const packages = data.packages.filter((pkg) => (
                (!pkg.branch_id || !item.branch_id || pkg.branch_id === item.branch_id)
                && (!pkg.valid_from || pkg.valid_from.slice(0, 10) <= root.dataset.today)
                && (!pkg.valid_until || pkg.valid_until.slice(0, 10) >= root.dataset.today)
            ));
            selectedPackageById = new Map(packages.map((pkg) => [String(pkg.id), pkg]));
            setOptions(offerForm.elements.namedItem('package'), 'Paket seçin', packages.map((pkg) => [pkg.id, pkg.name]));
            const requested = packages.find((pkg) => pkg.id === item.treatment_package_id);
            if (requested) {
                offerForm.elements.namedItem('package').value = requested.id;
                fillOfferPackage(requested);
            }
            offerForm.hidden = !packages.length;
            showStatus(status, packages.length ? '' : 'Bu görüşme için teklif gönderilebilecek aktif paket yok.');
        } catch (error) {
            if (request === dialogRequest) {
                showStatus(status, error.message);
            }
        }
    }

    function resetSession(session) {
        session.request++;
        session.doctors = [];
        setOptions(session.doctor, 'Doktor seçin', []);
        setOptions(session.time, 'Saat seçin', []);
        session.doctor.disabled = true;
        session.time.disabled = true;
        showStatus(session.status);
    }

    async function loadSlots(session) {
        resetSession(session);
        if (!branchField.value || !session.date.value) {
            return;
        }

        const request = session.request;
        showStatus(session.status, 'Uygun saatler yükleniyor…');

        try {
            const query = new URLSearchParams({
                branch_id: branchField.value,
                treatment_id: session.treatmentId,
                date: session.date.value,
            });
            const result = await api('/offers/' + bookingOffer.id + '/appointments/options?' + query);
            if (request !== session.request || !bookingDialog.open) {
                return;
            }

            session.doctors = result.doctors;
            setOptions(session.doctor, 'Doktor seçin', result.doctors.map((doctor) => [doctor.id, doctor.name]));
            session.doctor.disabled = !result.doctors.length;
            showStatus(session.status, result.doctors.length ? '' : 'Bu tarihte uygun saat yok.');
        } catch (error) {
            if (request === session.request && bookingDialog.open) {
                showStatus(session.status, error.message);
            }
        }
    }

    function addSession(treatment, number) {
        const row = get('session-template').content.firstElementChild.cloneNode(true);
        row.querySelector('[data-session-name]').textContent = treatment.name + ' · ' + number + '. seans';

        const session = {
            treatmentId: treatment.treatment_id,
            date: row.querySelector('[data-date]'),
            doctor: row.querySelector('[data-doctor]'),
            time: row.querySelector('[data-time]'),
            status: row.querySelector('[data-session-status]'),
            doctors: [],
            request: 0,
        };
        session.date.min = root.dataset.today;
        resetSession(session);
        session.date.addEventListener('change', () => loadSlots(session));
        session.doctor.addEventListener('change', () => {
            const doctor = session.doctors.find((item) => String(item.id) === session.doctor.value);
            setOptions(session.time, 'Saat seçin', (doctor?.slots ?? []).map((slot) => [slot, slot.slice(11, 16)]));
            session.time.disabled = !doctor;
        });
        sessionList.append(row);
        sessions.push(session);
    }

    async function openBookingDialog(offer) {
        const request = ++dialogRequest;
        bookingOffer = offer;
        bookingForm.reset();
        bookingForm.hidden = true;
        sessions = [];
        sessionList.replaceChildren();
        get('booking-offer-title').textContent = offer.title;
        const status = bookingDialog.querySelector('[data-status]');
        showStatus(status, 'Randevu seçenekleri yükleniyor…');
        bookingDialog.showModal();

        try {
            const data = await api('/offers/' + offer.id + '/appointments/options');
            if (request !== dialogRequest) {
                return;
            }

            if (!data.branches.length) {
                showStatus(status, 'Paketin tüm tedavileri için uygun şube bulunamadı.');
                return;
            }

            setOptions(branchField, 'Şube seçin', data.branches.map((branch) => [branch.id, branch.name]));
            if (data.branches.length === 1) {
                branchField.value = data.branches[0].id;
            }

            for (const treatment of data.treatments) {
                for (let number = 1; number <= treatment.quantity; number++) {
                    addSession(treatment, number);
                }
            }

            bookingForm.hidden = false;
            showStatus(status);
        } catch (error) {
            if (request === dialogRequest) {
                showStatus(status, error.message);
            }
        }
    }

    function onSubmit(form, status, handler) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]');
            if (submit.disabled) {
                return;
            }

            submit.disabled = true;
            showStatus(status);
            try {
                await handler(new FormData(form));
            } catch (error) {
                showStatus(form.closest('dialog')?.open === false ? notice : status, error.message);
            } finally {
                submit.disabled = false;
            }
        });
    }

    onSubmit(signIn, notice, async (fields) => {
        const result = await api('/login', Object.fromEntries(fields));
        token = result.token;
        try {
            user = (await api('/me')).user;
        } catch (error) {
            endSession();
            throw error;
        }

        signIn.reset();
        get('account-name').textContent = user.name;
        catalogButton.hidden = ownedBusinesses().length === 0;
        get('account').hidden = false;
        syncPage();

        const requested = pendingPackage;
        pendingPackage = undefined;
        if (requested) {
            try {
                await startConversation(requested);
            } catch (error) {
                if (user) {
                    await loadConversations(1);
                }
                throw error;
            }
        } else {
            await loadConversations(1);
        }
    });

    get('logout').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            await api('/logout', {});
            endSession();
            showStatus(notice, 'Oturum kapatıldı.');
        } catch (error) {
            showStatus(notice, error.message);
        } finally {
            button.disabled = false;
        }
    });

    get('previous-page').addEventListener('click', () => {
        loadConversations(currentPage - 1).catch((error) => showStatus(notice, error.message));
    });
    get('next-page').addEventListener('click', () => {
        loadConversations(currentPage + 1).catch((error) => showStatus(notice, error.message));
    });
    newOfferButton.addEventListener('click', () => openOfferDialog(conversation));

    function fillOfferPackage(pkg) {
        offerForm.elements.namedItem('title').value = pkg.name;
        offerForm.elements.namedItem('amount').value = pkg.price ?? '';
        offerForm.elements.namedItem('currency').value = pkg.currency;
    }

    offerForm.elements.namedItem('package').addEventListener('change', (event) => {
        const pkg = selectedPackageById.get(event.target.value);
        if (pkg) {
            fillOfferPackage(pkg);
        }
    });

    onSubmit(offerForm, offerDialog.querySelector('[data-status]'), async (fields) => {
        const item = conversation;
        await api('/conversations/' + item.id + '/offers', {
            treatment_package_id: Number(fields.get('package')),
            title: fields.get('title').trim(),
            amount: fields.get('amount'),
            currency: fields.get('currency').toUpperCase(),
            description: fields.get('description').trim() || null,
            valid_until: fields.get('valid_until') ? new Date(fields.get('valid_until')).toISOString() : null,
        });
        offerDialog.close();
        showStatus(notice, 'Paket teklifi hastaya gönderildi.');
        await loadOffers(item);
    });

    onSubmit(rejectForm, rejectStatus, async (fields) => {
        const { offer, item } = rejection;
        await api('/offers/' + offer.id + '/reject', {
            rejection_reason: fields.get('rejection_reason').trim() || null,
        });
        rejectDialog.close();
        showStatus(notice, 'Paket teklifi reddedildi.');
        await loadOffers(item);
    });

    branchField.addEventListener('change', () => sessions.forEach(loadSlots));
    onSubmit(bookingForm, bookingDialog.querySelector('[data-status]'), async () => {
        if (sessions.some((session) => !session.doctor.value || !session.time.value)) {
            throw new Error('Her seans için doktor ve saat seçin.');
        }

        const item = conversation;
        const result = await api('/offers/' + bookingOffer.id + '/appointments', {
            branch_id: Number(branchField.value),
            sessions: sessions.map((session) => ({
                treatment_id: session.treatmentId,
                doctor_id: Number(session.doctor.value),
                starts_at: session.time.value,
            })),
        });

        bookingDialog.close();
        showStatus(notice, result.appointments.length + ' seans için randevu oluşturuldu.');
        await loadOffers(item);
    });

    onSubmit(appointmentForm, appointmentNotice, async (fields) => {
        const { appointment, action, refresh } = appointmentAction;
        const body = {};
        const reason = (fields.get('reason') ?? '').trim();

        if (action === 'cancel') {
            if (!reason) {
                throw new Error('İptal nedeni yazın.');
            }
            body.cancellation_reason = reason;
        }
        if (action === 'reschedule') {
            body.starts_at = fields.get('starts_at');
            body.reason = reason || null;
        }

        await api('/appointments/' + appointment.id + '/' + action, body);
        appointmentDialog.close();
        showStatus(notice, 'Randevu güncellendi.');
        await refresh();
    });

    for (const dialog of [offerDialog, rejectDialog, bookingDialog, appointmentDialog]) {
        const pending = () => dialog.querySelector('[type="submit"]').disabled;
        dialog.querySelector('[data-close]').addEventListener('click', () => {
            if (!pending()) {
                dialog.close();
            }
        });
        dialog.addEventListener('cancel', (event) => {
            if (pending()) {
                event.preventDefault();
            }
        });
        dialog.addEventListener('close', () => {
            dialogRequest++;
            if (dialog === bookingDialog) {
                sessions.forEach(resetSession);
            }
        });
    }

    syncPage();
}
