<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Magento\Magento;
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
        
        $stores = Magento::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        Magento::getInstance()->setCurrentStore($defaultStoreId);
        $result = array();

        $products = \Mage::getResourceModel('catalog/product_collection');
        foreach ($products as $productItem) {
            // $productItem->load();

            $galleryImages = $productItem->getMediaGalleryImages();
            if (is_null($galleryImages))
            	continue;

            $defaultImagePath = $productItem->getImage();

            foreach ($galleryImages as $galleryImage) {
            	$image = new ConnectorImage();
                $image->setId(new Identity('product-' . $galleryImage->value_id));
                $image->setRelationType('product');
                $image->setForeignKey(new Identity($productItem->entity_id, $productItem->jtl_erp_id));
                $image->setFilename($galleryImage->url);
                $image->setSort($galleryImage->position_default);

                $result[] = $image;
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
            $image->setRelationType('category');
            $image->setForeignKey(new Identity($category_id, $model->jtl_erp_id));
            $image->setFilename($model->getImageUrl());
            $image->setSort(0);

            $result[] = $image;
        }

        return $result;
    }

    public function push(ConnectorImage $image)
    {
        $result = new ConnectorImage();

        $hostId = $image->getForeignKey()->getHost();

        switch ($image->getRelationType()) {
            case 'category':
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);

                Logger::write(sprintf('set image %s', $image->getFilename()));

                $mediaFilename = implode(DIRECTORY_SEPARATOR, array(
                    \Mage::getBaseDir('media'),
                    'catalog',
                    'category',
                    basename($image->getFilename())
                ));
                if (file_exists($mediaFilename)) {
                    $mediaFilename = implode(DIRECTORY_SEPARATOR, array(
                        \Mage::getBaseDir('media'),
                        'catalog',
                        'category',
                        str_replace('.', '-' . $hostId . '.', basename($image->getFilename()), 1)
                    ));
                }

                copy($image->getFilename(), $mediaFilename);
                
                Logger::write('category image: ' . $mediaFilename);

                $model = \Mage::getModel('catalog/category')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                // $model->addImageToMediaGallery($image->getFilename, array('thumbnail'), true, false);
                // $model->setThumbnail($image->getFilename());
                $model->setImage(basename($mediaFilename));
                $model->setJtlErpImageId($image->getId()->getHost());

                $model->save();

                $result->setId(new Identity(
                    sprintf('category-%u', $model->getId()),
                    $image->getId()->getHost()
                ));
                break;
            default:
                throw new \Exception(sprintf('Image type "%s" not implemented', $image->getRelationType()));
                break;
        }

        // Clean up
        if (file_exists($image->getFilename())) {
            unlink($image->getFilename());
        }

        $result->setRelationType($image->getRelationType());
        return $result;
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $stores = Magento::getInstance()->getStoreMapping();
            reset($stores);
            $defaultLocale = key($stores);
            $defaultStoreId = array_shift($stores);

            Magento::getInstance()->setCurrentStore($defaultStoreId);

            $result = 0;

            $products = \Mage::getResourceModel('catalog/product_collection');
            foreach ($products as $productItem) {
                // $productItem->load();

                $galleryImages = $productItem->getMediaGalleryImages();
                if (is_null($galleryImages))
                    continue;

                $result += count($galleryImages);
            }


            $rootCategoryId = \Mage::getStoreConfig('jtl_connector/general/root_category');
            $rootCategory = \Mage::getModel('catalog/category')
                ->load($rootCategoryId);
                
            $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
                ->addFieldToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
                ->load();

            foreach ($categoryCollection as $category) {
                if (false == $category->getImageUrl())
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
