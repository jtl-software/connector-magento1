<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Core\Model\QueryFilter;
use jtl\Core\Rpc\Error;
use jtl\Core\Utilities\ClassName;
use jtl\Connector\Magento\Mapper\Category as CategoryMapper;
use jtl\Connector\ModelContainer\CategoryContainer;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;
use jtl\Connector\Transaction\Handler as TransactionHandler;


/**
 * Description of Category
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Category extends AbstractController
{
    public function commit($params, $trid)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $container = TransactionHandler::getContainer($this->getMethod()->getController(), $trid);

            $mapper = new CategoryMapper();
            $result = $mapper->push($container);

            $action->setResult($result->getPublic());
        }
        catch (\Exception $e) {
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage($e->getTraceAsString() . PHP_EOL . $e->getMessage()); //'Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function delete($params)
    {
        
    }

    public function statistic($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new CategoryMapper();
            $available = $mapper->getAvailableCount();

            $statistic = new Statistic();
            $statistic->setControllerName(lcfirst(ClassName::getFromNS(get_called_class())));
            $statistic->setAvailable($available);
            $statistic->setPending($available);

            $action->setResult($statistic->getPublic());
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function pull($filter)
    {
        $action = new Action();
        $action->setHandled(true);
    
        try {
            $mapper = new CategoryMapper();
            $categories = $mapper->pull($filter);
            
            $action->setResult($categories);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
    
        return $action;
    }

    public function push($params)
    {

    }    
}
