<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Core\Exception\TransactionException;
use jtl\Core\Model\DataModel;
use jtl\Core\Model\QueryFilter;
use jtl\Core\Rpc\Error;
use jtl\Core\Utilities\ClassName;
use jtl\Connector\Magento\Mapper\Product as ProductMapper;
use jtl\Connector\Model\Statistic;
use jtl\Connector\ModelContainer\ProductContainer;
use jtl\Connector\Result\Action;
use jtl\Connector\Result\Transaction as TransactionResult;
use jtl\Connector\Transaction\Handler as TransactionHandler;

/**
 * Description of Product
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Product extends AbstractController
{
    public function commit($params, $trid)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $container = TransactionHandler::getContainer($this->getMethod()->getController(), $trid);

            $mapper = new ProductMapper();
            $result = $mapper->push($container);

            $action->setResult($result->getPublic());
        }
        catch (\Exception $e) {
            ob_start();
            var_dump($e->getMessage());
            $dump = ob_get_clean();
            error_log($dump);
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage('Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function delete($params)
    {
        
    }

    public function pull(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new ProductMapper();
            $products = $mapper->pull($filter);
            
            $action->setResult($products);
        }
        catch (\Exception $e) {
            ob_start();
            var_dump($e);
            $dump = ob_get_clean();
            error_log($dump);
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage('Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function push(DataModel $model)
    {

    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new ProductMapper();
            $available = $mapper->getAvailableCount();

            $statistic = new Statistic();
            $statistic->setControllerName(lcfirst(ClassName::getFromNS(get_called_class())));
            $statistic->setAvailable($mapper->getAvailableCount());
            $statistic->setPending(0);

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
}
