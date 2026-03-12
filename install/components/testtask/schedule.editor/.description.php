<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => 'Расписание врача',
    'DESCRIPTION' => 'Комплексный компонент для отображения списка врачей и их расписания работы. Поддерживает ЧПУ и два режима: список врачей и детальная страница с расписанием.',
    'ICON' => '/images/icon.gif',
    'SORT' => 10,
    'CACHE_PATH' => 'Y',
    'COMPLEX' => 'Y',
    'PATH' => [
        'ID' => 'testtask',
        'NAME' => 'TestTask',
        'CHILD' => [
            'ID' => 'schedule',
            'NAME' => 'Расписание',
        ],
    ],
];
