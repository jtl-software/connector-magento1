<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use \jtl\Connector\Model\CustomerOrder as ConnectorCustomerOrder;
use \jtl\Connector\Model\CustomerOrderBillingAddress as ConnectorCustomerOrderBillingAddress;
use \jtl\Connector\Model\CustomerOrderItem as ConnectorCustomerOrderItem;
use \jtl\Connector\Model\CustomerOrderItemVariation as ConnectorCustomerOrderItemVariation;
use \jtl\Connector\Model\CustomerOrderShippingAddress as ConnectorCustomerOrderShippingAddress;
use \jtl\Connector\ModelContainer\CustomerOrderContainer;
use \jtl\Magento\Magento;

/**
 * Description of Order
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Order
{
    private $paymentMethods = array(
        'checkmo' => 'pm_bank_transfer'
    );

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $orderModel = \Mage::getModel('sales/order');
            $orderCollection = $orderModel->getCollection()
                ->addAttributeToSelect('*');

            return $orderCollection->count();
        }
         catch (Exception $e) {
            return 0;
        }
    }

    public function pull(QueryFilter $filter = null)
    {
        Magento::getInstance();
        $stores = MapperDatabase::getInstance()->getStoreMapping();

        $orderModel = \Mage::getModel('sales/order');
        $orderCollection = $orderModel->getCollection()
            ->addAttributeToSelect('*');

        if (!is_null($filter)) {
            $orderCollection
                ->getSelect()
                ->limit($filter->getLimit(), $filter->getOffset());
        }

        $orders = $orderCollection->load();

        $result = array();
        foreach ($orders as $order) {
            $orderTaxInfo = $order->getFullTaxInfo();

            $container = new CustomerOrderContainer();

            $created_at = new \DateTime($order->created_at);

            $customerOrder = new ConnectorCustomerOrder();
            $customerOrder->_id = $order->entity_id;
            $customerOrder->_basketId = NULL;
            $customerOrder->_customerId = (int)(intval($order->customer_id));
            $customerOrder->_shippingAddressId = $order->shipping_address_id;
            $customerOrder->_billingAddressId = $order->billing_address_id;
            $customerOrder->_shippingMethodId = 2;
            $customerOrder->_localeName = array_search($order->store_id, $stores);
            $customerOrder->_currencyIso = $order->order_currency_code;
            $customerOrder->_paymentMethodType = NULL;
            $customerOrder->_credit = 0.00;
            $customerOrder->_totalSum = $order->grand_total;
            $customerOrder->_cSession = NULL;
            $customerOrder->_shippingMethodName = $order->shipping_description;
            $customerOrder->_paymentMethodName = '';
            $customerOrder->_orderNumber = $order->increment_id;
            $customerOrder->_shippingInfo = '';
            $customerOrder->_shippingDate = NULL;
            $customerOrder->_paymentDate = NULL;
            $customerOrder->_ratingNotificationDate = NULL;
            $customerOrder->_tracking = '';
            $customerOrder->_note = '';
            $customerOrder->_logistic = '';
            $customerOrder->_trackingURL = '';
            $customerOrder->_ip = $order->remote_ip;
            $customerOrder->_isFetched = false;
            $customerOrder->_status = NULL;
            $customerOrder->_created = $created_at->format('c');

            $payment = $order->getPayment();
            $code = $payment->getMethodInstance()->getCode();

            if (array_key_exists($code, $this->paymentMethods))
                $customerOrder->_paymentModuleId = $this->paymentMethods[$code];
            else
                $customerOrder->_paymentModuleId = 'pm_bank_transfer';

            $container->add('customer_order', $customerOrder->getPublic(array('_fields')));

            foreach ($order->getAllItems() as $magento_item) {
                $item = new ConnectorCustomerOrderItem();
                $item->_id = $magento_item->item_id;
                $item->_customerOrderId = $order->entity_id;
                $item->_basketId = NULL;
                $item->_productId = $magento_item->product_id;
                $item->_shippingClassId = NULL;
                $item->_name = $magento_item->name;
                $item->_sku = $magento_item->sku;
                $item->_vat = $magento_item->tax_percent;
                $item->_price = $magento_item->getOriginalPrice() / (1 + $item->_vat / 100.0);
                $item->_quantity = $magento_item->getQtyToInvoice();
                $item->_type = 'product';
                $item->_unique = NULL;
                $item->_configItemId = 0;

                $container->add('customer_order_item', $item->getPublic(array('_fields')));

                $productOptions = $magento_item->getProductOptions();
                if (array_key_exists('options', $productOptions)) {
                    foreach ($productOptions['options'] as $option) {
                        $variation = new ConnectorCustomerOrderItemVariation();
                        $variation->_id = $magento_item->item_id . '-' . $option['option_id'];
                        $variation->_customerOrderitemId = $magento_item->item_id;
                        $variation->_productVariationId = $option['option_id'];
                        $variation->_productVariationValueId = $option['option_value'];                        
                        $variation->_productVariationName = $option['label'];
                        $variation->_productVariationValueName = $option['print_value'];
                        $variation->_surcharge = 0.00;

                        $container->add('customer_order_item_variation', $variation->getPublic(array('_fields')));
                    }
                }
            }

            // Shipment item
            $shippingGrossAmount = $order->getShippingAmount() + $order->getShippingTaxAmount();
            $shippingTaxRate = $orderTaxInfo[0]['percent'];

            $item = new ConnectorCustomerOrderItem();
            $item->_id = 0;
            $item->_customerOrderId = $order->entity_id;
            $item->_basketId = NULL;
            $item->_productId = 0;
            $item->_shippingClassId = NULL;
            $item->_name = $order->shipping_description;
            $item->_sku = '';
            $item->_vat = (double)$shippingTaxRate;
            $item->_price = $shippingGrossAmount / (1 + $shippingTaxRate / 100.0);
            $item->_quantity = 1;
            $item->_type = 'shipment';
            $item->_unique = NULL;
            $item->_configmagento_itemId = 0;
            $container->add('customer_order_item', $item->getPublic(array('_fields')));

            $shippingAddressEntry = $order->getShippingAddress();
            $shippingAddress = new ConnectorCustomerOrderShippingAddress();
            $shippingAddress->_id = $shippingAddressEntry->entity_id;
            $shippingAddress->_customerId = (int)(intval($shippingAddressEntry->customer_id));
            $shippingAddress->_salutation = NULL;
            $shippingAddress->_firstName = $shippingAddressEntry->firstname;
            $shippingAddress->_lastName = $shippingAddressEntry->lastname;
            $shippingAddress->_title = NULL;
            $shippingAddress->_company = $shippingAddressEntry->company;
            $shippingAddress->_deliveryInstruction = NULL;
            $shippingAddress->_street = $shippingAddressEntry->street;
            $shippingAddress->_extraAddressLine = NULL;
            $shippingAddress->_zipCode = $shippingAddressEntry->postcode;
            $shippingAddress->_city = $shippingAddressEntry->city;
            $shippingAddress->_state = $shippingAddressEntry->region;
            $shippingAddress->_countryIso = $shippingAddressEntry->country_id;
            $shippingAddress->_phone = $shippingAddressEntry->telephone;
            $shippingAddress->_mobile = NULL;
            $shippingAddress->_fax = NULL;
            $shippingAddress->_eMail = $shippingAddressEntry->email;
            $container->add('customer_order_shipping_address', $shippingAddress->getPublic(array('_fields')));

            $billingAddressEntry = $order->getBillingAddress();
            $billingAddress = new ConnectorCustomerOrderBillingAddress();
            $billingAddress->_id = (int)(intval($billingAddressEntry->entity_id));
            $billingAddress->_customerId = $billingAddressEntry->customer_id;
            $billingAddress->_salutation = NULL;
            $billingAddress->_firstName = $billingAddressEntry->firstname;
            $billingAddress->_lastName = $billingAddressEntry->lastname;
            $billingAddress->_title = NULL;
            $billingAddress->_company = $billingAddressEntry->company;
            $billingAddress->_deliveryInstruction = NULL;
            $billingAddress->_street = $billingAddressEntry->street;
            $billingAddress->_extraAddressLine = NULL;
            $billingAddress->_zipCode = $billingAddressEntry->postcode;
            $billingAddress->_city = $billingAddressEntry->city;
            $billingAddress->_state = $billingAddressEntry->region;
            $billingAddress->_countryIso = $billingAddressEntry->country_id;
            $billingAddress->_phone = $billingAddressEntry->telephone;
            $billingAddress->_mobile = NULL;
            $billingAddress->_fax = NULL;
            $billingAddress->_eMail = $billingAddressEntry->email;
            $container->add('customer_order_billing_address', $billingAddress->getPublic(array('_fields')));

            $result[] = $container->getPublic(array('items'), array('_fields'));
        }

        return $result;
    }
}
