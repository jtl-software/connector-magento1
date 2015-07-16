<?php

/**
 * @copyright 2010-2014 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Utilities\Language;

class LocaleMapper
{
    public static function localeToLanguageIso($locale)
    {
        return Language::map($locale);
    }

    public static function languageToLocale($languageIso)
    {
        return Language::map(null, null, $languageIso);
    }
}
