<?php $C = $arResult['CITY'] ?? null; if (!$C) { echo 'Город не определён'; return; }
$P = $C['PROPS'] ?? [];
?>
<div class="city-contacts">
  <?php if (!empty($P['PHONE'])): ?>
  <div class="city-contacts__row"><b>Телефон:</b> <?=htmlspecialcharsbx(implode(', ', $P['PHONE']))?></div>
  <?php endif; ?>
  <?php if (!empty($P['EMAIL'])): ?>
  <div class="city-contacts__row"><b>E-mail:</b> <?=htmlspecialcharsbx(implode(', ', $P['EMAIL']))?></div>
  <?php endif; ?>
  <?php if (!empty($P['ADDRESS'])): ?>
  <div class="city-contacts__row"><b>Адрес:</b> <?=htmlspecialcharsbx(implode(', ', $P['ADDRESS']))?></div>
  <?php endif; ?>
  <?php if (!empty($P['WORKING_HOURS'])): ?>
  <div class="city-contacts__row"><b>Режим:</b> <?=htmlspecialcharsbx(implode('; ', $P['WORKING_HOURS']))?></div>
  <?php endif; ?>

  <?php if ($arParams['SHOW_MAP'] === 'Y' && !empty($P['MAP_YANDEX'])): ?>
    <div class="city-contacts__map" style="height: <?=intval($arParams['MAP_HEIGHT'])?>px">
      <?php
        // map_yandex хранит строку "lat,lon"
        $coords = explode(',', (string)$P['MAP_YANDEX']); 
        $lat = trim($coords[0]??''); 
        $lon = trim($coords[1]??''); 
      ?>
      <div data-lat="<?=htmlspecialcharsbx($lat)?>" data-lon="<?=htmlspecialcharsbx($lon)?>" class="js-yamap"></div>
    </div>
  <?php endif; ?>
</div>
