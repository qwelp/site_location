<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

/** @var array $arResult */
/** @var array $arParams */
/** @var CBitrixComponentTemplate $this */

// Подключаем языковые файлы
Loc::loadMessages(__FILE__);

if (!$arResult['IBLOCK_ID']):
	?>
	<div class="city-selector"><?= Loc::getMessage('QWELP_CITY_SELECTOR_IBLOCK_NOT_SET') ?></div>
	<?php return; endif;

// Получаем текущий город
$currentCity = null;
if (class_exists('\Qwelp\SiteLocation\Context')) {
	$currentCity = \Qwelp\SiteLocation\Context::getCurrent();
}

$componentId = 'city-selector-' . randString(6);

// Путь к ajax файлу в текущем шаблоне
$ajaxTestPath = $this->GetFolder() . '/ajax_test.php';

// Создаем структуру данных для трехуровневого селектора
$countries = [];
$regions = [];
$subjects = [];
$sections = $arResult['SECTIONS'] ?? [];

foreach ($sections as $sectionId => $section) {
	if (!$section['IBLOCK_SECTION_ID']) {
		// Это страна (корневой уровень)
		$countries[$sectionId] = $section;
	} else {
		// Ищем родителя
		$parentId = $section['IBLOCK_SECTION_ID'];
		if (isset($countries[$parentId])) {
			// Это регион (второй уровень)
			$regions[$sectionId] = $section;
			$regions[$sectionId]['COUNTRY_ID'] = $parentId;
		} else {
			// Это субъект (третий уровень)
			$subjects[$sectionId] = $section;
			// Ищем регион-родителя
			if (isset($regions[$parentId])) {
				$subjects[$sectionId]['REGION_ID'] = $parentId;
				$subjects[$sectionId]['COUNTRY_ID'] = $regions[$parentId]['COUNTRY_ID'];
			}
		}
	}
}

// Определяем популярные города по свойству IS_POPULAR или берем все города
$popularCities = [];
$citiesWithProps = [];

// Получаем свойства городов
foreach ($arResult['CITIES'] as $city) {
	$cityWithProps = \Qwelp\SiteLocation\Context::byId($city['ID'], $arResult['IBLOCK_ID']);
	if ($cityWithProps) {
		$citiesWithProps[] = $cityWithProps;

		// Если у города есть свойство IS_POPULAR = Y, то он популярный
		if (isset($cityWithProps['PROPS']['IS_POPULAR']) && $cityWithProps['PROPS']['IS_POPULAR'] === 'Y') {
			$popularCities[] = $cityWithProps;
		}
	}
}

// Если популярных городов нет, берем первые 8 городов
if (empty($popularCities)) {
	$popularCities = array_slice($citiesWithProps, 0, 8);
}

// Используем битриксовые методы для работы с cookies
$request = Application::getInstance()->getContext()->getRequest();

// Проверяем, нужно ли показывать popup подтверждения
$showRegionPopup = $currentCity
	&& !$request->getCookie('REGION_CONFIRMED')
	&& !$request->getCookie('CITY_SELECTED_VIA_MODAL');

// Определяем текст для региона
$regionText = '';
if ($currentCity) {
	$props = $currentCity['PROPS'] ?? [];
	if (!empty($props['DECL_ACC'])) {
		$regionText = $props['DECL_ACC'];
	} else {
		$regionText = $currentCity['NAME'];
	}
}
?>

<div class="address-point">
	<button class="address-point__button"
	        aria-label="<?= Loc::getMessage('QWELP_CITY_SELECTOR_ARIA_SELECT_CITY') ?>"
	        aria-haspopup="dialog"
	        aria-expanded="false"
	        aria-controls="<?= $componentId ?>-popup"
	        onclick="LocationSelector.showSelector('<?= $componentId ?>')">
		<svg class="address-point__icon" aria-hidden="true">
			<use href="<?=SITE_TEMPLATE_PATH?>/assets/images/icons.svg#map"></use>
		</svg>
		<span class="address-point__current">
			<?= $currentCity ? htmlspecialcharsbx($currentCity['NAME']) : Loc::getMessage('QWELP_CITY_SELECTOR_SELECT_CITY') ?>
		</span>
	</button>

	<?php if ($showRegionPopup): ?>
		<div id="<?= $componentId ?>-popup"
		     class="address-point__popup"
		     role="dialog"
		     aria-label="<?= Loc::getMessage('QWELP_CITY_SELECTOR_ARIA_REGION_CONFIRM') ?>"
		     style="display: none;">
			<div class="address-point__popup-left">
				<div class="address-point__pin" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="29" height="38" viewBox="0 0 29 38" fill="none">
						<path opacity="0.2" d="M-0.000244141 15.2224C-0.000244141 6.88911 6.4442 0.111328 14.4442 0.111328C22.4442 0.111328 28.8886 6.88911 28.8886 15.2224C28.8886 22.0002 21.5553 31.5558 17.3331 36.5558C15.7775 38.3335 13.1109 38.3335 11.5553 36.5558C7.33309 31.5558 -0.000244141 22.0002 -0.000244141 15.2224Z" class="fill-primary"></path>
						<path d="M14.4442 9C17.5553 9 19.9998 11.4444 19.9998 14.5556C19.9998 17.6667 17.5553 20.1111 14.4442 20.1111C11.3331 20.1111 8.88867 17.6667 8.88867 14.5556C8.88867 11.4444 11.3331 9 14.4442 9Z" class="fill-primary"></path>
					</svg>
				</div>
				<p class="address-point__text">
					<span class="address-point__text-line"><?= Loc::getMessage('QWELP_CITY_SELECTOR_YOUR_REGION') ?> <?= htmlspecialcharsbx($regionText) ?>,</span>
					<span class="address-point__text-line"><?= Loc::getMessage('QWELP_CITY_SELECTOR_COUNTRY_RF') ?></span>
				</p>
			</div>
			<div class="address-point__actions" aria-label="<?= Loc::getMessage('QWELP_CITY_SELECTOR_ARIA_ACTIONS') ?>">
				<button type="button" class="button-main" onclick="LocationSelector.confirmRegion('<?= $componentId ?>')">
					<?= Loc::getMessage('QWELP_CITY_SELECTOR_CONFIRM_YES') ?>
				</button>
				<button type="button" class="button-main_light button-main" onclick="LocationSelector.showModal('<?= $componentId ?>')">
					<?= Loc::getMessage('QWELP_CITY_SELECTOR_CONFIRM_NO') ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<!-- Модальное окно селектора -->
	<div id="<?= $componentId ?>-overlay" class="location-selector__overlay" style="display: none;">
		<div class="location-selector__modal">
			<div class="location-selector__header">
				<h2 class="location-selector__title"><?= Loc::getMessage('QWELP_CITY_SELECTOR_CHOOSE_CITY') ?></h2>
				<button class="location-selector__close" onclick="LocationSelector.hideSelector('<?= $componentId ?>')" aria-label="<?= Loc::getMessage('QWELP_CITY_SELECTOR_CLOSE') ?>">
					<svg class="location-selector__close-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
						<path d="M1 1l8 8M9 1L1 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					</svg>
				</button>
			</div>

			<div class="location-selector__search">
				<svg class="location-selector__search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
					<path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
				</svg>
				<input type="text"
				       id="<?= $componentId ?>-search"
				       class="location-selector__search-input"
				       placeholder="<?= Loc::getMessage('QWELP_CITY_SELECTOR_SEARCH_PLACEHOLDER') ?>"
				       autocomplete="off">
			</div>

			<!-- Популярные города -->
			<div class="location-selector__popular" id="<?= $componentId ?>-popular" style="display: flex;">
				<?php foreach ($popularCities as $city): ?>
					<button class="location-selector__popular-city<?= ($currentCity && $currentCity['ID'] == $city['ID']) ? ' location-selector__popular-city_active' : '' ?>"
					        data-city-id="<?= $city['ID'] ?>"
					        data-city-name="<?= htmlspecialcharsbx($city['NAME']) ?>"
					        onclick="LocationSelector.selectCity('<?= $componentId ?>', <?= $city['ID'] ?>, '<?= CUtil::JSEscape($city['NAME']) ?>')">
						<?= htmlspecialcharsbx($city['NAME']) ?>
					</button>
				<?php endforeach; ?>
			</div>

			<!-- Трехколоночный селектор -->
			<div class="location-selector__content" id="<?= $componentId ?>-content" style="display: none;">
				<div class="location-selector__column">
					<h3 class="location-selector__column-title"><?= Loc::getMessage('QWELP_CITY_SELECTOR_COLUMN_DISTRICT') ?></h3>
					<div class="location-selector__list" id="<?= $componentId ?>-countries">
						<?php foreach ($countries as $countryId => $country): ?>
							<button class="location-selector__list-item"
							        data-country-id="<?= $countryId ?>"
							        onclick="LocationSelector.selectCountry('<?= $componentId ?>', <?= $countryId ?>)">
								<?= htmlspecialcharsbx($country['NAME']) ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="location-selector__column">
					<h3 class="location-selector__column-title"><?= Loc::getMessage('QWELP_CITY_SELECTOR_COLUMN_REGION') ?></h3>
					<div class="location-selector__list" id="<?= $componentId ?>-regions">
						<!-- Заполняется через JavaScript -->
					</div>
				</div>

				<div class="location-selector__column">
					<h3 class="location-selector__column-title"><?= Loc::getMessage('QWELP_CITY_SELECTOR_COLUMN_CITY') ?></h3>
					<div class="location-selector__list" id="<?= $componentId ?>-cities">
						<!-- Заполняется через JavaScript -->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	// Передаем данные компонента и языковые константы в JavaScript
	window.locationSelectorData = window.locationSelectorData || {};
	window.locationSelectorData['<?= $componentId ?>'] = {
		cities: <?= CUtil::PhpToJSObject($arResult['CITIES']) ?>,
		regions: <?= CUtil::PhpToJSObject($regions) ?>,
		countries: <?= CUtil::PhpToJSObject($countries) ?>,
		subjects: <?= CUtil::PhpToJSObject($subjects) ?>,
		messages: {
			'QWELP_CITY_SELECTOR_NO_RESULTS': '<?= CUtil::JSEscape(Loc::getMessage('QWELP_CITY_SELECTOR_NO_CITIES_FOUND')) ?>',
			'QWELP_CITY_SELECTOR_ERROR_SELECTION': '<?= CUtil::JSEscape(Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_SELECTION')) ?>',
			'QWELP_CITY_SELECTOR_ERROR_NETWORK': '<?= CUtil::JSEscape(Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_NETWORK')) ?>',
			'QWELP_CITY_SELECTOR_ERROR_BX_UNAVAILABLE': '<?= CUtil::JSEscape(Loc::getMessage('QWELP_CITY_SELECTOR_ERROR_BX_UNAVAILABLE')) ?>'
		},
		ajaxPath: '<?= $this->GetFolder() ?>/ajax.php',
		ajaxTestPath: '<?= $ajaxTestPath ?>',
		showRegionPopup: <?= $showRegionPopup ? 'true' : 'false' ?>
	};

	// Инициализируем селектор
	if (window.LocationSelector) {
		LocationSelector.init('<?= $componentId ?>', window.locationSelectorData['<?= $componentId ?>']);
	}
</script>