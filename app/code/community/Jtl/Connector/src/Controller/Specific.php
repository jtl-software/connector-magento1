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
use jtl\Connector\Magento\Mapper\Specific as SpecificMapper;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Specific as ConnectorSpecific;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;

/**
 * Description of Specific
 */
class Specific extends AbstractController
{
    public function pull(QueryFilter $filter)
    {

    }

    public function push(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $mapper = new SpecificMapper();
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

    public function delete(DataModel $model)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $hostId = $model->getId()->getHost();

            \Mage::getModel('catalog/product_attribute_api')
                ->remove($model->getId()->getEndpoint());
        }
        catch (\Exception $e) {
        }

        $action->setResult($model);

        return $action;

    }

    public function statistic(QueryFilter $filter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $mapper = new SpecificMapper();
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