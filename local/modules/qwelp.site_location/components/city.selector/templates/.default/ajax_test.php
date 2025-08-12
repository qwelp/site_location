<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Localization\Loc;

// Подключаем языковые файлы компонента
Loc::loadMessages(__DIR__ . '/template.php');

header('Content-Type: application/json; charset=utf-8');

if (!check_bitrix_sessid() && $_REQUEST['sessid']) {
	echo json_encode(['error' => Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_SESSID') ?: 'Неверный sessid']);
	die();
}

if (!\Bitrix\Main\Loader::includeModule('qwelp.site_location')) {
	echo json_encode(['error' => Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_MODULE') ?: 'Модуль qwelp.site_location не подключен']);
	die();
}

$action = $_POST['action'] ?? '';

switch ($action) {
	case 'get_current_city':
		$city = \Qwelp\SiteLocation\Context::getCurrent();

		if ($city) {
			echo json_encode([
				'success' => true,
				'city' => [
					'id' => $city['ID'],
					'name' => $city['NAME'],
					'code' => $city['CODE'],
					'section_id' => $city['SECTION_ID'],
					'properties' => $city['PROPS']
				],
				'timestamp' => date('Y-m-d H:i:s')
			]);
		} else {
			echo json_encode([
				'success' => false,
				'error' => Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_CITY_NOT_DEFINED') ?: 'Город не определен',
				'debug' => [
					'iblock_id' => (int)\Bitrix\Main\Config\Option::get('qwelp.site_location', 'IBLOCK_ID', '0'),
					'cookie' => $_COOKIE['SITE_CITY'] ?? null,
					'mode' => \Bitrix\Main\Config\Option::get('qwelp.site_location', 'MODE', 'path')
				]
			]);
		}
		break;

	case 'test_placeholders':
		$text = $_POST['text'] ?? '';
		$originalText = $text;

		$processedText = \Qwelp\SiteLocation\Context::replacePlaceholders($text);

		echo json_encode([
			'success' => true,
			'original' => $originalText,
			'processed' => $processedText,
			'city' => \Qwelp\SiteLocation\Context::getCurrent(),
			'timestamp' => date('Y-m-d H:i:s')
		]);
		break;

	default:
		echo json_encode(['error' => Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_UNKNOWN_ACTION') ?: 'Неизвестное действие: ' . $action]);
		break;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
?>