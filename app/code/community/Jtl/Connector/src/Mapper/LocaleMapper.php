<?php

/**
 * @copyright 2010-2014 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

class LocaleMapper
{
    private static $localeMappings = array(
        'ger' => 'de_DE',
        'eng' => 'en_US',
        'fra' => 'fr_FR'
    );

    public static function localeToLanguageIso($locale)
    {
        return array_search($locale, self::$localeMappings) ?: null;
    }

    public static function languageToLocale($languageIso)
    {
        if (array_key_exists($languageIso, self::$localeMappings)) {
            return self::$localeMappings[$languageIso];
        }
    }
}
