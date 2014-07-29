<?php
/**
 *
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Database;

use \jtl\Core\Exception\DatabaseException;

/**
 * Sqlite 3 Database Class
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Sqlite3
{
    /**
     * Sqlite 3 sharedcache value
     *
     * @var integer
     */
    const SQLITE3_OPEN_SHAREDCACHE = 0x00020000;
    
    /**
     * Database connection state
     *
     * @var bool
     */
    protected $_isConnected = false;
    
    /**
     * Sqlite 3 Database object
     *
     * @var Sqlite3 | NULL
     */
    protected $_db;
    
    /**
     * Database Singleton
     *
     * @var \jtl\Core\Database\Sqlite3
     */
    protected static $_instance;
    
    /**
     * Path to the SQLite database, or :memory: to use in-memory database.
     *
     * @var string
     */
    public $location;
    
    /**
     * Optional flags used to determine how to open the SQLite database.
     *
     * @var integer
     */
    public $mode;

    /**
     * Constructor
     */
    public function __construct(array $options = null) {
        $this->connect($options);        
    }
    
    /**
     * Singleton
     *
     * @return \jtl\Core\Database\Sqlite3
     */
    public static function getInstance()
    {
        if (self::$_instance === null)
            self::$_instance = new self;
    
        return self::$_instance;
    }
    
    /**
     * (non-PHPdoc)
     * 
     * @see \jtl\Core\Database\IDatabase::connect()
     * @throws \jtl\Core\Exception\DatabaseException
     */
    public function connect(array $options = null)
    {        
        $this->setOptions($options);
        if (!is_string($this->location) || strlen($this->location) == 0) {
            throw new DatabaseException("Wrong type or empty location");
        }
        
        if ($this->mode === null) {
            $this->mode = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE | self::SQLITE3_OPEN_SHAREDCACHE;
        }
        
        try {
            $this->_db = new \Sqlite3($this->location, $this->mode);
            $this->_db->busyTimeout(2000);
            
            $this->_isConnected = true;
        }
        catch (\Exception $exc) {
            throw new DatabaseException($exc->getMessage());
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->_isConnected) {
            $this->close();
        }
    }

    /**
     * (non-PHPdoc)
     * 
     * @see \jtl\Core\Database\IDatabase::close()
     */
    public function close()
    {
        return $this->_db->close();
    }

    /**
     * (non-PHPdoc)
     * 
     * @see \jtl\Core\Database\IDatabase::query()
     */
    public function query($query)
    {
        $command = substr($query, 0, strpos($query, " "));
        
        switch (strtoupper($command)) {
            case "SELECT":
                return $this->_fetch($query);
                break;
            
            case "UPDATE":
                return $this->_exec($query);
                break;
            
            case "INSERT":
                return $this->_insert($query);
                break;
            
            case "DELETE":
                return $this->_exec($query);
                break;
        }
        
        return null;
    }
    
    /**
     * Sqlite Select
     * 
     * @param string $query
     * @return multitype:array |NULL
     */
    protected function _fetch($query)
    {
        $result = $this->_db->query($query);
        if ($result) {
            $rows = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        
            return $rows;
        }
        
        return null;
    }
    
    /**
     * Sqlite Update or Delete
     * 
     * @param string $query
     * @return boolean
     */
    protected function _exec($query)
    {
        return $this->_db->exec($query);
    }
    
    /**
     * Sqlite Insert
     * 
     * @param string $query
     * @return number|boolean
     */
    protected function _insert($query)
    {
        if ($this->_db->exec($query)) {
            return $this->_db->lastInsertRowID();
        }
        
        return false;
    }

    /**
     * Executes a result-less query against a given database
     *
     * @param string $query            
     */
    public function exec($query)
    {
        return $this->_db->exec($query);
    }

    /**
     * (non-PHPdoc)
     * 
     * @see \jtl\Core\Database\IDatabase::isConnected()
     */
    public function isConnected()
    {
        return $this->_isConnected;
    }

    /**
     * Set Options
     *
     * @param array $options            
     */
    public function setOptions(array $options = null)
    {
        if ($options !== null && is_array($options)) {
            // Location
            if (isset($options["location"]) && is_string($options["location"]) && strlen($options["location"]) > 0) {
                $this->location = $options["location"];
            }
            
            // Mode
            if (isset($options["mode"]) && is_int($options["mode"])) {
                $this->mode = $options["mode"];
            }
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Core\Database\IDatabase::escapeString()
     */
    public function escapeString($query) 
    {
        return \Sqlite3::escapeString($query);
    }
}
