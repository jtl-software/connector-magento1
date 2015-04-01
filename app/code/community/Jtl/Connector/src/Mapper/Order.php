<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use jtl\Connector\Model\CustomerOrder as ConnectorCustomerOrder;
use jtl\Connector\Model\CustomerOrderBillingAddress as ConnectorCustomerOrderBillingAddress;
use jtl\Connector\Model\CustomerOrderItem as ConnectorCustomerOrderItem;
use jtl\Connector\Model\CustomerOrderItemVariation as ConnectorCustomerOrderItemVariation;
use jtl\Connector\Model\CustomerOrderShippingAddress as ConnectorCustomerOrderShippingAddress;
use jtl\Connector\Model\StatusChange as ConnectorStatusChange;
use jtl\Connector\Model\Identity;

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
            $orderCollection = \Mage::getModel('sales/order')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('jtl_erp_id',
                    array(
                        array('eq' => 0),
                        array('null' => true)
                    ),
                    'left'
                );

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

        $orderCollection = \Mage::getModel('sales/order')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('jtl_erp_id',
                array(
                    array('eq' => 0),
                    array('null' => true)
                ),
                'left'
            );

        if (!is_null($filter)) {
            $orderCollection
                ->getSelect()
                ->limit($filter->getLimit());
        }

        $orders = $orderCollection->load();

        $result = array();
        foreach ($orders as $order) {
            $orderTaxInfo = $order->getFullTaxInfo();

            $created_at = new \DateTime($order->created_at);

            $customerOrder = new ConnectorCustomerOrder();
            $customerOrder->setId(new Identity($order->entity_id));
            $customerOrder->setCustomerId(new Identity(intval($order->customer_id)));
            // $customerOrder->setShippingAddressId(new Identity($order->shipping_address_id));
            // $customerOrder->setBillingAddressId(new Identity($order->billing_address_id));
            $customerOrder->setLanguageIso(LocaleMapper::localeToLanguageIso(array_search($order->store_id, $stores)));
            $customerOrder->setCurrencyIso($order->order_currency_code);
            // $customerOrder->setCredit(0.00);
            $customerOrder->setTotalSum((double)$order->grand_total);
            $customerOrder->setShippingMethodName($order->shipping_description);
            $customerOrder->setPaymentModuleCode(''); // TODO
            $customerOrder->setOrderNumber($order->increment_id);
            $customerOrder->setShippingInfo('');
            // $customerOrder->setShippingDate(NULL);
            // $customerOrder->setPaymentDate(NULL);
            $customerOrder->setNote(''); // TODO
            // $customerOrder->setLogistic('');
            // $customerOrder->setIp($order->remote_ip);
            // $customerOrder->setIsFetched(false);
            $customerOrder->setStatus(NULL);
            $customerOrder->setCreationDate($created_at);

            $payment = $order->getPayment();
            $code = $payment->getMethodInstance()->getCode();

            // if (array_key_exists($code, $this->paymentMethods))
            //     $customerOrder->setPaymentModuleType($this->paymentMethods[$code]);
            // else
            //     $customerOrder->setPaymentModuleType('pm_bank_transfer');

            foreach ($order->getAllItems() as $magento_item) {
                $item = new ConnectorCustomerOrderItem();
                $item->setId(new Identity($magento_item->item_id));
                $item->setCustomerOrderId(new Identity($order->entity_id));
                $item->setProductId(new Identity($magento_item->product_id));
                // $item->setShippingClassId(NULL);
                $item->setName($magento_item->name);
                $item->setSku($magento_item->sku);
                $item->setVat((double)$magento_item->tax_percent);
                $item->setPrice($magento_item->getOriginalPrice() / (1 + $item->getVat() / 100.0));
                $item->setQuantity($magento_item->getQtyToInvoice());
                $item->setType('product');
                $item->setUnique(NULL);
                // $item->setConfigItemId(NULL);

                $customerOrder->addItem($item);

                $productOptions = $magento_item->getProductOptions();
                if (array_key_exists('options', $productOptions)) {
                    foreach ($productOptions['options'] as $option) {
                        $variation = new ConnectorCustomerOrderItemVariation();
                        $variation->setId(new Identity($magento_item->item_id . '-' . $option['option_id']));
                        $variation->setCustomerOrderitemId(new Identity($magento_item->item_id));
                        $variation->setProductVariationId(new Identity($option['option_id']));
                        $variation->setProductVariationValueId(new Identity($option['option_value']));
                        $variation->setProductVariationName($option['label']);
                        $variation->setProductVariationValueName($option['print_value']);
                        $variation->setSurcharge(0.00);

                        $item->addVariation($variation);
                    }
                }

                if ($magento_item->product_type == 'configurable') {
                    // Varcombi
                    // 
                    try {
                    
                    // Since we can assume that the frontend code is optimized, we should employ
                    // these APIs to load configurable product's information
                    $product = \Mage::getModel('catalog/product')
                        ->load($magento_item->product_id);

                    if ($product->product_id > 0) {
                        // Associated product still exists

                        $block = \Mage::app()->getLayout()->createBlock('catalog/product_view_type_configurable');
                        $block->setProduct($product);
                        $config = json_decode($block->getJsonConfig(), true);

                        $attributeValues = $productOptions['info_buyRequest']['super_attribute'];
                        foreach ($config['attributes'] as $attribute_id => $attribute) {
                            foreach ($attribute['options'] as $option) {
                                if ($attributeValues[$attribute_id] == $option['id']) {
                                    $variation = new ConnectorCustomerOrderItemVariation();
                                    $variation->setId(new Identity($magento_item->item_id . '-' . $option['id']));
                                    $variation->setCustomerOrderItemId(new Identity($magento_item->item_id));
                                    $variation->setProductVariationId(new Identity($attribute_id));
                                    $variation->setProductVariationValueId(new Identity($option['id']));
                                    $variation->setProductVariationName($attribute['label']);
                                    $variation->setProductVariationValueName($option['label']);
                                    $variation->setSurcharge((double)($option['price'] / (1 + $item->getVat() / 100.0)));

                                    $item->addVariation($variation);
                                }
                            }
                        }
                    }
                    else {
                        // Associated product has been removed
                    }

                    }
                    catch (\Exception $e)
                    {
                        die(var_dump($e));
                    }
                }
            }

            // Shipment item
            $shippingGrossAmount = $order->getShippingAmount() + $order->getShippingTaxAmount();
            $shippingTaxRate = $orderTaxInfo[0]['percent'];

            $item = new ConnectorCustomerOrderItem();
            $item->setId(new Identity($order->entity_id . '-shipment'));
            $item->setCustomerOrderId(new Identity($order->entity_id));
            // $item->setProductId(NULL);
            // $item->setShippingClassId(NULL);
            $item->setName($order->shipping_description);
            $item->setSku('');
            $item->setVat((double)$shippingTaxRate);
            $item->setPrice($shippingGrossAmount / (1 + $shippingTaxRate / 100.0));
            $item->setQuantity(1.0);
            $item->setType('shipment');
            $item->setUnique(NULL);
            // $item->setConfigItemId(NULL);

            $customerOrder->addItem($item);

            $shippingAddressEntry = $order->getShippingAddress();
            $shippingAddress = new ConnectorCustomerOrderShippingAddress();
            $shippingAddress->setId(new Identity($shippingAddressEntry->entity_id));
            $shippingAddress->setCustomerId(new Identity(intval($shippingAddressEntry->customer_id)));
            $shippingAddress->setSalutation(NULL);
            $shippingAddress->setFirstName($shippingAddressEntry->firstname);
            $shippingAddress->setLastName($shippingAddressEntry->lastname);
            $shippingAddress->setTitle(NULL);
            $shippingAddress->setCompany($shippingAddressEntry->company);
            $shippingAddress->setDeliveryInstruction(NULL);
            $shippingAddress->setStreet($shippingAddressEntry->street);
            $shippingAddress->setExtraAddressLine(NULL);
            $shippingAddress->setZipCode($shippingAddressEntry->postcode);
            $shippingAddress->setCity($shippingAddressEntry->city);
            $shippingAddress->setState($shippingAddressEntry->region);
            $shippingAddress->setCountryIso($shippingAddressEntry->country_id);
            $shippingAddress->setPhone($shippingAddressEntry->telephone);
            $shippingAddress->setMobile(NULL);
            $shippingAddress->setFax(NULL);
            $shippingAddress->setEMail($shippingAddressEntry->email);

            $customerOrder->setShippingAddress($shippingAddress);

            $billingAddressEntry = $order->getBillingAddress();
            $billingAddress = new ConnectorCustomerOrderBillingAddress();
            $billingAddress->setId(new Identity($billingAddressEntry->entity_id));
            $billingAddress->setCustomerId(new Identity(intval($billingAddressEntry->customer_id)));
            $billingAddress->setSalutation(NULL);
            $billingAddress->setFirstName($billingAddressEntry->firstname);
            $billingAddress->setLastName($billingAddressEntry->lastname);
            $billingAddress->setTitle(NULL);
            $billingAddress->setCompany($billingAddressEntry->company);
            $billingAddress->setDeliveryInstruction(NULL);
            $billingAddress->setStreet($billingAddressEntry->street);
            $billingAddress->setExtraAddressLine(NULL);
            $billingAddress->setZipCode($billingAddressEntry->postcode);
            $billingAddress->setCity($billingAddressEntry->city);
            $billingAddress->setState($billingAddressEntry->region);
            $billingAddress->setCountryIso($billingAddressEntry->country_id);
            $billingAddress->setPhone($billingAddressEntry->telephone);
            $billingAddress->setMobile(NULL);
            $billingAddress->setFax(NULL);
            $billingAddress->setEMail($billingAddressEntry->email);

            $customerOrder->setBillingAddress($billingAddress);

            $result[] = $customerOrder;
        }

        return $result;
    }

    public function processStatusUpdate(ConnectorStatusChange $statusChange)
    {
        $order = \Mage::getModel('sales/order')
            ->loadByIncrementId($statusChange->getCustomerOrderId());

        if ($order == null)
            return false;

        switch ($statusChange->getOrderStatus()) {
            case ConnectorCustomerOrder::STATUS_PROCESSING:
                $order->setState(\Mage_Sales_Model_Order::STATE_PROCESSING, true);
                break;
            case ConnectorCustomerOrder::STATUS_COMPLETED:
                $order->setState(\Mage_Sales_Model_Order::STATE_COMPLETE, true);
                break;
            case ConnectorCustomerOrder::STATUS_CANCELLED:
                $order->setState(\Mage_Sales_Model_Order::STATE_CANCELED, true);
                break;
        }

        $order->save();
        return true;
    }
}
