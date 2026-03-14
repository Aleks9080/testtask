<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Config\Option;
use Testtask\Schedule\ScheduleTable;

/**
 * Комплексный компонент «Расписание врача»
 *
 * Режимы работы:
 * - list   — список врачей (фото + название)
 * - detail — детальная страница врача с расписанием (только просмотр)
 *
 * Редактирование расписания доступно только в админке.
 */
class TtScheduleEditorComponent extends CBitrixComponent
{
    protected int $iblockId = 0;
    protected int $doctorId = 0;
    protected string $mode = 'list';

    protected int $specIblockId = 0;
    protected string $specProperty = 'DOCTOR_ID';

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['ELEMENT_ID'] = (int)($arParams['ELEMENT_ID'] ?? 0);
        
        // Загружаем настройки из Option, если не указаны в параметрах
        $arParams['SPECIALIZATIONS_IBLOCK_ID'] = (int)($arParams['SPECIALIZATIONS_IBLOCK_ID'] ?? Option::get('testtask.schedule', 'spec_iblock_id', 0));
        $arParams['SPECIALIZATION_PROPERTY'] = $arParams['SPECIALIZATION_PROPERTY'] ?? Option::get('testtask.schedule', 'spec_property', 'DOCTOR_ID');
        
        $arParams['SEF_MODE'] = ($arParams['SEF_MODE'] ?? 'N') === 'Y';
        $arParams['SEF_FOLDER'] = $arParams['SEF_FOLDER'] ?? '/';
        $arParams['SEF_URL_TEMPLATES'] = $arParams['SEF_URL_TEMPLATES'] ?? [];
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);
        $arParams['SET_TITLE'] = ($arParams['SET_TITLE'] ?? 'Y') === 'Y';
        $arParams['SET_STATUS_404'] = ($arParams['SET_STATUS_404'] ?? 'Y') === 'Y';

        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('testtask.schedule')) {
            ShowError('Модуль testtask.schedule не установлен');
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль «Информационные блоки» не установлен');
            return;
        }

        $this->iblockId = $this->arParams['IBLOCK_ID'];
        $this->specIblockId = $this->arParams['SPECIALIZATIONS_IBLOCK_ID'];
        $this->specProperty = $this->arParams['SPECIALIZATION_PROPERTY'];
        
        // Получаем смещение недели из GET-параметра
        $request = Application::getInstance()->getContext()->getRequest();
        $this->arParams['WEEK_OFFSET'] = (int)$request->get('week_offset');
        
        if ($this->iblockId <= 0) {
            ShowError('Не указан ID инфоблока');
            return;
        }

        // Определяем режим работы (list или detail)
        $this->determineMode();

        if ($this->mode === 'detail') {
            $this->executeDetail();
        } else {
            $this->executeList();
        }
    }

    /**
     * Определение режима работы компонента
     */
    protected function determineMode(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if ($this->arParams['SEF_MODE'] === 'Y') {
            // ЧПУ-режим
            $url = $request->getRequestedPage();
            $sefFolder = $this->arParams['SEF_FOLDER'];
            $urlTemplates = $this->arParams['SEF_URL_TEMPLATES'];

            // Убираем папку ЧПУ из URL
            if (strpos($url, $sefFolder) === 0) {
                $url = substr($url, strlen($sefFolder));
            }
            $url = trim($url, '/');

            // Проверяем шаблон детальной страницы
            if (!empty($urlTemplates['detail']) && $url) {
                $detailTemplate = trim($urlTemplates['detail'], '/');
                $detailTemplate = str_replace(['#ELEMENT_ID#', '#ELEMENT_CODE#'], '([^/]+)', $detailTemplate);
                if (preg_match('#^' . $detailTemplate . '$#', $url, $matches)) {
                    $this->mode = 'detail';
                    $elementIdOrCode = $matches[1] ?? '';
                    $this->doctorId = $this->resolveElementId($elementIdOrCode);
                    return;
                }
            }
        } else {
            // Обычный режим (GET-параметры)
            $this->doctorId = (int)($request->get('ELEMENT_ID') ?: $this->arParams['ELEMENT_ID']);
            if ($this->doctorId > 0) {
                $this->mode = 'detail';
                return;
            }
        }

        $this->mode = 'list';
    }

    /**
     * Разрешение ID элемента (по ID или символьному коду)
     */
    protected function resolveElementId($idOrCode): int
    {
        if (is_numeric($idOrCode)) {
            return (int)$idOrCode;
        }

        // Поиск по символьному коду
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, 'CODE' => $idOrCode, 'ACTIVE' => 'Y'],
            false, false,
            ['ID']
        );
        if ($el = $rs->Fetch()) {
            return (int)$el['ID'];
        }

        return 0;
    }

    /**
     * Режим списка врачей
     */
    protected function executeList(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $filterSpec = (int)$request->get('filter_spec');

        $this->arResult['IBLOCK_ID'] = $this->iblockId;
        $this->arResult['DOCTORS'] = $this->getDoctorsList($filterSpec);
        $this->arResult['SPECIALIZATIONS'] = $this->getAllSpecializations();
        $this->arResult['FILTER_SPEC'] = $filterSpec;
        $this->arResult['SEF_FOLDER'] = $this->arParams['SEF_FOLDER'];
        $this->arResult['SEF_URL_TEMPLATES'] = $this->arParams['SEF_URL_TEMPLATES'];

        $this->includeComponentTemplate('list');
    }

    /**
     * Режим детальной страницы врача
     */
    protected function executeDetail(): void
    {
        if ($this->doctorId <= 0) {
            if ($this->arParams['SET_STATUS_404'] === 'Y') {
                \CHTTP::SetStatus('404 Not Found');
            }
            ShowError('Врач не найден');
            return;
        }

        // Получаем данные элемента
        $rs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $this->iblockId, 'ID' => $this->doctorId, 'ACTIVE' => 'Y'],
            false, false,
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PREVIEW_TEXT', 'DETAIL_TEXT']
        );

        if (!($element = $rs->Fetch())) {
            if ($this->arParams['SET_STATUS_404'] === 'Y') {
                \CHTTP::SetStatus('404 Not Found');
            }
            ShowError('Врач не найден');
            return;
        }

        // Обработка картинок
        if ($element['PREVIEW_PICTURE']) {
            $element['PREVIEW_PICTURE'] = \CFile::GetPath($element['PREVIEW_PICTURE']);
        }
        if ($element['DETAIL_PICTURE']) {
            $element['DETAIL_PICTURE'] = \CFile::GetPath($element['DETAIL_PICTURE']);
        }

        // Получаем расписание на текущую неделю (или выбранную через параметр)
        $weekOffset = (int)($this->arParams['WEEK_OFFSET'] ?? 0);
        
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
        $weekSchedule = [];
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
        
        $scheduleByDates = ScheduleTable::getScheduleByDoctor($this->doctorId, $startDateObj, $endDateObj);
        
        // Формируем расписание для недели
        foreach ($weekDays as $dayInfo) {
            $dateStr = $dayInfo['date'];
            if (isset($scheduleByDates[$dateStr])) {
                $weekSchedule[$dayInfo['day_of_week']] = $scheduleByDates[$dateStr];
            } else {
                // Если для конкретной даты нет расписания, используем null
                $weekSchedule[$dayInfo['day_of_week']] = null;
            }
        }
        
        $schedule = $weekSchedule;

        $this->arResult['ELEMENT'] = $element;
        $this->arResult['SCHEDULE'] = $schedule;
        $this->arResult['WEEK_DAYS'] = $weekDays;
        $this->arResult['WEEK_START'] = $weekStart->format('Y-m-d');
        $this->arResult['WEEK_OFFSET'] = $weekOffset;
        $this->arResult['DAYS_OF_WEEK'] = [
            1 => ['NAME' => 'Понедельник', 'SHORT' => 'Пн'],
            2 => ['NAME' => 'Вторник',     'SHORT' => 'Вт'],
            3 => ['NAME' => 'Среда',       'SHORT' => 'Ср'],
            4 => ['NAME' => 'Четверг',     'SHORT' => 'Чт'],
            5 => ['NAME' => 'Пятница',     'SHORT' => 'Пт'],
            6 => ['NAME' => 'Суббота',     'SHORT' => 'Сб'],
            7 => ['NAME' => 'Воскресенье', 'SHORT' => 'Вс'],
        ];
        $this->arResult['DEFAULT_SCHEDULE'] = [
            'is_working'  => 1,
            'time_start'  => '09:00',
            'time_end'    => '18:00',
            'break_start' => '13:00',
            'break_end'   => '14:00',
        ];
        $this->arResult['SEF_FOLDER'] = $this->arParams['SEF_FOLDER'];
        $this->arResult['SPECIALIZATIONS'] = $this->getDoctorSpecializations($this->doctorId);

        // Установка заголовка
        if ($this->arParams['SET_TITLE'] === 'Y') {
            global $APPLICATION;
            $APPLICATION->SetTitle($element['NAME'] . ' — Расписание');
        }

        $this->includeComponentTemplate('detail');
    }

    /**
     * Список активных элементов (врачей) из инфоблока
     */
    protected function getDoctorsList(int $filterSpec = 0): array
    {
        $filter = ['IBLOCK_ID' => $this->iblockId, 'ACTIVE' => 'Y'];
        
        // Фильтрация по специальности
        if ($filterSpec > 0 && $this->specIblockId > 0) {
            // Находим ID врачей, у которых есть эта специальность
            $doctorIds = [];
            $rsSpec = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => $this->specIblockId,
                    'ID' => $filterSpec,
                    'ACTIVE' => 'Y',
                ],
                false, false,
                ['ID', 'PROPERTY_' . $this->specProperty]
            );
            while ($spec = $rsSpec->Fetch()) {
                $propValue = $spec['PROPERTY_' . $this->specProperty . '_VALUE'];
                if (is_array($propValue)) {
                    $doctorIds = array_merge($doctorIds, $propValue);
                } else {
                    $doctorIds[] = $propValue;
                }
            }
            $doctorIds = array_unique(array_filter($doctorIds));
            if (empty($doctorIds)) {
                return []; // Нет врачей с такой специальностью
            }
            $filter['ID'] = $doctorIds;
        }

        $doctors = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            $filter,
            false, false,
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'CODE']
        );
        while ($el = $rs->Fetch()) {
            $doctors[] = [
                'ID' => (int)$el['ID'],
                'NAME' => $el['NAME'],
                'CODE' => $el['CODE'] ?: $el['ID'],
                'PICTURE' => $el['PREVIEW_PICTURE'] ? \CFile::GetPath($el['PREVIEW_PICTURE']) : '',
            ];
        }
        return $doctors;
    }

    /**
     * Получить специальности и кабинеты врача
     */
    protected function getDoctorSpecializations(int $doctorId): array
    {
        if ($this->specIblockId <= 0) {
            return [];
        }

        $specializations = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                'IBLOCK_ID' => $this->specIblockId,
                'ACTIVE' => 'Y',
                'PROPERTY_' . $this->specProperty => $doctorId,
            ],
            false, false,
            ['ID', 'NAME', 'CODE', 'PREVIEW_PICTURE']
        );

        while ($el = $rs->Fetch()) {
            $specializations[] = [
                'ID' => (int)$el['ID'],
                'NAME' => $el['NAME'],
                'CODE' => $el['CODE'] ?: $el['ID'],
                'PICTURE' => $el['PREVIEW_PICTURE'] ? \CFile::GetPath($el['PREVIEW_PICTURE']) : '',
            ];
        }

        return $specializations;
    }

    /**
     * Получить все доступные специальности для фильтра
     */
    protected function getAllSpecializations(): array
    {
        if ($this->specIblockId <= 0) {
            return [];
        }

        $specs = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $this->specIblockId, 'ACTIVE' => 'Y'],
            false, false,
            ['ID', 'NAME', 'CODE']
        );

        while ($el = $rs->Fetch()) {
            $specs[] = [
                'ID' => (int)$el['ID'],
                'NAME' => $el['NAME'],
                'CODE' => $el['CODE'] ?: $el['ID'],
            ];
        }

        return $specs;
    }
}
