<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Utilities\IdConcatenator;
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
        $limit = 50;
        $result = array();
        $n = 0;

        // Pull category images first
        if ($this->getUnmappedCategoryImageCount() > 0) {
            $categoryImages = $this->pullUnmappedCategoryImages($limit);
            $result = array_merge($result, $categoryImages);
            $n += count($categoryImages);

            if ($n >= $limit) {
                return $result;
            }
        }

        // If there is space left, add product images
        if ($this->getUnmappedProductImageCount() > 0) {
            $productImages = $this->pullUnmappedProductImages($limit - $n);
            $result = array_merge($result, $productImages);
            $n += count($productImages);

            if ($n >= $limit) {
                return $result;
            }
        }

        return $result;
    }

    private function pullUnmappedCategoryImages($limit)
    {
        $result = array();

        $rootCategoryId = \Mage::getStoreConfig('jtl_connector/general/root_category');
        $rootCategory = \Mage::getModel('catalog/category')
            ->load($rootCategoryId);

        $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect('image')
            ->addAttributeToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
            ->addAttributeToFilter('image',
                array('neq' => ''),
                'left'
            )
            ->joinTable('jtl_connector/image_link', 'foreign_key=entity_id', array(
                'endpoint_id' => 'endpoint_id'
            ), null, 'left')
            ->addFieldToFilter('endpoint_id',
                array('null' => true)
            )
            ->setPageSize($limit)
            ->setCurPage(1);

        foreach ($categoryCollection as $category) {
            $image = new ConnectorImage();
            $image->setId(new Identity(
                IdConcatenator::link(
                    'category',
                    $category->entity_id,
                    $category->entity_id
                )
            ));
            $image->setRelationType('category');
            $image->setForeignKey(new Identity($category->entity_id, $category->jtl_erp_id));
            $image->setRemoteUrl($category->getImageUrl());
            $image->setSort(0);

            $result[] = $image;
        }

        return $result;
    }

    private function pullUnmappedProductImages($limit)
    {
        $result = array();
        $productMapCache = array();

        $stores = Magento::getInstance()->getStoreMapping();
        reset($stores);
        $defaultStoreId = array_shift($stores);

        $_readConnection = \Mage::getSingleton('core/resource')
            ->getConnection('catalog_read');
        $imageBaseUrl = \Mage::getBaseUrl(\Mage_Core_Model_Store::URL_TYPE_MEDIA);

        $imageSql = '
              SELECT
                gv.`value_id` AS image_id,
                gv.`position` AS sort,
                g.`entity_id` AS foreign_key,
                g.`value` AS filename
              FROM ' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery_value') . ' gv
              INNER JOIN
                ' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery') . ' g
                ON g.`value_id` = gv.`value_id`
              LEFT JOIN
                ' . \Mage::getSingleton('core/resource')->getTableName('jtl_connector_link_image') . ' li
                ON li.`endpoint_id` = gv.`value_id` AND li.`foreign_key` = g.`entity_id` AND li.`relation_type` = \'product\'
              WHERE
                gv.disabled = 0 AND (gv.store_id = \' . $defaultStoreId . \' OR gv.store_id = 0) AND li.`jtl_erp_id` IS NULL
              LIMIT ' . (int)$limit;

        $images = $_readConnection->fetchAll($imageSql);

        foreach ($images as $magentoImage) {
            if (!array_key_exists((int)$magentoImage['foreign_key'], $productMapCache)) {
                $productHostId = \Mage::getModel('catalog/product')
                    ->load($magentoImage['foreign_key'])
                    ->getJtlErpId();
                $productMapCache[(int)$magentoImage['foreign_key']] = $productHostId;
            }
            else {
                $productHostId = $productMapCache[(int)$magentoImage['foreign_key']];
            }

            $image = new ConnectorImage();
            $image->setId(new Identity(
                IdConcatenator::link(
                    'product',
                    $magentoImage['foreign_key'],
                    $magentoImage['image_id']
                )
            ));
            $image->setRelationType('product');
            $image->setForeignKey(new Identity($magentoImage['foreign_key'], $productHostId));
            $image->setRemoteUrl($imageBaseUrl . 'catalog/product' . $magentoImage['filename']);
            $image->setSort((int)$magentoImage['sort']);

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
        $categoryImageCount = $this->getUnmappedCategoryImageCount();
        $productImageCount = $this->getUnmappedProductImageCount();

        return $categoryImageCount + $productImageCount;
    }

    private function getUnmappedCategoryImageCount()
    {
        try {
            $rootCategoryId = \Mage::getStoreConfig('jtl_connector/general/root_category');
            $rootCategory = \Mage::getModel('catalog/category')
                ->load($rootCategoryId);

            $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('image')
                ->addAttributeToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
                ->addAttributeToFilter('image',
                    array('neq' => ''),
                    'left'
                )
                ->joinTable('jtl_connector/image_link', 'foreign_key=entity_id', array(
                    'endpoint_id' => 'endpoint_id'
                ), null, 'left')
                ->addFieldToFilter('endpoint_id',
                    array('null' => true)
                );

            return $categoryCollection->count();
        }
        catch (\Exception $ex)
        {
            return 0;
        }
    }

    private function getUnmappedProductImageCount()
    {
        try {
            $stores = Magento::getInstance()->getStoreMapping();
            reset($stores);
            $defaultStoreId = array_shift($stores);

            $_readConnection = \Mage::getSingleton('core/resource')
                ->getConnection('catalog_read');

            $statisticSql = '
              SELECT
                COUNT(gv.`value_id`)
              FROM ' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery_value') . ' gv
              INNER JOIN
                ' . \Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_media_gallery') . ' g
                ON g.`value_id` = gv.`value_id`
              LEFT JOIN
                ' . \Mage::getSingleton('core/resource')->getTableName('jtl_connector_link_image') . ' li
                ON li.`endpoint_id` = gv.`value_id` AND li.`foreign_key` = g.`entity_id` AND li.`relation_type` = \'product\'
              WHERE
                gv.disabled = 0 AND (gv.store_id = ' . $defaultStoreId . ' OR gv.store_id = 0) AND li.`jtl_erp_id` IS NULL';

            return (int)$_readConnection->fetchOne($statisticSql);
        }
        catch (\Exception $ex) {
            return 0;
        }
    }
}
