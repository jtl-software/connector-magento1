<?php
/**
 * 
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento;

use jtl\Connector\Base\Connector as BaseConnector;
use jtl\Connector\Core\Config\Config;
use jtl\Connector\Core\Config\Loader\Json as ConfigJson;
use jtl\Connector\Core\Config\Loader\System as ConfigSystem;
use jtl\Connector\Core\Exception\TransactionException;
use jtl\Connector\Core\Rpc\RequestPacket;
use jtl\Connector\Core\Utilities\RpcMethod;
use jtl\Connector\Core\Controller\Controller as CoreController;
use jtl\Connector\ModelContainer\MainContainer;
use jtl\Connector\Magento\Config\Loader\Config as ConfigLoader;
use jtl\Connector\Transaction\Handler as TransactionHandler;

/**
 * Magento Connector
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.de>
 */
class Connector extends BaseConnector
{
    /**
     * Current Controller
     *
     * @var \jtl\Core\Controller\Controller
     */
    protected $_controller;
    
    /**
     *
     * @var string
     */
    protected $_action;
    
    /**
     *
     * @var string
     */
    protected $_config;
    
    protected function __construct()
    {
        $this->initializeConfiguration();
    }
    
    protected function initializeConfiguration()
    {
        $config = null;
        if (isset($_SESSION['config'])) {
            $config = $_SESSION['config'];
        }
                
        if (empty($config)) {
            if (!is_null($this->_config)) {
                $config = $this->getConfig();
            }

            if (empty($config)) {
                // Application object is not initialized. Bypass by manually creating
                // the Config object
                $json = new ConfigJson(realpath(APP_DIR . '/../config/') . '/config.json');
                $config = new Config(array(
                  $json,
                  new ConfigSystem()
                ));
                $this->setConfig($config);
            }
        }
        
        // Read Magento configuration
        if (!$config->existsLoaderByName('MagentoConfig')) {
            $config->addLoader(new ConfigLoader(APP_DIR . '/../config/config.Magento.ini.php'));
        }

        if (!isset($_SESSION['config'])) {
            $_SESSION['config'] = $config;
        }
    }


    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::canHandle()
     */
    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        
        $class = "\\jtl\\Connector\\Magento\\Controller\\{$controller}";
        if (class_exists($class)) {
            $this->_controller = $class::getInstance();
            $this->_action = RpcMethod::buildAction($this->getMethod()->getAction());

            return is_callable(array($this->_controller, $this->_action));
        }

        return false;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::handle()
     */
    public function handle(RequestPacket $requestpacket)
    {
        $config = $this->getConfig();
        
        // Set the config to our controller 
        $this->_controller->setConfig($config);

        // Set the method to our controller
        $this->_controller->setMethod($this->getMethod());
        
        // Transaction Commit work around
        if (($this->_action !== "commit")) {
            return $this->_controller->{$this->_action}($requestpacket->getParams());
        }
        else if (TransactionHandler::exists($requestpacket) && MainContainer::isMain($this->getMethod()->getController()) && $this->_action === "commit") {
            return $this->_controller->{$this->_action}($requestpacket->getParams(), $requestpacket->getGlobals()->getTransaction()->getId());
        }
        else {
            throw new TransactionException("Only Main Controller can handle commit actions");
        }
    }
    
    /**
     * Getter Controller
     * 
     * @return \jtl\Core\Controller\Controller
     */
    public function getController()
    {
        return $this->_controller;
    }

    /**
     * Setter Controller
     * 
     * @param \jtl\Core\Controller\Controller $controller
     */
	public function setController(CoreController $controller)
    {
        $this->_controller = $controller;
    }

    /**
     * Getter Action
     * 
     * @return string
     */
	public function getAction()
    {
        return $this->_action;
    }

    /**
     * Setter Action
     * 
     * @param string $action
     */
	public function setAction($action)
    {
        $this->_action = $action;
    }
}
