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
        }
    }

    public function getEndpointId($hostId, $type)
    {

    }

    public function save($endpointId, $hostId, $type)
    {
        switch ($type)
        {
            // Category
            case IdentityLinker::TYPE_CATEGORY:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $category = \Mage::getModel('catalog/category')
                    ->load($endpointId);

                $category->setJtlErpId($hostId);
                $category->save();
                break;
            // Product
            case IdentityLinker::TYPE_PRODUCT:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $product = \Mage::getModel('catalog/product')
                    ->load($endpointId);

                $product->setJtlErpId($hostId);
                $product->save();
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
            $product->getResource()->saveAttribute($product, 'jtl_erp_id');
        }

        // Clear Category IDs
        $categories = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect(array('name','jtl_erp_id'))
            ->addAttributeToFilter('jtl_erp_id', array('gt' => '0'));

        foreach ($categories as $category) {
            $category->setJtlErpId(0);
            $category->getResource()->saveAttribute($category, 'jtl_erp_id');
        }

        return true;
    }
}