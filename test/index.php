<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Тестирование модуля городов");

// Подключаем модуль
if (!\Bitrix\Main\Loader::includeModule('qwelp.site_location')) {
	echo '<div style="color: red; padding: 20px; border: 1px solid red; margin: 20px;">Модуль qwelp.site_location не установлен!</div>';
	require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
	return;
}
?>

	<style>
        .test-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
        }

        .test-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
        }

        .demo-selector {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .city-info {
            background: #e6f3ff;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #0066cc;
        }

        .debug-info {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }

        .status-ok { color: #008000; font-weight: bold; }
        .status-error { color: #cc0000; font-weight: bold; }
        .status-warning { color: #ff8800; font-weight: bold; }
	</style>

	<div class="test-section">
		<h2>🏗️ Диагностика модуля</h2>

		<?php
		// Проверяем основные компоненты модуля
		$ibId = (int)\Bitrix\Main\Config\Option::get('qwelp.site_location', 'IBLOCK_ID', '0');
		echo "<p>ID инфоблока: <span class='" . ($ibId > 0 ? 'status-ok' : 'status-error') . "'>$ibId</span></p>";

		if ($ibId > 0 && \Bitrix\Main\Loader::includeModule('iblock')) {
			$ibRes = CIBlock::GetByID($ibId);
			if ($ib = $ibRes->Fetch()) {
				echo "<p>Инфоблок: <span class='status-ok'>найден</span> - {$ib['NAME']}</p>";

				$cityCount = CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, 'ACTIVE' => 'Y'], [], false);
				echo "<p>Количество городов: <span class='status-ok'>$cityCount</span></p>";
			} else {
				echo "<p>Инфоблок: <span class='status-error'>не найден</span></p>";
			}
		}

		// Проверяем Context
		if (class_exists('\Qwelp\SiteLocation\Context')) {
			echo "<p>Класс Context: <span class='status-ok'>найден</span></p>";

			$currentCity = \Qwelp\SiteLocation\Context::getCurrent();
			if ($currentCity) {
				echo "<p>Текущий город: <span class='status-ok'>определен</span> - {$currentCity['NAME']}</p>";
			} else {
				echo "<p>Текущий город: <span class='status-warning'>не определен</span></p>";
			}
		} else {
			echo "<p>Класс Context: <span class='status-error'>не найден</span></p>";
		}

		// Проверяем компоненты
		$componentsDir = $_SERVER['DOCUMENT_ROOT'] . '/local/components/qwelp.site_location';
		$components = ['city.context', 'city.selector', 'contacts.block'];

		echo "<h3>Компоненты:</h3>";
		foreach ($components as $comp) {
			$compPath = $componentsDir . '/' . $comp;
			$exists = is_dir($compPath);
			$hasTemplate = file_exists($compPath . '/templates/.default/template.php');

			echo "<p>$comp: ";
			echo $exists ? '<span class="status-ok">папка ✓</span>' : '<span class="status-error">папка ✗</span>';
			echo $hasTemplate ? ' <span class="status-ok">шаблон ✓</span>' : ' <span class="status-error">шаблон ✗</span>';
			echo "</p>";
		}
		?>
	</div>

	<div class="test-section">
		<h2>🏙️ Селектор городов</h2>

		<div class="demo-selector">
			<h3>Компонент селектора:</h3>
			<?$APPLICATION->IncludeComponent(
				"qwelp.site_location:city.selector",
				"",
				[]
			);?>
		</div>

		<div class="city-info">
			<h3>Текущий город через компонент:</h3>
			<strong>Ваш город:</strong>
			<?$APPLICATION->IncludeComponent(
				"qwelp.site_location:city.context",
				"",
				[]
			);?>
		</div>

		<div class="city-info">
			<h3>Информация о текущем городе:</h3>
			<?php
			if (class_exists('\Qwelp\SiteLocation\Context')) {
				$city = \Qwelp\SiteLocation\Context::getCurrent();
				if ($city) {
					echo "<p><strong>Название:</strong> {$city['NAME']}</p>";
					echo "<p><strong>Код:</strong> {$city['CODE']}</p>";
					echo "<p><strong>ID:</strong> {$city['ID']}</p>";

					$props = $city['PROPS'];
					if (!empty($props['PATH_SEGMENT'])) {
						echo "<p><strong>URL сегмент:</strong> {$props['PATH_SEGMENT']}</p>";
					}
					if (!empty($props['DECL_IN'])) {
						echo "<p><strong>Склонение «в»:</strong> {$props['DECL_IN']}</p>";
					}
				} else {
					echo "<p class='status-warning'>Город не определен</p>";
				}
			}
			?>
		</div>
	</div>

	<div class="test-section">
		<h2>📞 Контакты для текущего города</h2>

		<?$APPLICATION->IncludeComponent(
			"qwelp.site_location:contacts.block",
			"",
			[
				"SHOW_MAP" => "Y",
				"MAP_HEIGHT" => 300
			]
		);?>
	</div>

	<div class="test-section">
		<h2>🔧 Простой селектор для отладки</h2>

		<div style="background: white; padding: 20px; border-radius: 8px;">
			<label for="debug-city-select"><strong>Выберите город вручную:</strong></label>
			<select id="debug-city-select" onchange="debugSelectCity(this.value)" style="padding: 8px; margin-left: 10px; font-size: 16px;">
				<option value="">-- Выберите город --</option>
				<?php
				if ($ibId > 0) {
					$cities = CIBlockElement::GetList(['NAME' => 'ASC'], [
						'IBLOCK_ID' => $ibId,
						'ACTIVE' => 'Y'
					], false, false, ['ID', 'NAME']);

					$currentCookie = $_COOKIE['SITE_CITY'] ?? '';
					while ($city = $cities->Fetch()) {
						$selected = ($currentCookie == $city['ID']) ? 'selected' : '';
						echo "<option value='{$city['ID']}' $selected>{$city['NAME']}</option>";
					}
				}
				?>
			</select>

			<div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
				<strong>Текущая cookie SITE_CITY:</strong>
				<span id="cookie-value"><?= htmlspecialcharsbx($_COOKIE['SITE_CITY'] ?? 'не установлена') ?></span>
			</div>
		</div>
	</div>

	<div class="test-section">
		<h2>🧪 Тестирование API</h2>

		<div style="background: white; padding: 20px; border-radius: 8px;">
			<button onclick="testContextAPI()" style="padding: 10px 20px; margin: 5px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">
				Получить текущий город через API
			</button>

			<button onclick="testPlaceholders()" style="padding: 10px 20px; margin: 5px; background: #00cc66; color: white; border: none; border-radius: 4px; cursor: pointer;">
				Тест плейсхолдеров
			</button>

			<button onclick="clearCity()" style="padding: 10px 20px; margin: 5px; background: #cc6600; color: white; border: none; border-radius: 4px; cursor: pointer;">
				Очистить город
			</button>

			<div id="api-results" class="debug-info" style="margin-top: 15px; min-height: 100px;">
				Результаты тестов будут отображены здесь...
			</div>
		</div>
	</div>

	<div class="test-section">
		<h2>📊 Debug информация</h2>

		<div class="debug-info">
			<strong>Переменные окружения:</strong><br>
			REQUEST_URI: <?= htmlspecialcharsbx($_SERVER['REQUEST_URI'] ?? 'не задан') ?><br>
			HTTP_HOST: <?= htmlspecialcharsbx($_SERVER['HTTP_HOST'] ?? 'не задан') ?><br>
			REMOTE_ADDR: <?= htmlspecialcharsbx($_SERVER['REMOTE_ADDR'] ?? 'не задан') ?><br><br>

			<strong>Настройки модуля:</strong><br>
			<?php
			$moduleOptions = [
				'IBLOCK_ID', 'MODE', 'COOKIE_NAME', 'COOKIE_DAYS',
				'GEOIP_ENABLED', 'SEO_CANONICAL', 'SEO_HREFLANG'
			];

			foreach ($moduleOptions as $option) {
				$value = \Bitrix\Main\Config\Option::get('qwelp.site_location', $option, 'не задано');
				echo "$option: " . htmlspecialcharsbx($value) . "<br>";
			}
			?>
			<br>

			<strong>Текущие Cookies:</strong><br>
			<?php
			foreach ($_COOKIE as $name => $value) {
				if (strpos($name, 'SITE') !== false || strpos($name, 'CITY') !== false) {
					echo htmlspecialcharsbx($name) . ': ' . htmlspecialcharsbx($value) . "<br>";
				}
			}
			?>
		</div>
	</div>

	<script>
		// Функция для отладочного селектора
		function debugSelectCity(cityId) {
			if (!cityId) {
				document.cookie = 'SITE_CITY=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
			} else {
				document.cookie = 'SITE_CITY=' + cityId + '; path=/; max-age=' + (86400 * 180);
			}

			// Обновляем отображение cookie
			document.getElementById('cookie-value').textContent = cityId || 'не установлена';

			// Перезагружаем страницу через небольшую задержку
			setTimeout(function() {
				location.reload();
			}, 500);
		}

		// Тест Context API
		function testContextAPI() {
			var results = document.getElementById('api-results');
			results.innerHTML = 'Тестирование Context API...<br>';

			// Делаем AJAX запрос для получения текущего города
			BX.ajax({
				url: '/test/ajax_test.php',
				method: 'POST',
				data: {
					action: 'get_current_city'
				},
				dataType: 'json',
				onsuccess: function(data) {
					results.innerHTML += 'Результат API:<br>';
					results.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
				},
				onfailure: function(error) {
					results.innerHTML += 'Ошибка API: ' + error + '<br>';
				}
			});
		}

		// Тест плейсхолдеров
		function testPlaceholders() {
			var results = document.getElementById('api-results');
			results.innerHTML = 'Тестирование плейсхолдеров...<br>';

			BX.ajax({
				url: '/test/ajax_test.php',
				method: 'POST',
				data: {
					action: 'test_placeholders',
					text: 'Добро пожаловать #CITY_IN#! Телефон: #CITY_PHONE#. Адрес: #CITY_ADDRESS#.'
				},
				dataType: 'json',
				onsuccess: function(data) {
					results.innerHTML += 'Результат обработки плейсхолдеров:<br>';
					results.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
				},
				onfailure: function(error) {
					results.innerHTML += 'Ошибка: ' + error + '<br>';
				}
			});
		}

		// Очистка города
		function clearCity() {
			document.cookie = 'SITE_CITY=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
			document.getElementById('cookie-value').textContent = 'не установлена';

			var results = document.getElementById('api-results');
			results.innerHTML = 'Cookie очищена. Страница будет перезагружена...<br>';

			setTimeout(function() {
				location.reload();
			}, 1000);
		}

		// Слушаем событие смены города от компонента
		document.addEventListener('qwelp:cityChanged', function(event) {
			console.log('Событие смены города:', event.detail);

			var results = document.getElementById('api-results');
			if (results) {
				results.innerHTML = 'Событие qwelp:cityChanged получено:<br>';
				results.innerHTML += 'ID города: ' + event.detail.id + '<br>';
				results.innerHTML += 'Название: ' + event.detail.name + '<br>';
				results.innerHTML += 'Время: ' + new Date().toLocaleTimeString() + '<br>';
			}

			// Обновляем отображение cookie
			var cookieEl = document.getElementById('cookie-value');
			if (cookieEl) {
				cookieEl.textContent = event.detail.id;
			}
		});

		// Debug информация в консоль
		console.log('=== Debug информация модуля городов ===');
		console.log('Cookies:', document.cookie);
		console.log('URL:', location.href);
		console.log('User Agent:', navigator.userAgent);
	</script>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>