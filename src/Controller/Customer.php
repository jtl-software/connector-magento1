<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Core\Model\DataModel;
use jtl\Core\Model\QueryFilter;
use jtl\Core\Rpc\Error;
use jtl\Core\Utilities\ClassName;
use jtl\Connector\Result\Action;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Magento\Mapper\Customer as CustomerMapper;

/**
 * Description of Customer
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Customer extends AbstractController
{
    public function push(DataModel $model)
    {
        
    }

    public function delete($params)
    {
        
    }

    public function statistic($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new CustomerMapper();
            
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

    public function pull(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new CustomerMapper();
            $customers = $mapper->pull($filter);
            
            $action->setResult($customers);
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
