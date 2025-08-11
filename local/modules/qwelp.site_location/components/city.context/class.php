<?php
use Qwelp\SiteLocation\Context;

class QwelpSiteLocationCityContextComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if ($this->StartResultCache()) {
            $this->arResult['CITY'] = Context::getCurrent();
            $this->IncludeComponentTemplate();
        }
    }
}
