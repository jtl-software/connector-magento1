<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use \jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use \jtl\Connector\ModelContainer\ImageContainer;
use \jtl\Connector\Model\Image as ConnectorImage;
use \jtl\Magento\Magento;

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
            $container = new ImageContainer();

            $galleryImages = $productItem->getMediaGalleryImages();
            if (is_null($galleryImages))
            	continue;

            $defaultImagePath = $productItem->getImage();

            foreach ($galleryImages as $galleryImage) {
            	$image = new ConnectorImage();
            	$image->_id = 'product-' . $galleryImage->value_id;
            	$image->_masterImageId = 0;
            	$image->_relationType = 'product';
            	$image->_foreignKey = $productItem->entity_id;
            	$image->_filename = $galleryImage->url;
            	$image->_isMainImage = ($galleryImage->file === $defaultImagePath);
            	$image->_sort = $galleryImage->position_default;

                $result[] = $image->getPublic();
//            	$container->add('image', $image->getPublic(array('_fields')));
            }

//            $result[] = $container->getPublic(array('items'), array('_fields'));
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

            $container = new ImageContainer();

            $image = new ConnectorImage();
            $image->_id = 'category-' . $category_id;
        	$image->_masterImageId = 0;
        	$image->_relationType = 'category';
        	$image->_foreignKey = $category_id;
        	$image->_filename = $model->getImageUrl();
        	$image->_isMainImage = true;
        	$image->_sort = 1;

            $result[] = $image->getPublic();
        	// $container->add('image', $image->getPublic(array('_fields')));
         //    $result[] = $container->getPublic(array('items'), array('_fields'));
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
