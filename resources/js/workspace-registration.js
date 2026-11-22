export function mountWorkspaceRegistration(dialog, { api, onRegistered, onApplicationSubmitted }) {
    const find = (selector) => dialog.querySelector(selector);
    const patient = find('[data-registration-patient]');
    const clinic = find('[data-registration-business]');
    const title = find('#registration-title');
    const status = find('[data-registration-status]');
    const closeButton = find('[data-registration-close]');
    let contract;
    let request = 0;
    let submitting = false;

    function showStatus(message) {
        status.textContent = message;
        status.hidden = !message;
    }

    async function loadContract(pending) {
        clinic.hidden = true;
        clinic.elements.namedItem('contract_accepted').checked = false;
        contract = undefined;
        showStatus('Klinik üyelik sözleşmesi yükleniyor…');
        try {
            const published = await api('/register/business/contract');
            if (pending !== request) {
                return;
            }

            contract = published;
            find('[data-contract-heading]').textContent = contract.title + ' · Sürüm ' + contract.version;
            find('[data-contract-content]').textContent = contract.content;
            find('[data-contract-heading]').closest('details').open = false;
            clinic.hidden = false;
            showStatus('');
        } catch (error) {
            if (pending === request) {
                showStatus(error.message);
            }
        }
    }

    function close() {
        request++;
        dialog.close();
        patient.reset();
        clinic.reset();
    }

    closeButton.addEventListener('click', () => {
        if (!submitting) {
            close();
        }
    });
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (!submitting) {
            close();
        }
    });

    for (const form of [patient, clinic]) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitting) {
                return;
            }

            const clinicRegistration = form === clinic;
            if (clinicRegistration && !contract) {
                return;
            }

            const fields = Object.fromEntries(new FormData(form));
            if (fields.password !== fields.password_confirmation) {
                showStatus('Şifreler eşleşmiyor.');
                return;
            }
            if (clinicRegistration) {
                fields.platform_contract_id = contract.id;
                fields.contract_accepted = true;
            }

            const submit = form.querySelector('[type="submit"]');
            submitting = true;
            closeButton.disabled = true;
            submit.disabled = true;
            showStatus('');
            try {
                const result = await api(clinicRegistration ? '/register/business/application' : '/register/patient', fields);
                close();
                if (clinicRegistration) {
                    onApplicationSubmitted();
                } else {
                    await onRegistered(result.token);
                }
            } catch (error) {
                if (dialog.open && clinicRegistration && error.status === 409) {
                    await loadContract(++request);
                    if (contract) {
                        showStatus(error.message);
                    }
                } else {
                    showStatus(error.message);
                }
            } finally {
                submitting = false;
                closeButton.disabled = false;
                submit.disabled = false;
            }
        });
    }

    return {
        open(type) {
            const pending = ++request;
            patient.reset();
            clinic.reset();
            patient.hidden = type !== 'patient';
            clinic.hidden = true;
            title.textContent = type === 'patient' ? 'Hasta hesabı oluştur' : 'Klinik başvurusu yap';
            showStatus('');
            dialog.showModal();
            if (type === 'business') {
                loadContract(pending);
            }
        },
    };
}
