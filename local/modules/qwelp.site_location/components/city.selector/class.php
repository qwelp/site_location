<?php
use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Config\Option;
use Qwelp\SiteLocation\Context;

class QwelpSiteLocationCitySelectorComponent extends CBitrixComponent implements \Bitrix\Main\Engine\Contract\Controllerable
{
    public function configureActions()
    {
        return [
            'select' => [
                'prefilters' => [new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST])]
            ]
        ];
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) return;
        $this->arResult['IBLOCK_ID'] = (int)Option::get('qwelp.site_location', 'IBLOCK_ID', '0');
        if ($this->arResult['IBLOCK_ID'] <= 0) { $this->includeComponentTemplate(); return; }

        // Дерево разделов: Страна → Регион → Субъект (упрощённо)
        $sections = [];
        $secRes = CIBlockSection::GetList(['LEFT_MARGIN'=>'ASC'], ['IBLOCK_ID'=>$this->arResult['IBLOCK_ID'], 'ACTIVE'=>'Y'], false, ['ID','NAME','IBLOCK_SECTION_ID']);
        while ($s = $secRes->Fetch()) { $sections[(int)$s['ID']] = $s + ['CHILDREN'=>[]]; }
        foreach ($sections as $id=>&$s) { if ($s['IBLOCK_SECTION_ID']) { $sections[(int)$s['IBLOCK_SECTION_ID']]['CHILDREN'][] = $id; } }
        unset($s);
        $this->arResult['SECTIONS'] = $sections;

        // Города (алфавит)
        $cities = [];
        $elRes = CIBlockElement::GetList(['NAME'=>'ASC'], ['IBLOCK_ID'=>$this->arResult['IBLOCK_ID'], 'ACTIVE'=>'Y'], false, false, ['ID','IBLOCK_SECTION_ID','NAME']);
        while ($e = $elRes->Fetch()) { $cities[] = ['ID'=>(int)$e['ID'],'SECTION_ID'=>(int)$e['IBLOCK_SECTION_ID'],'NAME'=>$e['NAME']]; }
        $this->arResult['CITIES'] = $cities;

        // TODO: Избранные из sale (по SALE_LOCATION_CODE) — маппинг при необходимости

        $this->includeComponentTemplate();
    }

    public function selectAction(int $cityId)
    {
        Context::setCurrent($cityId);
        return ['status' => 'ok'];
    }
}
