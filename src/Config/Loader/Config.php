<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Config\Loader;

use jtl\Connector\Core\Config\Loader\Base as BaseLoader;
use jtl\Connector\Core\Exception\ConfigException;
use jtl\Connector\Core\Filesystem\Tool;

/**
 * Description of Config
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Config extends BaseLoader
{
    // Configuration params in Magento
    const CFG_ROOT_PATH = 'PFAD_ROOT';

    // Constant translations/keys
    protected $trans = array(
        self::CFG_ROOT_PATH => 'magento_path'
    );
    
    protected $_configFile;
    
    protected $name = 'MagentoConfig';

    /**
     * Creates the instance.
     * 
     * @param string $file The full filename of a Magento config file
     */
    public function __construct($config_file)
    {
        $this->config_file = $config_file;
    }

    /**
     * Will be triggered before the READ method is called, to initialize the 
     * content when it is required.
     * 
     * @throws ConfigException
     */
    public function beforeRead()
    {
        if (!Tool::is_file($this->config_file)) {
            throw new ConfigException(sprintf('Unable to load Magento configuration file "%s"', $this->config_file), 100);
        }
        require_once $this->config_file;
        $keys = $this->getConfigKeys();
        $this->data = array();
        if (!empty($keys)) {
            foreach ($keys as $key => $value) {
                $s = null;
                if (defined($value)) {
                    $s = constant($value);
                }
                $this->data[$this->trans[$value]] = $s;
            }
        }
    }

    /**
     * Returns the configuration keys.
     * 
     * @return array
     */
    public function getConfigKeys()
    {
        $rc = new \ReflectionClass(get_called_class());
        $consts = $rc->getConstants();
        $keys = array();
        foreach ($consts as $key => $value) {
            if (substr($key, 0, 3) === 'CFG') {
                $keys[$key] = $value;
            }
        }
        return $keys;
    }
}
