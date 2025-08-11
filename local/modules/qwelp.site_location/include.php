<?php
use Bitrix\Main\Loader;
Loader::registerAutoLoadClasses('qwelp.site_location', [
    'Qwelp\SiteLocation\Context' => 'lib/Context.php',
    'Qwelp\SiteLocation\EventHandlers' => 'lib/EventHandlers.php',
]);
