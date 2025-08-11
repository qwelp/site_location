<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Iblock;

if (!check_bitrix_sessid()) return;

$moduleId = 'qwelp.site_location';
Loader::includeModule('iblock');

// Список ИБ
$iblocks = [];
$res = CIBlock::GetList(['NAME' => 'ASC'], []);
while ($ib = $res->Fetch()) { $iblocks[$ib['ID']] = '['.$ib['ID'].'] '.$ib['NAME']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    CUtil::JSPostUnescape();
    Option::set($moduleId, 'IBLOCK_ID', (string)($_POST['IBLOCK_ID'] ?? ''));
    Option::set($moduleId, 'MODE', in_array($_POST['MODE'] ?? 'path', ['path','subdomain']) ? $_POST['MODE'] : 'path');
    Option::set($moduleId, 'COOKIE_NAME', trim((string)$_POST['COOKIE_NAME']));
    Option::set($moduleId, 'COOKIE_DAYS', (string)(int)$_POST['COOKIE_DAYS']);
    Option::set($moduleId, 'GEOIP_ENABLED', isset($_POST['GEOIP_ENABLED']) ? 'Y' : 'N');
    Option::set($moduleId, 'SEO_CANONICAL', isset($_POST['SEO_CANONICAL']) ? 'Y' : 'N');
    Option::set($moduleId, 'SEO_HREFLANG', isset($_POST['SEO_HREFLANG']) ? 'Y' : 'N');
    echo CAdminMessage::ShowNote('Сохранено');
}

$IBLOCK_ID = (int)Option::get($moduleId, 'IBLOCK_ID', '');
$MODE = Option::get($moduleId, 'MODE', 'path');
$COOKIE_NAME = Option::get($moduleId, 'COOKIE_NAME', 'SITE_CITY');
$COOKIE_DAYS = (int)Option::get($moduleId, 'COOKIE_DAYS', '180');
$GEOIP_ENABLED = Option::get($moduleId, 'GEOIP_ENABLED', 'Y') === 'Y';
$SEO_CANONICAL = Option::get($moduleId, 'SEO_CANONICAL', 'Y') === 'Y';
$SEO_HREFLANG = Option::get($moduleId, 'SEO_HREFLANG', 'Y') === 'Y';
?>
<form method="post">
  <?=bitrix_sessid_post()?>
  <table class="adm-detail-content-table edit-table">
    <tr>
      <td width="40%">Инфоблок «Города»</td>
      <td>
        <select name="IBLOCK_ID">
          <?php foreach ($iblocks as $id=>$title): ?>
            <option value="<?=$id?>" <?=($IBLOCK_ID==$id?'selected':'')?>><?=$title?></option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    <tr>
      <td>Режим адресации</td>
      <td>
        <label><input type="radio" name="MODE" value="path" <?=($MODE==='path'?'checked':'')?>> path (/<b>ekb</b>/...)</label><br>
        <label><input type="radio" name="MODE" value="subdomain" <?=($MODE==='subdomain'?'checked':'')?>> subdomain (<b>ekb</b>.site.ru)</label>
      </td>
    </tr>
    <tr>
      <td>Cookie (имя / дней)</td>
      <td>
        <input type="text" name="COOKIE_NAME" value="<?=htmlspecialcharsbx($COOKIE_NAME)?>" size="20">
        <input type="number" name="COOKIE_DAYS" value="<?=$COOKIE_DAYS?>" min="1" max="3650" style="width:100px">
      </td>
    </tr>
    <tr>
      <td>GeoIP (стандартный модуль)</td>
      <td><label><input type="checkbox" name="GEOIP_ENABLED" <?=($GEOIP_ENABLED?'checked':'')?>> Включено</label></td>
    </tr>
    <tr>
      <td>SEO помощники</td>
      <td>
        <label><input type="checkbox" name="SEO_CANONICAL" <?=($SEO_CANONICAL?'checked':'')?>> rel=canonical</label><br>
        <label><input type="checkbox" name="SEO_HREFLANG" <?=($SEO_HREFLANG?'checked':'')?>> rel=hreflang</label>
      </td>
    </tr>
  </table>
  <br>
  <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
</form>
