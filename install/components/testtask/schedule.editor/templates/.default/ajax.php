<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Testtask\Schedule\ScheduleTable;

if (!Loader::includeModule('testtask.schedule') || !Loader::includeModule('iblock')) {
    echo Json::encode(['success' => false, 'error' => 'Модули не установлены']);
    die();
}

$request = Application::getInstance()->getContext()->getRequest();
$action = $request->get('action');

// Проверка CSRF для POST-запросов
if ($request->isPost() && !check_bitrix_sessid()) {
    echo Json::encode(['success' => false, 'error' => 'Неверный токен безопасности']);
    die();
}

if ($action === 'load_week') {
    $doctorId = (int)$request->get('doctor_id');
    $weekOffset = (int)$request->get('week_offset');
    
    if ($doctorId <= 0) {
        echo Json::encode(['success' => false, 'error' => 'Не указан ID врача']);
        die();
    }
    
    // Вычисляем даты недели
    $today = new \DateTime();
    $today->setTime(0, 0, 0);
    
    // Находим начало текущей недели (понедельник)
    $dayOfWeek = (int)$today->format('N'); // 1=Пн, 7=Вс
    $weekStart = clone $today;
    if ($dayOfWeek > 1) {
        $weekStart->modify('-' . ($dayOfWeek - 1) . ' days');
    }
    
    // Применяем смещение недели
    if ($weekOffset != 0) {
        $weekStart->modify($weekOffset . ' weeks');
    }
    
    // Генерируем даты недели (7 дней)
    $weekDays = [];
    for ($i = 0; $i < 7; $i++) {
        $date = clone $weekStart;
        $date->modify('+' . $i . ' days');
        $dateStr = $date->format('Y-m-d');
        $dayOfWeekNum = (int)$date->format('N'); // 1=Пн, 7=Вс
        
        $weekDays[] = [
            'date' => $dateStr,
            'day' => (int)$date->format('d'),
            'month' => (int)$date->format('m'),
            'year' => (int)$date->format('Y'),
            'day_of_week' => $dayOfWeekNum,
            'day_name' => ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'][$dayOfWeekNum],
            'day_short' => ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'][$dayOfWeekNum],
        ];
    }
    
    // Загружаем расписание для этой недели
    $startDateObj = new \Bitrix\Main\Type\Date($weekStart->format('Y-m-d'), 'Y-m-d');
    $endDate = clone $weekStart;
    $endDate->modify('+6 days');
    $endDateObj = new \Bitrix\Main\Type\Date($endDate->format('Y-m-d'), 'Y-m-d');
    
    $scheduleByDates = ScheduleTable::getScheduleByDoctor($doctorId, $startDateObj, $endDateObj);
    
    // Формируем расписание для недели (индексируем по дате для удобства)
    $weekScheduleByDate = [];
    foreach ($weekDays as $dayInfo) {
        $dateStr = $dayInfo['date'];
        if (isset($scheduleByDates[$dateStr])) {
            $weekScheduleByDate[$dateStr] = $scheduleByDates[$dateStr];
        } else {
            $weekScheduleByDate[$dateStr] = null;
        }
    }
    
    // Формируем HTML для дней недели
    $html = '';
    foreach ($weekDays as $dayInfo) {
        $day = $dayInfo['day_of_week'];
        $dateStr = $dayInfo['date'];
        
        $dayData = $weekScheduleByDate[$dateStr] ?? null;
        if ($dayData === null) {
            $isWorking = 0;
            $dayData = ['is_working' => 0, 'time_start' => '', 'time_end' => '', 'break_start' => '', 'break_end' => ''];
        } else {
            $isWorking = (int)($dayData['is_working'] ?? 0);
        }
        $isWeekend = ($day >= 6);
        $isToday = ($dateStr === date('Y-m-d'));
        $cardClass = 'tt-schedule-detail__day';
        if (!$isWorking) $cardClass .= ' tt-schedule-detail__day--off';
        if ($isWeekend) $cardClass .= ' tt-schedule-detail__day--weekend';
        if ($isToday) $cardClass .= ' tt-schedule-detail__day--today';
        
        $html .= '<div class="' . $cardClass . '" data-day="' . $day . '" data-date="' . $dateStr . '">';
        $html .= '<div class="tt-schedule-detail__day-header">';
        $html .= '<div class="tt-schedule-detail__day-name">';
        $html .= '<span class="tt-schedule-detail__day-short">' . htmlspecialchars($dayInfo['day_short']) . '</span>';
        $html .= '<span class="tt-schedule-detail__day-full">' . htmlspecialchars($dayInfo['day_name']) . '</span>';
        $html .= '<span class="tt-schedule-detail__day-date">' . $dayInfo['day'] . '.' . $dayInfo['month'] . '</span>';
        $html .= '</div>';
        $html .= '<span class="tt-schedule-detail__day-badge tt-schedule-detail__day-badge--' . ($isWorking ? 'working' : 'off') . '">';
        $html .= htmlspecialchars($isWorking ? 'Рабочий' : 'Выходной');
        $html .= '</span>';
        $html .= '</div>';
        
        if ($isWorking && !empty($dayData['time_start']) && !empty($dayData['time_end'])) {
            $html .= '<div class="tt-schedule-detail__day-body">';
            $html .= '<div class="tt-schedule-detail__time">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<circle cx="12" cy="12" r="10"/>';
            $html .= '<polyline points="12 6 12 12 16 14"/>';
            $html .= '</svg>';
            $html .= '<span class="tt-schedule-detail__time-text">';
            $html .= htmlspecialchars($dayData['time_start'] ?? '09:00') . ' — ' . htmlspecialchars($dayData['time_end'] ?? '18:00');
            $html .= '</span>';
            $html .= '</div>';
            
            if (!empty($dayData['break_start']) && !empty($dayData['break_end'])) {
                $html .= '<div class="tt-schedule-detail__break">';
                $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/>';
                $html .= '<path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>';
                $html .= '</svg>';
                $html .= '<span class="tt-schedule-detail__break-text">';
                $html .= 'Перерыв: ' . htmlspecialchars($dayData['break_start']) . ' — ' . htmlspecialchars($dayData['break_end']);
                $html .= '</span>';
                $html .= '</div>';
            }
            
            // Подсчёт часов
            $start = $dayData['time_start'] ?? '09:00';
            $end = $dayData['time_end'] ?? '18:00';
            $breakStart = $dayData['break_start'] ?? '';
            $breakEnd = $dayData['break_end'] ?? '';
            
            $startParts = explode(':', $start);
            $endParts = explode(':', $end);
            $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
            $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
            $totalMinutes = $endMinutes - $startMinutes;
            
            if ($breakStart && $breakEnd) {
                $breakStartParts = explode(':', $breakStart);
                $breakEndParts = explode(':', $breakEnd);
                $breakStartMinutes = (int)$breakStartParts[0] * 60 + (int)$breakStartParts[1];
                $breakEndMinutes = (int)$breakEndParts[0] * 60 + (int)$breakEndParts[1];
                $totalMinutes -= ($breakEndMinutes - $breakStartMinutes);
            }
            
            $hours = max(0, $totalMinutes / 60);
            $html .= '<div class="tt-schedule-detail__day-hours">';
            $html .= '<span class="tt-schedule-detail__day-hours-value">' . number_format($hours, 1) . '</span>';
            $html .= '<span class="tt-schedule-detail__day-hours-label">часов</span>';
            $html .= '</div>';
            $html .= '</div>';
        } else {
            $html .= '<div class="tt-schedule-detail__day-off">';
            $html .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
            $html .= '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>';
            $html .= '<circle cx="9" cy="7" r="4"/>';
            $html .= '<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>';
            $html .= '<path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
            $html .= '</svg>';
            $html .= '<span>Выходной день</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // Подсчёт итого
    $totalDays = 0;
    $totalHours = 0;
    foreach ($weekScheduleByDate as $dayData) {
        if ($dayData && (int)($dayData['is_working'] ?? 0) && !empty($dayData['time_start']) && !empty($dayData['time_end'])) {
            $totalDays++;
            $start = $dayData['time_start'] ?? '09:00';
            $end = $dayData['time_end'] ?? '18:00';
            $breakStart = $dayData['break_start'] ?? '';
            $breakEnd = $dayData['break_end'] ?? '';
            
            $startParts = explode(':', $start);
            $endParts = explode(':', $end);
            $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
            $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
            $dayMinutes = $endMinutes - $startMinutes;
            
            if ($breakStart && $breakEnd) {
                $breakStartParts = explode(':', $breakStart);
                $breakEndParts = explode(':', $breakEnd);
                $breakStartMinutes = (int)$breakStartParts[0] * 60 + (int)$breakStartParts[1];
                $breakEndMinutes = (int)$breakEndParts[0] * 60 + (int)$breakEndParts[1];
                $dayMinutes -= ($breakEndMinutes - $breakStartMinutes);
            }
            $totalHours += max(0, $dayMinutes / 60);
        }
    }
    
    $summaryHtml = '<div class="tt-schedule-detail__summary-item">';
    $summaryHtml .= '<span class="tt-schedule-detail__summary-number">' . $totalDays . '</span>';
    $summaryHtml .= '<span class="tt-schedule-detail__summary-label">рабочих дней</span>';
    $summaryHtml .= '</div>';
    $summaryHtml .= '<div class="tt-schedule-detail__summary-divider"></div>';
    $summaryHtml .= '<div class="tt-schedule-detail__summary-item">';
    $summaryHtml .= '<span class="tt-schedule-detail__summary-number">' . number_format($totalHours, 1) . '</span>';
    $summaryHtml .= '<span class="tt-schedule-detail__summary-label">часов</span>';
    $summaryHtml .= '</div>';
    
    // Формируем диапазон дат
    $firstDay = reset($weekDays);
    $lastDay = end($weekDays);
    $weekRange = date('d.m', strtotime($firstDay['date'])) . ' — ' . date('d.m.Y', strtotime($lastDay['date']));
    
    echo Json::encode([
        'success' => true,
        'html' => $html,
        'summary' => $summaryHtml,
        'weekRange' => $weekRange
    ]);
    die();
}

echo Json::encode(['success' => false, 'error' => 'Неизвестное действие']);
