<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Controller;

use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Utilities\ClassName;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Magento\Mapper\Order as OrderMapper;

/**
 * Description of CustomerOrder
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class CustomerOrder extends AbstractController
{
    public function setStatus($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $magentoOrderIncrementId = $params->id;
            $status = $params->status;

            $order = \Mage::getModel('sales/order')
                ->loadByIncrementId($magentoOrderIncrementId);

            switch ($status) {
                case 'PROCESSING':
                    $order->setState(\Mage_Sales_Model_Order::STATE_PROCESSING, true);
                    break;
                
                case 'PAYMENT_COMPLETED':
                    $order->setState(\Mage_Sales_Model_Order::STATE_PROCESSING, true);
                    break;
                
                case 'COMPLETED':
                    $order->setState(\Mage_Sales_Model_Order::STATE_COMPLETE, true);
                    break;
                
                case 'PARTIALLY_SHIPPED':
                    $order->setState(\Mage_Sales_Model_Order::STATE_PROCESSING, true);
                    break;
                
                case 'CANCELLED':
                    $order->setState(\Mage_Sales_Model_Order::STATE_CANCELED, true);
                    break;
                
                case 'REACTIVATED':
                    // Cancel and re-create
                    $order->setState(\Mage_Sales_Model_Order::STATE_CANCELED, true);
                    
                    break;
                
                case 'UPDATED':
                    // Cancel and re-create
                                        
                    break;
                
                case 'PENDING_PAYMENT':
                    $order->setState(\Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true);
                    break;
            }
            
            $action->setResult('customer_order.setStatus');
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }
    
    public function getPaymentMethodId($params)
    {
        
    }

    public function setPaymentStatus($params)
    {
        
    }

    public function statistic($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $mapper = new OrderMapper();
            
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
    
    public function delete($params)
    {
        
    }

    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $filter = new QueryFilter();
            $filter->set($params);

            $mapper = new OrderMapper();
            $orders = $mapper->pull($filter);
            
            $action->setResult($orders);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }

    public function push($params)
    {

    }
}
