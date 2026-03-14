<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

// Получаем список инфоблоков
$arIblocks = [];
if (\Bitrix\Main\Loader::includeModule('iblock')) {
    $rs = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N']);
    while ($ib = $rs->Fetch()) {
        $arIblocks[$ib['ID']] = '[' . $ib['ID'] . '] ' . $ib['NAME'];
    }
}

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => ['NAME' => 'Основные настройки', 'SORT' => 100],
        'SEF' => ['NAME' => 'Настройки ЧПУ', 'SORT' => 200],
    ],
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Инфоблок с врачами',
            'TYPE' => 'LIST',
            'VALUES' => $arIblocks,
            'REFRESH' => 'Y',
        ],
        'SPECIALIZATIONS_IBLOCK_ID' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Инфоблок со специальностями/кабинетами (опционально)',
            'TYPE' => 'LIST',
            'VALUES' => [0 => 'Не использовать'] + $arIblocks,
            'DEFAULT' => 0,
        ],
        'SPECIALIZATION_PROPERTY' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Код свойства связи (должен быть DOCTOR_ID)',
            'TYPE' => 'STRING',
            'DEFAULT' => 'DOCTOR_ID',
        ],
        'SEF_MODE' => [
            'PARENT' => 'SEF',
            'NAME' => 'Включить поддержку ЧПУ',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
            'REFRESH' => 'Y',
        ],
        'SEF_FOLDER' => [
            'PARENT' => 'SEF',
            'NAME' => 'Каталог ЧПУ (относительно корня сайта)',
            'TYPE' => 'STRING',
            'DEFAULT' => '/doctors/',
        ],
        'SEF_URL_TEMPLATES' => [
            'PARENT' => 'SEF',
            'NAME' => 'Адреса страниц',
            'TYPE' => 'CUSTOM',
            'JS_FILE' => '/bitrix/js/iblock/parameters.js',
            'JS_EVENT' => 'IBlockParametersInit',
            'JS_DATA' => \Bitrix\Main\Web\Json::encode([
                'list' => [
                    'NAME' => 'Список врачей',
                    'TEMPLATE' => '',
                    'SELECT' => '',
                ],
                'detail' => [
                    'NAME' => 'Детальная страница врача',
                    'TEMPLATE' => '#ELEMENT_ID#/',
                    'SELECT' => ['ID', 'CODE'],
                ],
            ]),
            'DEFAULT' => [
                'list' => '',
                'detail' => '#ELEMENT_ID#/',
            ],
        ],
        'ELEMENT_ID' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'ID врача (для режима без ЧПУ)',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'SET_TITLE' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Устанавливать заголовок страницы',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SET_STATUS_404' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Устанавливать статус 404',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
    ],
];
