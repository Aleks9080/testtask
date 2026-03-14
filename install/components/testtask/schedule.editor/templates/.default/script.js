/**
 * TtSchedule — JavaScript для компонента расписания врачей
 * Управление темой, фильтрацией, календарём
 */
var TtSchedule = (function () {
    'use strict';

    var scheduleData = {}; // Расписание по дням недели
    var breakData = {}; // Перерывы по дням недели


    /**
     * Загрузка расписания из DOM
     */
    function loadSchedule() {
        scheduleData = {};
        breakData = {};
        
        for (var day = 1; day <= 7; day++) {
            var dayCard = document.querySelector('.tt-schedule-detail__day[data-day="' + day + '"]');
            if (!dayCard) continue;

            // Проверяем, что день рабочий (есть бейдж "working")
            var badge = dayCard.querySelector('.tt-schedule-detail__day-badge--working');
            if (!badge) continue; // Пропускаем выходные дни

            // Проверяем наличие времени работы
            var timeText = dayCard.querySelector('.tt-schedule-detail__time-text');
            if (!timeText) continue; // Пропускаем, если нет времени

            var timeStr = timeText.textContent.trim();
            // Парсим формат "09:00 — 18:00" или "09:00-18:00"
            var match = timeStr.match(/(\d{1,2}):(\d{2})\s*[—\-]\s*(\d{1,2}):(\d{2})/);
            if (!match) continue; // Пропускаем, если не удалось распарсить время
            
            var startH = match[1].length === 1 ? '0' + match[1] : match[1];
            var startM = match[2];
            var endH = match[3].length === 1 ? '0' + match[3] : match[3];
            var endM = match[4];
            
            // Сохраняем только если время валидное
            if (startH && startM && endH && endM) {
                scheduleData[day] = {
                    start: startH + ':' + startM,
                    end: endH + ':' + endM
                };
            }

            // Загружаем перерыв (опционально)
            var breakText = dayCard.querySelector('.tt-schedule-detail__break-text');
            if (breakText) {
                var breakStr = breakText.textContent.trim();
                var breakMatch = breakStr.match(/(\d{1,2}):(\d{2})\s*[—\-]\s*(\d{1,2}):(\d{2})/);
                if (breakMatch) {
                    var breakStartH = breakMatch[1].length === 1 ? '0' + breakMatch[1] : breakMatch[1];
                    var breakStartM = breakMatch[2];
                    var breakEndH = breakMatch[3].length === 1 ? '0' + breakMatch[3] : breakMatch[3];
                    var breakEndM = breakMatch[4];
                    if (breakStartH && breakStartM && breakEndH && breakEndM) {
                        breakData[day] = {
                            start: breakStartH + ':' + breakStartM,
                            end: breakEndH + ':' + breakEndM
                        };
                    }
                }
            }
        }
    }

    /**
     * Генерация календаря приёма на месяц
     */
    function generateCalendar() {
        var calendarEl = document.getElementById('tt-calendar-month');
        if (!calendarEl) return;

        loadSchedule();

        // Генерируем календарь на текущий месяц + следующий месяц (до 60 дней)
        var now = new Date();
        now.setHours(0, 0, 0, 0);
        var year = now.getFullYear();
        var month = now.getMonth();
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var daysInMonth = lastDay.getDate();
        // getDay() возвращает: 0=Вс, 1=Пн, 2=Вт, ..., 6=Сб
        // Нужно преобразовать: Пн=1, Вт=2, ..., Вс=7
        var firstDayOfWeek = firstDay.getDay();
        var startDayOfWeek = firstDayOfWeek === 0 ? 7 : firstDayOfWeek; // Пн = 1, Вс = 7

        // Вычисляем количество дней для отображения (текущий месяц + следующий, но не более 60 дней)
        var maxDays = 60;
        var daysToShow = Math.min(daysInMonth - now.getDate() + 1 + 31, maxDays); // Текущий месяц + следующий месяц

        var html = '<div class="tt-calendar-grid">';
        html += '<div class="tt-calendar-header">';
        var dayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        for (var i = 0; i < 7; i++) {
            html += '<div class="tt-calendar-day-name">' + dayNames[i] + '</div>';
        }
        html += '</div>';

        // Пустые ячейки до первого дня текущего месяца
        for (var i = 1; i < startDayOfWeek; i++) {
            html += '<div class="tt-calendar-day-empty"></div>';
        }

        // Дни текущего месяца (начиная с первого дня месяца)
        var currentDate = new Date(firstDay);
        var daysAdded = 0;
        
        while (daysAdded < daysToShow && currentDate <= new Date(year, month + 2, 0)) {
            var day = currentDate.getDate();
            var currentMonth = currentDate.getMonth();
            var currentYear = currentDate.getFullYear();
            // getDay() возвращает: 0=Вс, 1=Пн, 2=Вт, ..., 6=Сб
            // Преобразуем: Пн=1, Вт=2, ..., Вс=7
            var dayOfWeekRaw = currentDate.getDay();
            var dayOfWeek = dayOfWeekRaw === 0 ? 7 : dayOfWeekRaw;
            var daySchedule = scheduleData[dayOfWeek];
            // День доступен для записи только если есть настроенное расписание с валидным временем
            var isWorking = daySchedule && daySchedule.start && daySchedule.end && 
                           daySchedule.start.length === 5 && daySchedule.end.length === 5;
            var isToday = currentDate.getTime() === now.getTime();
            var isPast = currentDate < now && !isToday;

            var dayClass = 'tt-calendar-day';
            if (isToday) dayClass += ' tt-calendar-day--today';
            if (!isWorking) dayClass += ' tt-calendar-day--off';
            if (isPast) dayClass += ' tt-calendar-day--past';

            var dateStr = currentYear + '-' + String(currentMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            html += '<div class="' + dayClass + '" data-date="' + dateStr + '" data-day-of-week="' + dayOfWeek + '">';
            html += '<div class="tt-calendar-day-number">' + day + '</div>';
            
            if (isWorking && !isPast) {
                var badgeText = calendarEl ? (calendarEl.getAttribute('data-lang-badge') || 'Запись') : 'Запись';
                html += '<div class="tt-calendar-day-badge">' + badgeText + '</div>';
            }
            
            html += '</div>';
            
            // Переходим к следующему дню
            currentDate.setDate(currentDate.getDate() + 1);
            daysAdded++;
        }

        html += '</div>';
        calendarEl.innerHTML = html;

        // Добавляем обработчики кликов
        var dayElements = calendarEl.querySelectorAll('.tt-calendar-day:not(.tt-calendar-day--off):not(.tt-calendar-day--past)');
        dayElements.forEach(function(dayEl) {
            dayEl.addEventListener('click', function() {
                var date = this.getAttribute('data-date');
                var dayOfWeek = parseInt(this.getAttribute('data-day-of-week'), 10);
                openTimePopup(date, dayOfWeek);
            });
        });
    }

    /**
     * Получить локализованную строку из data-атрибутов
     */
    function getLang(key) {
        var calendarEl = document.getElementById('tt-calendar-month');
        if (!calendarEl) return key;
        return calendarEl.getAttribute('data-lang-' + key) || key;
    }

    /**
     * Открытие попапа с выбором времени
     */
    function openTimePopup(date, dayOfWeek) {
        var daySchedule = scheduleData[dayOfWeek];
        var dayBreak = breakData[dayOfWeek] || null;
        
        if (!daySchedule) return;

        // Генерируем слоты времени
        var slots = generateTimeSlots(daySchedule.start, daySchedule.end, dayBreak);
        
        // Форматируем дату
        var dateParts = date.split('-');
        var dateObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
        var dayNames = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
        var monthNames = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        var formattedDate = dateParts[2] + ' ' + monthNames[parseInt(dateParts[1]) - 1] + ' ' + dateParts[0] + ', ' + dayNames[dateObj.getDay()];

        // Создаём попап
        var popup = document.createElement('div');
        popup.className = 'tt-time-popup';
        popup.id = 'tt-time-popup';
        
        var html = '<div class="tt-time-popup__overlay"></div>';
        html += '<div class="tt-time-popup__content">';
        html += '<div class="tt-time-popup__header">';
        html += '<h3 class="tt-time-popup__title">' + getLang('appointment-title') + '</h3>';
        html += '<button class="tt-time-popup__close" onclick="TtSchedule.closeTimePopup()">×</button>';
        html += '</div>';
        html += '<div class="tt-time-popup__date">' + formattedDate + '</div>';
        html += '<div class="tt-time-popup__slots">';
        
        if (slots.length === 0) {
            html += '<div class="tt-time-popup__empty">' + getLang('no-time') + '</div>';
        } else {
            slots.forEach(function(slot) {
                html += '<label class="tt-time-popup__slot">';
                html += '<input type="radio" name="appointment_time" value="' + slot + '" onchange="TtSchedule.updateSelectedSlot(this)">';
                html += '<span class="tt-time-popup__slot-label">' + slot + '</span>';
                html += '</label>';
            });
        }
        
        html += '</div>';
        html += '<div class="tt-time-popup__footer">';
        html += '<button class="tt-time-popup__cancel" onclick="TtSchedule.closeTimePopup()">' + getLang('cancel') + '</button>';
        html += '<button class="tt-time-popup__submit" onclick="TtSchedule.submitAppointment(\'' + date + '\')">' + getLang('submit') + '</button>';
        html += '</div>';
        html += '</div>';
        
        popup.innerHTML = html;
        document.body.appendChild(popup);
        
        // Закрытие по клику на overlay
        popup.querySelector('.tt-time-popup__overlay').addEventListener('click', closeTimePopup);
    }

    /**
     * Закрытие попапа
     */
    function closeTimePopup() {
        var popup = document.getElementById('tt-time-popup');
        if (popup) {
            popup.remove();
        }
    }

    /**
     * Обновление выбранного слота
     */
    function updateSelectedSlot(radio) {
        // Убираем выделение со всех слотов
        var allSlots = document.querySelectorAll('.tt-time-popup__slot');
        allSlots.forEach(function(slot) {
            slot.classList.remove('tt-time-popup__slot--selected');
        });
        
        // Добавляем выделение к выбранному
        if (radio.checked) {
            radio.closest('.tt-time-popup__slot').classList.add('tt-time-popup__slot--selected');
        }
    }

    /**
     * Отправка записи
     */
    function submitAppointment(date) {
        var selectedTime = document.querySelector('input[name="appointment_time"]:checked');
        if (!selectedTime) {
            alert(getLang('select-time'));
            return;
        }
        
        var time = selectedTime.value;
        var msg = getLang('appointment-msg');
        msg = msg.replace('#DATE#', date).replace('#TIME#', time);
        alert(msg);
        closeTimePopup();
    }

    /**
     * Генерация слотов времени по 20 минут (исключая перерыв)
     */
    function generateTimeSlots(start, end, breakTime) {
        var slots = [];
        var startParts = start.split(':');
        var endParts = end.split(':');
        var startHour = parseInt(startParts[0], 10);
        var startMin = parseInt(startParts[1], 10);
        var endHour = parseInt(endParts[0], 10);
        var endMin = parseInt(endParts[1], 10);

        var breakStart = null;
        var breakEnd = null;
        if (breakTime && breakTime.start && breakTime.end) {
            var breakStartParts = breakTime.start.split(':');
            var breakEndParts = breakTime.end.split(':');
            breakStart = parseInt(breakStartParts[0], 10) * 60 + parseInt(breakStartParts[1], 10);
            breakEnd = parseInt(breakEndParts[0], 10) * 60 + parseInt(breakEndParts[1], 10);
        }

        var currentHour = startHour;
        var currentMin = startMin;

        while (currentHour < endHour || (currentHour === endHour && currentMin < endMin)) {
            var currentMinutes = currentHour * 60 + currentMin;
            
            // Пропускаем время перерыва
            if (breakStart && breakEnd && currentMinutes >= breakStart && currentMinutes < breakEnd) {
                currentMin += 20;
                if (currentMin >= 60) {
                    currentMin = 0;
                    currentHour++;
                }
                continue;
            }
            
            var timeStr = (currentHour < 10 ? '0' : '') + currentHour + ':' + (currentMin < 10 ? '0' : '') + currentMin;
            slots.push(timeStr);

            currentMin += 20;
            if (currentMin >= 60) {
                currentMin = 0;
                currentHour++;
            }
        }

        return slots;
    }

    /**
     * Фильтрация по специальности
     */
    function filterBySpecialization(specId) {
        var url = window.location.pathname;
        var params = new URLSearchParams(window.location.search);
        
        if (specId === '0' || specId === 0) {
            params.delete('filter_spec');
        } else {
            params.set('filter_spec', specId);
        }
        
        var queryString = params.toString();
        window.location.href = url + (queryString ? '?' + queryString : '');
    }

    /**
     * Переключение недели через AJAX
     */
    function changeWeek(offset) {
        var params = new URLSearchParams(window.location.search);
        var currentOffset = parseInt(params.get('week_offset') || '0', 10);
        var newOffset = currentOffset + offset;
        
        // Получаем ID врача из URL или из data-атрибута
        var doctorId = null;
        var doctorIdEl = document.querySelector('[data-doctor-id]');
        if (doctorIdEl) {
            doctorId = doctorIdEl.getAttribute('data-doctor-id');
        } else {
            // Пытаемся извлечь из URL
            var urlMatch = window.location.pathname.match(/\/(\d+)\/?$/);
            if (urlMatch) {
                doctorId = urlMatch[1];
            }
        }
        
        if (!doctorId) {
            console.error('Не удалось определить ID врача');
            return;
        }
        
        // Блокируем кнопки
        var prevBtn = document.getElementById('tt-week-prev');
        var nextBtn = document.getElementById('tt-week-next');
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        
        // Показываем индикатор загрузки
        var weekDaysEl = document.getElementById('tt-schedule-week-days');
        var summaryEl = document.querySelector('.tt-schedule-detail__summary');
        if (weekDaysEl) {
            weekDaysEl.style.opacity = '0.5';
        }
        
        // AJAX-запрос
        var xhr = new XMLHttpRequest();
        // Используем путь из window или fallback на стандартный путь
        var url = window.ttScheduleAjaxUrl || '/local/modules/testtask.schedule/install/components/testtask/schedule.editor/ajax.php';
        // Получаем sessid из скрытого поля или из BX
        var sessid = '';
        var sessidInput = document.querySelector('input[name="sessid"]');
        if (sessidInput) {
            sessid = sessidInput.value;
        } else if (typeof BX !== 'undefined' && BX.bitrix_sessid) {
            sessid = BX.bitrix_sessid();
        }
        
        var formData = new FormData();
        formData.append('action', 'load_week');
        formData.append('doctor_id', doctorId);
        formData.append('week_offset', newOffset);
        if (sessid) {
            formData.append('sessid', sessid);
        }
        
        xhr.open('POST', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            
            if (prevBtn) prevBtn.disabled = false;
            if (nextBtn) nextBtn.disabled = false;
            if (weekDaysEl) weekDaysEl.style.opacity = '1';
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Обновляем HTML
                        if (weekDaysEl) {
                            weekDaysEl.innerHTML = response.html;
                        }
                        if (summaryEl) {
                            summaryEl.innerHTML = response.summary;
                        }
                        var weekRangeEl = document.getElementById('tt-week-range');
                        if (weekRangeEl) {
                            weekRangeEl.textContent = response.weekRange;
                        }
                        
                        // Обновляем URL без перезагрузки
                        if (newOffset === 0) {
                            params.delete('week_offset');
                        } else {
                            params.set('week_offset', newOffset);
                        }
                        var queryString = params.toString();
                        var newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
                        window.history.pushState({}, '', newUrl);
                        
                        // Перезагружаем расписание для календаря
                        loadSchedule();
                        generateCalendar();
                    } else {
                        alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                    }
                } catch (ex) {
                    console.error('Ошибка парсинга ответа:', ex);
                    alert('Ошибка обработки ответа сервера');
                }
            } else {
                alert('Ошибка сервера (HTTP ' + xhr.status + ')');
            }
        };
        
        xhr.send(formData);
    }

    /**
     * Инициализация при загрузке страницы
     */
    function init() {
        generateCalendar();
    }

    // Запуск при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Публичный API
    return {
        filterBySpecialization: filterBySpecialization,
        changeWeek: changeWeek,
        closeTimePopup: closeTimePopup,
        submitAppointment: submitAppointment,
        updateSelectedSlot: updateSelectedSlot
    };

})();