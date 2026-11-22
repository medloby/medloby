import { mountCalendarBooking } from './clinic-calendar-booking';
import { mountAppointmentCard } from './clinic-appointment-card';

const weekdays = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
export const appointmentStatuses = {
    pending: 'Bekliyor', confirmed: 'Onaylandı', cancelled: 'İptal edildi',
    completed: 'Tamamlandı', rescheduled: 'Yeniden planlandı', no_show: 'Hasta gelmedi',
};
const editorLabels = {
    overrides: ['Yeni tarih istisnası', 'Tarih istisnasını düzenle', 'İstisnayı ekle'],
    hours: ['Yeni çalışma aralığı', 'Çalışma aralığını düzenle', 'Çalışma aralığını ekle'],
    leaves: ['Yeni izin', 'İzni düzenle', 'İzin ekle'],
    blocks: ['', 'Saat engelini düzenle', 'Saat engelini ekle'],
};
const todayInClinic = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Istanbul' });
const displayDate = new Intl.DateTimeFormat('tr-TR', { dateStyle: 'medium', timeZone: 'UTC' });

export function clinicToday() {
    return todayInClinic.format(new Date());
}

export function dateLabel(value) {
    return displayDate.format(new Date(value.slice(0, 10) + 'T00:00:00Z'));
}

function rangeLabel(start, end) {
    if (start.slice(0, 10) === end.slice(0, 10)) {
        return dateLabel(start);
    }
    return dateLabel(start) + ' – ' + dateLabel(end);
}

export function timeLabel(start, end) {
    const times = [start, end].map((value) => value.slice(11, 16));
    return start.slice(0, 10) === end.slice(0, 10)
        ? times.join(' – ')
        : dateLabel(start) + ' ' + times[0] + ' – ' + dateLabel(end) + ' ' + times[1];
}

export function mountClinicCalendar(panel, api, { openBookingDialog, openAppointmentAction, appointmentActionLabels }) {
    const find = (part, root = panel) => root.querySelector('[data-calendar-' + part + ']');
    const day = find('day');
    const management = find('management');
    const ruleForm = find('rule-form');
    const doctor = find('doctor');
    const appointmentCard = mountAppointmentCard(find('appointment-card'), { api, dateLabel, timeLabel, appointmentStatuses });
    const forms = Object.fromEntries(Array.from(panel.querySelectorAll('[data-calendar-form]'))
        .map((form) => [form.dataset.calendarForm, form]));
    const blockDoctor = forms.blocks.elements.namedItem('doctor_id');
    const weekday = forms.hours.elements.namedItem('day_of_week');
    const booking = mountCalendarBooking(find('booking'), {
        api, openBookingDialog, today: clinicToday,
        onSaved: (start, confirmation) => selectDay(start.slice(0, 10), confirmation),
    });
    let scope = null;

    weekday.replaceChildren(...[1, 2, 3, 4, 5, 6, 0].map((value) => new Option(weekdays[value], value)));

    function showStatus(text = '') {
        find('status').textContent = text;
        find('status').hidden = !text;
    }

    function row(name, meta, ...actions) {
        const item = find('row').content.firstElementChild.cloneNode(true);
        find('name', item).textContent = name;
        find('meta', item).textContent = meta;
        find('actions', item).append(...actions);
        return item;
    }

    function action(label, handler) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'offer-button offer-button-secondary';
        button.textContent = label;
        button.addEventListener('click', handler);
        return button;
    }

    function resetEditor(kind, record = null) {
        const form = forms[kind];
        form.reset();
        if (scope) {
            scope.edits[kind] = record?.id ?? null;
        }
        const [createTitle, editTitle, addLabel] = editorLabels[kind];
        const title = find('form-title', form);
        title.textContent = record ? editTitle : createTitle;
        title.hidden = !title.textContent;
        find('save', form).textContent = record ? 'Değişiklikleri kaydet' : addLabel;
        find('discard', form).hidden = !record;

        for (const field of form.elements) {
            if (!field.name) {
                continue;
            }
            if (record) {
                const value = record[field.name] ?? '';
                field.value = field.type === 'date' ? value.slice(0, 10)
                    : field.type === 'time' ? value.slice(0, 5)
                    : field.type === 'datetime-local' ? value.slice(0, 16).replace(' ', 'T')
                    : value;
            } else if (field.type === 'date') {
                field.value = day.value;
            }
        }
    }

    function openEditor(kind, record) {
        if (!scope || scope.loading || scope.saving) {
            return;
        }
        resetEditor(kind, record);
        if (kind === 'blocks') {
            find('block-editor').open = true;
        }
        forms[kind].querySelector('input, select').focus();
    }

    function endpoint(current, kind, id = null) {
        if (kind === 'hours' && id !== null) {
            return current.path + '/working-hours/' + id;
        }
        const doctorPath = current.path + '/doctors/' + current.doctorId;
        const base = kind === 'hours' ? doctorPath + '/working-hours'
            : kind === 'leaves' ? doctorPath + '/leaves'
            : current.path + '/' + kind;
        return id === null ? base : base + '/' + id;
    }

    function renderAgenda(current, data) {
        find('day-rule').textContent = data.open ? 'Çevrimiçi randevu açık' : 'Çevrimiçi randevu kapalı';
        find('empty-appointments').hidden = data.appointments.length > 0;
        find('appointments').replaceChildren(...data.appointments.map((item) => {
            const actions = item.actions.map((key) => action(appointmentActionLabels[key], () => {
                if (scope === current && !current.loading && !current.saving) {
                    openAppointmentAction({ ...item, treatment_name: item.treatment }, key, (result) => {
                        if (scope === current) {
                            const date = key === 'reschedule' ? result.starts_at.slice(0, 10) : day.value;
                            return selectDay(date, 'Randevu güncellendi.');
                        }
                    }, current.path + '/appointments');
                }
            }));
            const cardButton = action('Randevu kartı', () => {
                if (scope === current) {
                    appointmentCard.open(current.path + '/appointments/' + item.id);
                }
            });
            cardButton.setAttribute('aria-haspopup', 'dialog');
            const entry = row(
                [timeLabel(item.starts_at, item.ends_at), item.patient || 'Hasta adı girilmemiş'].join(' · '),
                [item.doctor, item.treatment, item.package, appointmentStatuses[item.status] ?? item.status].filter(Boolean).join(' · '),
                cardButton,
                ...actions,
            );
            const heading = find('name', entry);
            heading.dataset.appointmentId = item.id;
            heading.tabIndex = -1;
            return entry;
        }));
        booking.show(current.path, day.value, data.can_create_appointments);

        find('empty-blocks').hidden = data.blocks.length > 0;
        find('blocks').replaceChildren(...data.blocks.map((item) => {
            const actions = [];
            if (current.canManage) {
                if (item.doctor_id === null || data.doctors.some((entry) => entry.id === item.doctor_id)) {
                    actions.push(action('Düzenle', () => openEditor('blocks', item)));
                }
                actions.push(action('Engeli kaldır', () => write(current, endpoint(current, 'blocks', item.id), undefined, 'DELETE', 'Saat engeli kaldırıldı.', 'blocks')));
            }
            return row(item.title || 'Saat engeli', [
                timeLabel(item.starts_at, item.ends_at), item.doctor || (item.doctor_id === null ? 'Tüm şube' : 'Hekime özel'),
                item.reason,
            ].filter(Boolean).join(' · '), ...actions);
        }));
        find('agenda').hidden = false;
        find('day-blocks').hidden = false;
    }

    function renderRules(current, rules) {
        ruleForm.elements.namedItem('default_status').value = rules.default_status;
        const upcoming = rules.overrides.filter((record) => record.end_date.slice(0, 10) >= day.value);
        find('overrides').replaceChildren(...upcoming.map((record) => row(
            rangeLabel(record.start_date, record.end_date),
            [record.status === 'open' ? 'Açık' : 'Kapalı', record.reason].filter(Boolean).join(' · '),
            action('Düzenle', () => openEditor('overrides', record)),
            action('İstisnayı kaldır', () => write(current, endpoint(current, 'overrides', record.id), undefined, 'DELETE', 'Tarih istisnası kaldırıldı.', 'overrides')),
        )));
    }

    function renderDoctorSchedule(current, hours, leaves) {
        find('hours').replaceChildren(...hours.map((record) => row(
            weekdays[record.day_of_week] + ' · ' + record.start_time.slice(0, 5) + ' – ' + record.end_time.slice(0, 5),
            record.is_active ? 'Aktif' : 'Pasif',
            action('Düzenle', () => openEditor('hours', record)),
            action(record.is_active ? 'Pasife al' : 'Etkinleştir', () => write(
                current, endpoint(current, 'hours', record.id), { is_active: !record.is_active }, 'PUT', 'Çalışma aralığı kaydedildi.', 'hours',
            )),
        )));
        find('leaves').replaceChildren(...leaves.map((record) => row(
            rangeLabel(record.start_date, record.end_date),
            [record.branch_id === null ? 'Tüm şubeler' : '', record.is_approved ? 'Aktif izin' : 'Pasif izin', record.reason].filter(Boolean).join(' · '),
            action('Düzenle', () => openEditor('leaves', record)),
            record.is_approved
                ? action('İzni kaldır', () => write(current, endpoint(current, 'leaves', record.id), undefined, 'DELETE', 'İzin kaldırıldı.', 'leaves'))
                : action('İzni etkinleştir', () => write(current, endpoint(current, 'leaves', record.id), { is_approved: true }, 'PUT', 'İzin etkinleştirildi.', 'leaves')),
        )));
        find('doctor-schedule').hidden = !current.doctorId;
    }

    function renderDoctors(current, doctors) {
        const names = doctors.map((record) => [record.name, record.id]);
        if (!doctors.some((record) => String(record.id) === current.doctorId)) {
            current.doctorId = '';
        }
        doctor.replaceChildren(new Option('Hekim seçin', ''), ...names.map(([name, id]) => new Option(name, id)));
        doctor.value = current.doctorId;
        find('no-doctors').hidden = doctors.length > 0;
        doctor.disabled = doctors.length === 0;
        const selectedBlockDoctor = blockDoctor.value;
        blockDoctor.replaceChildren(new Option('Tüm şube', ''), ...names.map(([name, id]) => new Option(name, id)));
        if (doctors.some((record) => String(record.id) === selectedBlockDoctor)) {
            blockDoctor.value = selectedBlockDoctor;
        } else if (selectedBlockDoctor) {
            resetEditor('blocks');
        }
    }

    async function load(current, confirmation = '') {
        const pending = ++current.request;
        current.loading = true;
        current.loaded = false;
        management.disabled = true;
        showStatus('Takvim yükleniyor…');
        const isCurrent = () => scope === current && current.request === pending;
        try {
            const [agenda, rules] = await Promise.all([
                api(current.path + '?date=' + encodeURIComponent(day.value)),
                current.canManage ? api(current.path + '/rules') : null,
            ]);
            if (!isCurrent()) {
                return;
            }

            renderAgenda(current, agenda);
            if (current.canManage) {
                renderRules(current, rules);
                renderDoctors(current, agenda.doctors);
                const [hours, leaves] = current.doctorId ? await Promise.all([
                    api(endpoint(current, 'hours')),
                    api(endpoint(current, 'leaves')),
                ]) : [{ working_hours: [] }, { leaves: [] }];
                if (!isCurrent()) {
                    return;
                }
                renderDoctorSchedule(current, hours.working_hours, leaves.leaves);
            }
            management.hidden = !current.canManage;
            current.loaded = true;
            showStatus(confirmation);
        } catch (error) {
            if (isCurrent()) {
                find('agenda').hidden = true;
                find('booking').hidden = true;
                find('day-blocks').hidden = true;
                management.hidden = true;
                showStatus([confirmation, error.message].filter(Boolean).join(' '));
            }
        } finally {
            if (isCurrent()) {
                current.loading = false;
                management.disabled = current.saving;
            }
        }
    }

    async function write(current, path, data, method, confirmation, kind = null) {
        if (scope !== current || current.loading || current.saving) {
            return;
        }
        current.saving = true;
        management.disabled = true;
        showStatus('Takvim değişikliği kaydediliyor…');
        try {
            await api(path, data, method);
            if (scope === current) {
                if (kind) {
                    resetEditor(kind);
                }
                await load(current, confirmation);
            }
        } catch (error) {
            if (scope === current) {
                showStatus(error.message);
            }
        } finally {
            if (scope === current) {
                current.saving = false;
                management.disabled = current.loading;
            }
        }
    }

    function selectDay(value, confirmation = '') {
        if (!scope) {
            return;
        }
        day.value = value || clinicToday();
        find('agenda').hidden = true;
        find('booking').hidden = true;
        find('day-blocks').hidden = true;
        resetEditor('blocks');
        resetEditor('overrides');
        resetEditor('leaves');
        return load(scope, confirmation);
    }

    function moveDay(offset) {
        const date = new Date(day.value + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + offset);
        selectDay(date.toISOString().slice(0, 10));
    }

    panel.addEventListener('toggle', () => {
        if (!panel.open) {
            appointmentCard.close();
        }
        if (panel.open && scope && !scope.loaded && !scope.loading) {
            load(scope);
        }
    });
    day.addEventListener('change', () => selectDay(day.value));
    find('previous').addEventListener('click', () => moveDay(-1));
    find('next').addEventListener('click', () => moveDay(1));
    find('today').addEventListener('click', () => selectDay(clinicToday()));

    doctor.addEventListener('change', () => {
        scope.doctorId = doctor.value;
        resetEditor('hours');
        resetEditor('leaves');
        load(scope);
    });

    ruleForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (scope) {
            write(scope, scope.path + '/rules', Object.fromEntries(new FormData(ruleForm)), 'PUT', 'Varsayılan gün kuralı kaydedildi.');
        }
    });

    for (const [kind, form] of Object.entries(forms)) {
        find('discard', form).addEventListener('click', () => resetEditor(kind));
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!scope) {
                return;
            }
            const payload = Object.fromEntries(Array.from(new FormData(form), ([key, value]) => [key, value.trim() || null]));
            for (const key of ['starts_at', 'ends_at']) {
                if (payload[key]) {
                    const timestamp = payload[key].replace('T', ' ');
                    payload[key] = timestamp.length === 16 ? timestamp + ':00' : timestamp;
                }
            }
            const id = scope.edits[kind] ?? null;
            write(scope, endpoint(scope, kind, id), payload, id === null ? 'POST' : 'PUT', 'Takvim kaydı kaydedildi.', kind);
        });
    }

    return {
        async openAppointment(appointment) {
            const current = scope;
            const date = appointment.starts_at.slice(0, 10);
            panel.open = true;
            await selectDay(date);
            if (scope === current && current?.loaded && day.value === date) {
                const heading = find('appointments').querySelector('[data-appointment-id="' + Number(appointment.id) + '"]');
                (heading ?? day).focus();
            }
        },
        async bookForPatient(patient) {
            const current = scope;
            const today = clinicToday();
            const date = day.value >= today ? day.value : today;
            panel.open = true;
            await selectDay(date);
            if (scope === current && current?.loaded && day.value === date) {
                booking.selectPatient(patient);
            }
        },
        setBranch(id, canManage) {
            booking.reset();
            appointmentCard.close();
            scope = id ? {
                path: '/branches/' + id + '/clinic-calendar', canManage, doctorId: '', edits: {}, request: 0,
                loading: false, loaded: false, saving: false,
            } : null;
            panel.open = false;
            panel.hidden = !scope;
            day.value = clinicToday();
            for (const kind of Object.keys(forms)) {
                resetEditor(kind);
            }
            for (const part of ['appointments', 'blocks', 'overrides', 'hours', 'leaves', 'doctor', 'day-rule']) {
                find(part).replaceChildren();
            }
            blockDoctor.replaceChildren();
            management.hidden = true;
            management.disabled = false;
            find('agenda').hidden = true;
            find('day-blocks').hidden = true;
            for (const details of management.querySelectorAll('details')) {
                details.open = false;
            }
            showStatus();
        },
    };
}
