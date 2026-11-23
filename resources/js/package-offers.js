import { mountPackageCatalog } from './package-catalog';
import { mountPackageServiceAdministration } from './package-service-administration';
import { mountPackageDiscovery } from './package-discovery';
import { mountPackageConversation } from './package-conversation';
import { mountWorkspaceNotifications, platformNotificationTarget } from './workspace-notifications';
import { mountWorkspaceRegistration } from './workspace-registration';
import { mountClinicApplications } from './clinic-applications';
import { mountClinicCrm } from './clinic-crm';
import { mountSupportTickets } from './support-tickets';
import { mountOfferTravelPlan } from './offer-travel-plan';
import { clinicToday } from './clinic-calendar';
import { mountPatientAppointments, patientAppointmentsPath } from './patient-appointments';
import { mountPatientJourney } from './patient-journey';
import { mountPatientBooking } from './patient-booking';
import { createWorkspaceApi } from './workspace-api';

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

const pendingPackageKey = 'medloby-pending-package';

function restorePackageChoice() {
    try {
        const choice = JSON.parse(sessionStorage.getItem(pendingPackageKey));
        return Number.isSafeInteger(choice?.id) && choice.id > 0 && typeof choice.name === 'string' ? choice : undefined;
    } catch {
        return undefined;
    }
}

export function mountPackageOffers(root) {
    const get = (id) => root.querySelector('#' + id);
    const api = createWorkspaceApi(() => token, endSession);
    const notice = get('page-message');
    const signIn = get('sign-in-form');
    const offerList = get('offer-list');
    const newOfferButton = get('new-offer');
    const returnToLead = get('conversation-return-to-lead');
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
    const appointmentDay = appointmentForm.elements.namedItem('slot_date');
    const appointmentSlot = appointmentForm.elements.namedItem('slot_start');
    let appointmentSlotRequest = 0;
    const sessionList = get('booking-sessions');
    const conversationList = get('conversation-list');
    const branchField = bookingForm.elements.namedItem('branch');
    const catalogButton = get('catalog-button');
    const platformAdmin = get('platform-admin-link');
    const packageServiceAdministration = mountPackageServiceAdministration(get('package-service-dialog'), api);
    const packageCatalog = mountPackageCatalog(get('catalog-dialog'), api, packageServiceAdministration.open);
    const clinicApplications = mountClinicApplications(get('clinic-applications'), {
        api,
        timeLabel: (value) => offerDeadline.format(new Date(value)),
        onChanged: syncPage,
    });
    const clinicCrm = mountClinicCrm(get('clinic-crm'), api, {
        openBookingDialog, openAppointmentAction, appointmentActionLabels, openConversation: openCrmConversation,
    });
    const supportTickets = mountSupportTickets(get('support-tickets-dialog'), get('support-tickets-button'), {
        api,
        timeLabel: (value) => offerDeadline.format(new Date(value)),
    });
    const travelPlan = mountOfferTravelPlan(get('offer-travel-dialog'), api);
    const patientAppointments = mountPatientAppointments(get('patient-appointments'), {
        api,
        renderAppointment: (appointment) => renderScheduledAppointment(appointment),
    });
    const patientJourney = mountPatientJourney(get('patient-journey'), { api, openActivity: openJourneyActivity });
    const patientBooking = mountPatientBooking(get('patient-booking'), {
        api, today: clinicToday, setOptions,
        onBooked(appointment) {
            showStatus(notice, appointment.status === 'confirmed' ? 'Randevunuz onaylandı.' : 'Randevu talebiniz alındı. Klinik onayı bekleniyor.');
            window.location.hash = 'randevular/' + appointment.id;
            syncPage();
            get('page-title').focus();
        },
    });
    const discoverySection = get('package-discovery');
    const discovery = mountPackageDiscovery(discoverySection, { api, continueInAccount });
    const confirmPackage = get('package-request-confirm');
    const cancelPackage = get('package-request-cancel');
    const thread = mountPackageConversation(get('package-conversation'), {
        api,
        downloadAttachment,
        timeLabel: (value) => offerDeadline.format(new Date(value)),
    });

    const notifications = mountWorkspaceNotifications(
        get('notifications-dialog'),
        get('notifications-button'),
        get('notifications-count'),
        {
            api,
            openTarget: openNotificationTarget,
            focusTarget: focusNotificationTarget,
            timeLabel: (value) => offerDeadline.format(new Date(value)),
        },
    );

    const registration = mountWorkspaceRegistration(get('registration-dialog'), {
        api,
        onApplicationSubmitted() {
            showStatus(notice, 'Klinik başvurunuz alındı. Başvuru sonucu e-postayla bildirilecek. Onaylanırsa giriş bilgileriniz de e-postayla gönderilecek.');
        },
        async onRegistered(sessionToken) {
            try {
                await activateSession(sessionToken);
            } catch (error) {
                showStatus(notice, error.message);
            }
        },
    });

    let token;
    let user;
    let staffCrmIds = new Set();
    let conversation;
    let crmConversationOrigin = null;
    let pendingPackage = restorePackageChoice();
    let currentPage = 1;
    let selectedPackageById = new Map();
    let bookingContext;
    let rejection;
    let appointmentAction;
    let sessions = [];
    let offerRequest = 0;
    let pageRequest = 0;
    let dialogRequest = 0;

    async function downloadAttachment(attachment) {
        const sessionToken = token;
        const blob = await api('/attachments/' + attachment.id + '/view', undefined, 'GET', true);
        if (sessionToken !== token) {
            return;
        }
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = attachment.original_name;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function selectedClinicCrm() {
        return /^#klinik-crm\/([1-9][0-9]*)$/.exec(window.location.hash)?.[1];
    }

    function canOpenClinicCrm(businessId) {
        return Boolean(businessId && (
            String(user.default_crm_business_id) === businessId
            || ownedBusinesses().some((item) => String(item.id) === businessId && item.status === 'active' && item.is_verified)
            || clinicApplications.canOpenCrm(businessId) || staffCrmIds.has(businessId)
        ));
    }

    function selectedPatientAppointments() {
        return /^#randevular(?:\/([1-9][0-9]*))?$/.exec(window.location.hash);
    }

    function syncPage(preparedAppointment = null) {
        const browsing = window.location.hash === '#katalog';
        const openingCatalogue = browsing && discoverySection.hidden;
        const verified = Boolean(user?.email_verified_at || user?.staff_business_id);
        const crmBusiness = selectedClinicCrm();
        const inCrm = verified && canOpenClinicCrm(crmBusiness);
        const appointmentsTarget = selectedPatientAppointments();
        const canSeeAppointments = Boolean(user?.email_verified_at && user.patient_profile_exists);
        const inAppointments = Boolean(canSeeAppointments && appointmentsTarget);
        const inJourney = canSeeAppointments && window.location.hash === '#tedavi-surecim';
        const bookingSelection = clinicBookingSelection();
        const outsideConversations = browsing || inCrm || inAppointments || inJourney || bookingSelection !== null;
        const requestingPackage = !outsideConversations && verified && user.patient_profile_exists && pendingPackage;

        discoverySection.hidden = !browsing;
        get('package-request').hidden = !requestingPackage;
        get('package-request-name').textContent = requestingPackage ? pendingPackage.name : '';
        get('page-title').textContent = inCrm ? 'Klinik CRM' : browsing ? 'Tedavi paketleri' : inAppointments ? 'Randevularım'
            : inJourney ? 'Tedavi sürecim' : bookingSelection ? 'Randevu al' : 'Paket teklifleri';
        document.title = get('page-title').textContent + ' | Medloby';
        get('discovery-link').setAttribute('aria-current', browsing ? 'page' : 'false');
        get('offers-link').setAttribute('aria-current', outsideConversations ? 'false' : 'page');
        get('patient-appointments-link').hidden = !canSeeAppointments;
        get('patient-appointments-link').setAttribute('aria-current', inAppointments ? 'page' : 'false');
        get('patient-journey-link').hidden = !canSeeAppointments;
        get('patient-journey-link').setAttribute('aria-current', inJourney ? 'page' : 'false');
        get('sign-in').hidden = browsing || Boolean(user);
        get('sign-in-description').textContent = bookingSelection
            ? 'Seçtiğiniz şubede randevu almak için hasta hesabınızla giriş yapın.'
            : 'Klinik veya hasta hesabınızla görüşmelerinizdeki paket tekliflerine erişin.';
        get('email-verification').hidden = browsing || !user || verified;
        get('verification-address').textContent = user?.email ?? '';
        get('booking-return').hidden = bookingSelection === null;
        get('booking-account-required').hidden = !bookingSelection || !user || user.patient_profile_exists;
        get('workspace').hidden = outsideConversations || !verified;
        if (get('workspace').hidden) {
            crmConversationOrigin = null;
        }
        syncConversationReturn();

        clinicApplications.setVisible(!outsideConversations && Boolean(user));
        get('staff-crm').hidden = outsideConversations || !verified || staffCrmIds.size === 0;
        clinicCrm.setBusiness(inCrm ? crmBusiness : null);
        patientAppointments.setView(inAppointments, appointmentsTarget?.[1] ?? null, preparedAppointment);
        patientJourney.setVisible(inJourney);
        patientBooking.setSelection(canSeeAppointments ? bookingSelection : null);

        if (openingCatalogue) {
            discovery.open();
        }
    }

    window.addEventListener('hashchange', () => syncPage());

    function clinicBookingSelection() {
        const match = /^#randevu-al\/([1-9][0-9]*)(?:\/([1-9][0-9]*)(?:\/([1-9][0-9]*)\/(\d{4}-\d{2}-\d{2}))?)?$/.exec(window.location.hash);
        return match ? { branch: match[1], doctor: match[2] ?? null, treatment: match[3] ?? null, date: match[4] ?? null } : null;
    }

    function rememberPackage(pkg) {
        pendingPackage = pkg;
        try {
            if (pkg) {
                sessionStorage.setItem(pendingPackageKey, JSON.stringify({ id: pkg.id, name: pkg.name }));
            } else {
                sessionStorage.removeItem(pendingPackageKey);
            }
        } catch {
            // Keep the choice in the current page when tab storage is unavailable.
        }
    }

    function continueInAccount(pkg) {
        const canRequest = !user || user.patient_profile_exists;
        rememberPackage(canRequest ? pkg : undefined);
        window.location.hash = 'gorusmeler';
        syncPage();
        if (!canRequest) {
            showStatus(notice, 'Paket görüşmesi başlatmak için hasta hesabınızla giriş yapın.');
        } else if (!user) {
            showStatus(notice, pkg.name + ' için hasta hesabınızla giriş yapın veya hesap oluşturun.');
            signIn.elements.namedItem('identifier').focus();
        } else if (!user.email_verified_at) {
            showStatus(notice, pkg.name + ' için e-posta adresinizi doğrulayın.');
            get('check-verification').focus();
        } else {
            showStatus(notice);
            confirmPackage.focus();
        }
    }

    confirmPackage.addEventListener('click', async () => {
        const actor = user;
        const requested = pendingPackage;
        if (!actor?.email_verified_at || !actor.patient_profile_exists || !requested || confirmPackage.disabled) {
            return;
        }
        confirmPackage.disabled = true;
        cancelPackage.disabled = true;
        try {
            const item = await api('/conversations', { treatment_package_id: requested.id });
            if (user === actor && pendingPackage === requested) {
                rememberPackage(undefined);
                syncPage();
                await loadConversations(1, item);
                if (user === actor) {
                    showStatus(notice, 'Paket görüşmesi açıldı.');
                }
            }
        } catch (error) {
            if (user === actor) {
                showStatus(notice, error.message);
            }
        } finally {
            confirmPackage.disabled = false;
            cancelPackage.disabled = false;
        }
    });

    cancelPackage.addEventListener('click', () => {
        rememberPackage(undefined);
        syncPage();
        showStatus(notice);
        get('offers-link').focus();
    });

    async function openJourneyActivity(item) {
        const actor = user;
        if (!item.conversation_id) {
            const patientAppointment = await api(patientAppointmentsPath + '/' + item.target_id);
            if (user === actor) {
                focusNotificationTarget({ patientAppointment });
            }
            return;
        }

        try {
            const target = await openNotificationTarget({
                conversation_id: item.conversation_id,
                offer_id: item.kind.startsWith('offer_') ? item.target_id : null,
            });
            if (target && user === actor) {
                focusNotificationTarget(target);
            }
        } catch (error) {
            if (user === actor && window.location.hash === '#gorusmeler') {
                showStatus(notice, error.message);
            } else {
                throw error;
            }
        }
    }

    async function openNotificationTarget(data) {
        const actor = user;
        const platformTarget = platformNotificationTarget(data);
        if (platformTarget && actor?.is_admin && actor.email_verified_at) {
            return { platformTarget };
        }
        const supportTicketId = Number(data.support_ticket_id);
        if (Number.isSafeInteger(supportTicketId) && supportTicketId > 0) {
            const supportTicket = await supportTickets.fetchTicket(supportTicketId);
            return user === actor ? { supportTicket } : null;
        }
        const waitingListId = Number(data.waiting_list_id);
        if (Number.isSafeInteger(waitingListId) && waitingListId > 0) {
            const booking = await api(patientAppointmentsPath + '/waiting-list/' + waitingListId);
            return user === actor ? { booking } : null;
        }
        const followUpId = Number(data.crm_follow_up_id);
        if (Number.isSafeInteger(followUpId) && followUpId > 0) {
            const followUp = await api('/branches/' + Number(data.branch_id) + '/crm/follow-ups/' + followUpId);
            if (user !== actor) {
                return null;
            }
            if (!followUp.crm_lead_id) {
                throw new Error('Bu takip artık bu şubedeki bir adaya bağlı değil.');
            }
            return { followUp };
        }
        const applicationId = Number(data.clinic_application_id);
        if (Number.isSafeInteger(applicationId) && applicationId > 0) {
            if (data.notification_type === 'clinic_application_submitted') {
                throw new Error('Klinik başvurularını incelemek için doğrulanmış yönetici hesabı gerekir.');
            }

            const applicationHeading = await clinicApplications.getApplicationHeading(applicationId);
            if (!applicationHeading || user !== actor) {
                throw new Error('Bu klinik başvurusu hesabınızdan görüntülenemiyor.');
            }
            window.location.hash = 'gorusmeler';
            syncPage();
            return { applicationHeading };
        }

        let conversationId = Number(data.conversation_id);
        let offerId = data.offer_id;
        const appointmentId = Number(data.appointment_id);
        const hasAppointment = Number.isSafeInteger(appointmentId) && appointmentId > 0;
        if (hasAppointment && actor?.email_verified_at && actor.patient_profile_exists) {
            try {
                const patientAppointment = await api(patientAppointmentsPath + '/' + appointmentId);
                return user === actor ? { patientAppointment } : null;
            } catch (error) {
                if (user !== actor) {
                    return null;
                }
                if (error.status !== 404) {
                    throw error;
                }
            }
        }

        const appointmentBranchId = Number(data.branch_id);
        if (hasAppointment && Number.isSafeInteger(appointmentBranchId) && appointmentBranchId > 0) {
            const clinicAppointment = await api(`/branches/${appointmentBranchId}/clinic-calendar/appointments/${appointmentId}/location`);
            if (user !== actor) {
                return null;
            }

            const businessId = String(clinicAppointment.business_id);
            if (!canOpenClinicCrm(businessId)) {
                await loadStaffCrm();
            }
            if (user !== actor) {
                return null;
            }
            if (!canOpenClinicCrm(businessId)) {
                throw new Error('Bu kliniğin çalışma alanına erişim yetkiniz yok.');
            }

            return { clinicAppointment };
        }

        if (!conversationId && hasAppointment) {
            const appointment = await api('/appointments/' + appointmentId);
            conversationId = appointment.offer?.conversation_id;
            offerId = appointment.offer?.id;
        }
        conversationId = Number(conversationId);
        if (!Number.isSafeInteger(conversationId) || conversationId <= 0 || user !== actor) {
            return null;
        }

        const { conversation: item } = await api('/conversations/' + conversationId);
        if (user !== actor) {
            return null;
        }
        await showConversationPage(item);
        return { offerId, appointmentId };
    }

    async function showConversationPage(item) {
        window.location.hash = 'gorusmeler';
        syncPage();
        await loadConversations(1, item);
    }

    function syncConversationReturn() {
        returnToLead.hidden = !crmConversationOrigin || crmConversationOrigin.actor !== user
            || crmConversationOrigin.conversationId !== conversation?.id;
    }

    async function openCrmConversation(item, origin) {
        const actor = user;
        crmConversationOrigin = { ...origin, conversationId: item.id, actor };
        try {
            await showConversationPage(item);
            if (user === actor && window.location.hash === '#gorusmeler' && conversation?.id === item.id) {
                thread.openLatest();
            }
        } catch (error) {
            if (user === actor && window.location.hash === '#gorusmeler') {
                showStatus(notice, error.message);
            }
        }
    }

    returnToLead.addEventListener('click', async () => {
        if (returnToLead.hidden || returnToLead.disabled) {
            return;
        }

        const origin = crmConversationOrigin;
        returnToLead.disabled = true;
        try {
            const preparedLead = await api('/branches/' + origin.branch_id + '/crm/leads/' + origin.crm_lead_id);
            if (crmConversationOrigin !== origin || returnToLead.hidden) {
                return;
            }
            window.location.hash = 'klinik-crm/' + origin.business_id;
            syncPage();
            await clinicCrm.openLead(origin, { openWork: true, preparedLead });
        } catch (error) {
            if (crmConversationOrigin === origin && !returnToLead.hidden) {
                showStatus(notice, error.message);
            }
        } finally {
            returnToLead.disabled = false;
        }
    });

    function focusNotificationTarget(target) {
        if (target.platformTarget) {
            const { section, id } = target.platformTarget;
            window.location.assign(platformAdmin.href + '#' + section + '/' + id);
            return;
        }
        if (target.supportTicket) {
            supportTickets.open(target.supportTicket);
            return;
        }
        if (target.booking) {
            const { branch_id, doctor_id, treatment_id, date } = target.booking;
            window.location.hash = `randevu-al/${branch_id}/${doctor_id}/${treatment_id}/${date}`;
            syncPage();
            get('page-title').focus();
            return;
        }
        if (target.patientAppointment) {
            window.location.hash = 'randevular/' + target.patientAppointment.id;
            syncPage(target.patientAppointment);
            get('page-title').focus();
            return;
        }
        if (target.clinicAppointment) {
            window.location.hash = 'klinik-crm/' + target.clinicAppointment.business_id;
            syncPage();
            clinicCrm.openAppointment(target.clinicAppointment);
            return;
        }
        if (target.followUp) {
            window.location.hash = 'klinik-crm/' + target.followUp.business_id;
            syncPage();
            clinicCrm.openLead(target.followUp, { followUpId: target.followUp.id });
            return;
        }
        if (target.applicationHeading) {
            target.applicationHeading.focus();
            return;
        }
        if (!target.offerId) {
            thread.openLatest();
            return;
        }

        const card = offerList.querySelector('[data-offer-id="' + Number(target.offerId) + '"]');
        if (card && target.appointmentId) {
            const schedule = card.querySelector('[data-schedule]');
            if (!schedule.hidden) {
                schedule.open = true;
                schedule.querySelector('summary').focus();
                return;
            }
        }

        (card ?? get('conversation-title')).focus();
    }

    function endSession() {
        token = undefined;
        user = undefined;
        conversation = undefined;
        rememberPackage(undefined);
        bookingContext = undefined;
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
        packageServiceAdministration.close();
        packageCatalog.close();
        notifications.stop();
        supportTickets.stop();
        travelPlan.stop();
        clinicApplications.stop();
        staffCrmIds.clear();
        get('staff-crm-list').replaceChildren();
        catalogButton.hidden = true;
        platformAdmin.hidden = true;
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
        if (!get('workspace').hidden) {
            showStatus(notice, 'Görüşmeler yükleniyor…');
        }
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

        if (!get('workspace').hidden) {
            showStatus(notice);
        }
        if (items.length) {
            await showConversation(items.find((item) => item.id === preferred?.id) ?? items[0]);
        } else {
            conversation = undefined;
            syncConversationReturn();
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
        syncConversationReturn();
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
        card.dataset.offerId = offer.id;
        card.tabIndex = -1;
        const snapshot = offer.package_snapshot;
        const hotelIncluded = [true, 1].includes(snapshot.includes_hotel);
        const transferIncluded = [true, 1].includes(snapshot.includes_transfer);
        const inclusionList = [hotelIncluded && 'Konaklama dahil', transferIncluded && 'Transfer dahil'].filter(Boolean);
        const booked = offer.appointments_exists;
        card.querySelector('[data-title]').textContent = offer.title;
        card.querySelector('[data-price]').textContent = priceLabel(offer.amount, offer.currency);
        const deadline = offer.valid_until ? new Date(offer.valid_until) : null;
        showStatus(card.querySelector('[data-valid-until]'), deadline ? 'Geçerlilik sonu: ' + offerDeadline.format(deadline) : '');
        card.querySelector('[data-offer-status]').textContent = booked
            ? 'Randevular oluşturuldu'
            : statusLabels[offer.status] ?? offer.status;
        showStatus(card.querySelector('[data-description]'), offer.description ?? '');
        const travelInclusions = card.querySelector('[data-travel-inclusions]');
        travelInclusions.textContent = inclusionList.join(' · ');
        travelInclusions.hidden = inclusionList.length === 0;

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
        if (isPatient(item) && offer.status === 'pending') {
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
        } else if (isPatient(item) && offer.status === 'accepted' && !booked && !offer.deadline_passed) {
            const plan = element('button', 'Seansları planla', 'offer-button');
            plan.type = 'button';
            plan.addEventListener('click', () => openBookingDialog(offer));
            actions.append(plan);
        }
        if (offer.status === 'accepted' && inclusionList.length) {
            const showTravel = element('button', 'Seyahat planı', 'offer-button offer-button-secondary');
            showTravel.type = 'button';
            showTravel.addEventListener('click', () => travelPlan.open(offer));
            actions.append(showTravel);
        }
        actions.hidden = !actions.childElementCount;

        if (booked) {
            connectSchedule(card.querySelector('[data-schedule]'), offer, isPatient(item));
        }

        return card;
    }

    function connectSchedule(details, offer, patient) {
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
                const appointments = await api('/offers/' + offer.id + '/appointments' + (patient ? '?include_patient_actions=1' : ''));
                list.replaceChildren(...appointments.map((appointment) => renderScheduledAppointment(appointment, {
                    actions: patient ? appointment.patient_actions : appointment.actions, patient, refresh,
                })));
                showStatus(status, appointments.length ? '' : 'Bu teklife ait randevu bulunamadı.');
            } catch (error) {
                showStatus(status, error.message);
            } finally {
                loading = false;
            }
        }

        details.addEventListener('toggle', refresh);
    }

    function renderScheduledAppointment(appointment, { actions, patient, refresh } = {}) {
        const row = element('li', '', 'grid min-w-0 gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900');
        const date = element('time', appointmentDateLabel(appointment.starts_at, appointment.ends_at));
        date.dateTime = appointment.starts_at.replace(' ', 'T');
        const context = [appointment.business_name, appointment.branch_name, appointment.doctor_name].filter(Boolean).join(' · ');
        row.append(
            element('strong', appointment.treatment_name ?? 'Randevu', 'break-words'),
            date,
        );
        if (context) {
            row.append(element('span', context, 'break-words text-slate-600 dark:text-slate-300'));
        }
        if (appointment.offer_title) {
            row.append(element('span', appointment.offer_title, 'break-words text-slate-600 dark:text-slate-300'));
        }
        row.append(element('span', appointmentStatusLabels[appointment.status] ?? appointment.status, 'text-xs font-semibold text-slate-600 dark:text-slate-300'));

        if (actions?.length) {
            const controls = element('div', '', 'mt-2 flex flex-wrap gap-2');
            for (const action of actions) {
                const button = element('button', appointmentActionLabels[action], 'offer-button offer-button-secondary');
                button.type = 'button';
                button.addEventListener('click', () => openAppointmentAction(appointment, action, refresh,
                    patient ? patientAppointmentsPath : undefined, patient));
                controls.append(button);
            }
            row.append(controls);
        }

        return row;
    }

    function openAppointmentAction(appointment, action, refresh, path = '/appointments', patient = false) {
        appointmentAction = { appointment, action, refresh, path, patient };
        appointmentForm.reset();
        showStatus(appointmentNotice);
        get('appointment-action-title').textContent = appointmentActionLabels[action];
        get('appointment-action-context').textContent = [
            appointment.patient,
            appointment.treatment_name,
            appointmentDateLabel(appointment.starts_at, appointment.ends_at),
        ].filter(Boolean).join(' · ');

        const needsDate = action === 'reschedule';
        const needsSlots = needsDate && patient;
        const needsReason = action === 'cancel';
        const date = appointmentForm.elements.namedItem('starts_at');
        const reason = appointmentForm.elements.namedItem('reason');
        date.disabled = !needsDate || needsSlots;
        date.required = !date.disabled;
        date.value = date.disabled ? '' : appointment.starts_at.replace(' ', 'T').slice(0, 16);
        appointmentDay.disabled = !needsSlots;
        appointmentDay.required = needsSlots;
        appointmentDay.value = needsSlots ? appointment.starts_at.slice(0, 10) : '';
        appointmentSlot.disabled = !needsSlots;
        appointmentSlot.required = needsSlots;
        reason.disabled = !needsDate && !needsReason;
        reason.required = needsReason;
        appointmentForm.querySelector('[data-action-start]').hidden = date.disabled;
        appointmentForm.querySelector('[data-action-slots]').hidden = !needsSlots;
        appointmentForm.querySelector('[data-action-reason]').hidden = reason.disabled;
        appointmentForm.querySelector('[data-action-reason-label]').textContent = needsReason ? 'İptal nedeni' : 'Neden (isteğe bağlı)';
        appointmentDialog.showModal();
        if (needsSlots) {
            loadAppointmentSlots();
        }
    }

    async function loadAppointmentSlots() {
        const request = ++appointmentSlotRequest;
        const current = appointmentAction;
        const date = appointmentDay.value;
        const isCurrent = () => appointmentDialog.open && current === appointmentAction && request === appointmentSlotRequest;
        setOptions(appointmentSlot, 'Saat seçin', []);
        showStatus(appointmentNotice, date ? 'Uygun saatler yükleniyor…' : 'Randevu gününü seçin.');
        if (!date) {
            return;
        }

        try {
            const { slots } = await api(current.path + '/' + current.appointment.id + '/reschedule-options?' + new URLSearchParams({ date }));
            if (isCurrent()) {
                setOptions(appointmentSlot, 'Saat seçin', slots.map((slot) => [slot, slot.slice(11, 16)]));
                showStatus(appointmentNotice, slots.length ? '' : 'Bu günde uygun saat yok. Başka bir gün seçin.');
            }
        } catch (error) {
            if (isCurrent()) {
                showStatus(appointmentNotice, error.message);
            }
        }
    }

    appointmentDay.addEventListener('change', loadAppointmentSlots);

    async function openOfferDialog(item) {
        const request = ++dialogRequest;
        offerForm.reset();
        offerForm.hidden = true;
        const status = offerDialog.querySelector('[data-status]');
        showStatus(status, 'Paketler yükleniyor…');
        offerDialog.showModal();

        try {
            const { packages } = await api('/conversations/' + item.id + '/offers/package-options');
            if (request !== dialogRequest) {
                return;
            }

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
            const result = await api(bookingContext.path + '/options?' + query);
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
            notes: row.querySelector('[data-session-notes]'),
            status: row.querySelector('[data-session-status]'),
            doctors: [],
            request: 0,
        };
        session.date.min = clinicToday();
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

    async function openBookingDialog(offer, calendar = null) {
        const request = ++dialogRequest;
        bookingContext = {
            path: calendar?.path ?? '/offers/' + offer.id + '/appointments',
            onBooked: calendar?.onBooked,
        };
        bookingForm.reset();
        bookingForm.hidden = true;
        sessions = [];
        sessionList.replaceChildren();
        get('booking-offer-title').textContent = [offer.title, offer.patient].filter(Boolean).join(' · ');
        const status = bookingDialog.querySelector('[data-status]');
        showStatus(status, 'Randevu seçenekleri yükleniyor…');
        bookingDialog.showModal();

        try {
            const data = await api(bookingContext.path + '/options');
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
            if (calendar?.date >= clinicToday()) {
                for (const session of sessions) {
                    session.date.value = calendar.date;
                    loadSlots(session);
                }
            }
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

    async function loadStaffCrm() {
        const currentToken = token;
        if (!user.business_memberships.some((member) => member.is_active && ['manager', 'staff'].includes(member.role))) {
            return;
        }

        try {
            const clinics = await api('/clinic-crm/staff-workspaces');
            if (currentToken !== token) {
                return;
            }
            staffCrmIds = new Set(clinics.map((clinic) => String(clinic.id)));
            get('staff-crm-list').replaceChildren(...clinics.map((clinic) => {
                const row = document.createElement('li');
                const link = element('a', clinic.name + ' CRM', 'offer-button offer-button-secondary');
                link.href = '#klinik-crm/' + clinic.id;
                row.append(link);
                return row;
            }));
            syncPage();
        } catch (error) {
            if (currentToken === token) {
                showStatus(notice, error.message);
            }
        }
    }

    async function openVerifiedWorkspace(defaultCrmBusinessId = null) {
        const currentToken = token;
        const initialHash = window.location.hash;
        const businesses = ownedBusinesses();
        catalogButton.hidden = businesses.length === 0;
        platformAdmin.hidden = !(user.is_admin && user.email_verified_at);
        notifications.start(businesses);
        supportTickets.start();
        clinicApplications.setAccount(businesses, true);
        const staffWorkspaces = loadStaffCrm();
        if (pendingPackage && !user.patient_profile_exists) {
            rememberPackage(undefined);
            showStatus(notice, 'Paket görüşmesi başlatmak için hasta hesabınızla giriş yapın.');
        }
        const patientPage = user.patient_profile_exists && (selectedPatientAppointments() || initialHash === '#tedavi-surecim');
        if (defaultCrmBusinessId && !clinicBookingSelection() && !patientPage) {
            const clinicId = selectedClinicCrm();
            if (clinicId && !canOpenClinicCrm(clinicId)) {
                await staffWorkspaces;
            }
            if (currentToken !== token) {
                return;
            }
            if (window.location.hash === initialHash && !canOpenClinicCrm(clinicId)) {
                window.location.hash = 'klinik-crm/' + defaultCrmBusinessId;
            }
        }
        syncPage();
        if (!get('package-request').hidden) {
            confirmPackage.focus();
        }
        await loadConversations(1);
    }

    async function activateSession(sessionToken, openDefaultCrm = false) {
        token = sessionToken;
        try {
            const sessionUser = (await api('/me')).user;
            if (token !== sessionToken) {
                return;
            }
            user = sessionUser;
        } catch (error) {
            if (token === sessionToken) {
                endSession();
            }
            throw error;
        }

        signIn.reset();
        get('account-name').textContent = user.name;
        get('account').hidden = false;
        showStatus(notice);
        if (user.email_verified_at || user.staff_business_id) {
            await openVerifiedWorkspace(openDefaultCrm ? user.default_crm_business_id : null);
        } else {
            syncPage();
            clinicApplications.setAccount(ownedBusinesses(), false);
        }
    }

    onSubmit(signIn, notice, async (fields) => {
        const firstPassword = get('first-password');
        const credentials = Object.fromEntries(fields);
        credentials.identifier = credentials.identifier.trim().toLowerCase();
        const result = await api('/login', credentials);
        if (result.password_change_required) {
            firstPassword.hidden = false;
            firstPassword.disabled = false;
            firstPassword.querySelector('input').focus();
            return;
        }

        firstPassword.hidden = true;
        firstPassword.disabled = true;
        await activateSession(result.token, true);
    });

    get('patient-registration').addEventListener('click', () => registration.open('patient'));
    function openClinicRegistration() {
        rememberPackage(undefined);
        syncPage();
        registration.open('business');
    }
    get('clinic-registration').addEventListener('click', openClinicRegistration);

    async function verifyAccount(button, action) {
        button.disabled = true;
        showStatus(notice);
        try {
            await action();
        } catch (error) {
            showStatus(notice, error.message);
        } finally {
            button.disabled = false;
        }
    }

    get('resend-verification').addEventListener('click', (event) => verifyAccount(event.currentTarget, async () => {
        await api('/email/verification-notification', {});
        showStatus(notice, 'Doğrulama e-postası gönderim sırasına alındı.');
    }));

    get('check-verification').addEventListener('click', (event) => verifyAccount(event.currentTarget, async () => {
        const previous = user;
        const verifiedUser = (await api('/me')).user;
        if (user !== previous) {
            return;
        }
        user = verifiedUser;
        if (user.email_verified_at) {
            await openVerifiedWorkspace();
            if (!notice.textContent) {
                showStatus(notice, 'E-posta adresiniz doğrulandı.');
            }
        } else {
            showStatus(notice, 'E-postanız henüz doğrulanmadı. Gelen kutunuzdaki bağlantıyı açın.');
        }
    }));

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
        const { path, onBooked } = bookingContext;
        const result = await api(path, {
            branch_id: Number(branchField.value),
            sessions: sessions.map((session) => ({
                treatment_id: session.treatmentId,
                doctor_id: Number(session.doctor.value),
                starts_at: session.time.value,
                notes: session.notes.value.trim() || null,
            })),
        });

        bookingDialog.close();
        showStatus(notice, result.appointments.length + ' seans için randevu oluşturuldu.');
        if (onBooked) {
            await onBooked(result);
        } else {
            await loadOffers(item);
        }
    });

    onSubmit(appointmentForm, appointmentNotice, async (fields) => {
        const { appointment, action, refresh, path, patient } = appointmentAction;
        const body = {};
        const reason = (fields.get('reason') ?? '').trim();

        if (action === 'cancel') {
            if (!reason) {
                throw new Error('İptal nedeni yazın.');
            }
            body.cancellation_reason = reason;
        }
        if (action === 'reschedule') {
            body.starts_at = fields.get(patient ? 'slot_start' : 'starts_at');
            body.reason = reason || null;
        }

        let result;
        try {
            result = await api(path + '/' + appointment.id + '/' + action, body);
        } catch (error) {
            if (patient && action === 'reschedule' && error.status === 422) {
                await loadAppointmentSlots();
            }
            throw error;
        }
        appointmentDialog.close();
        showStatus(notice, 'Randevu güncellendi.');
        await refresh(result);
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
    if (window.location.hash === '#clinic-registration') {
        openClinicRegistration();
    }
    const pageUrl = new URL(window.location.href);
    if (pageUrl.searchParams.get('email_verified') === '1') {
        showStatus(notice, 'Doğrulamayı kontrol etmek için hesabınıza giriş yapın.');
        pageUrl.searchParams.delete('email_verified');
        history.replaceState(null, '', pageUrl);
    }
}
