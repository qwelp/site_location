<?php
namespace Qwelp\SiteLocation;

use Bitrix\Iblock\PropertyEnumerationTable;
use CIBlockElement;

class EventHandlers
{
    /**
     * Гарантируем единственность IS_DEFAULT=Y в ИБ
     */
    public static function onBeforeElementSave(&$arFields): bool
    {
        if (empty($arFields['IBLOCK_ID'])) return true;
        $ibId = (int)$arFields['IBLOCK_ID'];

        // Узнаём ID списка значения 'Y' для свойства IS_DEFAULT
        $propEnumIdY = null;
        $propEnum = PropertyEnumerationTable::getList([
            'select' => ['ID', 'PROPERTY_ID', 'VALUE', 'XML_ID'],
            'filter' => ['VALUE' => 'Y', 'PROPERTY.CODE' => 'IS_DEFAULT', 'PROPERTY.IBLOCK_ID' => $ibId]
        ]);
        if ($row = $propEnum->fetch()) { $propEnumIdY = (int)$row['ID']; }

        $isDefaultSelected = false;
        if (!empty($arFields['PROPERTY_VALUES']['IS_DEFAULT'])) {
            foreach ($arFields['PROPERTY_VALUES']['IS_DEFAULT'] as $v) {
                $val = is_array($v) ? ($v['VALUE'] ?? null) : $v;
                if ((int)$val === $propEnumIdY) { $isDefaultSelected = true; break; }
                if ($val === 'Y') { $isDefaultSelected = true; break; } // на случай установки строкой
            }
        }

        if ($isDefaultSelected && $propEnumIdY) {
            // Снимаем флаг у остальных элементов
            $filter = ['IBLOCK_ID' => $ibId];
            if (!empty($arFields['ID'])) { $filter['!ID'] = $arFields['ID']; }
            $res = CIBlockElement::GetList([], $filter, false, false, ['ID']);
            while ($el = $res->Fetch()) {
                CIBlockElement::SetPropertyValuesEx((int)$el['ID'], $ibId, ['IS_DEFAULT' => ['VALUE' => '']]);
            }
        }
        return true;
    }
}
