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
$days = $arResult['DAYS_OF_WEEK'];
$defaults = $arResult['DEFAULT_SCHEDULE'];
$sefFolder = $arResult['SEF_FOLDER'];
?>

<div class="tt-schedule-detail">
    <!-- Информация о враче -->
    <div class="tt-schedule-detail__header">
        <?php if ($element['PREVIEW_PICTURE'] || $element['DETAIL_PICTURE']): ?>
            <div class="tt-schedule-detail__photo">
                <img src="<?= htmlspecialcharsbx($element['DETAIL_PICTURE'] ?: $element['PREVIEW_PICTURE']) ?>" 
                     alt="<?= htmlspecialcharsbx($element['NAME']) ?>">
            </div>
        <?php endif; ?>
        <div class="tt-schedule-detail__info">
            <h1 class="tt-schedule-detail__name"><?= htmlspecialcharsbx($element['NAME']) ?></h1>
            <?php if ($element['PREVIEW_TEXT']): ?>
                <div class="tt-schedule-detail__preview">
                    <?= $element['PREVIEW_TEXT'] ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Расписание -->
    <div class="tt-schedule-detail__schedule">
        <h2 class="tt-schedule-detail__schedule-title">Расписание работы</h2>

        <div class="tt-schedule-detail__days">
            <?php for ($day = 1; $day <= 7; $day++):
                $dayData = $schedule[$day] ?? $defaults;
                $isWorking = (int)($dayData['is_working'] ?? 1);
                $isWeekend = ($day >= 6);
                $cardClass = 'tt-schedule-detail__day';
                if (!$isWorking) $cardClass .= ' tt-schedule-detail__day--off';
                if ($isWeekend) $cardClass .= ' tt-schedule-detail__day--weekend';
            ?>
                <div class="<?= $cardClass ?>">
                    <div class="tt-schedule-detail__day-header">
                        <div class="tt-schedule-detail__day-name">
                            <span class="tt-schedule-detail__day-short"><?= $days[$day]['SHORT'] ?></span>
                            <span class="tt-schedule-detail__day-full"><?= $days[$day]['NAME'] ?></span>
                        </div>
                        <span class="tt-schedule-detail__day-badge tt-schedule-detail__day-badge--<?= $isWorking ? 'working' : 'off' ?>">
                            <?= $isWorking ? 'Рабочий день' : 'Выходной' ?>
                        </span>
                    </div>

                    <?php if ($isWorking): ?>
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
            <?php endfor; ?>
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
                <span class="tt-schedule-detail__summary-label">часов в неделю</span>
            </div>
        </div>
    </div>

    <!-- Кнопка "Назад к списку" -->
    <div class="tt-schedule-detail__back">
        <a href="<?= $sefFolder ?>" class="tt-schedule-detail__back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Вернуться к списку врачей
        </a>
    </div>
</div>
