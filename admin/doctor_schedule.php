<?php

/**
 * Административная страница: Расписание врача
 *
 * Двухшаговый интерфейс:
 * 1. Выбор инфоблока (справочник врачей)
 * 2. Выбор врача (элемента инфоблока)
 * 3. Настройка расписания выбранного врача
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Проверка прав доступа
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('TESTTASK_SCHEDULE_ACCESS_DENIED'));
}

// Проверка установки модулей
if (!Loader::includeModule('testtask.schedule')) {
    ShowError(Loc::getMessage('TESTTASK_SCHEDULE_MODULE_NOT_INSTALLED'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

if (!Loader::includeModule('iblock')) {
    ShowError('Модуль «Информационные блоки» не установлен');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

use Testtask\Schedule\ScheduleTable;

// Заголовок страницы
$APPLICATION->SetTitle(Loc::getMessage('TESTTASK_SCHEDULE_PAGE_TITLE'));

// Параметры из GET
$iblockId = (int)($_REQUEST['iblock_id'] ?? 0);
$doctorId = (int)($_REQUEST['doctor_id'] ?? 0);

// ========== 1. Получаем список инфоблоков ==========
$iblocks = [];
$rsIblocks = \CIBlock::GetList(
    ['SORT' => 'ASC', 'NAME' => 'ASC'],
    ['ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N']
);
while ($ib = $rsIblocks->Fetch()) {
    $typeName = $ib['IBLOCK_TYPE_ID'];
    $iblocks[$ib['ID']] = '[' . $ib['ID'] . '] ' . $ib['NAME'] . ' (' . $typeName . ')';
}

// ========== 2. Получаем список врачей (элементы выбранного инфоблока) ==========
$doctors = [];
if ($iblockId > 0) {
    $rsElements = \CIBlockElement::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
        false, false,
        ['ID', 'NAME', 'PREVIEW_PICTURE']
    );
    while ($el = $rsElements->Fetch()) {
        $doctors[$el['ID']] = [
            'NAME' => $el['NAME'],
            'PICTURE' => $el['PREVIEW_PICTURE'] ? \CFile::GetPath($el['PREVIEW_PICTURE']) : '',
        ];
    }
}

// ========== 3. Загружаем расписание врача ==========
$schedule = [];
if ($doctorId > 0) {
    $schedule = ScheduleTable::getScheduleByDoctor($doctorId);
}

// Дни недели
$daysOfWeek = [];
for ($i = 1; $i <= 7; $i++) {
    $daysOfWeek[$i] = [
        'name' => Loc::getMessage('TESTTASK_SCHEDULE_DAY_' . $i),
        'short' => Loc::getMessage('TESTTASK_SCHEDULE_DAY_SHORT_' . $i),
    ];
}

// Значения по умолчанию
$defaultSchedule = [
    'is_working' => 1,
    'time_start' => '09:00',
    'time_end' => '18:00',
    'break_start' => '13:00',
    'break_end' => '14:00',
];

// Подключение ресурсов
$APPLICATION->SetAdditionalCSS('/bitrix/css/testtask.schedule/schedule.css');
$APPLICATION->AddHeadScript('/bitrix/js/testtask.schedule/schedule.js');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<div class="tt-schedule-wrapper">
    <!-- Шапка -->
    <div class="tt-schedule-header">
        <div class="tt-schedule-header__icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="tt-schedule-header__text">
            <h1>Расписание врача</h1>
            <p>Выберите инфоблок с врачами, затем врача для настройки расписания</p>
        </div>
    </div>

    <!-- Шаг 1: Выбор инфоблока -->
    <div class="tt-schedule-selector-card">
        <div class="tt-schedule-selector-step">
            <span class="tt-schedule-step-number">1</span>
            <div class="tt-schedule-selector-content">
                <label class="tt-schedule-label" for="tt-iblock-id">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    Инфоблок с врачами
                </label>
                <select id="tt-iblock-id" class="tt-schedule-select" onchange="TtSchedule.changeIblock(this.value)">
                    <option value="0">— Выберите инфоблок —</option>
                    <?php foreach ($iblocks as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $id === $iblockId ? 'selected' : '' ?>>
                            <?= htmlspecialcharsbx($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($iblockId > 0): ?>
        <!-- Шаг 2: Выбор врача -->
        <div class="tt-schedule-selector-step">
            <span class="tt-schedule-step-number">2</span>
            <div class="tt-schedule-selector-content">
                <label class="tt-schedule-label" for="tt-doctor-id">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?= Loc::getMessage('TESTTASK_SCHEDULE_DOCTOR_LABEL') ?>
                </label>

                <?php if (empty($doctors)): ?>
                    <div class="tt-schedule-no-items">
                        В выбранном инфоблоке нет активных элементов
                    </div>
                <?php else: ?>
                    <div class="tt-schedule-doctor-grid">
                        <?php foreach ($doctors as $id => $doc):
                            $isActive = ($id === $doctorId);
                        ?>
                            <a href="?iblock_id=<?= $iblockId ?>&doctor_id=<?= $id ?>"
                               class="tt-schedule-doctor-card <?= $isActive ? 'tt-schedule-doctor-card--active' : '' ?>">
                                <div class="tt-schedule-doctor-card__avatar">
                                    <?php if ($doc['PICTURE']): ?>
                                        <img src="<?= $doc['PICTURE'] ?>" alt="<?= htmlspecialcharsbx($doc['NAME']) ?>">
                                    <?php else: ?>
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <span class="tt-schedule-doctor-card__name"><?= htmlspecialcharsbx($doc['NAME']) ?></span>
                                <?php if ($isActive): ?>
                                    <span class="tt-schedule-doctor-card__badge">Выбран</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($doctorId > 0 && isset($doctors[$doctorId])): ?>
    <!-- Выбранный врач -->
    <div class="tt-schedule-current-doctor">
        <div class="tt-schedule-current-doctor__info">
            <div class="tt-schedule-current-doctor__avatar">
                <?php if ($doctors[$doctorId]['PICTURE']): ?>
                    <img src="<?= $doctors[$doctorId]['PICTURE'] ?>" alt="">
                <?php else: ?>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div>
                <div class="tt-schedule-current-doctor__name">
                    <?= htmlspecialcharsbx($doctors[$doctorId]['NAME']) ?>
                </div>
                <div class="tt-schedule-current-doctor__id">
                    Элемент #<?= $doctorId ?> из инфоблока #<?= $iblockId ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Форма расписания -->
    <form id="tt-schedule-form" class="tt-schedule-form" data-doctor-id="<?= $doctorId ?>">
        <input type="hidden" name="doctor_id" value="<?= $doctorId ?>">
        <?= bitrix_sessid_post() ?>

        <!-- Быстрые шаблоны -->
        <div class="tt-schedule-presets">
            <span class="tt-schedule-presets__label">Быстрые шаблоны:</span>
            <button type="button" class="tt-schedule-preset-btn" onclick="TtSchedule.applyPreset('standard')">
                🏢 Стандартная неделя
            </button>
            <button type="button" class="tt-schedule-preset-btn" onclick="TtSchedule.applyPreset('short')">
                ⏱ Сокращённая неделя
            </button>
            <button type="button" class="tt-schedule-preset-btn" onclick="TtSchedule.applyPreset('shift')">
                🔄 Сменный график
            </button>
            <button type="button" class="tt-schedule-preset-btn" onclick="TtSchedule.applyPreset('clear')">
                ✖ Очистить всё
            </button>
        </div>

        <!-- Дни недели -->
        <div class="tt-schedule-days">
            <?php for ($day = 1; $day <= 7; $day++):
                $dayData = $schedule[$day] ?? $defaultSchedule;
                $isWorking = (int)($dayData['is_working'] ?? 1);
                $isWeekend = ($day >= 6);
                $cardClass = 'tt-schedule-day';
                if (!$isWorking) $cardClass .= ' tt-schedule-day--off';
                if ($isWeekend) $cardClass .= ' tt-schedule-day--weekend';
            ?>
                <div class="<?= $cardClass ?>" data-day="<?= $day ?>">
                    <div class="tt-schedule-day__header">
                        <div class="tt-schedule-day__name">
                            <span class="tt-schedule-day__short"><?= $daysOfWeek[$day]['short'] ?></span>
                            <span class="tt-schedule-day__full"><?= $daysOfWeek[$day]['name'] ?></span>
                        </div>
                        <label class="tt-schedule-toggle">
                            <input type="checkbox"
                                   name="days[<?= $day ?>][is_working]"
                                   value="1"
                                   <?= $isWorking ? 'checked' : '' ?>
                                   onchange="TtSchedule.toggleDay(<?= $day ?>, this.checked)">
                            <span class="tt-schedule-toggle__slider"></span>
                            <span class="tt-schedule-toggle__label">
                                <?= $isWorking ? 'Рабочий' : 'Выходной' ?>
                            </span>
                        </label>
                    </div>

                    <div class="tt-schedule-day__body" <?= !$isWorking ? 'style="display:none"' : '' ?>>
                        <div class="tt-schedule-time-group">
                            <div class="tt-schedule-time-group__label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Время приёма
                            </div>
                            <div class="tt-schedule-time-row">
                                <div class="tt-schedule-time-field">
                                    <label>с</label>
                                    <input type="time"
                                           name="days[<?= $day ?>][time_start]"
                                           value="<?= htmlspecialcharsbx($dayData['time_start'] ?? '09:00') ?>"
                                           class="tt-schedule-time-input">
                                </div>
                                <div class="tt-schedule-time-separator">—</div>
                                <div class="tt-schedule-time-field">
                                    <label>до</label>
                                    <input type="time"
                                           name="days[<?= $day ?>][time_end]"
                                           value="<?= htmlspecialcharsbx($dayData['time_end'] ?? '18:00') ?>"
                                           class="tt-schedule-time-input">
                                </div>
                            </div>
                        </div>

                        <div class="tt-schedule-time-group tt-schedule-time-group--break">
                            <div class="tt-schedule-time-group__label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
                                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                                    <line x1="6" y1="1" x2="6" y2="4"/>
                                    <line x1="10" y1="1" x2="10" y2="4"/>
                                    <line x1="14" y1="1" x2="14" y2="4"/>
                                </svg>
                                Перерыв
                            </div>
                            <div class="tt-schedule-time-row">
                                <div class="tt-schedule-time-field">
                                    <label>с</label>
                                    <input type="time"
                                           name="days[<?= $day ?>][break_start]"
                                           value="<?= htmlspecialcharsbx($dayData['break_start'] ?? '') ?>"
                                           class="tt-schedule-time-input tt-schedule-time-input--break">
                                </div>
                                <div class="tt-schedule-time-separator">—</div>
                                <div class="tt-schedule-time-field">
                                    <label>до</label>
                                    <input type="time"
                                           name="days[<?= $day ?>][break_end]"
                                           value="<?= htmlspecialcharsbx($dayData['break_end'] ?? '') ?>"
                                           class="tt-schedule-time-input tt-schedule-time-input--break">
                                </div>
                            </div>
                        </div>

                        <div class="tt-schedule-day__hours">
                            <span class="tt-schedule-day__hours-value" id="hours-<?= $day ?>">—</span>
                            <span class="tt-schedule-day__hours-label">часов</span>
                        </div>
                    </div>

                    <div class="tt-schedule-day__off-message" <?= $isWorking ? 'style="display:none"' : '' ?>>
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span>Выходной день</span>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Итого -->
        <div class="tt-schedule-summary">
            <div class="tt-schedule-summary__item">
                <span class="tt-schedule-summary__number" id="total-working-days">0</span>
                <span class="tt-schedule-summary__label">рабочих дней</span>
            </div>
            <div class="tt-schedule-summary__divider"></div>
            <div class="tt-schedule-summary__item">
                <span class="tt-schedule-summary__number" id="total-hours">0</span>
                <span class="tt-schedule-summary__label">часов в неделю</span>
            </div>
        </div>

        <div class="tt-schedule-actions">
            <button type="submit" class="tt-schedule-save-btn" id="tt-save-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Сохранить расписание
            </button>
        </div>
    </form>

    <?php elseif ($iblockId > 0 && !empty($doctors)): ?>
        <div class="tt-schedule-empty">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <p>Выберите врача из списка выше</p>
        </div>

    <?php elseif ($iblockId === 0): ?>
        <div class="tt-schedule-empty">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            <p>Выберите инфоблок с врачами, чтобы начать</p>
        </div>
    <?php endif; ?>
</div>

<!-- Уведомление -->
<div class="tt-schedule-toast" id="tt-toast"></div>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
?>
