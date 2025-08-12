/**
 * Селектор городов для модуля qwelp.site_location
 */
window.LocationSelector = (function() {
	'use strict';

	// Переменная для отслеживания уже выведенной информации
	let cityInfoLogged = false;

	// Приватные методы
	function getComponentData(componentId) {
		return window.locationSelectorData && window.locationSelectorData[componentId]
			? window.locationSelectorData[componentId]
			: null;
	}

	function getMessage(componentId, key) {
		const data = getComponentData(componentId);
		return data && data.messages && data.messages[key] ? data.messages[key] : key;
	}

	// Используем только битриксовые методы для работы с cookie
	function setCookie(name, value, days) {
		if (typeof BX !== 'undefined' && BX.cookie) {
			const expires = new Date();
			expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
			BX.cookie.set(name, value, { expires: expires, path: '/' });
		}
	}

	function deleteCookie(name) {
		if (typeof BX !== 'undefined' && BX.cookie) {
			BX.cookie.set(name, '', { expires: new Date(0), path: '/' });
		}
	}

	function getCookie(name) {
		if (typeof BX !== 'undefined' && BX.cookie) {
			return BX.cookie.get(name);
		}
		return null;
	}

	function dispatchCityChangedEvent(cityId, cityName) {
		const event = new CustomEvent('qwelp:cityChanged', {
			detail: {
				id: cityId,
				name: cityName
			}
		});

		document.dispatchEvent(event);
	}

	// Функция для вывода информации о текущем городе
	function logCurrentCityInfo(componentId) {
		// Предотвращаем дублирование
		if (cityInfoLogged) return;
		cityInfoLogged = true;

		const data = getComponentData(componentId);
		const ajaxTestPath = data && data.ajaxTestPath ? data.ajaxTestPath : '/test/ajax_test.php';

		// Получаем информацию из PHP
		if (typeof BX !== 'undefined' && BX.ajax) {
			BX.ajax({
				url: ajaxTestPath,
				method: 'POST',
				data: {
					action: 'get_current_city'
				},
				dataType: 'json',
				onsuccess: function(response) {
					if (response.success && response.city) {
						console.log({
							current_city: {
								id: response.city.id,
								name: response.city.name,
								code: response.city.code,
								section_id: response.city.section_id,
								properties: response.city.properties
							},
							timestamp: response.timestamp,
							source: 'php_context'
						});
					} else {
						console.log({
							current_city: null,
							error: response.error || 'Город не определен',
							debug: response.debug || null,
							timestamp: new Date().toISOString(),
							source: 'php_context'
						});
					}
				},
				onfailure: function(error) {
					console.log({
						current_city: null,
						error: 'AJAX request failed',
						timestamp: new Date().toISOString(),
						source: 'php_context'
					});
				}
			});
		} else {
			// Fallback - попытаемся получить из DOM
			const cityContextElement = document.querySelector('.city-context');
			const addressPointCurrent = document.querySelector('.address-point__current');

			if (cityContextElement || addressPointCurrent) {
				const cityName = (cityContextElement && cityContextElement.textContent.trim() !== 'Город не определён')
					? cityContextElement.textContent.trim()
					: addressPointCurrent ? addressPointCurrent.textContent.trim() : null;

				if (cityName && cityName !== 'Выберите город') {
					console.log({
						current_city: {
							name: cityName,
							source: 'dom'
						},
						timestamp: new Date().toISOString(),
						note: 'Retrieved from DOM, limited data available'
					});
				} else {
					console.log({
						current_city: null,
						error: 'City not defined',
						timestamp: new Date().toISOString(),
						source: 'dom'
					});
				}
			}
		}
	}

	// Публичные методы
	return {
		/**
		 * Показ popup подтверждения региона
		 */
		showRegionPopup: function(componentId) {
			const popup = document.getElementById(componentId + '-popup');
			if (popup) {
				setTimeout(function() {
					popup.style.display = 'flex';
				}, 1000);
			}
		},

		/**
		 * Подтверждение региона
		 */
		confirmRegion: function(componentId) {
			const data = getComponentData(componentId);
			const ajaxPath = data && data.ajaxPath ? data.ajaxPath : '/local/components/qwelp.site_location/city.selector/ajax.php';

			if (typeof BX !== 'undefined' && BX.ajax) {
				BX.ajax({
					url: ajaxPath,
					method: 'POST',
					data: {
						action: 'confirmRegion'
					},
					dataType: 'json',
					onsuccess: function(response) {
						if (response.status === 'ok') {
							const popup = document.getElementById(componentId + '-popup');
							if (popup) {
								popup.style.display = 'none';
							}
						}
					},
					onfailure: function(error) {
					}
				});
			} else {
				const popup = document.getElementById(componentId + '-popup');
				if (popup) {
					popup.style.display = 'none';
				}
			}
		},

		/**
		 * Показ модального окна селектора
		 */
		showSelector: function(componentId) {
			const popup = document.getElementById(componentId + '-popup');
			if (popup) {
				popup.style.display = 'none';
			}

			const overlay = document.getElementById(componentId + '-overlay');
			if (overlay) {
				overlay.style.display = 'flex';
				document.body.style.overflow = 'hidden';

				const searchInput = document.getElementById(componentId + '-search');
				if (searchInput) {
					searchInput.value = '';
				}

				this.showPopular(componentId);
			}
		},

		/**
		 * Показ модального окна из popup подтверждения
		 */
		showModal: function(componentId) {
			const popup = document.getElementById(componentId + '-popup');
			if (popup) {
				popup.style.display = 'none';
			}

			this.showSelector(componentId);
		},

		/**
		 * Скрытие модального окна селектора
		 */
		hideSelector: function(componentId) {
			const overlay = document.getElementById(componentId + '-overlay');
			if (overlay) {
				overlay.style.display = 'none';
				document.body.style.overflow = '';
			}
		},

		/**
		 * Показ популярных городов
		 */
		showPopular: function(componentId) {
			const popular = document.getElementById(componentId + '-popular');
			const content = document.getElementById(componentId + '-content');

			if (popular && content) {
				popular.style.display = 'flex';
				content.style.display = 'none';
			}
		},

		/**
		 * Показ трехколоночного селектора
		 */
		showColumns: function(componentId) {
			const popular = document.getElementById(componentId + '-popular');
			const content = document.getElementById(componentId + '-content');

			if (popular && content) {
				popular.style.display = 'none';
				content.style.display = 'grid';
			}
		},

		/**
		 * Выбор страны/округа
		 */
		selectCountry: function(componentId, countryId) {
			const data = getComponentData(componentId);
			if (!data) {
				return;
			}

			this.showColumns(componentId);

			const countryButtons = document.querySelectorAll('#' + componentId + '-countries .location-selector__list-item');
			for (let i = 0; i < countryButtons.length; i++) {
				countryButtons[i].classList.remove('location-selector__list-item_active');
			}

			const selectedCountry = document.querySelector('#' + componentId + '-countries [data-country-id="' + countryId + '"]');
			if (selectedCountry) {
				selectedCountry.classList.add('location-selector__list-item_active');
			}

			const regionsContainer = document.getElementById(componentId + '-regions');
			if (regionsContainer) {
				let regionsHtml = '';
				for (const regionId in data.regions) {
					const region = data.regions[regionId];
					if (region.COUNTRY_ID == countryId) {
						regionsHtml += '<button class="location-selector__list-item" ' +
							'data-region-id="' + regionId + '" ' +
							'onclick="LocationSelector.selectRegion(\'' + componentId + '\', ' + regionId + ')">' +
							region.NAME + '</button>';
					}
				}
				regionsContainer.innerHTML = regionsHtml;
			}

			const citiesContainer = document.getElementById(componentId + '-cities');
			if (citiesContainer) {
				citiesContainer.innerHTML = '';
			}
		},

		/**
		 * Выбор региона - ИСПРАВЛЕНО
		 */
		selectRegion: function(componentId, regionId) {
			const data = getComponentData(componentId);
			if (!data) {
				return;
			}

			const regionButtons = document.querySelectorAll('#' + componentId + '-regions .location-selector__list-item');
			for (let i = 0; i < regionButtons.length; i++) {
				regionButtons[i].classList.remove('location-selector__list-item_active');
			}

			const selectedRegion = document.querySelector('#' + componentId + '-regions [data-region-id="' + regionId + '"]');
			if (selectedRegion) {
				selectedRegion.classList.add('location-selector__list-item_active');
			}

			const citiesContainer = document.getElementById(componentId + '-cities');
			if (citiesContainer) {
				let citiesHtml = '';

				// ИСПРАВЛЕНИЕ: data.cities это массив, нужно перебирать его как массив
				if (Array.isArray(data.cities)) {
					for (let i = 0; i < data.cities.length; i++) {
						const city = data.cities[i];
						if (city.SECTION_ID == regionId) {
							citiesHtml += '<button class="location-selector__list-item" ' +
								'onclick="LocationSelector.selectCity(\'' + componentId + '\', ' + city.ID + ', \'' + city.NAME.replace(/'/g, '\\\'') + '\')">' +
								city.NAME + '</button>';
						}
					}
				}

				citiesContainer.innerHTML = citiesHtml;
			}
		},

		/**
		 * Выбор города - с использованием языковых констант для ошибок
		 */
		selectCity: function(componentId, cityId, cityName) {
			const button = document.querySelector('#' + componentId + ' .address-point__current');
			if (button) {
				button.textContent = cityName;
			}

			const data = getComponentData(componentId);
			const ajaxPath = data && data.ajaxPath ? data.ajaxPath : '/local/components/qwelp.site_location/city.selector/ajax.php';

			if (typeof BX !== 'undefined' && BX.ajax) {
				BX.ajax({
					url: ajaxPath,
					method: 'POST',
					data: {
						action: 'selectCity',
						cityId: cityId
					},
					dataType: 'json',
					onsuccess: function(response) {
						if (response.status === 'ok') {
							dispatchCityChangedEvent(cityId, cityName);
							LocationSelector.hideSelector(componentId);
							setTimeout(function() {
								window.location.reload();
							}, 100);
						} else {
							const errorMsg = getMessage(componentId, 'QWELP_CITY_SELECTOR_ERROR_SELECTION');
							alert(errorMsg + (response.message ? ': ' + response.message : ''));
						}
					},
					onfailure: function(error) {
						const errorMsg = getMessage(componentId, 'QWELP_CITY_SELECTOR_ERROR_NETWORK');
						alert(errorMsg);
					}
				});
			} else {
				const errorMsg = getMessage(componentId, 'QWELP_CITY_SELECTOR_ERROR_BX_UNAVAILABLE');
				alert(errorMsg);
			}
		},

		/**
		 * Поиск городов - ИСПРАВЛЕНО
		 */
		searchCities: function(componentId, searchTerm) {
			const data = getComponentData(componentId);
			if (!data) {
				return;
			}

			searchTerm = searchTerm.toLowerCase().trim();

			if (searchTerm === '') {
				this.showPopular(componentId);
				return;
			}

			this.showColumns(componentId);

			const countriesContainer = document.getElementById(componentId + '-countries');
			const regionsContainer = document.getElementById(componentId + '-regions');
			const citiesContainer = document.getElementById(componentId + '-cities');

			if (countriesContainer) countriesContainer.innerHTML = '';
			if (regionsContainer) regionsContainer.innerHTML = '';

			if (citiesContainer) {
				let citiesHtml = '';
				let foundCount = 0;

				// ИСПРАВЛЕНИЕ: data.cities это массив
				if (Array.isArray(data.cities)) {
					for (let i = 0; i < data.cities.length; i++) {
						const city = data.cities[i];
						if (city.NAME.toLowerCase().indexOf(searchTerm) !== -1) {
							citiesHtml += '<button class="location-selector__list-item" ' +
								'onclick="LocationSelector.selectCity(\'' + componentId + '\', ' + city.ID + ', \'' + city.NAME.replace(/'/g, '\\\'') + '\')">' +
								city.NAME + '</button>';
							foundCount++;

							if (foundCount >= 20) break;
						}
					}
				}

				if (foundCount === 0) {
					citiesHtml = '<div class="location-selector__no-results">' +
						getMessage(componentId, 'QWELP_CITY_SELECTOR_NO_RESULTS') +
						'</div>';
				}

				citiesContainer.innerHTML = citiesHtml;
			}
		},

		/**
		 * Инициализация автодополнения поиска
		 */
		initSearch: function(componentId) {
			const searchInput = document.getElementById(componentId + '-search');
			if (!searchInput) {
				return;
			}

			let searchTimeout;

			searchInput.addEventListener('input', function() {
				const searchTerm = this.value;

				if (searchTimeout) {
					clearTimeout(searchTimeout);
				}

				searchTimeout = setTimeout(function() {
					LocationSelector.searchCities(componentId, searchTerm);
				}, 300);
			});

			searchInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					LocationSelector.searchCities(componentId, this.value);
				}
			});
		},

		/**
		 * Получение и вывод текущего города
		 */
		logCurrentCity: function(componentId) {
			logCurrentCityInfo(componentId);
		},

		/**
		 * Инициализация селектора
		 */
		init: function(componentId, options) {
			options = options || {};

			if (!window.locationSelectorData) {
				window.locationSelectorData = {};
			}
			window.locationSelectorData[componentId] = options;

			this.initSearch(componentId);

			if (options.showRegionPopup) {
				this.showRegionPopup(componentId);
			}

			const overlay = document.getElementById(componentId + '-overlay');
			if (overlay) {
				overlay.addEventListener('click', function(e) {
					if (e.target === overlay) {
						LocationSelector.hideSelector(componentId);
					}
				});
			}

			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
					const visibleOverlay = document.querySelector('.location-selector__overlay[style*="flex"]');
					if (visibleOverlay) {
						const overlayId = visibleOverlay.id;
						const componentId = overlayId.replace('-overlay', '');
						LocationSelector.hideSelector(componentId);
					}
				}
			});

			// Выводим информацию о текущем городе при инициализации только один раз
			setTimeout(function() {
				logCurrentCityInfo(componentId);
			}, 500);
		}
	};
})();

// Автоинициализация всех селекторов на странице после загрузки DOM
document.addEventListener('DOMContentLoaded', function() {
	const selectors = document.querySelectorAll('[id^="city-selector-"]');
	let firstSelector = null;

	selectors.forEach(function(selector, index) {
		const componentId = selector.id;

		if (window.locationSelectorData && window.locationSelectorData[componentId]) {
			LocationSelector.init(componentId, window.locationSelectorData[componentId]);

			// Запоминаем первый селектор
			if (!firstSelector) {
				firstSelector = componentId;
			}
		}
	});

	// Выводим информацию о текущем городе только через первый селектор (один раз)
	if (firstSelector) {
		setTimeout(function() {
			if (window.LocationSelector) {
				window.LocationSelector.logCurrentCity(firstSelector);
			}
		}, 1000);
	}
});