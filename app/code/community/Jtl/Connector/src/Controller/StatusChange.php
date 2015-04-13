<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Result\Action;
use jtl\Connector\Magento\Mapper\Order as OrderMapper;

class StatusChange extends AbstractController
{
    public function push(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new OrderMapper();
            $result = $mapper->processStatusUpdate($model);

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
