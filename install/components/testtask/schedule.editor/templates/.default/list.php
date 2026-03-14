<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();


/**
 * Шаблон списка врачей
 *
 * @var array $arResult
 * @var array $arParams
 * @var CBitrixComponentTemplate $this
 */

$doctors = $arResult['DOCTORS'];
$specializations = $arResult['SPECIALIZATIONS'] ?? [];
$filterSpec = (int)($arResult['FILTER_SPEC'] ?? 0);
$sefFolder = $arResult['SEF_FOLDER'];
$urlTemplates = $arResult['SEF_URL_TEMPLATES'];
$detailUrl = $urlTemplates['detail'] ?? '#ELEMENT_ID#/';
?>

<div class="tt-schedule-list">
    <div class="tt-schedule-list__header">
        <h1 class="tt-schedule-list__title">Врачи</h1>
        <p class="tt-schedule-list__subtitle">Выберите врача для просмотра расписания</p>
    </div>

    <?php if (!empty($specializations)): ?>
    <div class="tt-schedule-list__filter">
        <label class="tt-schedule-list__filter-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Фильтр по специальности
        </label>
        <select class="tt-schedule-list__filter-select" id="tt-filter-spec" onchange="TtSchedule.filterBySpecialization(this.value)">
            <option value="0" <?= $filterSpec === 0 ? 'selected' : '' ?>>Все специальности</option>
            <?php foreach ($specializations as $spec): ?>
                <option value="<?= $spec['ID'] ?>" <?= $filterSpec === $spec['ID'] ? 'selected' : '' ?>>
                    <?= htmlspecialcharsbx($spec['NAME']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if (empty($doctors)): ?>
        <div class="tt-schedule-list__empty">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <p>Врачи не найдены</p>
        </div>
    <?php else: ?>
        <div class="tt-schedule-list__grid">
            <?php foreach ($doctors as $doctor):
                // Формируем URL детальной страницы
                if ($arParams['SEF_MODE'] === 'Y') {
                    $url = str_replace(['#ELEMENT_ID#', '#ELEMENT_CODE#'], $doctor['CODE'], $detailUrl);
                    $url = $sefFolder . ltrim($url, '/');
                } else {
                    $url = '?ELEMENT_ID=' . $doctor['ID'];
                }
            ?>
                <a href="<?= $url ?>" class="tt-schedule-list__item">
                    <div class="tt-schedule-list__item-avatar">
                        <?php if ($doctor['PICTURE']): ?>
                            <img src="<?= htmlspecialcharsbx($doctor['PICTURE']) ?>" 
                                 alt="<?= htmlspecialcharsbx($doctor['NAME']) ?>">
                        <?php else: ?>
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="tt-schedule-list__item-name">
                        <?= htmlspecialcharsbx($doctor['NAME']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?= $this->GetFolder() ?>/script.js"></script>
