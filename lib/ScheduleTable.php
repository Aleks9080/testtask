<?php

namespace Testtask\Schedule;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateField;

/**
 * ORM-сущность для таблицы testtask_doctor_schedule
 *
 * Хранит расписание врача по дням недели:
 * - doctor_id   — ID врача (пользователь Bitrix)
 * - day_of_week — день недели (1=Пн … 7=Вс)
 * - is_working  — рабочий день или выходной
 * - time_start  — время начала приёма
 * - time_end    — время окончания приёма
 * - break_start — начало перерыва (опционально)
 * - break_end   — конец перерыва (опционально)
 */
class ScheduleTable extends DataManager
{
    /**
     * Имя таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'testtask_doctor_schedule';
    }

    /**
     * Карта полей ORM-сущности
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('id', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),
            new IntegerField('doctor_id', [
                'required' => true,
                'title' => 'ID врача',
            ]),
            new DateField('date', [
                'required' => true,
                'title' => 'Дата расписания',
            ]),
            new BooleanField('is_working', [
                'values' => [0, 1],
                'default_value' => 1,
                'title' => 'Рабочий день',
            ]),
            new StringField('time_start', [
                'size' => 5,
                'default_value' => '09:00',
                'title' => 'Начало работы',
            ]),
            new StringField('time_end', [
                'size' => 5,
                'default_value' => '18:00',
                'title' => 'Окончание работы',
            ]),
            new StringField('break_start', [
                'size' => 5,
                'nullable' => true,
                'title' => 'Начало перерыва',
            ]),
            new StringField('break_end', [
                'size' => 5,
                'nullable' => true,
                'title' => 'Конец перерыва',
            ]),
        ];
    }

    /**
     * Получить расписание врача по датам
     *
     * @param int $doctorId ID врача
     * @param \Bitrix\Main\Type\Date|null $startDate Начальная дата
     * @param \Bitrix\Main\Type\Date|null $endDate Конечная дата
     * @return array Массив расписания по датам (ключ - дата YYYY-MM-DD) или по дням недели (если даты не указаны)
     */
    public static function getScheduleByDoctor(int $doctorId, \Bitrix\Main\Type\Date $startDate = null, \Bitrix\Main\Type\Date $endDate = null): array
    {
        $filter = ['=doctor_id' => $doctorId];
        
        if ($startDate) {
            $filter['>=date'] = $startDate;
        }
        if ($endDate) {
            $filter['<=date'] = $endDate;
        }
        
        $result = self::getList([
            'filter' => $filter,
            'order' => ['date' => 'ASC'],
        ]);

        $schedule = [];
        while ($row = $result->fetch()) {
            if (isset($row['date'])) {
                // Если есть поле date, используем его
                $dateStr = $row['date']->format('Y-m-d');
                $schedule[$dateStr] = $row;
            } else {
                // Иначе используем day_of_week (для обратной совместимости)
                $schedule[$row['day_of_week']] = $row;
            }
        }

        return $schedule;
    }

    /**
     * Сохранить расписание врача
     *
     * @param int $doctorId ID врача
     * @param array $days Массив данных по датам (ключ - дата YYYY-MM-DD)
     * @return bool
     */
    public static function saveSchedule(int $doctorId, array $days): bool
    {
        foreach ($days as $dateStr => $dayData) {
            try {
                $date = new \Bitrix\Main\Type\Date($dateStr, 'Y-m-d');
            } catch (\Exception $e) {
                continue; // Пропускаем некорректные даты
            }
            
            $existing = self::getList([
                'filter' => [
                    '=doctor_id' => $doctorId,
                    '=date' => $date,
                ],
                'select' => ['id'],
            ])->fetch();

            $fields = [
                'doctor_id' => $doctorId,
                'date' => $date,
                'is_working' => (int)($dayData['is_working'] ?? 0),
                'time_start' => $dayData['time_start'] ?? '09:00',
                'time_end' => $dayData['time_end'] ?? '18:00',
                'break_start' => $dayData['break_start'] ?: null,
                'break_end' => $dayData['break_end'] ?: null,
            ];

            if ($existing) {
                $res = self::update($existing['id'], $fields);
            } else {
                $res = self::add($fields);
            }

            if (!$res->isSuccess()) {
                return false;
            }
        }

        return true;
    }
}
