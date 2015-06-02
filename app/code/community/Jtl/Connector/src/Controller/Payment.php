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
use jtl\Connector\Magento\Mapper\Payment as PaymentMapper;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;

/**
 * Description of Payment
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Payment extends AbstractController
{
    public function pull(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new PaymentMapper();
            $payments = $mapper->pull($filter);
            
            $action->setResult($payments);
        }
        catch (\Exception $e) {
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage($e->getMessage()); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function push(DataModel $model)
    {

    }

    public function delete(DataModel $model)
    {

    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new PaymentMapper();
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