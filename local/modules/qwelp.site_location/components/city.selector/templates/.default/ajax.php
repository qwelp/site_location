<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Qwelp\SiteLocation\Context;

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('qwelp.site_location')) {
	http_response_code(500);
	echo json_encode(['status' => 'error', 'message' => 'Модуль не подключен']);
	exit;
}

// Получаем данные запроса
$input = json_decode(file_get_contents('php://input'), true);

// Если JSON не распарсился, пробуем получить через POST
if (!$input) {
	$input = $_POST;
}

if (!$input || !isset($input['action'])) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Неверный запрос']);
	exit;
}

switch ($input['action']) {
	case 'selectCity':
		if (!isset($input['cityId']) || !is_numeric($input['cityId'])) {
			http_response_code(400);
			echo json_encode(['status' => 'error', 'message' => 'ID города не указан']);
			exit;
		}

		try {
			$cityId = (int)$input['cityId'];

			// Используем битриксовые методы для работы с контекстом
			Context::setCurrent($cityId);

			// Устанавливаем дополнительные cookies через битриксовый способ
			$response = Application::getInstance()->getContext()->getResponse();
			$response->addCookie(new \Bitrix\Main\Web\Cookie('CITY_SELECTED_VIA_MODAL', 'Y', time() + 86400 * 30, '/'));

			echo json_encode(['status' => 'ok', 'cityId' => $cityId]);
		} catch (Exception $e) {
			http_response_code(500);
			echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
		}
		break;

	case 'confirmRegion':
		try {
			// Устанавливаем cookie что регион подтвержден
			$response = Application::getInstance()->getContext()->getResponse();
			$response->addCookie(new \Bitrix\Main\Web\Cookie('REGION_CONFIRMED', 'Y', time() + 86400 * 30, '/'));

			echo json_encode(['status' => 'ok']);
		} catch (Exception $e) {
			http_response_code(500);
			echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
		}
		break;

	default:
		http_response_code(400);
		echo json_encode(['status' => 'error', 'message' => 'Неизвестное действие']);
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
?>