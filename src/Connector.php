<?php
/**
 * 
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento;

use jtl\Connector\Base\Connector as BaseConnector;
use jtl\Connector\Core\Exception\TransactionException;
use jtl\Connector\Core\Rpc\Method;
use jtl\Connector\Core\Rpc\RequestPacket;
use jtl\Connector\Core\Utilities\RpcMethod;
use jtl\Connector\Core\Controller\Controller as CoreController;
use jtl\Connector\ModelContainer\MainContainer;
use jtl\Connector\Magento\TokenLoader;
use jtl\Connector\Magento\Mapper\PrimaryKeyMapper;
use jtl\Connector\Result\Action;
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
        // Destroy Magento's session
        if ('' != session_id()) {
            session_destroy();
        }

        $this->setPrimaryKeyMapper(new PrimaryKeyMapper());
        $this->setTokenLoader(new TokenLoader());
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
        
        if ($this->_action === Method::ACTION_PUSH || $this->_action === Method::ACTION_DELETE) {
            if (!is_array($requestpacket->getParams())) {
                throw new TransactionException("Expecting request array, invalid data given");
            }

            $action = new Action();
            $results = array();
            $errors = array();
            foreach ($requestpacket->getParams() as $param) {
                $result = $this->_controller->{$this->_action}($param);
                $results[] = $result->getResult();
            }

            $action->setHandled(true)
                ->setResult($results)
                ->setError($result->getError());    // @todo: refactor to array of errors

            return $action;
        }
        else {
            return $this->_controller->{$this->_action}($requestpacket->getParams());
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
