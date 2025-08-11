<?php
use Qwelp\SiteLocation\Context;

class QwelpSiteLocationContactsBlockComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if ($this->StartResultCache()) {
            $city = Context::getCurrent();
            $this->arResult['CITY'] = $city;
            $this->IncludeComponentTemplate();
        }
    }
}
