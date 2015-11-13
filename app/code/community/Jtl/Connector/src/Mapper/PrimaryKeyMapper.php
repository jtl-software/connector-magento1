<?php

namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Linker\IdentityLinker;
use jtl\Connector\Magento\Utilities\IdConcatenator;
use jtl\Connector\Mapper\IPrimaryKeyMapper;

class PrimaryKeyMapper implements IPrimaryKeyMapper
{
    public function getHostId($endpointId, $type)
    {
        switch ($type)
        {
            // Category
            case IdentityLinker::TYPE_CATEGORY:
                $category = \Mage::getModel('catalog/category')
                    ->load($endpointId);
                return ($category != null ? $category->getJtlErpId() : null);
            // Product
            case IdentityLinker::TYPE_PRODUCT:
                $product = \Mage::getModel('catalog/product')
                    ->load($endpointId);
                return ($product != null ? $product->getJtlErpId() : null);
            case IdentityLinker::TYPE_CUSTOMER:
                $customer = \Mage::getModel('customer/customer')
                    ->load($endpointId);
                return ($customer != null ? $customer->getJtlErpId() : null);
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                $order = \Mage::getModel('sales/order')
                    ->load($endpointId);
                return ($order != null ? $order->jtl_erp_id : null);
            case IdentityLinker::TYPE_PAYMENT:
                $payment = \Mage::getModel('sales/order_payment')
                    ->load($endpointId);
                return ($payment != null ? $payment->entity_id : null);
            case IdentityLinker::TYPE_SPECIFIC:
                $link = \Mage::getModel('jtl_connector/specific_link')
                    ->load($endpointId, 'attribute_code');
                return ($link != null ? $link->jtl_erp_id : null);
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                $link = \Mage::getModel('jtl_connector/specificvalue_link')
                    ->load($endpointId, 'option_id');
                return ($link != null ? $link->jtl_erp_id : null);
            case IdentityLinker::TYPE_IMAGE:
                $endpointIds = IdConcatenator::unlink($endpointId);

                $link = \Mage::getModel('jtl_connector/image_link')
                    ->getCollection()
                    ->addFieldToFilter('relation_type', $endpointIds[0])
                    ->addFieldToFilter('image_id', $endpointId)
                    ->getFirstItem();
                return ($link != null ? $link->jtl_erp_id : null);
        }
    }

    public function getEndpointId($hostId, $type, $relationType = NULL)
    {
        switch ($type)
        {
            // Category
            case IdentityLinker::TYPE_CATEGORY:
                $category = \Mage::getModel('catalog/category')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                return ($category != null ? $category->getId() : null);
            // Product
            case IdentityLinker::TYPE_PRODUCT:
                $product = \Mage::getModel('catalog/product')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                return ($product != null ? $product->getId() : null);
            case IdentityLinker::TYPE_CUSTOMER:
                $customer = \Mage::getModel('customer/customer')
                    ->load($hostId, 'jtl_erp_id');
                return ($customer != null ? $customer->getId() : null);
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                $order = \Mage::getModel('sales/order')
                    ->load($hostId, 'jtl_erp_id');
                return ($order != null ? $order->increment_id : null);
            case IdentityLinker::TYPE_PAYMENT:
                $payment = \Mage::getModel('sales/order_payment')
                    ->load($hostId, 'jtl_erp_id');
                return ($payment != null ? $payment->entity_id : null);
            case IdentityLinker::TYPE_SPECIFIC:
                $link = \Mage::getModel('jtl_connector/specific_link')
                    ->load($hostId, 'jtl_erp_id');
                return ($link != null ? $link->attribute_code : null);
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                $link = \Mage::getModel('jtl_connector/specificvalue_link')
                    ->load($hostId, 'jtl_erp_id');
                return ($link != null ? $link->option_id : null);
            case IdentityLinker::TYPE_IMAGE:
                $link = \Mage::getModel('jtl_connector/image_link')
                    ->getCollection()
                    ->addFieldToFilter('jtl_erp_id', $hostId)
                    ->getFirstItem();
                return ($link != null ? $link->image_id : null);
        }
    }

    public function save($endpointId, $hostId, $type)
    {
        switch ($type)
        {
            case IdentityLinker::TYPE_CATEGORY:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $category = \Mage::getModel('catalog/category')
                    ->load($endpointId);

                $category->setJtlErpId($hostId);
                $category->save();
                break;
            case IdentityLinker::TYPE_PRODUCT:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $product = \Mage::getModel('catalog/product')
                    ->load($endpointId);

                $product->setJtlErpId($hostId);
                $product->save();
                break;
            case IdentityLinker::TYPE_CUSTOMER:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $customer = \Mage::getModel('customer/customer')
                    ->load($endpointId);

                $customer->jtl_erp_id = $hostId;
                $customer->save();
                break;
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $order = \Mage::getModel('sales/order')
                    ->loadByIncrementId($endpointId);

                $order->jtl_erp_id = $hostId;
                $order->save();
                break;
            case IdentityLinker::TYPE_IMAGE:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);

                $endpointIds = IdConcatenator::unlink($endpointId);
                $link = \Mage::getModel('jtl_connector/image_link');
                $link->relation_type = $endpointIds[0];
                $link->image_id = $endpointId;
                $link->endpoint_id = $endpointIds[2];
                $link->foreign_key = $endpointIds[1];
                $link->jtl_erp_id = $hostId;

                $link->save();

                break;
            case IdentityLinker::TYPE_PAYMENT:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $order = \Mage::getModel('sales/order_payment')
                    ->loadByIncrementId($endpointId);

                $order->jtl_erp_id = $hostId;
                $order->save();
                break;
            case IdentityLinker::TYPE_SPECIFIC:
                $link = \Mage::getModel('jtl_connector/specific_link');
                $link->attribute_code = $endpointId;
                $link->jtl_erp_id = $hostId;
                $link->save();
                break;
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                $link = \Mage::getModel('jtl_connector/specificvalue_link');
                $link->option_id = $endpointId;
                $link->jtl_erp_id = $hostId;
                $link->save();
                break;
        }
    }

    public function delete($endpointId = null, $hostId = null, $type)
    {
        switch ($type) {
            case IdentityLinker::TYPE_IMAGE:
                $endpointIds = IdConcatenator::unlink($endpointId);

                $collection = \Mage::getResourceModel('jtl_connector/image_link_collection')
                    ->addFieldToFilter('jtl_erp_id', $hostId)
                    ->addFieldToFilter('relation_type', $endpointIds[0])
                    ->addFieldToFilter('foreign_key', $endpointIds[1])
                    ->addFieldToFilter('endpoint_id', $endpointIds[2]);
                break;
            case IdentityLinker::TYPE_SPECIFIC:
                $collection = \Mage::getResourceModel('jtl_connector/specific_link_collection')
                    ->addFieldToFilter('jtl_erp_id', $hostId);

                if (!is_null($endpointId)) {
                    $collection->addFieldToFilter('attribute_code', $endpointId);
                }
                break;
            case IdentityLinker::TYPE_SPECIFIC_VALUE:
                $collection = \Mage::getResourceModel('jtl_connector/specificvalue_link_collection')
                    ->addFieldToFilter('jtl_erp_id', $hostId);

                if (!is_null($endpointId)) {
                    $collection->addFieldToFilter('option_id', $endpointId);
                }
                break;
            default:
                return;
        }

        error_log(sprintf(
            'Deleting for hostId == %s, endpointId == %s',
            $hostId,
            $endpointId
        ));
        if ($collection instanceof \Varien_Data_Collection_Db) {
            foreach ($collection as $item) {
                error_log('Delete entry: ' . json_encode($item->getData()));
                $item->delete();
            }
        }
    }

    public function clear()
    {
        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        
        // Clear Product IDs
        $products = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect(array('name','jtl_erp_id'))
            ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));

        foreach ($products as $product) {
            $product->setJtlErpId(0);
            $product->save();
        }

        // Clear Category IDs
        $categories = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect(array('name','jtl_erp_id'))
            ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));

        foreach ($categories as $category) {
            $category->setJtlErpId(0);
            $category->setJtlErpImageId(0);
            $category->save();
        }

        // Clear Customer IDs
        $customers = \Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect(array('name','jtl_erp_id'))
            ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));

        foreach ($customers as $customer) {
            $customer->setJtlErpId(0);
            $customer->save();
        }

        // Clear Order IDs
        // $orders = \Mage::getModel('sales/order')
        //     ->getCollection()
        //     ->addAttributeToSelect(array('name', 'jtl_erp_id'))
        //     ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));
        $orders = \Mage::getModel('sales/order')
            ->getCollection();

        foreach ($orders as $order) {
            $order->setJtlErpId(0);
            $order->save();
        }

        // Clear Payment IDs
        $payments = \Mage::getModel('sales/order_payment')
            ->getCollection()
            ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));

        foreach ($payments as $payment) {
            $payment->setJtlErpId(0);
            $payment->save();
        }

        // Clear Image IDs
        \Mage::getResourceModel('jtl_connector/image_link')
            ->truncate();

        // Clear Specific IDs
        \Mage::getResourceModel('jtl_connector/specific_link')
            ->truncate();

        // Clear Specific value IDs
        \Mage::getResourceModel('jtl_connector/specificvalue_link')
            ->truncate();

        return true;
    }

    public function gc()
    {
        // Pseudo implementation for the gc() function since it is impossible to create links without a referenced entity
        return true;
    }
}