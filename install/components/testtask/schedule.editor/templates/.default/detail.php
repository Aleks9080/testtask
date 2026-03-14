<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();


/**
 * Шаблон детальной страницы врача с расписанием
 *
 * @var array $arResult
 * @var array $arParams
 * @var CBitrixComponentTemplate $this
 */

$element = $arResult['ELEMENT'];
$schedule = $arResult['SCHEDULE'];
$weekDays = $arResult['WEEK_DAYS'] ?? [];
$weekStart = $arResult['WEEK_START'] ?? date('Y-m-d');
$weekOffset = $arResult['WEEK_OFFSET'] ?? 0;
$specializations = $arResult['SPECIALIZATIONS'] ?? [];
$days = $arResult['DAYS_OF_WEEK'];
$defaults = $arResult['DEFAULT_SCHEDULE'];
$sefFolder = $arResult['SEF_FOLDER'];

// Формируем даты недели для отображения
$weekStartDate = new DateTime($weekStart);
$weekEndDate = clone $weekStartDate;
$weekEndDate->modify('+6 days');
$weekRange = $weekStartDate->format('d.m') . ' — ' . $weekEndDate->format('d.m.Y');
?>

<div class="tt-schedule-detail" data-doctor-id="<?= $element['ID'] ?>">
    <!-- Основной контент: врач слева, расписание справа -->
    <div class="tt-schedule-detail__main">
        <!-- Врач слева -->
        <div class="tt-schedule-detail__doctor">
            <div class="tt-schedule-detail__photo-section">
                <?php if ($element['PREVIEW_PICTURE'] || $element['DETAIL_PICTURE']): ?>
                    <div class="tt-schedule-detail__photo">
                        <img src="<?= htmlspecialcharsbx($element['DETAIL_PICTURE'] ?: $element['PREVIEW_PICTURE']) ?>" 
                             alt="<?= htmlspecialcharsbx($element['NAME']) ?>">
                    </div>
                <?php else: ?>
                    <div class="tt-schedule-detail__photo">
                        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                <?php endif; ?>
                <h1 class="tt-schedule-detail__name"><?= htmlspecialcharsbx($element['NAME']) ?></h1>
                <?php if ($element['PREVIEW_TEXT']): ?>
                    <div class="tt-schedule-detail__preview">
                        <?= $element['PREVIEW_TEXT'] ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Специальности -->
            <?php if (!empty($specializations)): ?>
            <div class="tt-schedule-detail__specializations">
                <h2 class="tt-schedule-detail__specializations-title">Специальности</h2>
                <div class="tt-schedule-detail__specializations-list">
                    <?php foreach ($specializations as $spec): ?>
                        <div class="tt-schedule-detail__specialization-item">
                            <?php if ($spec['PICTURE']): ?>
                                <img src="<?= htmlspecialcharsbx($spec['PICTURE']) ?>" alt="<?= htmlspecialcharsbx($spec['NAME']) ?>" class="tt-schedule-detail__specialization-icon">
                            <?php else: ?>
                                <div class="tt-schedule-detail__specialization-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <span class="tt-schedule-detail__specialization-name"><?= htmlspecialcharsbx($spec['NAME']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Расписание справа -->
        <div class="tt-schedule-detail__schedule">
        <div class="tt-schedule-detail__schedule-header">
            <h2 class="tt-schedule-detail__schedule-title">Расписание работы</h2>
            
            <!-- Навигация по неделям -->
            <div class="tt-schedule-detail__week-nav">
                <button type="button" class="tt-schedule-detail__week-nav-btn" onclick="TtSchedule.changeWeek(-1)" id="tt-week-prev">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Предыдущая неделя
                </button>
                <div class="tt-schedule-detail__week-range" id="tt-week-range">
                    <?= htmlspecialcharsbx($weekRange) ?>
                </div>
                <button type="button" class="tt-schedule-detail__week-nav-btn" onclick="TtSchedule.changeWeek(1)" id="tt-week-next">
                    Следующая неделя
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="tt-schedule-detail__days" id="tt-schedule-week-days">
            <?php foreach ($weekDays as $dayInfo):
                $day = $dayInfo['day_of_week'];
                $dateStr = $dayInfo['date'];
                
                // Если расписание не настроено для этого дня, используем дефолт как выходной
                $dayData = $schedule[$day] ?? null;
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
            ?>
                <div class="<?= $cardClass ?>" data-day="<?= $day ?>" data-date="<?= $dateStr ?>">
                    <div class="tt-schedule-detail__day-header">
                        <div class="tt-schedule-detail__day-name">
                            <span class="tt-schedule-detail__day-short"><?= $dayInfo['day_short'] ?></span>
                            <span class="tt-schedule-detail__day-full"><?= $dayInfo['day_name'] ?></span>
                            <span class="tt-schedule-detail__day-date"><?= $dayInfo['day'] ?>.<?= $dayInfo['month'] ?></span>
                        </div>
                        <span class="tt-schedule-detail__day-badge tt-schedule-detail__day-badge--<?= $isWorking ? 'working' : 'off' ?>">
                            <?= $isWorking ? 'Рабочий' : 'Выходной' ?>
                        </span>
                    </div>

                    <?php if ($isWorking && !empty($dayData['time_start']) && !empty($dayData['time_end'])): ?>
                        <div class="tt-schedule-detail__day-body">
                            <div class="tt-schedule-detail__time">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <span class="tt-schedule-detail__time-text">
                                    <?= htmlspecialcharsbx($dayData['time_start'] ?? '09:00') ?>
                                    —
                                    <?= htmlspecialcharsbx($dayData['time_end'] ?? '18:00') ?>
                                </span>
                            </div>

                            <?php if (!empty($dayData['break_start']) && !empty($dayData['break_end'])): ?>
                                <div class="tt-schedule-detail__break">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
                                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                                    </svg>
                                    <span class="tt-schedule-detail__break-text">
                                        Перерыв: <?= htmlspecialcharsbx($dayData['break_start']) ?>
                                        —
                                        <?= htmlspecialcharsbx($dayData['break_end']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php
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
                            ?>
                            <div class="tt-schedule-detail__day-hours">
                                <span class="tt-schedule-detail__day-hours-value"><?= number_format($hours, 1) ?></span>
                                <span class="tt-schedule-detail__day-hours-label">часов</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="tt-schedule-detail__day-off">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <span>Выходной день</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Итого -->
        <?php
        $totalDays = 0;
        $totalHours = 0;
        foreach ($schedule as $dayData) {
            if (!empty($dayData['is_working'])) {
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
        ?>
        <div class="tt-schedule-detail__summary">
            <div class="tt-schedule-detail__summary-item">
                <span class="tt-schedule-detail__summary-number"><?= $totalDays ?></span>
                <span class="tt-schedule-detail__summary-label">рабочих дней</span>
            </div>
            <div class="tt-schedule-detail__summary-divider"></div>
            <div class="tt-schedule-detail__summary-item">
                <span class="tt-schedule-detail__summary-number"><?= number_format($totalHours, 1) ?></span>
                <span class="tt-schedule-detail__summary-label">часов</span>
            </div>
        </div>
        </div>
    </div>

    <!-- Календарь приёма на месяц -->
    <div class="tt-schedule-detail__calendar">
        <h2 class="tt-schedule-detail__calendar-title">Запись на приём</h2>
        <div class="tt-schedule-detail__calendar-month" 
             id="tt-calendar-month"
             data-lang-appointment-title="Запись на приём"
             data-lang-no-time="Нет доступного времени"
             data-lang-cancel="Отмена"
             data-lang-submit="Записаться"
             data-lang-select-time="Выберите время"
             data-lang-appointment-msg="Вы записаны на"
             data-lang-badge="Запись">
        </div>
    </div>

    <!-- Кнопка "Назад к списку" -->
    <div class="tt-schedule-detail__back">
        <?php
        // Формируем правильный URL для списка
        if ($arParams['SEF_MODE'] === 'Y') {
            $backUrl = $sefFolder;
        } else {
            $backUrl = '?';
        }
        ?>
        <a href="<?= htmlspecialcharsbx($backUrl) ?>" class="tt-schedule-detail__back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Назад к списку
        </a>
    </div>
</div>

<input type="hidden" name="sessid" value="<?= bitrix_sessid() ?>">
<script>
    window.ttScheduleAjaxUrl = '<?= $this->GetFolder() ?>/ajax.php';
</script>
<script src="<?= $this->GetFolder() ?>/script.js"></script>
