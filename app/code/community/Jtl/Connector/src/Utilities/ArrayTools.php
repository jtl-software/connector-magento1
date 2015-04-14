<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Utilities;

use jtl\Connector\Model\Identity;

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

    public static function filterOneByLanguage($haystack, $language, $inverted=false)
    {
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {
                if ($value->getLanguageIso() !== $language) {
                    return $value;
                }
            }
            else {
                if ($value->getLanguageIso() === $language) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    public static function filterOneByLanguageOrFirst($haystack, $language, $inverted=false)
    {
        $item = self::filterOneByLanguage($haystack, $language, $inverted);
        if ($item === null) {
            $item = reset($haystack);
        }

        return $item;
    }

    public static function filterByItemKey($haystack, $needle, $key, $inverted=false)
    {
        $result = array();
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {

                if ($value->{$getter}() !== $needle) {
                    $result[$haystack_key] = $value;
                }
            }
            else {
                if ($value->{$getter}() === $needle) {
                    $result[$haystack_key] = $value;
                }
            }
        }
        
        return $result;
    }

    public static function filterOneByItemKey($haystack, $needle, $key, $inverted=false)
    {
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            if ($inverted) {

                if ($value->{$getter}() !== $needle) {
                    return $value;
                }
            }
            else {
                if ($value->{$getter}() === $needle) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    public static function filterOneByItemKeyOrFirst($haystack, $needle, $key, $inverted=false)
    {
        $item = self::filterOneByItemKey($haystack, $needle, $key, $inverted);
        if ($item === null) {
            $item = reset($haystack);
        }

        return $item;
    }

    public static function filterByItemEndpointId($haystack, $needle, $key, $inverted=false)
    {
        $result = array();
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            $identity = $value->{$getter}();
            if (!($identity instanceof Identity)) {
                return null;
            }

            if ($inverted) {
                if ($identity->getEndpoint() !== $needle) {
                    $result[$haystack_key] = $value;
                }
            }
            else {
                if ($identity->getEndpoint() === $needle) {
                    $result[$haystack_key] = $value;
                }
            }
        }
        
        return $result;
    }

    public static function filterOneByItemEndpointId($haystack, $needle, $key, $inverted=false)
    {
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            $identity = $value->{$getter}();
            if (!($identity instanceof Identity)) {
                return null;
            }

            if ($inverted) {
                if ($identity->getEndpoint() !== $needle) {
                    return $value;
                }
            }
            else {
                if ($identity->getEndpoint() === $needle) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    public static function filterOneByItemEndpointIdOrFirst($haystack, $needle, $key, $inverted=false)
    {
        $item = self::filterOneByItemEndpointId($haystack, $needle, $key, $inverted);
        if ($item === null) {
            $item = reset($haystack);
        }

        return $item;
    }

    public static function filterByItemHostId($haystack, $needle, $key, $inverted=false)
    {
        $result = array();
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            $identity = $value->{$getter}();
            if (!($identity instanceof Identity)) {
                return null;
            }

            if ($inverted) {
                if ($identity->getHost() !== $needle) {
                    $result[$haystack_key] = $value;
                }
            }
            else {
                if ($identity->getHost() === $needle) {
                    $result[$haystack_key] = $value;
                }
            }
        }
        
        return $result;
    }

    public static function filterOneByItemHostId($haystack, $needle, $key, $inverted=false)
    {
        $getter = 'get' . ucfirst($key);
        
        foreach ($haystack as $haystack_key => $value) {
            $identity = $value->{$getter}();
            if (!($identity instanceof Identity)) {
                return null;
            }

            if ($inverted) {
                if ($identity->getHost() !== $needle) {
                    return $value;
                }
            }
            else {
                if ($identity->getHost() === $needle) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    public static function filterOneByItemHostIdOrFirst($haystack, $needle, $key, $inverted=false)
    {
        $item = self::filterOneByItemHostId($haystack, $needle, $key, $inverted);
        if ($item === null) {
            $item = reset($haystack);
        }

        return $item;
    }

    public static function filterByLocale($array, $locale, $key = '_localeName', $inverted=false)
    {
        return static::filterByItemKey($array, $locale, $key, $inverted);
    }
    
    public static function filterOneByLocale($array, $locale, $key = '_localeName', $inverted=false)
    {
        return static::filterOneByItemKey($array, $locale, $key, $inverted);
    }
}
