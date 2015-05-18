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
        foreach ($products as $productItem) {
            $productModel = \Mage::getModel('catalog/product')
                ->load($productItem->entity_id);

            $galleryImages = $productModel->getMediaGalleryImages();
            if (is_null($galleryImages))
            	continue;

            $defaultImagePath = $productItem->getImage();

            foreach ($galleryImages as $galleryImage) {
            	$image = new ConnectorImage();
                $image->setId(new Identity('product-' . $galleryImage->value_id));
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
                Logger::write('product image: ' . $mediaFilename);

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

                $mediaApi->create($model->getId(), $newImage, null, 'id');
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
        $result = new ConnectorImage();

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
                $productModel = \Mage::getModel('catalog/product')
                    ->load($productItem->entity_id);

                $galleryImages = $productModel->getMediaGalleryImages();
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
