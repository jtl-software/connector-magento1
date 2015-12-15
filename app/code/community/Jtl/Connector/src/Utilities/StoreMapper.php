<?php

namespace Jtl\Connector\Magento\Utilities;

use jtl\Connector\Core\Utilities\Singleton;

class StoreMapper extends Singleton
{
    private $_storeMapping;
    private $_localeResultCache = array();
    private $_storeResultCache = array();

    protected function __construct()
    {
        $this->_storeMapping = unserialize(\Mage::getStoreConfig('jtl_connector/general/store_mapping'));
    }

    public function getLocaleFromStore($website, $store)
    {
        if ($this->isLocaleResultCached($website, $store)) {
            return $this->_localeResultCache[$website][$store];
        }

        foreach ($this->_storeMapping as $mapEntry)
        {
            if ($mapEntry['website'] === $website && $mapEntry['store'] === $store) {
                $locale = $mapEntry['locale'];

                $this->storeLocaleResult($website, $store, $locale);
                return $locale;
            }
        }

        return null;
    }

    public function getStoreFromLocale($website, $locale)
    {
        if ($this->isStoreResultCached($website, $locale)) {
            return $this->_storeResultCache[$website][$locale];
        }

        foreach ($this->_storeMapping as $mapEntry)
        {
            if ($mapEntry['website'] === $website && $mapEntry['locale'] === $locale) {
                $store = $mapEntry['store'];

                $this->storeStoreResult($website, $locale, $store);
                return $store;
            }
        }

        return null;
    }

    private function storeLocaleResult($website, $store, $locale)
    {
        $this->_localeResultCache[$website][$store] = $locale;
    }

    private function isLocaleResultCached($website, $store)
    {
        if (!array_key_exists($_localeResultCache, $website))
            return false;

        return is_array($this->_localeResultCache[$website] && array_key_exists($this->_localeResultCache[$website], $store);
    }

    private function storeStoreResult($website, $locale, $store)
    {
        $this->_storeResultCache[$website][$locale] = $store;
    }

    private function isStoreResultCached($website, $locale)
    {
        if (!array_key_exists($_storeResultCache, $website))
            return false;

        return is_array($this->_storeResultCache[$website] && array_key_exists($this->_storeResultCache[$website], $locale);
    }
}
