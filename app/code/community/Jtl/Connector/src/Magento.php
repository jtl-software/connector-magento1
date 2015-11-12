<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento;

use jtl\Connector\Core\Config\Config;
use jtl\Connector\Core\Utilities\Singleton;
use jtl\Connector\Magento\Connector as MagentoConnector;

/**
 * PHP-Magento main singleton
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Magento extends Singleton
{
    /**
     * Current store
     * @var int
     */
    protected $_store = null;
    
    /**
     * Website cache
     * @var array
     */
    protected $_websites = null;
    
    /**
     * Website cache
     * @var array
     */
    protected $_usedWebsites = null;

    /**
     * Store mapping
     * @var array
     */
    protected $_storeMapping = array();

    /**
     * Tax rate mapping
     * @var array
     */
    protected $_taxRateMapping = array();

    /**
     * Constructor
     */
    protected function __construct()
    {
        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $configStoreMapping = unserialize(\Mage::getStoreConfig('jtl_connector/general/store_mapping'));
        foreach ($configStoreMapping as $mapping) {
            $this->_storeMapping[$mapping['locale']] = $mapping['store'];
        }
        
        $this->_store = \Mage::app()->getStore();

        $configTaxRateMapping = unserialize(\Mage::getStoreConfig('jtl_connector/general/taxrate_mapping'));
        foreach ($configTaxRateMapping as $mapping) {
            $taxRate = \Mage::getModel('tax/calculation_rate')
                ->load($mapping['taxRate']);

            $this->_taxRateMapping[$mapping['taxClass']] = $taxRate->getRate();
        }
    }
    
    /**
     * Return Magento version string
     * 
     * @return string
     */
    public function getVersion()
    {
        return \Mage::getVersion();
    }
    
    /**
     * Enable real-time indexing
     */
    public function enableRealtimeIndex()
    {
        $processes = \Mage::getSingleton('index/indexer')->getProcessesCollection();
        foreach ($processes as $process) {
            $process
                ->setMode(\Mage_Index_Model_Process::MODE_REAL_TIME)
                ->save();
        }
    }
    
    /**
     * Disable real-time indexing
     */
    public function disableRealtimeIndex()
    {
        $processes = \Mage::getSingleton('index/indexer')->getProcessesCollection();
        foreach ($processes as $process) {
            $process
                ->setMode(\Mage_Index_Model_Process::MODE_MANUAL)
                ->save();
        }
    }
    
    /**
     * Disable real-time indexing
     */
    public function reindexEverything()
    {
        if (\Mage::helper('catalog/category_flat')->isEnabled()) {
            \Mage::getModel('catalog/category_indexer_flat')
                ->reindexAll();
        }

        if (\Mage::helper('catalog/product_flat')->isEnabled()) {
            \Mage::getModel('catalog/product_flat_indexer')
                ->reindexAll();
        }
    }
    
    /**
     * Clear all caches
     */
    public function clearCache()
    {
        $allTypes = \Mage::app()->useCache();
        foreach (array_keys($allTypes) as $type) {
            \Mage::app()->getCacheInstance()->clean($type);
        }
    }
    
    /**
     * Getter for $_store
     * 
     * @return object
     */
    public function getCurrentStore()
    {
        return $this->_store;
    }
    
    /**
     * Setter for $_store
     * 
     * @return object
     */
    public function setCurrentStore($store)
    {
        \Mage::app()->setCurrentStore($store);
        $this->_store = $store;
        return true;
    }
    
    /**
     * Get an array of the configured Magento websites
     */
    public function getWebsites()
    {
        if (is_array($this->_websites))
            return $this->_websites;
        
        $websiteModel = \Mage::getModel('core/website')
            ->getCollection()
            ->setLoadDefault(false);
        
        $this->_websites = array();
        foreach ($websiteModel as $website) {
            $this->_websites[$website->getWebsiteId()] = $website;
        }
        
        return $this->_websites;
    }

    /**
     * Get the default customer group ID
     */
    public function getDefaultCustomerGroupId()
    {
        return (int)\Mage::getStoreConfig('jtl_connector/general/default_customer_group');
    }

    /**
     * Getter for $_storeMapping
     * 
     * @return array
     */
    public function getStoreMapping()
    {
        return $this->_storeMapping;
    }
   
    /**
     * Getter for $_taxRateMapping
     * 
     * @return array
     */
    public function getTaxRateMapping()
    {
        return $this->_taxRateMapping;
    }
}
