<?php
use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Iblock;

Loc::loadMessages(__FILE__);

class qwelp_site_location extends CModule
{
	public $MODULE_ID = 'qwelp.site_location';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';
		$this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d');
		$this->MODULE_NAME = 'Местоположения сайта (инфоблок)';
		$this->MODULE_DESCRIPTION = 'Справочник городов в ИБ + селектор города + контекст';
		$this->PARTNER_NAME = 'Qwelp';
		$this->PARTNER_URI = 'https://example.com';
	}

	public function DoInstall()
	{
		if (!Loader::includeModule('iblock')) {
			throw new \RuntimeException('Модуль iblock обязателен');
		}

		try {
			// 1. Сначала регистрируем модуль
			RegisterModule($this->MODULE_ID);

			// 2. Устанавливаем файлы
			$this->InstallFiles();

			// 3. Устанавливаем БД и настройки
			$this->InstallDB();

			// 4. Регистрируем события
			$this->InstallEvents();

			return true;
		} catch (\Exception $e) {
			// При ошибке - откатываем установку
			UnRegisterModule($this->MODULE_ID);
			throw $e;
		}
	}

	public function DoUninstall()
	{
		// Удаляем события
		$this->UnInstallEvents();

		// Удаляем файлы
		$this->UnInstallFiles();

		// Удаляем настройки модуля
		Option::delete($this->MODULE_ID);

		// Снимаем регистрацию модуля
		UnRegisterModule($this->MODULE_ID);
	}

	public function InstallFiles()
	{
		$sourceDir = __DIR__ . '/../components';
		$targetDir = $_SERVER['DOCUMENT_ROOT'] . '/local/components/qwelp.site_location';

		if (!is_dir($sourceDir)) {
			throw new \RuntimeException("Папка компонентов не найдена: $sourceDir");
		}

		// Создаем целевую папку если не существует
		if (!is_dir($targetDir)) {
			if (!mkdir($targetDir, 0755, true)) {
				throw new \RuntimeException("Не удалось создать папку: $targetDir");
			}
		}

		// Копируем компоненты
		if (!CopyDirFiles($sourceDir, $targetDir, true, true)) {
			throw new \RuntimeException("Не удалось скопировать компоненты из $sourceDir в $targetDir");
		}

		// Сохраняем информацию об установленных файлах
		Option::set($this->MODULE_ID, 'INSTALLED_FILES', serialize([
			'components_dir' => $targetDir
		]));
	}

	public function UnInstallFiles()
	{
		// Получаем информацию об установленных файлах
		$installedFiles = Option::get($this->MODULE_ID, 'INSTALLED_FILES', '');

		if ($installedFiles) {
			$files = unserialize($installedFiles);

			// Удаляем компоненты
			if (isset($files['components_dir']) && is_dir($files['components_dir'])) {
				$this->removeDirectory($files['components_dir']);
			}
		}

		// Удаляем временный файл конфигурации (если был создан)
		$configFile = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/qwelp_site_location_config.php';
		if (file_exists($configFile)) {
			unlink($configFile);
		}
	}

	public function InstallEvents()
	{
		// Валидатор единственного IS_DEFAULT
		Main\EventManager::getInstance()->registerEventHandler(
			'iblock',
			'OnBeforeIBlockElementAdd',
			$this->MODULE_ID,
			\Qwelp\SiteLocation\EventHandlers::class,
			'onBeforeElementSave'
		);
		Main\EventManager::getInstance()->registerEventHandler(
			'iblock',
			'OnBeforeIBlockElementUpdate',
			$this->MODULE_ID,
			\Qwelp\SiteLocation\EventHandlers::class,
			'onBeforeElementSave'
		);
	}

	public function UnInstallEvents()
	{
		Main\EventManager::getInstance()->unRegisterEventHandler(
			'iblock',
			'OnBeforeIBlockElementAdd',
			$this->MODULE_ID,
			\Qwelp\SiteLocation\EventHandlers::class,
			'onBeforeElementSave'
		);
		Main\EventManager::getInstance()->unRegisterEventHandler(
			'iblock',
			'OnBeforeIBlockElementUpdate',
			$this->MODULE_ID,
			\Qwelp\SiteLocation\EventHandlers::class,
			'onBeforeElementSave'
		);
	}

	public function InstallDB()
	{
		Loader::includeModule('iblock');

		// 1) Создаём тип ИБ (если нет)
		$typeId = 'references';
		$this->ensureIblockType($typeId, 'Справочники');

		// 2) Создаём инфоблок «Города»
		$ibId = $this->ensureIblockCities($typeId);

		// 3) Создаём разделы (Россия → Урал → Свердловская область)
		[$countryId, $regionId, $subjectId] = $this->ensureSections($ibId, [
			'Россия', 'Урал', 'Свердловская область'
		]);

		// 4) Свойства элементов
		$this->ensureProperties($ibId);

		// 5) Демо-элементы
		$this->ensureDemoCities($ibId, $subjectId);

		// 6) Сохраняем настройки модуля
		$this->saveModuleOptions($ibId);
	}

	private function saveModuleOptions(int $ibId): void
	{
		$options = [
			'IBLOCK_ID' => (string)$ibId,
			'MODE' => 'path',
			'COOKIE_NAME' => 'SITE_CITY',
			'COOKIE_DAYS' => '180',
			'GEOIP_ENABLED' => 'Y',
			'SEO_CANONICAL' => 'Y',
			'SEO_HREFLANG' => 'Y'
		];

		// Пытаемся сохранить настройки
		foreach ($options as $name => $value) {
			try {
				Option::set($this->MODULE_ID, $name, $value);
			} catch (\Exception $e) {
				// Если не удается сохранить через Option, пробуем старый способ
				try {
					COption::SetOptionString($this->MODULE_ID, $name, $value);
				} catch (\Exception $e2) {
					throw new \RuntimeException("Не удалось сохранить настройку $name: " . $e2->getMessage());
				}
			}
		}

		// Проверяем, что главная настройка сохранилась
		$savedId = 0;
		try {
			$savedId = (int)Option::get($this->MODULE_ID, 'IBLOCK_ID', '0');
		} catch (\Exception $e) {
			$savedId = (int)COption::GetOptionString($this->MODULE_ID, 'IBLOCK_ID', '0');
		}

		if ($savedId !== $ibId) {
			// Крайний случай - создаем файл с константой
			$this->createConfigFile($ibId);

			// Сохраняем информацию о файле конфигурации
			Option::set($this->MODULE_ID, 'CONFIG_FILE_CREATED', 'Y');

			// Не выбрасываем исключение - модуль будет работать через константу
		}
	}

	private function createConfigFile(int $ibId): void
	{
		$configDir = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface';
		if (!is_dir($configDir)) {
			mkdir($configDir, 0755, true);
		}

		$configFile = $configDir . '/qwelp_site_location_config.php';
		$configContent = "<?php\n";
		$configContent .= "// Конфигурация модуля qwelp.site_location\n";
		$configContent .= "// Создан автоматически при установке модуля\n";
		$configContent .= "if (!defined('QWELP_SITE_LOCATION_IBLOCK_ID')) {\n";
		$configContent .= "    define('QWELP_SITE_LOCATION_IBLOCK_ID', $ibId);\n";
		$configContent .= "}\n";

		file_put_contents($configFile, $configContent);
	}

	private function removeDirectory(string $dir): bool
	{
		if (!is_dir($dir)) {
			return false;
		}

		$files = array_diff(scandir($dir), ['.', '..']);

		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		return rmdir($dir);
	}

	private function ensureIblockType(string $typeId, string $name)
	{
		$res = CIBlockType::GetByID($typeId);
		if ($res->Fetch()) return;

		$arFields = [
			'ID' => $typeId,
			'SECTIONS' => 'Y',
			'IN_RSS' => 'N',
			'SORT' => 100,
			'LANG' => [
				'ru' => [
					'NAME' => $name,
					'SECTION_NAME' => 'Раздел',
					'ELEMENT_NAME' => 'Элемент',
				],
			],
		];
		$obBlocktype = new CIBlockType;
		if (!$obBlocktype->Add($arFields)) {
			throw new \RuntimeException('Не удалось создать тип ИБ: ' . $obBlocktype->LAST_ERROR);
		}
	}

	private function ensureIblockCities(string $typeId): int
	{
		// Определяем сайты
		$siteIds = [];
		$siteRes = Main\SiteTable::getList(['select' => ['LID']]);
		while ($site = $siteRes->fetch()) { $siteIds[] = $site['LID']; }
		if (!$siteIds) { $siteIds = ['s1']; }

		$code = 'CITIES';
		$ibRes = CIBlock::GetList([], ['CODE' => $code]);
		if ($ib = $ibRes->Fetch()) {
			return (int)$ib['ID'];
		}

		$ib = new CIBlock;
		$ibId = $ib->Add([
			'ACTIVE' => 'Y',
			'NAME' => 'Города',
			'CODE' => $code,
			'LIST_PAGE_URL' => '#SITE_DIR#/cities/',
			'DETAIL_PAGE_URL' => '#SITE_DIR#/cities/#ELEMENT_CODE#/',
			'IBLOCK_TYPE_ID' => $typeId,
			'SITE_ID' => $siteIds,
			'GROUP_ID' => ['2' => 'R'], // все могут читать
			'VERSION' => 2,
		]);
		if (!$ibId) {
			throw new \RuntimeException('Не удалось создать ИБ Города: ' . $ib->LAST_ERROR);
		}
		return (int)$ibId;
	}

	private function ensureSections(int $ibId, array $chain): array
	{
		$parentId = 0; $ids = [];
		foreach ($chain as $name) {
			$secId = $this->getOrCreateSection($ibId, $name, $parentId);
			$ids[] = $secId; $parentId = $secId;
		}
		return $ids; // [country, region, subject]
	}

	private function getOrCreateSection(int $ibId, string $name, int $parentId = 0): int
	{
		$filter = ['IBLOCK_ID' => $ibId, 'SECTION_ID' => $parentId, 'NAME' => $name];
		$res = CIBlockSection::GetList([], $filter, false, ['ID']);
		if ($sec = $res->Fetch()) return (int)$sec['ID'];

		$bs = new CIBlockSection();
		$secId = $bs->Add([
			'IBLOCK_ID' => $ibId,
			'IBLOCK_SECTION_ID' => $parentId ?: false,
			'ACTIVE' => 'Y',
			'NAME' => $name,
			'CODE' => ToLower(CUtil::translit($name, 'ru', ['replace_space' => '-', 'replace_other' => '-']))
		]);
		if (!$secId) throw new \RuntimeException('Не удалось создать раздел: ' . $bs->LAST_ERROR);
		return (int)$secId;
	}

	private function ensureProperties(int $ibId): void
	{
		$props = [
			// Базовые
			['CODE' => 'SALE_LOCATION_CODE', 'NAME' => 'Код местоположения (Sale)', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'IS_DEFAULT', 'NAME' => 'Город по умолчанию', 'PROPERTY_TYPE' => 'L', 'LIST_TYPE' => 'C', 'VALUES' => [['VALUE' => 'Y', 'DEF' => 'N', 'SORT' => 10]]],

			// Адресация
			['CODE' => 'MAIN_DOMAIN', 'NAME' => 'Основной домен/поддомен', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DOMAINS', 'NAME' => 'Доп. домены', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
			['CODE' => 'PATH_SEGMENT', 'NAME' => 'Путь (URL сегмент)', 'PROPERTY_TYPE' => 'S'],

			// Контакты
			['CODE' => 'PHONE', 'NAME' => 'Телефон', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
			['CODE' => 'EMAIL', 'NAME' => 'E-mail', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
			['CODE' => 'ADDRESS', 'NAME' => 'Адрес', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
			['CODE' => 'WORKING_HOURS', 'NAME' => 'Режим работы', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'Y'],
			['CODE' => 'CONTACTS_TEXT', 'NAME' => 'Текст для страницы контактов', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'HTML'],

			// Карта (Яндекс)
			['CODE' => 'MAP_YANDEX', 'NAME' => 'Карта Яндекс', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'map_yandex'],

			// Привязки к Битрикс
			['CODE' => 'CATALOG_STORES', 'NAME' => 'ID складов (catalog_store)', 'PROPERTY_TYPE' => 'N', 'MULTIPLE' => 'Y'],
			['CODE' => 'PRICE_GROUPS', 'NAME' => 'ID типов цен (catalog_group)', 'PROPERTY_TYPE' => 'N', 'MULTIPLE' => 'Y'],

			// Склонения
			['CODE' => 'DECL_NOM', 'NAME' => 'Именительный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_GEN', 'NAME' => 'Родительный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_DAT', 'NAME' => 'Дательный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_ACC', 'NAME' => 'Винительный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_INS', 'NAME' => 'Творительный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_PRE', 'NAME' => 'Предложный', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_IN', 'NAME' => 'Форма после «в»', 'PROPERTY_TYPE' => 'S'],
			['CODE' => 'DECL_FROM', 'NAME' => 'Форма после «из»', 'PROPERTY_TYPE' => 'S'],
		];

		foreach ($props as $p) {
			$this->ensureProperty($ibId, $p);
		}
	}

	private function ensureProperty(int $ibId, array $fields): void
	{
		$byCode = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ibId, 'CODE' => $fields['CODE']]);
		if ($byCode->Fetch()) return;

		$def = [
			'IBLOCK_ID' => $ibId,
			'ACTIVE' => 'Y',
			'SORT' => 100,
			'MULTIPLE' => $fields['MULTIPLE'] ?? 'N',
			'IS_REQUIRED' => 'N',
			'FILTRABLE' => 'Y',
		];
		$prop = new CIBlockProperty();
		$id = $prop->Add(array_merge($def, $fields));
		if (!$id) throw new \RuntimeException('Свойство не создано: ' . $fields['CODE'] . ' — ' . $prop->LAST_ERROR);
	}

	private function ensureDemoCities(int $ibId, int $sectionId): void
	{
		$this->createCity($ibId, $sectionId, [
			'NAME' => 'Екатеринбург',
			'CODE' => 'ekaterinburg',
			'PATH_SEGMENT' => 'ekb',
			'IS_DEFAULT' => 'Y',
			'DECL' => [
				'DECL_NOM' => 'Екатеринбург',
				'DECL_GEN' => 'Екатеринбурга',
				'DECL_DAT' => 'Екатеринбургу',
				'DECL_ACC' => 'Екатеринбург',
				'DECL_INS' => 'Екатеринбургом',
				'DECL_PRE' => 'Екатеринбурге',
				'DECL_IN'  => 'в Екатеринбурге',
				'DECL_FROM'=> 'из Екатеринбурга',
			],
		]);

		$this->createCity($ibId, $sectionId, [
			'NAME' => 'Нижний Тагил',
			'CODE' => 'nizhniy-tagil',
			'PATH_SEGMENT' => 'ntagil',
			'DECL' => [
				'DECL_NOM' => 'Нижний Тагил',
				'DECL_GEN' => 'Нижнего Тагила',
				'DECL_DAT' => 'Нижнему Тагилу',
				'DECL_ACC' => 'Нижний Тагил',
				'DECL_INS' => 'Нижним Тагилом',
				'DECL_PRE' => 'Нижнем Тагиле',
				'DECL_IN'  => 'в Нижнем Тагиле',
				'DECL_FROM'=> 'из Нижнего Тагила',
			],
		]);
	}

	private function createCity(int $ibId, int $sectionId, array $data): void
	{
		$el = new CIBlockElement();
		$PROP = [];
		if (!empty($data['IS_DEFAULT'])) {
			// Для свойства-списка получаем ID значения "Y"
			$listValueId = $this->getPropertyListValueId($ibId, 'IS_DEFAULT', 'Y');
			if ($listValueId) {
				$PROP['IS_DEFAULT'] = $listValueId;
			}
		}
		if (!empty($data['PATH_SEGMENT'])) {
			$PROP['PATH_SEGMENT'] = $data['PATH_SEGMENT'];
		}
		if (!empty($data['DECL'])) {
			foreach ($data['DECL'] as $code => $val) { $PROP[$code] = $val; }
		}

		$fields = [
			'IBLOCK_ID' => $ibId,
			'IBLOCK_SECTION_ID' => $sectionId,
			'ACTIVE' => 'Y',
			'NAME' => $data['NAME'],
			'CODE' => $data['CODE'],
			'SORT' => 500,
			'PROPERTY_VALUES' => $PROP,
		];

		// Проверка на существование
		$exists = CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, 'CODE' => $data['CODE']], false, false, ['ID'])->Fetch();
		if ($exists) return;

		$id = $el->Add($fields);
		if (!$id) throw new \RuntimeException('Не удалось создать город ' . $data['NAME'] . ': ' . $el->LAST_ERROR);
	}

	private function getPropertyListValueId(int $ibId, string $propCode, string $value): ?int
	{
		$prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ibId, 'CODE' => $propCode])->Fetch();
		if (!$prop) return null;

		$propValues = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'VALUE' => $value]);
		if ($enumValue = $propValues->Fetch()) {
			return (int)$enumValue['ID'];
		}
		return null;
	}
}