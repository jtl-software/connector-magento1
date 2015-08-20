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
use jtl\Connector\Magento\Mapper\DeliveryNote as DeliveryNoteMapper;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;

/**
 * Description of DeliveryNote
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class DeliveryNote extends AbstractController
{
    public function pull(QueryFilter $filter)
    {

    }

    public function push(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $mapper = new DeliveryNoteMapper();
            $result = $mapper->updateDeliveryNote($model);

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

    public function delete(DataModel $model)
    {

    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new DeliveryNoteMapper();
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