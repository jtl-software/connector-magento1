<?php

namespace jtl\Connector\Magento\Utilities;

use jtl\Connector\Core\Utilities\Singleton;

class StoreMapper extends Singleton
{
    private $_storeMapping;
    private $_localeResultCache = array();
    private $_storeResultCache = array();
    private $_mappingResultCache = array();

    protected function __construct()
    {
        $this->_storeMapping = unserialize(\Mage::getStoreConfig('jtl_connector/general/store_mapping'));
    }

    public function getLocaleFromStore($store, $website = null)
    {
        if (is_null($website)) {
            $website = \Mage::app()->getWebsite();
        }

        if ($this->isLocaleResultCached($store, $website)) {
            return $this->_localeResultCache[$website][$store];
        }

        foreach ($this->_storeMapping as $mapEntry)
        {
            if ($mapEntry['website'] === $website && $mapEntry['store'] === $store) {
                $locale = $mapEntry['locale'];

                $this->storeLocaleResult($store, $website, $locale);
                return $locale;
            }
        }

        return null;
    }

    public function getStoreFromLocale($locale, $website = null)
    {
        if (is_null($website)) {
            $website = \Mage::app()->getWebsite();
        }

        if ($this->isStoreResultCached($locale, $website)) {
            return $this->_storeResultCache[$website][$locale];
        }

        foreach ($this->_storeMapping as $mapEntry)
        {
            if ($mapEntry['website'] === $website && $mapEntry['locale'] === $locale) {
                $store = $mapEntry['store'];

                $this->storeStoreResult($locale, $website, $store);
                return $store;
            }
        }

        return null;
    }

    public function getMappingForWebsite($website = null)
    {
        if (is_null($website)) {
            $website = \Mage::app()->getWebsite();
        }

        if ($this->isMappingResultCached($website)) {
            return $this->_mappingResultCache[$website];
        }

        $mapping = array();
        foreach ($this->_storeMapping as $mapEntry) {
            if ($mapEntry['website'] === $website) {
                $mapping[$mapEntry['locale']] = $mapEntry['store'];
            }
        }

        $this->storeMappingResult($website, $mapping);
        return $mapping;
    }

    private function storeLocaleResult($store, $website, $locale)
    {
        $this->_localeResultCache[$website][$store] = $locale;
    }

    private function isLocaleResultCached($store, $website)
    {
        if (!array_key_exists($this->_localeResultCache, $website))
            return false;

        return is_array($this->_localeResultCache[$website] && array_key_exists($this->_localeResultCache[$website], $store);
    }

    private function storeStoreResult($locale, $website, $store)
    {
        $this->_storeResultCache[$website][$locale] = $store;
    }

    private function isStoreResultCached($locale, $website)
    {
        if (!array_key_exists($this->_storeResultCache, $website))
            return false;

        return is_array($this->_storeResultCache[$website] && array_key_exists($this->_storeResultCache[$website], $locale);
    }

    private function storeMappingResult($website, array $mapping)
    {
        $this->_mappingResultCache[$website] = $mapping;
    }

    private function isMappingResultCached($website)
    {
        return array_key_exists($this->_mappingResultCache, $website);
    }
}
