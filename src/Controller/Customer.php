<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Utilities\ClassName;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Magento\Mapper\Customer as CustomerMapper;

/**
 * Description of Customer
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Customer extends AbstractController
{
    public function push($params)
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
            $statistic->_controllerName = lcfirst(ClassName::getFromNS(get_called_class()));
            $statistic->_available = $mapper->getAvailableCount();
            $statistic->_pending = 0;

            $action->setResult($statistic->getPublic(array('_fields', '_isEncrypted')));
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $filter = new QueryFilter();
            $filter->set($params);

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
