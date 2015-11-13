<?php

namespace jtl\Connector\Magento\Utilities;

final class IdConcatenator
{
    const SEPARATOR = '-';

    private function __construct() { }
    private function __clone() { }

    public static function link($endpointIds)
    {
        if (is_array($endpointIds)) {
            return implode(self::SEPARATOR, $endpointIds);
        }
        else {
            return implode(self::SEPARATOR, func_get_args());
        }
    }

    public static function unlink($endpointId)
    {
        return explode(self::SEPARATOR, $endpointId);
    }

    public static function isProductId($endpointId)
    {
        return (bool) preg_match('/\d{1,}' . self::SEPARATOR . '\d{1,}/', $endpointId);
    }

    public static function isImageId($endpointId)
    {
        return (bool) preg_match('/[a-z]{1}' . self::SEPARATOR . '\d{1,}' . self::SEPARATOR . '\d{1,}/', $endpointId);
    }
}