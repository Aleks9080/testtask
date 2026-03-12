<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('testtask.schedule', [
    'Testtask\\Schedule\\ScheduleTable' => 'lib/ScheduleTable.php',
]);
