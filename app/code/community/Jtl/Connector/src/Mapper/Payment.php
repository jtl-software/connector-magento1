<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Model\Payment as ConnectorPayment;
use jtl\Connector\Model\Identity;

/**
 * Description of Payment
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Payment
{
    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $paymentCollection = \Mage::getModel('sales/order_payment')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('jtl_erp_id',
                    array(
                        array('eq' => 0),
                        array('null' => true)
                    ),
                    'left'
                )
                ->addAttributeToFilter('last_trans_id',
                    array('neq' => 'null')
                );

            return $paymentCollection->count();
        }
        catch (Exception $e) {
            return 0;
        }
    }

    public function pull(QueryFilter $filter = null)
    {
        Magento::getInstance();

        $paymentCollection = \Mage::getModel('sales/order_payment')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('jtl_erp_id',
                array(
                    array('eq' => 0),
                    array('null' => true)
                ),
                'left'
            )
            ->addAttributeToFilter('last_trans_id',
                array('neq' => 'null')
            )
            ->setPageSize(25)
            ->setCurPage(1);

        $result = array();
        foreach ($paymentCollection as $item)
        {
            $order = $item->getOrder();

            $payment = new ConnectorPayment();
            $payment
                ->setId(new Identity($item->entity_id))
                ->setCustomerOrderId(new Identity($item->order_id))
                ->setCreationDate(new \DateTime($item->created_at))
                ->setTotalSum((double)$item->amount_paid)
                ->setTransactionId($item->last_trans_id);

            if (array_key_exists($item->method, Order::$paymentMethods))
                $payment->setPaymentModuleCode(Order::$paymentMethods[$item->method]);
            else
                $payment->setPaymentModuleCode('pm_bank_transfer');

            $result[] = $payment;
        }
        
        return $result;
    }
}
