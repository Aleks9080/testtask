<?php

/**
 * AJAX-обработчик для сохранения расписания врача
 *
 * Принимает POST-запрос с данными формы и сохраняет через ORM.
 * Возвращает JSON-ответ.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    // Проверка авторизации
    global $USER;
    if (!$USER->IsAdmin()) {
        throw new \Exception('Доступ запрещён');
    }

    // Проверка CSRF-токена
    if (!check_bitrix_sessid()) {
        throw new \Exception('Неверный токен безопасности. Обновите страницу.');
    }

    // Проверка метода
    $request = Application::getInstance()->getContext()->getRequest();
    if (!$request->isPost()) {
        throw new \Exception('Допускается только POST-запрос');
    }

    // Загрузка модуля
    if (!Loader::includeModule('testtask.schedule')) {
        throw new \Exception('Модуль testtask.schedule не установлен');
    }

    $doctorId = (int)$request->getPost('doctor_id');
    $days = $request->getPost('days');

    if ($doctorId <= 0) {
        throw new \Exception('Не указан ID врача');
    }

    if (!is_array($days) || empty($days)) {
        throw new \Exception('Не переданы данные расписания');
    }

    // Валидация времени
    $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
    foreach ($days as $dayNum => $dayData) {
        $dayNum = (int)$dayNum;
        if ($dayNum < 1 || $dayNum > 7) {
            throw new \Exception("Некорректный номер дня: $dayNum");
        }

        if (!empty($dayData['is_working'])) {
            // Проверяем формат времени
            if (!preg_match($timePattern, $dayData['time_start'])) {
                throw new \Exception("Некорректное время начала для дня $dayNum");
            }
            if (!preg_match($timePattern, $dayData['time_end'])) {
                throw new \Exception("Некорректное время окончания для дня $dayNum");
            }

            // Проверяем, что время окончания позже начала
            if ($dayData['time_start'] >= $dayData['time_end']) {
                throw new \Exception("Время окончания должно быть позже начала (день $dayNum)");
            }

            // Проверяем перерыв, если указан
            if (!empty($dayData['break_start']) && !empty($dayData['break_end'])) {
                if (!preg_match($timePattern, $dayData['break_start'])) {
                    throw new \Exception("Некорректное время начала перерыва (день $dayNum)");
                }
                if (!preg_match($timePattern, $dayData['break_end'])) {
                    throw new \Exception("Некорректное время окончания перерыва (день $dayNum)");
                }
                if ($dayData['break_start'] >= $dayData['break_end']) {
                    throw new \Exception("Время окончания перерыва должно быть позже начала (день $dayNum)");
                }
                if ($dayData['break_start'] < $dayData['time_start'] || $dayData['break_end'] > $dayData['time_end']) {
                    throw new \Exception("Перерыв должен быть внутри рабочего времени (день $dayNum)");
                }
            }
        }
    }

    // Сохранение
    $result = \Testtask\Schedule\ScheduleTable::saveSchedule($doctorId, $days);

    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Расписание успешно сохранено';
    } else {
        throw new \Exception('Ошибка при сохранении данных');
    }

} catch (\Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo Json::encode($response);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
