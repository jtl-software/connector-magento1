<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\DataModel;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Result\Action;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Mapper\GlobalData as GlobalDataMapper;
use jtl\Connector\Model\ConnectorIdentification;

/**
 * Description of Connector
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Connector extends AbstractController
{
    private $controllers = array(
        'Category',
        'Customer',
        'CustomerOrder',
        'GlobalData',
        'Image',
        'Product'
    );

    public function push(DataModel $model)
    {
        
    }

    public function pull(QueryFilter $filter)
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
            $result = array();

            foreach ($this->controllers as $controller) {
                $controller = __NAMESPACE__ . '\\' . $controller;
                $obj = new $controller();

                if (method_exists($obj, 'statistic')) {
                    $method_result = $obj->statistic($filter);

                    $result[] = $method_result->getResult();
                }
            }

            $action->setResult($result);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    /**
     * Identify
     *
     * @return \jtl\Connector\Result\Action
     */
    public function identify()
    {
        $action = new Action();
        $action->setHandled(true);

        $identification = new ConnectorIdentification();
        $identification->setEndpointVersion('1.0.5.0')
            ->setPlatformName('Magento')
            ->setPlatformVersion(\Mage::getVersion())
            ->setProtocolVersion(Application()->getProtocolVersion());

        $action->setResult($identification);

        return $action;
    }

    /**
     * Finish
     *
     * @return \jtl\Connector\Result\Action
     */
    public function finish()
    {
        $action = new Action();
        $action->setHandled(true);

        Logger::write('reindexing everything...');
        Magento::getInstance()->reindexEverything();
        Logger::write('indexing has finished');

        $action->setResult(true);

        return $action;
    }
}
