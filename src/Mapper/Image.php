<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Image as ConnectorImage;

/**
 * Description of Image
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Image
{
    public function pull()
    {
        Magento::getInstance();
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        Magento::getInstance()->setCurrentStore($defaultStoreId);
        $result = array();

        $products = \Mage::getResourceModel('catalog/product_collection');
        foreach ($products as $productItem) {
            $productItem->load();

            $galleryImages = $productItem->getMediaGalleryImages();
            if (is_null($galleryImages))
            	continue;

            $defaultImagePath = $productItem->getImage();

            foreach ($galleryImages as $galleryImage) {
            	$image = new ConnectorImage();
                $image->setId(new Identity('product-' . $galleryImage->value_id));
                $image->setMasterImageId(0);
                $image->setRelationType('product');
                $image->setForeignKey($productItem->entity_id);
                $image->setFilename($galleryImage->url);
                $image->setIsMainImage($galleryImage->file === $defaultImagePath);
                $image->setSort($galleryImage->position_default);

                $result[] = $image->getPublic();
            }
        }


        $rootCategoryId = \Mage::app()->getStore()->getRootCategoryId();
        $categoryCollection = \Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToSelect('all_children')
            ->addAttributeToFilter('parent_id', $rootCategoryId)
            ->load();

        $categoryIds = array();
        foreach ($categoryCollection as $category) {
            $categoryIds  = array_merge_recursive($categoryIds, explode(',', $category->getAllChildren()));
        }

        foreach ($categoryIds as $category_id) {
            $model = \Mage::getModel('catalog/category')
                ->load($category_id);

            if (false == $model->getImageUrl())
            	continue;

            $image = new ConnectorImage();
            $image->setId(new Identity('category-' . $category_id));
            $image->setMasterImageId(0);
            $image->setRelationType('category');
            $image->setForeignKey($category_id);
            $image->setFilename($model->getImageUrl());
            $image->setIsMainImage(true);
            $image->setSort(1);

            $result[] = $image->getPublic();
        }

        return $result;
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $stores = MapperDatabase::getInstance()->getStoreMapping();
            reset($stores);
            $defaultLocale = key($stores);
            $defaultStoreId = array_shift($stores);

            Magento::getInstance()->setCurrentStore($defaultStoreId);

            $result = 0;

            $products = \Mage::getResourceModel('catalog/product_collection');
            foreach ($products as $productItem) {
                $productItem->load();

                $galleryImages = $productItem->getMediaGalleryImages();
                if (is_null($galleryImages))
                    continue;

                $result += count($galleryImages);
            }


            $rootCategoryId = \Mage::app()->getStore()->getRootCategoryId();
            $categoryCollection = \Mage::getModel('catalog/category')
                ->getCollection()
                ->addAttributeToSelect('all_children')
                ->addAttributeToFilter('parent_id', $rootCategoryId)
                ->load();

            $categoryIds = array();
            foreach ($categoryCollection as $category) {
                $categoryIds  = array_merge_recursive($categoryIds, explode(',', $category->getAllChildren()));
            }

            foreach ($categoryIds as $category_id) {
                $model = \Mage::getModel('catalog/category')
                    ->load($category_id);

                if (false == $model->getImageUrl())
                    continue;

                $result++;
            }

            return $result;
        }
        catch (Exception $e) {
            return 0;
        }
    }
}
