<?php
/*
 * Заготовка крон
 * */

$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('BX_NO_ACCELERATOR_RESET', true);
define('CHK_EVENT', true);
define('BX_WITH_ON_AFTER_EPILOG', true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

@set_time_limit(0);
@ignore_user_abort(true);

define("BX_CRONTAB_SUPPORT", true);
define("BX_CRONTAB", true);

// Include the qwelp.site_settings module
if (!CModule::IncludeModule('qwelp.site_settings')) {
    die('Module qwelp.site_settings is not installed');
}



$prop = \Qwelp\SiteSettings\OptionsManager::getTechData('dopolnitelnyy-tsvet-element');

echo "<pre>";
print_r($prop);
echo "</pre>";
