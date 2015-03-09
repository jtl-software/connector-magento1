<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Utilities;

/**
 * Description of ArrayTools
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class ArrayTools
{
    public static function filterByLanguage($haystack, $language, $inverted=false)
    {
        $result = array();
        
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {
                if ($value->getLanguageIso() !== $language) {
                    $result[$haystack_key] = $value;
                }
            }
            else {
                if ($value->getLanguageIso() === $language) {
                    $result[$haystack_key] = $value;
                }
            }
        }
        
        return $result;
    }

    public static function filterByItemKey($haystack, $needle, $key, $inverted=false)
    {
        $result = array();
        
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {
                if ($value->$key !== $needle) {
                    $result[$haystack_key] = $value;
                }
            }
            else {
                if ($value->$key === $needle) {
                    $result[$haystack_key] = $value;
                }
            }
        }
        
        return $result;
    }
    
    public static function filterByLocale($array, $locale, $key = '_localeName', $inverted=false)
    {
        return static::filterByItemKey($array, $locale, $key, $inverted);
    }
    
    public static function filterOneByItemKey($haystack, $needle, $key, $inverted=false)
    {
        $result = array();
        
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {
                if ($value->$key !== $needle) {
                    return $value;
                }
            }
            else {
                if ($value->$key === $needle) {
                    return $value;
                }
            }
        }
        
        return null;
    }
    
    public static function filterOneByLocale($array, $locale, $key = '_localeName', $inverted=false)
    {
        return static::filterOneByItemKey($array, $locale, $key, $inverted);
    }
}
