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

        Magento::getInstance()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $result = array();

        $products = \Mage::getResourceModel('catalog/product_collection');
        $this->addImagesToProductCollection($products);
        foreach ($products as $productItem) {
            $galleryImages = $productItem->getMediaGalleryImages();
            if (is_null($galleryImages))
            	continue;

            $defaultImagePath = $productItem->getImage();

            foreach ($galleryImages as $galleryImage) {
            	$image = new ConnectorImage();
                $image->setId(new Identity(sprintf('product-%u-%u', $galleryImage->value_id, $galleryImage->position_default)));
                $image->setRelationType('product');
                $image->setForeignKey(new Identity($productItem->entity_id, $productItem->jtl_erp_id));
                $image->setRemoteUrl($galleryImage->url);
                $image->setSort((int)$galleryImage->position_default);

                $result[] = $image;
            }
        }


        $rootCategoryId = \Mage::app()->getStore()->getRootCategoryId();
        $rootCategory = \Mage::getModel('catalog/category')
            ->load($rootCategoryId);

        $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
            ->addFieldToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
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
            $image->setRemoteUrl($model->getImageUrl());
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
            case ImageRelationType::TYPE_CATEGORY:
                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                Logger::write(sprintf('set category image %s', $image->getFilename()));

                $mediaFilename = implode(DIRECTORY_SEPARATOR, array(
                    \Mage::getBaseDir('media'),
                    'catalog',
                    'category',
                    basename($image->getFilename())
                ));
                if (file_exists($mediaFilename)) {
                    $count = 1;
                    $mediaFilename = implode(DIRECTORY_SEPARATOR, array(
                        \Mage::getBaseDir('media'),
                        'catalog',
                        'category',
                        str_replace('.', '-' . $hostId . '.', basename($image->getFilename()), $count)
                    ));
                }

                copy($image->getFilename(), $mediaFilename);

                Logger::write('category image: ' . $mediaFilename);

                $model = \Mage::getModel('catalog/category')
                    ->loadByAttribute('jtl_erp_id', $hostId);

                if ($model !== false && ($model->getId() > 0)) {
                    $model->setImage(basename($mediaFilename));
                    $model->setThumbnail(basename($mediaFilename));
                    $model->setJtlErpImageId($image->getId()->getHost());

                    $model->save();

                    $result->setId(new Identity(
                        sprintf('category-%u', $model->getId()),
                        $image->getId()->getHost()
                    ));
                }
                
                break;

            case ImageRelationType::TYPE_PRODUCT:
                $model = \Mage::getModel('catalog/product')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                if ($model === false || ($model->getId() == 0)) {
                    // Send "seems legit" to the client
                    break;
                }

                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                Logger::write(sprintf('set product image %s', $image->getFilename()));

                $mediaFilename = $this->importProductImage($image->getFilename());
                Logger::write('product image: ' . $mediaFilename, Logger::DEBUG);

                $fileinfo = pathinfo($mediaFilename);
                switch ($fileinfo['extension']) {
                    case 'png':
                        $mimeType = 'image/png';
                        break;
                    case 'jpg':
                        $mimeType = 'image/jpeg';
                        break;
                    case 'gif':
                        $mimeType = 'image/gif';
                        break;
                }

                $mediaApi = \Mage::getModel('catalog/product_attribute_media_api');
                $types = ($image->getSort() === 1) ? array('image', 'small_image', 'thumbnail') : array();
                $newImage = array(
                    'file' => array(
                        'content' => base64_encode($mediaFilename),
                        'mime' => $mimeType,
                        'name' => basename($mediaFilename)
                    ),
                    'label' => basename($image->getFilename()),
                    'position' => $image->getSort(),
                    'types' => $types,
                    'exclude' => 0
                );

                $currentItems = $mediaApi->items($model->getId(), \Mage_Core_Model_App::ADMIN_STORE_ID);
                foreach ($currentItems as $currentItem) {
                    if ((int)$currentItem['position'] == (int)$image->getSort()) {
                        try {
                            $mediaApi->remove($model->getId(), $currentItem['file']);
                        }
                        catch (\Exception $ex) {
                        }
                    }
                }

                try {
                    $mediaApi->update($model->getId(), $mediaFilename, $newImage, \Mage_Core_Model_App::ADMIN_STORE_ID, 'id');
                }
                catch (\Mage_Api_Exception $ex) {
                    Logger::write('error while updating image: ' . $ex->getMessage(), Logger::DEBUG);
                    Logger::write('re-creating image....', Logger::DEBUG);
                    $mediaApi->create($model->getId(), $newImage, \Mage_Core_Model_App::ADMIN_STORE_ID, 'id');
                }

                $result->setId(new Identity(
                    sprintf('product-%u-%u', $model->getId(), $newImage['position']),
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

    private function importProductImage($tempFilename)
    {
        if (!file_exists($tempFilename) || !is_readable($tempFilename))
            return null;

        $importPath = implode(DIRECTORY_SEPARATOR, array(
            \Mage::getBaseDir('media'),
            'import'
        ));
        if (!is_dir($importPath)) {
            mkdir($importPath);
        }

        $filename = basename($tempFilename);
        $extension = substr(strrchr($filename, '.'), 1);
        $newFilename = sprintf('%s.%s', md5($tempFilename . strval(time())), $extension);
        $newFilepath = implode(DIRECTORY_SEPARATOR, array(
            \Mage::getBaseDir('media'),
            'import',
            $newFilename
        ));

        copy($tempFilename, $newFilepath);

        return $newFilepath;
    }

    public function delete(ConnectorImage $image)
    {
        $hostId = $image->getForeignKey()->getHost();

        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_CATEGORY:
                $model = \Mage::getModel('catalog/category')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                if ($model === false || ($model->getId() == 0))
                    break;

                $model->setImage(null);
                $model->setThumbnail(null);
                $model->setJtlErpImageId(0);
                $model->save();

                $result->setId(new Identity(
                    sprintf('category-%u', $model->getId()),
                    $image->getId()->getHost()
                ));

                break;
            case ImageRelationType::TYPE_PRODUCT:
                $model = \Mage::getModel('catalog/product')
                    ->loadByAttribute('jtl_erp_id', $hostId);
                if ($model === false || ($model->getId() == 0)) {
                    // Send "seems legit" to the client
                    break;
                }

                \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
                $mediaApi = \Mage::getModel('catalog/product_attribute_media_api');

                $currentItems = $mediaApi->items($model->getId(), \Mage_Core_Model_App::ADMIN_STORE_ID);
                foreach ($currentItems as $currentItem) {
                    if ((int)$currentItem['position'] == (int)$image->getSort()) {
                        try {
                            $mediaApi->remove($model->getId(), $currentItem['file']);
                        }
                        catch (\Exception $ex) {
                            die(var_dump($ex->getMessage()));
                        }
                    }
                }

                break;
        }

        return $image;
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
            $this->addImagesToProductCollection($products);

            foreach ($products as $productItem) {
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

    private function addImagesToProductCollection(\Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection $_productCollection)
    {
        $_mediaGalleryAttributeId = \Mage::getSingleton('eav/config')
            ->getAttribute('catalog_product', 'media_gallery')
            ->getAttributeId();
        $_read = \Mage::getSingleton('core/resource')
            ->getConnection('catalog_read');

        if ($_productCollection->getSize() == 0)
            return;

        $_mediaGalleryData = $_read->fetchAll('
            SELECT
                main.entity_id, `main`.`value_id`, `main`.`value` AS `file`,
                `value`.`label`, `value`.`position`, `value`.`disabled`, `default_value`.`label` AS `label_default`,
                `default_value`.`position` AS `position_default`,
                `default_value`.`disabled` AS `disabled_default`
            FROM `' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery') . '` AS `main`
                LEFT JOIN `' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery_value') . '` AS `value`
                    ON main.value_id=value.value_id AND value.store_id=' . \Mage::app()->getStore()->getId() . '
                LEFT JOIN `' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery_value') . '` AS `default_value`
                    ON main.value_id=default_value.value_id AND default_value.store_id=0
            WHERE (
                main.attribute_id = ' . $_read->quote($_mediaGalleryAttributeId) . ') 
                AND (main.entity_id IN (' . $_read->quote($_productCollection->getAllIds()) . '))
            ORDER BY IF(value.position IS NULL, default_value.position, value.position) ASC    
        ');
    
    
        $_mediaGalleryByProductId = array();
        foreach ($_mediaGalleryData as $_galleryImage) {
            $k = $_galleryImage['entity_id'];
            unset($_galleryImage['entity_id']);
            if (!isset($_mediaGalleryByProductId[$k])) {
                $_mediaGalleryByProductId[$k] = array();
            }
            $_mediaGalleryByProductId[$k][] = $_galleryImage;
        }
        unset($_mediaGalleryData);

        foreach ($_productCollection as &$_product) {
            $_productId = $_product->getData('entity_id');
            if (isset($_mediaGalleryByProductId[$_productId])) {
                $_product->setData('media_gallery', array('images' => $_mediaGalleryByProductId[$_productId]));
            }
        }
        unset($_mediaGalleryByProductId);

        return $_productCollection;
    }
}
