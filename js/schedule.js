/**
 * TtSchedule — Управление расписанием врача
 *
 * Отвечает за:
 * - переключение рабочих/выходных дней
 * - подсчёт рабочих часов
 * - применение быстрых шаблонов
 * - AJAX-сохранение расписания
 */
var TtSchedule = (function () {
    'use strict';

    /**
     * Переключение дня (рабочий / выходной)
     */
    function toggleDay(dayNum, isWorking) {
        var card = document.querySelector('.tt-schedule-day[data-day="' + dayNum + '"]');
        if (!card) return;

        var body = card.querySelector('.tt-schedule-day__body');
        var offMsg = card.querySelector('.tt-schedule-day__off-message');
        var label = card.querySelector('.tt-schedule-toggle__label');

        if (isWorking) {
            card.classList.remove('tt-schedule-day--off');
            if (body) body.style.display = '';
            if (offMsg) offMsg.style.display = 'none';
            if (label) label.textContent = 'Рабочий';
        } else {
            card.classList.add('tt-schedule-day--off');
            if (body) body.style.display = 'none';
            if (offMsg) offMsg.style.display = '';
            if (label) label.textContent = 'Выходной';
        }

        recalcAll();
    }

    /**
     * Подсчёт часов для конкретного дня
     */
    function calcDayHours(dayNum) {
        var card = document.querySelector('.tt-schedule-day[data-day="' + dayNum + '"]');
        if (!card) return 0;

        var checkbox = card.querySelector('input[name="days[' + dayNum + '][is_working]"]');
        if (!checkbox || !checkbox.checked) return 0;

        var startInput = card.querySelector('input[name="days[' + dayNum + '][time_start]"]');
        var endInput = card.querySelector('input[name="days[' + dayNum + '][time_end]"]');
        var breakStartInput = card.querySelector('input[name="days[' + dayNum + '][break_start]"]');
        var breakEndInput = card.querySelector('input[name="days[' + dayNum + '][break_end]"]');

        var start = timeToMinutes(startInput ? startInput.value : '');
        var end = timeToMinutes(endInput ? endInput.value : '');

        if (start === null || end === null || end <= start) return 0;

        var totalMinutes = end - start;

        // Вычитаем перерыв
        var breakStart = timeToMinutes(breakStartInput ? breakStartInput.value : '');
        var breakEnd = timeToMinutes(breakEndInput ? breakEndInput.value : '');

        if (breakStart !== null && breakEnd !== null && breakEnd > breakStart) {
            totalMinutes -= (breakEnd - breakStart);
        }

        return Math.max(0, totalMinutes / 60);
    }

    /**
     * Конвертация времени HH:MM в минуты
     */
    function timeToMinutes(timeStr) {
        if (!timeStr || timeStr.indexOf(':') === -1) return null;
        var parts = timeStr.split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) return null;
        return h * 60 + m;
    }

    /**
     * Пересчёт всех показателей
     */
    function recalcAll() {
        var totalHours = 0;
        var workingDays = 0;

        for (var day = 1; day <= 7; day++) {
            var hours = calcDayHours(day);
            var hoursEl = document.getElementById('hours-' + day);
            if (hoursEl) {
                hoursEl.textContent = hours > 0 ? hours.toFixed(1) : '—';
            }
            if (hours > 0) {
                workingDays++;
                totalHours += hours;
            }
        }

        var totalDaysEl = document.getElementById('total-working-days');
        var totalHoursEl = document.getElementById('total-hours');

        if (totalDaysEl) totalDaysEl.textContent = workingDays;
        if (totalHoursEl) totalHoursEl.textContent = totalHours.toFixed(1);
    }

    /**
     * Применение быстрого шаблона
     */
    function applyPreset(preset) {
        var presets = {
            standard: {
                // Пн-Пт: 09:00–18:00, перерыв 13:00–14:00, Сб-Вс — выходной
                1: { working: true, start: '09:00', end: '18:00', bStart: '13:00', bEnd: '14:00' },
                2: { working: true, start: '09:00', end: '18:00', bStart: '13:00', bEnd: '14:00' },
                3: { working: true, start: '09:00', end: '18:00', bStart: '13:00', bEnd: '14:00' },
                4: { working: true, start: '09:00', end: '18:00', bStart: '13:00', bEnd: '14:00' },
                5: { working: true, start: '09:00', end: '18:00', bStart: '13:00', bEnd: '14:00' },
                6: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                7: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' }
            },
            short: {
                // Пн-Чт: 08:00–17:00, Пт: 08:00–16:00, Сб-Вс — выходной
                1: { working: true, start: '08:00', end: '17:00', bStart: '12:00', bEnd: '13:00' },
                2: { working: true, start: '08:00', end: '17:00', bStart: '12:00', bEnd: '13:00' },
                3: { working: true, start: '08:00', end: '17:00', bStart: '12:00', bEnd: '13:00' },
                4: { working: true, start: '08:00', end: '17:00', bStart: '12:00', bEnd: '13:00' },
                5: { working: true, start: '08:00', end: '16:00', bStart: '12:00', bEnd: '13:00' },
                6: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                7: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' }
            },
            shift: {
                // Через день: Пн, Ср, Пт — 08:00–20:00
                1: { working: true, start: '08:00', end: '20:00', bStart: '13:00', bEnd: '14:00' },
                2: { working: false, start: '08:00', end: '20:00', bStart: '', bEnd: '' },
                3: { working: true, start: '08:00', end: '20:00', bStart: '13:00', bEnd: '14:00' },
                4: { working: false, start: '08:00', end: '20:00', bStart: '', bEnd: '' },
                5: { working: true, start: '08:00', end: '20:00', bStart: '13:00', bEnd: '14:00' },
                6: { working: false, start: '08:00', end: '20:00', bStart: '', bEnd: '' },
                7: { working: false, start: '08:00', end: '20:00', bStart: '', bEnd: '' }
            },
            clear: {
                1: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                2: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                3: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                4: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                5: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                6: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' },
                7: { working: false, start: '09:00', end: '18:00', bStart: '', bEnd: '' }
            }
        };

        var data = presets[preset];
        if (!data) return;

        for (var day = 1; day <= 7; day++) {
            var d = data[day];
            var card = document.querySelector('.tt-schedule-day[data-day="' + day + '"]');
            if (!card) continue;

            var checkbox = card.querySelector('input[name="days[' + day + '][is_working]"]');
            var startInput = card.querySelector('input[name="days[' + day + '][time_start]"]');
            var endInput = card.querySelector('input[name="days[' + day + '][time_end]"]');
            var bStartInput = card.querySelector('input[name="days[' + day + '][break_start]"]');
            var bEndInput = card.querySelector('input[name="days[' + day + '][break_end]"]');

            if (checkbox) checkbox.checked = d.working;
            if (startInput) startInput.value = d.start;
            if (endInput) endInput.value = d.end;
            if (bStartInput) bStartInput.value = d.bStart;
            if (bEndInput) bEndInput.value = d.bEnd;

            toggleDay(day, d.working);
        }

        recalcAll();
    }

    /**
     * Смена инфоблока — перезагрузка страницы с новым iblock_id
     */
    function changeIblock(iblockId) {
        if (!iblockId || iblockId === '0') {
            window.location.href = '/bitrix/admin/doctor_schedule.php';
        } else {
            window.location.href = '/bitrix/admin/doctor_schedule.php?iblock_id=' + iblockId;
        }
    }

    /**
     * Смена врача — переход на страницу с текущим iblock_id и новым doctor_id
     */
    function changeDoctor(doctorId) {
        var iblockSelect = document.getElementById('tt-iblock-id');
        var iblockId = iblockSelect ? iblockSelect.value : '0';

        if (!doctorId || doctorId === '0') {
            window.location.href = '/bitrix/admin/doctor_schedule.php?iblock_id=' + iblockId;
        } else {
            window.location.href = '/bitrix/admin/doctor_schedule.php?iblock_id=' + iblockId + '&doctor_id=' + doctorId;
        }
    }

    /**
     * Показать toast-уведомление
     */
    function showToast(message, type) {
        var toast = document.getElementById('tt-toast');
        if (!toast) return;

        toast.textContent = message;
        toast.className = 'tt-schedule-toast tt-schedule-toast--' + (type || 'success');

        // Показать
        setTimeout(function () {
            toast.classList.add('tt-schedule-toast--visible');
        }, 10);

        // Скрыть через 3 секунды
        setTimeout(function () {
            toast.classList.remove('tt-schedule-toast--visible');
        }, 3500);
    }

    /**
     * Отправка формы через AJAX
     */
    function handleSubmit(e) {
        e.preventDefault();

        var form = document.getElementById('tt-schedule-form');
        if (!form) return;

        var btn = document.getElementById('tt-save-btn');
        if (btn) {
            btn.classList.add('tt-schedule-save-btn--loading');
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Сохранение...';
        }

        var formData = new FormData(form);

        // Для выходных дней нужно явно отправить is_working = 0
        for (var day = 1; day <= 7; day++) {
            var checkbox = form.querySelector('input[name="days[' + day + '][is_working]"]');
            if (checkbox && !checkbox.checked) {
                formData.set('days[' + day + '][is_working]', '0');
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/bitrix/admin/doctor_schedule_ajax.php', true);

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            if (btn) {
                btn.classList.remove('tt-schedule-save-btn--loading');
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Сохранить расписание';
            }

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast('✓ ' + response.message, 'success');
                    } else {
                        showToast('✗ ' + response.message, 'error');
                    }
                } catch (ex) {
                    showToast('✗ Ошибка обработки ответа сервера', 'error');
                }
            } else {
                showToast('✗ Ошибка сервера (HTTP ' + xhr.status + ')', 'error');
            }
        };

        xhr.send(formData);
    }

    /**
     * Инициализация при загрузке страницы
     */
    function init() {
        // Подписка на отправку формы
        var form = document.getElementById('tt-schedule-form');
        if (form) {
            form.addEventListener('submit', handleSubmit);
        }

        // Подписка на изменение полей времени для пересчёта
        var timeInputs = document.querySelectorAll('.tt-schedule-time-input');
        for (var i = 0; i < timeInputs.length; i++) {
            timeInputs[i].addEventListener('change', recalcAll);
            timeInputs[i].addEventListener('input', recalcAll);
        }

        // Начальный расчёт
        recalcAll();
    }

    // Запуск при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Публичный API
    return {
        toggleDay: toggleDay,
        applyPreset: applyPreset,
        changeIblock: changeIblock,
        changeDoctor: changeDoctor,
        recalcAll: recalcAll
    };

})();
