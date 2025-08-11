<?php

use Bitrix\Main\Loader;
use Qwelp\SiteLocation\Context;

class QwelpSiteLocationCityContextComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('qwelp.site_location')) {
            return;
        }

        if ($this->StartResultCache()) {
            $this->arResult['CITY'] = Context::getCurrent();
            $this->IncludeComponentTemplate();
        }
    }
}
