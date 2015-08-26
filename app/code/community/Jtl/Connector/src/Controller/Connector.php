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
use jtl\Connector\Model\ConnectorServerInfo;
use jtl\Connector\Result\Action;
use jtl\Connector\Magento\Magento;
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

        $returnBytes = function($value) {
            $value = trim($value);
            $unit = strtolower($value[strlen($value) - 1]);
            switch ($unit) {
                case 'g':
                    $value *= 1024;
                case 'm':
                    $value *= 1024;
                    break;
                case 'k':
                    $value *= 1024;
            }

            return $value;
        };

        $serverInfo = new ConnectorServerInfo();
        $serverInfo->setMemoryLimit($returnBytes(ini_get('memory_limit')))
            ->setExecutionTime((int) ini_get('max_execution_time'))
            ->setPostMaxSize($returnBytes(ini_get('post_max_size')))
            ->setUploadMaxFilesize($returnBytes(ini_get('upload_max_filesize')));

        $identification = new ConnectorIdentification();
        $identification->setEndpointVersion('1.1.0.1')
            ->setPlatformName('Magento')
            ->setPlatformVersion(\Mage::getVersion())
            ->setProtocolVersion(Application()->getProtocolVersion())
            ->setServerInfo($serverInfo);

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
