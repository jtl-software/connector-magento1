<?php

namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Linker\IdentityLinker;
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
                return ($order != null ? $order->getJtlErpId() : null);
        }
    }

    public function getEndpointId($hostId, $type)
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
                    ->loadByAttribute('jtl_erp_id', $hostId);
                return ($customer != null ? $customer->getId() : null);
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                $order = \Mage::getModel('sales/order')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                return ($order != null ? $order->getId() : null);
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

                $customer->setJtlErpId($hostId);
                $customer->save();
                break;
            case IdentityLinker::TYPE_CUSTOMER_ORDER:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $order = \Mage::getModel('sales/order')
                    ->load($endpointId);

                $order->setJtlErpId($hostId);
                $order->save();
                break;
        }
    }

    public function delete($endpointId = null, $hostId = null, $type)
    {

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

        return true;
    }
}