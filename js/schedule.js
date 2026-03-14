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

    var currentWeek = 1;
    var totalWeeks = 5;

    /**
     * Переключение дня (рабочий / выходной)
     */
    function toggleDay(date, isWorking) {
        var card = document.querySelector('.tt-schedule-day[data-date="' + date + '"]');
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
     * Показать неделю
     */
    function showWeek(direction) {
        currentWeek += direction;
        if (currentWeek < 1) currentWeek = 1;
        if (currentWeek > totalWeeks) currentWeek = totalWeeks;

        // Скрываем все недели
        var weeks = document.querySelectorAll('.tt-schedule-week');
        weeks.forEach(function(week) {
            week.style.display = 'none';
        });

        // Показываем текущую неделю
        var currentWeekEl = document.querySelector('.tt-schedule-week[data-week="' + currentWeek + '"]');
        if (currentWeekEl) {
            currentWeekEl.style.display = '';
        }

        // Обновляем кнопки навигации
        var prevBtn = document.getElementById('tt-week-prev');
        var nextBtn = document.getElementById('tt-week-next');
        var weekInfo = document.getElementById('tt-current-week');
        var weekDates = document.getElementById('tt-current-week-dates');

        if (prevBtn) prevBtn.disabled = (currentWeek === 1);
        if (nextBtn) nextBtn.disabled = (currentWeek === totalWeeks);
        
        if (weekInfo) {
            // Получаем текст из первого заголовка недели
            var weekTitle = document.querySelector('.tt-schedule-week[data-week="' + currentWeek + '"] .tt-schedule-week__title');
            if (weekTitle) {
                weekInfo.textContent = weekTitle.textContent;
            } else {
                weekInfo.textContent = 'Неделя ' + currentWeek;
            }
        }
        
        if (weekDates) {
            // Получаем диапазон дат из заголовка недели
            var weekDatesEl = document.querySelector('.tt-schedule-week[data-week="' + currentWeek + '"] .tt-schedule-week__dates');
            if (weekDatesEl) {
                weekDates.textContent = weekDatesEl.textContent.trim();
            } else {
                weekDates.textContent = '';
            }
        }

        recalcAll();
    }

    /**
     * Подсчёт часов для конкретного дня
     */
    function calcDayHours(date) {
        var card = document.querySelector('.tt-schedule-day[data-date="' + date + '"]');
        if (!card) return 0;

        var checkbox = card.querySelector('input[name="days[' + date + '][is_working]"]');
        if (!checkbox || !checkbox.checked) return 0;

        var startInput = card.querySelector('input[name="days[' + date + '][time_start]"]');
        var endInput = card.querySelector('input[name="days[' + date + '][time_end]"]');
        var breakStartInput = card.querySelector('input[name="days[' + date + '][break_start]"]');
        var breakEndInput = card.querySelector('input[name="days[' + date + '][break_end]"]');

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

        // Пересчитываем только видимые дни (текущая неделя)
        var visibleDays = document.querySelectorAll('.tt-schedule-week[style*="display:"] .tt-schedule-day');
        visibleDays.forEach(function(dayCard) {
            var date = dayCard.getAttribute('data-date');
            if (!date) return;

            var hours = calcDayHours(date);
            var hoursEl = dayCard.querySelector('.tt-schedule-day__hours-value');
            if (hoursEl) {
                hoursEl.textContent = hours > 0 ? hours.toFixed(1) : '—';
            }
            if (hours > 0) {
                workingDays++;
                totalHours += hours;
            }
        });

        // Пересчитываем все дни для общего итога
        var allDays = document.querySelectorAll('.tt-schedule-day');
        var totalAllHours = 0;
        var totalAllDays = 0;
        allDays.forEach(function(dayCard) {
            var date = dayCard.getAttribute('data-date');
            if (!date) return;
            var hours = calcDayHours(date);
            if (hours > 0) {
                totalAllDays++;
                totalAllHours += hours;
            }
        });

        var totalDaysEl = document.getElementById('total-working-days');
        var totalHoursEl = document.getElementById('total-hours');

        if (totalDaysEl) totalDaysEl.textContent = totalAllDays;
        if (totalHoursEl) totalHoursEl.textContent = totalAllHours.toFixed(1);
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

        // Применяем шаблон ко всем дням календаря на основе дня недели
        var allDays = document.querySelectorAll('.tt-schedule-day');
        allDays.forEach(function(card) {
            var dayOfWeek = parseInt(card.getAttribute('data-day-of-week'), 10);
            if (!dayOfWeek || dayOfWeek < 1 || dayOfWeek > 7) return;

            var d = data[dayOfWeek];
            if (!d) return;

            var date = card.getAttribute('data-date');
            if (!date) return;

            var checkbox = card.querySelector('input[name="days[' + date + '][is_working]"]');
            var startInput = card.querySelector('input[name="days[' + date + '][time_start]"]');
            var endInput = card.querySelector('input[name="days[' + date + '][time_end]"]');
            var bStartInput = card.querySelector('input[name="days[' + date + '][break_start]"]');
            var bEndInput = card.querySelector('input[name="days[' + date + '][break_end]"]');

            if (checkbox) checkbox.checked = d.working;
            if (startInput) startInput.value = d.start;
            if (endInput) endInput.value = d.end;
            if (bStartInput) bStartInput.value = d.bStart;
            if (bEndInput) bEndInput.value = d.bEnd;

            toggleDay(date, d.working);
        });

        recalcAll();
    }

    /**
     * Получить текущие параметры из URL
     */
    function getUrlParams() {
        var params = {};
        var urlParams = new URLSearchParams(window.location.search);
        params.iblock_id = urlParams.get('iblock_id') || '0';
        params.spec_iblock_id = urlParams.get('spec_iblock_id') || '0';
        params.spec_property = urlParams.get('spec_property') || '';
        params.doctor_id = urlParams.get('doctor_id') || '0';
        return params;
    }

    /**
     * Построить URL с параметрами
     */
    function buildUrl(params) {
        var url = '/bitrix/admin/doctor_schedule.php?';
        var parts = [];
        
        // Добавляем sessid
        var sessidEl = document.getElementById('tt-sessid');
        if (sessidEl) {
            parts.push('sessid=' + encodeURIComponent(sessidEl.value));
        }
        
        if (params.iblock_id && params.iblock_id !== '0') {
            parts.push('iblock_id=' + params.iblock_id);
        }
        if (params.spec_iblock_id && params.spec_iblock_id !== '0') {
            parts.push('spec_iblock_id=' + params.spec_iblock_id);
        }
        if (params.spec_property) {
            parts.push('spec_property=' + encodeURIComponent(params.spec_property));
        }
        if (params.doctor_id && params.doctor_id !== '0') {
            parts.push('doctor_id=' + params.doctor_id);
        }
        return url + parts.join('&');
    }

    /**
     * Смена инфоблока — перезагрузка страницы с новым iblock_id
     */
    function changeIblock(iblockId) {
        var params = getUrlParams();
        params.iblock_id = iblockId;
        params.doctor_id = '0'; // Сбрасываем выбор врача
        window.location.href = buildUrl(params);
    }

    /**
     * Смена инфоблока со специальностями
     */
    function changeSpecIblock(specIblockId) {
        var params = getUrlParams();
        params.spec_iblock_id = specIblockId;
        params.spec_property = ''; // Сбрасываем свойство
        window.location.href = buildUrl(params);
    }

    /**
     * Смена свойства связи со специальностями
     */
    function changeSpecProperty(specProperty) {
        var params = getUrlParams();
        params.spec_property = specProperty;
        window.location.href = buildUrl(params);
    }

    /**
     * Смена врача — переход на страницу с текущими параметрами и новым doctor_id
     */
    function changeDoctor(doctorId) {
        var params = getUrlParams();
        params.doctor_id = doctorId;
        window.location.href = buildUrl(params);
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
        // Ищем все дни в форме (по датам)
        var dayInputs = form.querySelectorAll('input[name^="days["]');
        dayInputs.forEach(function(input) {
            var name = input.name;
            // Извлекаем дату из name="days[YYYY-MM-DD][...]"
            var match = name.match(/days\[([^\]]+)\]/);
            if (match && match[1]) {
                var dateStr = match[1];
                var checkbox = form.querySelector('input[name="days[' + dateStr + '][is_working]"]');
                if (checkbox && !checkbox.checked) {
                    formData.set('days[' + dateStr + '][is_working]', '0');
                }
            }
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/bitrix/admin/doctor_schedule_ajax.php', true);

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            if (btn) {
                btn.classList.remove('tt-schedule-save-btn--loading');
                var saveText = btn.getAttribute('data-save-text') || 'Сохранить расписание';
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> ' + saveText;
            }

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        var message = response.message || 'Расписание успешно сохранено';
                        showToast('✓ ' + message, 'success');
                    } else {
                        var errorMsg = response.message || 'Ошибка при сохранении расписания';
                        showToast('✗ ' + errorMsg, 'error');
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
        initWeekNav();
        
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

    /**
     * Инициализация навигации по неделям
     */
    function initWeekNav() {
        var weeks = document.querySelectorAll('.tt-schedule-week');
        totalWeeks = weeks.length;
        
        var prevBtn = document.getElementById('tt-week-prev');
        var nextBtn = document.getElementById('tt-week-next');
        var weekInfo = document.getElementById('tt-current-week');
        var weekDates = document.getElementById('tt-current-week-dates');

        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = (totalWeeks <= 1);
        
        // Инициализируем информацию о первой неделе
        if (weekInfo) {
            var firstWeekTitle = document.querySelector('.tt-schedule-week[data-week="1"] .tt-schedule-week__title');
            if (firstWeekTitle) {
                weekInfo.textContent = firstWeekTitle.textContent;
            } else {
                weekInfo.textContent = 'Неделя 1';
            }
        }
        
        if (weekDates) {
            var firstWeekDates = document.querySelector('.tt-schedule-week[data-week="1"] .tt-schedule-week__dates');
            if (firstWeekDates) {
                weekDates.textContent = firstWeekDates.textContent.trim();
            }
        }
    }

    // Публичный API
    return {
        toggleDay: toggleDay,
        applyPreset: applyPreset,
        changeIblock: changeIblock,
        changeSpecIblock: changeSpecIblock,
        changeSpecProperty: changeSpecProperty,
        changeDoctor: changeDoctor,
        recalcAll: recalcAll,
        showWeek: showWeek
    };

})();
