<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento;
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Core\Utilities\Singleton;
use jtl\Connector\Magento\Database\Sqlite3;

/**
 * Description of Database
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Database extends Singleton
{
    /**
     * Connector configuration database
     * 
     * @var \jtl\Core\Database\Sqlite3
     */
    protected $_configDB = null;
    
    /**
     * Class constructor
     * 
     * Load configuration database
     */
    protected function __construct()
    {
        $filename = ENDPOINT_DIR . '/db/mapper.s3db';

        if (!is_dir(ENDPOINT_DIR . '/db')) {
            mkdir(ENDPOINT_DIR . '/db');
        }
        
        if (!file_exists($filename)) {
            touch($filename);
            chmod($filename, 0666);
        }
        
        $this->_configDB = new Sqlite3(array(
            'location' => $filename
        ));
    }
    
    public function initialize()
    {
        // Clear SQLite database
        $this->dropAllTables();
        
        // Initialize SQLite database
        $this->createStructure();
    }
    
    private function createStructure()
    {
        $this->_configDB->exec('CREATE TABLE product (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_id UNSIGNED INTEGER NOT NULL, magento_sku VARCHAR(255) UNIQUE NOT NULL, last_sync UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE category (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_id UNSIGNED INTEGER NOT NULL, magento_id VARCHAR(255) UNIQUE NOT NULL, last_sync UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE specific (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_id UNSIGNED INTEGER NOT NULL, magento_code VARCHAR(255) UNIQUE NOT NULL, last_sync UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE variation (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_varcombi_id UNSIGNED INTEGER NOT NULL, wawi_id UNSIGNED INTEGER NOT NULL, magento_code VARCHAR(255) UNIQUE NOT NULL, last_sync UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE variation_value (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_variation_id UNSIGNED INTEGER NOT NULL, wawi_id UNSIGNED INTEGER NOT NULL, magento_code VARCHAR(255) NOT NULL, magento_id UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE locale (id INTEGER PRIMARY KEY, locale VARCHAR(10) UNIQUE NOT NULL, magento_store UNSIGNED INTEGER NOT NULL)');

        $this->_configDB->exec('CREATE TABLE varcombi (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_id UNSIGNED INTEGER NOT NULL, magento_attribute_set VARCHAR(255) NOT NULL DEFAULT "", last_sync UNSIGNED INTEGER NOT NULL)');
        $this->_configDB->exec('CREATE TABLE varcombi_child (id INTEGER PRIMARY KEY AUTOINCREMENT, wawi_varcombi_id UNSIGNED INTEGER NOT NULL, wawi_id UNSIGNED INTEGER NOT NULL, magento_id UNSIGNED INTEGER NOT NULL, magento_sku VARCHAR(255) UNIQUE NOT NULL, attribute_data TEXT NULL, last_sync UNSIGNED INTEGER NOT NULL)');
    }
    
    private function dropAllTables()
    {
        $tables = $this->_configDB->query('SELECT name FROM sqlite_master WHERE type = "table" AND name <> "sqlite_sequence"');
        
        foreach ($tables as $table) {
            $this->_configDB->exec('DROP TABLE ' . $table['name']);
        }
    }
    
    public function query($stmt)
    {
        return $this->_configDB->query($stmt);
    }
    
    public function exec($stmt)
    {
        return $this->_configDB->exec($stmt);
    }
    
    public function escapeString($string)
    {
        return $this->_configDB->escapeString($string);
    }
    
    public function getStoreMapping()
    {
        static $mapping = null;
        if (!is_null($mapping))
            return $mapping;
        
        $dbMapping = $this->query('SELECT locale,magento_store FROM locale');
        $mapping = array();
        foreach ($dbMapping as $row) {
            $mapping[$row['locale']] = $row['magento_store'];
        }
        
        return $mapping;
    }
    
    public function getDefaultLocale()
    {
        static $defaultLocale = null;
        if (!is_null($defaultLocale))
            return $defaultLocale;
        
        $mapping = array_keys($this->getStoreMapping());
        $defaultLocale = reset($mapping);
        
        return $defaultLocale;
    }
}
