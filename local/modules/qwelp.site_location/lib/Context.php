<?php
namespace Qwelp\SiteLocation;

use Bitrix\Main;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use CIBlockElement;

class Context
{
    private static $current; // кэш текущего города (массив)

    public static function getCurrent(): ?array
    {
        if (self::$current !== null) return self::$current;
        $ibId = (int)Option::get('qwelp.site_location', 'IBLOCK_ID', '0');
        if ($ibId <= 0) return self::$current = null;

        Loader::includeModule('iblock');

        $mode = Option::get('qwelp.site_location', 'MODE', 'path'); // path|subdomain

        // 1) По домену/пути
        $byRouting = ($mode === 'subdomain') ? self::byDomain($ibId) : self::byPath($ibId);
        if ($byRouting) return self::$current = $byRouting;

        // 2) По cookie
        $cookieName = Option::get('qwelp.site_location', 'COOKIE_NAME', 'SITE_CITY');
        if (!empty($_COOKIE[$cookieName])) {
            $city = self::byId((int)$_COOKIE[$cookieName], $ibId);
            if ($city) return self::$current = $city;
        }

        // 3) GeoIP (стандартный модуль)
        if (Option::get('qwelp.site_location', 'GEOIP_ENABLED', 'Y') === 'Y' && Loader::includeModule('main')) {
            $ip = Main\Context::getCurrent()?->getRequest()?->getRemoteAddress();
            if ($ip) {
                $data = Main\Service\GeoIp\Manager::getDataResult($ip, 'ru');
                if ($data && $data->isSuccess()) {
                    $cityName = $data->getGeoData()->cityName; // строка
                    if ($cityName) {
                        $byName = self::byName($cityName, $ibId);
                        if ($byName) return self::$current = $byName;
                    }
                }
            }
        }

        // 4) Дефолтный
        $default = self::getDefault($ibId);
        return self::$current = $default;
    }

    public static function setCurrent(int $cityId): void
    {
        $ibId = (int)Option::get('qwelp.site_location', 'IBLOCK_ID', '0');
        if ($ibId <= 0) return;
        Loader::includeModule('iblock');
        $city = self::byId($cityId, $ibId);
        if (!$city) return;

        $cookieName = Option::get('qwelp.site_location', 'COOKIE_NAME', 'SITE_CITY');
        $days = (int)Option::get('qwelp.site_location', 'COOKIE_DAYS', '180');
        setcookie($cookieName, (string)$cityId, time() + 86400 * $days, '/');
        self::$current = $city;
    }

    public static function replacePlaceholders(string $text, ?array $city = null): string
    {
        $city = $city ?? self::getCurrent();
        if (!$city) return $text;
        $p = $city['PROPS'];
        $repl = [
            '#CITY_NOM#' => $p['DECL_NOM'] ?: $city['NAME'],
            '#CITY_GEN#' => $p['DECL_GEN'] ?? '',
            '#CITY_DAT#' => $p['DECL_DAT'] ?? '',
            '#CITY_ACC#' => $p['DECL_ACC'] ?? '',
            '#CITY_INS#' => $p['DECL_INS'] ?? '',
            '#CITY_PRE#' => $p['DECL_PRE'] ?? '',
            '#CITY_IN#'  => $p['DECL_IN'] ?? '',
            '#CITY_FROM#'=> $p['DECL_FROM'] ?? '',
            '#CITY_PHONE#' => implode(', ', $p['PHONE'] ?? []),
            '#CITY_ADDRESS#' => implode(', ', $p['ADDRESS'] ?? []),
            '#CITY_HOURS#' => implode('; ', $p['WORKING_HOURS'] ?? []),
            '#CITY_EMAIL#' => implode(', ', $p['EMAIL'] ?? []),
        ];
        return strtr($text, $repl);
    }

    public static function getDefault(int $ibId): ?array
    {
        Loader::includeModule('iblock');
        $filter = [
            'IBLOCK_ID' => $ibId,
            'ACTIVE' => 'Y',
            'PROPERTY_IS_DEFAULT_VALUE' => 'Y',
        ];
        $res = CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, ['nTopCount' => 1], ['ID']);
        if ($row = $res->Fetch()) return self::byId((int)$row['ID'], $ibId);
        // fallback: первый элемент по алфавиту
        $row = CIBlockElement::GetList(['NAME' => 'ASC'], ['IBLOCK_ID' => $ibId, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID'])->Fetch();
        return $row ? self::byId((int)$row['ID'], $ibId) : null;
    }

    public static function byId(int $id, int $ibId): ?array
    {
        Loader::includeModule('iblock');
        $res = CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, 'ID' => $id, 'ACTIVE' => 'Y'], false, false, ['*', 'PROPERTY_*']);
        if ($el = $res->GetNextElement()) {
            $fields = $el->GetFields();
            $props = $el->GetProperties();
            return self::pack($fields, $props);
        }
        return null;
    }

    public static function byName(string $name, int $ibId): ?array
    {
        Loader::includeModule('iblock');
        $res = CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, 'ACTIVE' => 'Y', '%NAME' => $name], false, ['nTopCount' => 1], ['*', 'PROPERTY_*']);
        if ($el = $res->GetNextElement()) { return self::pack($el->GetFields(), $el->GetProperties()); }
        return null;
    }

    private static function byDomain(int $ibId): ?array
    {
        Loader::includeModule('iblock');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!$host) return null;
        $res = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $ibId,
            'ACTIVE' => 'Y',
            [
                'LOGIC' => 'OR',
                ['PROPERTY_MAIN_DOMAIN' => $host],
                ['PROPERTY_DOMAINS' => $host],
            ]
        ], false, ['nTopCount' => 1], ['*', 'PROPERTY_*']);
        if ($el = $res->GetNextElement()) { return self::pack($el->GetFields(), $el->GetProperties()); }
        return null;
    }

    private static function byPath(int $ibId): ?array
    {
        Loader::includeModule('iblock');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!$uri) return null;
        $path = trim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
        if ($path === '') return null;
        $segment = explode('/', $path)[0] ?? '';
        if ($segment === '') return null;

        $res = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $ibId,
            'ACTIVE' => 'Y',
            'PROPERTY_PATH_SEGMENT' => $segment,
        ], false, ['nTopCount' => 1], ['*', 'PROPERTY_*']);
        if ($el = $res->GetNextElement()) { return self::pack($el->GetFields(), $el->GetProperties()); }
        return null;
    }

    private static function pack(array $fields, array $props): array
    {
        $P = [];
        foreach ($props as $code => $p) {
            if ($p['MULTIPLE'] === 'Y') {
                $val = [];
                if (is_array($p['~VALUE'])) {
                    foreach ($p['~VALUE'] as $v) { 
                        if ($v !== '' && $v !== null) $val[] = is_array($v) && isset($v['TEXT']) ? $v['TEXT'] : $v; 
                    }
                } elseif ($p['~VALUE'] !== '' && $p['~VALUE'] !== null) {
                    $val = [$p['~VALUE']];
                }
                $P[$code] = $val;
            } else {
                $P[$code] = is_array($p['~VALUE']) && isset($p['~VALUE']['TEXT']) ? $p['~VALUE']['TEXT'] : $p['~VALUE'];
            }
        }
        return [
            'ID' => (int)$fields['ID'],
            'NAME' => $fields['NAME'],
            'CODE' => $fields['CODE'],
            'SECTION_ID' => (int)$fields['IBLOCK_SECTION_ID'],
            'PROPS' => $P,
        ];
    }
}
