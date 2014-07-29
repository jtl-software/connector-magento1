<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use jtl\Core\Exception\TransactionException;
use jtl\Core\Result\Transaction as TransactionResult;
use jtl\Core\Rpc\Error;
use jtl\Core\Utilities\ClassName;
use jtl\Connector\Magento\Mapper\Image as ImageMapper;
use jtl\Connector\ModelContainer\ImageContainer;
use jtl\Connector\Model\Statistic;
use jtl\Connector\Result\Action;
use jtl\Connector\Transaction\Handler as TransactionHandler;

/**
 * Description of Image
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Image extends AbstractController
{
    public function commit($params, $trid)
    {

    }

    public function delete($params)
    {
        
    }

    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new ImageMapper();
            $images = $mapper->pull();
            
            $action->setResult($images);
        }
        catch (\Exception $e) {
            error_log(var_export($e, true));
            $err = new Error();
            $err->setCode(31337); //$e->getCode());
            $err->setMessage('Internal error'); //$e->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function push($params)
    {

    }

    public function statistic($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new ImageMapper();
            
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
}
