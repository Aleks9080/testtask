<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
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

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['ELEMENT_ID'] = (int)($arParams['ELEMENT_ID'] ?? 0);
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
        if ($this->iblockId <= 0) {
            ShowError('Не указан инфоблок с врачами');
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
        $this->arResult['IBLOCK_ID'] = $this->iblockId;
        $this->arResult['DOCTORS'] = $this->getDoctorsList();
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

        // Получаем расписание
        $schedule = ScheduleTable::getScheduleByDoctor($this->doctorId);

        $this->arResult['ELEMENT'] = $element;
        $this->arResult['SCHEDULE'] = $schedule;
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

        // Установка заголовка
        if ($this->arParams['SET_TITLE'] === 'Y') {
            global $APPLICATION;
            $APPLICATION->SetTitle('Расписание: ' . $element['NAME']);
        }

        $this->includeComponentTemplate('detail');
    }

    /**
     * Список активных элементов (врачей) из инфоблока
     */
    protected function getDoctorsList(): array
    {
        $doctors = [];
        $rs = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $this->iblockId, 'ACTIVE' => 'Y'],
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
}
