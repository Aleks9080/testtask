<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class testtask_schedule extends CModule
{
    public $MODULE_ID = 'testtask.schedule';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('TESTTASK_SCHEDULE_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('TESTTASK_SCHEDULE_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('TESTTASK_SCHEDULE_PARTNER_NAME');
        $this->PARTNER_URI = '';
    }

    /**
     * Установка модуля
     */
    public function DoInstall()
    {
        // Проверяем наличие зависимости — модуль «Информационные блоки»
        if (!Loader::includeModule('iblock')) {
            throw new \Bitrix\Main\SystemException(
                'Для работы модуля необходим модуль «Информационные блоки» (iblock)'
            );
        }

        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
    }

    /**
     * Удаление модуля
     */
    public function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    /**
     * Создание таблиц в БД
     */
    public function InstallDB()
    {
        $connection = Application::getConnection();

        $sql = "CREATE TABLE IF NOT EXISTS testtask_doctor_schedule (
            id INT(11) NOT NULL AUTO_INCREMENT,
            doctor_id INT(11) NOT NULL,
            date DATE NOT NULL COMMENT 'Дата расписания',
            is_working TINYINT(1) NOT NULL DEFAULT 1,
            time_start VARCHAR(5) DEFAULT '09:00',
            time_end VARCHAR(5) DEFAULT '18:00',
            break_start VARCHAR(5) DEFAULT NULL,
            break_end VARCHAR(5) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_doctor_date (doctor_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $connection->queryExecute($sql);
    }

    /**
     * Удаление таблиц из БД
     */
    public function UnInstallDB()
    {
        $connection = Application::getConnection();
        $connection->queryExecute("DROP TABLE IF EXISTS testtask_doctor_schedule");
    }

    /**
     * Копирование файлов модуля
     */
    public function InstallFiles()
    {
        $modulePath = __DIR__ . '/..';

        // Копируем CSS
        CopyDirFiles(
            $modulePath . '/css',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/testtask.schedule',
            true, true
        );

        // Копируем JS
        CopyDirFiles(
            $modulePath . '/js',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/testtask.schedule',
            true, true
        );

        // Копируем административные страницы
        CopyDirFiles(
            $modulePath . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true, true
        );

        // Копируем компоненты
        CopyDirFiles(
            __DIR__ . '/components/testtask',
            $_SERVER['DOCUMENT_ROOT'] . '/local/components/testtask',
            true, true
        );

        return true;
    }

    /**
     * Удаление файлов модуля
     */
    public function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );

        $cssDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/testtask.schedule';
        if (Directory::isDirectoryExists($cssDir)) {
            Directory::deleteDirectory($cssDir);
        }

        $jsDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/testtask.schedule';
        if (Directory::isDirectoryExists($jsDir)) {
            Directory::deleteDirectory($jsDir);
        }

        // Удаляем компоненты
        $compDir = $_SERVER['DOCUMENT_ROOT'] . '/local/components/testtask/schedule.editor';
        if (Directory::isDirectoryExists($compDir)) {
            Directory::deleteDirectory($compDir);
        }

        return true;
    }
}
