<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Новая страница");
?>
	<!-- Селектор для выбора города -->
<?$APPLICATION->IncludeComponent("qwelp.site_location:city.selector", "", []);?>

	<!-- Отображение текущего города -->
	<div class="current-city">
		Ваш город: <?$APPLICATION->IncludeComponent("qwelp.site_location:city.context", "", []);?>
	</div>

	<!-- Контакты для текущего города -->
	<div class="contacts">
		<?$APPLICATION->IncludeComponent("qwelp.site_location:contacts.block", "", [
			"SHOW_MAP" => "Y",
			"MAP_HEIGHT" => 400
		]);?>
	</div>

	<script>
		// Слушаем событие смены города
		document.addEventListener('qwelp:cityChanged', function(event) {
			console.log('Город изменен на ID:', event.detail.id);
			// Можно перезагрузить страницу или обновить контент
			location.reload();
		});
	</script>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>