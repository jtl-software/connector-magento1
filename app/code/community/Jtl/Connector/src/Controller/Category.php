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
use jtl\Connector\Magento\Mapper\Category as CategoryMapper;
use jtl\Connector\Model\Category as ConnectorCategory;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;


/**
 * Description of Category
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Category extends AbstractController
{
    public function delete(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $hostId = $model->getId()->getHost();
            $category = \Mage::getModel('catalog/category')
                ->loadByAttribute('jtl_erp_id', $hostId);

            if ($category) {
                \Mage::register('isSecureArea', true);
                $category->delete();
                \Mage::unregister('isSecureArea');
            }
        }
        catch (\Exception $e) {
        }

        $result = new ConnectorCategory();
        $result->setId(new Identity('', $hostId));
        $action->setResult($result);

        return $action;
    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new CategoryMapper();
            $available = $mapper->getAvailableCount();

            $statistic = new Statistic();
            $statistic->setControllerName(lcfirst(ClassName::getFromNS(get_called_class())));
            $statistic->setAvailable($available);

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

    public function pull(QueryFilter $filter)
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

    public function push(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new CategoryMapper();
            $result = $mapper->push($model);

            $action->setResult($result);
        }
        catch (\Exception $e) {
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage($e->getTraceAsString() . PHP_EOL . $e->getMessage()); //'Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }    
}
