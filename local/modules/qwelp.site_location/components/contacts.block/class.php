<?php

use Bitrix\Main\Loader;
use Qwelp\SiteLocation\Context;

class QwelpSiteLocationContactsBlockComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('qwelp.site_location')) {
            return;
        }

        if ($this->StartResultCache()) {
            $city = Context::getCurrent();
            $this->arResult['CITY'] = $city;
            $this->IncludeComponentTemplate();
        }
    }
}
