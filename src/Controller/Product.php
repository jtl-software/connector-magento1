<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Utilities\ClassName;
use jtl\Connector\Magento\Mapper\Product as ProductMapper;
use jtl\Connector\Model\Statistic;
use jtl\Connector\ModelContainer\ProductContainer;
use jtl\Connector\Result\Action;

/**
 * Description of Product
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Product extends AbstractController
{
    public function delete(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $hostId = $model->getId()->getHost();
            $product = \Mage::getModel('catalog/product')
                ->loadByAttribute('jtl_erp_id', $hostId);

            if ($product) {
                \Mage::register('isSecureArea', true);
                $product->delete();
                \Mage::unregister('isSecureArea');
            }

            $action->setResult(true);
        }
        catch (\Exception $e) {
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage($e->getTraceAsString() . PHP_EOL . $e->getMessage()); //'Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        return $action;
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
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new ProductMapper();
            $result = $mapper->push($model);

            $action->setResult($result);
        }
        catch (\Exception $e) {
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage($e->getTraceAsString() . PHP_EOL . $e->getMessage()); //'Internal error'); //$e->getMessage());
            die(var_dump($err));
            $action->setError($err);
        }
        
        return $action;
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

            $action->setResult($statistic);
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
