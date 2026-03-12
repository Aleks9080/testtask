<?php

/**
 * Пункт меню в административной панели Bitrix
 */

use Bitrix\Main\Loader;

if (!Loader::includeModule('testtask.schedule')) {
    return [];
}

return [
    [
        'parent_menu' => 'global_menu_services',
        'sort' => 500,
        'text' => 'Расписание врачей',
        'title' => 'Управление расписанием врачей',
        'icon' => 'fileman_sticker_icon',
        'page_icon' => 'fileman_sticker_icon',
        'items_id' => 'testtask_schedule',
        'items' => [
            [
                'text' => 'Настройка расписания',
                'title' => 'Настройка рабочей недели врача',
                'url' => 'doctor_schedule.php',
                'more_url' => ['doctor_schedule.php'],
            ],
        ],
    ],
];
