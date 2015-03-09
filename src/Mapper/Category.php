<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use jtl\Connector\Magento\Utilities\ArrayTools;
use jtl\Connector\Model\Category as ConnectorCategory;
use jtl\Connector\Model\CategoryI18n as ConnectorCategoryI18n;
use jtl\Connector\Model\Identity;
use jtl\Connector\ModelContainer\CategoryContainer;
use jtl\Connector\Result\Transaction;

/**
 * Description of Category
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Category
{
    private $stores;
    private $defaultLocale;
    private $defaultStoreId;
    private $rootCategoryId;

    public function __construct()
    {
        Magento::getInstance();

        $this->rootCategoryId = \Mage::getStoreConfig('jtl_connector/general/root_category');

        $this->stores = MapperDatabase::getInstance()->getStoreMapping();
        $this->defaultLocale = key($this->stores);
        $this->defaultStoreId = current($this->stores);
    }

    private function insert(ConnectorCategory $category)
    {
        Logger::write('insert category', Logger::ERROR, 'general');
        $result = new ConnectorCategory();

        $identity = $category->getId();
        $categoryId = $identity->getEndpoint();

        if ($identity->getHost() == 0)
            return;

        $model = \Mage::getModel('catalog/category');

        // Set parent category
        Logger::write('parent host : ' . $category->getParentCategoryId()->getHost(), Logger::ERROR, 'general');
        if ((int)$category->getParentCategoryId()->getHost() == 0) {
            $parentCategory = \Mage::getModel('catalog/category')
                ->load(\Mage::getStoreConfig('jtl_connector/general/root_category'));
        }
        else {
            $parentCategoryHostId = $category->getParentCategoryId()->getHost();
            $parentCategory = \Mage::getModel('catalog/category')
                ->loadByAttribute('jtl_erp_id', $parentCategoryHostId);
        }
        
        $model->setPath($parentCategory->getPath());              

        // Insert default language
        Logger::write('insert categoryi18ns');
        Logger::write($category->getI18ns());
        $categoryI18n = reset($category->getI18ns());

        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setIsActive(true);

        if ($categoryI18n instanceof ConnectorCategoryI18n) {
            $model->setName($categoryI18n->getName() !== '' ? $categoryI18n->getName() : 'Kategorie "' . $categoryI18n->getName() . '"');
            $model->setUrlKey($categoryI18n->getUrlPath());
            $model->setDescription((string)$categoryI18n->getDescription());
            $model->setMetaDescription((string)$categoryI18n->getMetaDescription());
            $model->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
            $model->setMetaTitle((string)$categoryI18n->getTitleTag());
        }
        $model->setJtlErpId($category->getId()->getHost());
        $model->save();

        $result->setId(new Identity($model->getId(), $category->getId()->getHost()));
        
        foreach ($this->stores as $locale => $storeId) {
            $categoryI18n = ArrayTools::filterByLanguage($category->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
            if (!($categoryI18n instanceof ConnectorCategoryI18n)) {
                Logger::write('skip categoryI18n ' . $locale);                
                continue;
            }

            $singleton = \Mage::getSingleton('catalog/category');
            $singleton->setId($categoryId);
            $singleton->setStoreId($storeId);

            $singleton->setName($categoryI18n->getName());
            $singleton->setUrlKey($categoryI18n->getUrlPath());
            \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'name');
            \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'url_key');

            if ($categoryI18n->getDescription() !== '') {
                $singleton->setDescription((string)$categoryI18n->getDescription());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'description');
            }
            if ($categoryI18n->getMetaDescription() !== '') {
                $singleton->setMetaDescription((string)$categoryI18n->getMetaDescription());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_description');
            }
            if ($categoryI18n->getMetaKeywords() !== '') {
                $singleton->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_keywords');
            }
            if ($categoryI18n->getTitleTag() !== '') {
                $singleton->setMetaTitle((string)$categoryI18n->getTitleTag());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_title');
            }
        }

        return $result;
    }

    private function update(ConnectorCategory $category)
    {
        Logger::write('update category');
        $result = new ConnectorCategory();

        $identity = $category->getId();
        $categoryId = $identity->getEndpoint();

        $model = \Mage::getModel('catalog/category')->load($categoryId);
        //$result->addIdentity('category', $identity);
        
        foreach ($this->stores as $locale => $storeId) {
            $categoryI18n = ArrayTools::filterByLanguage($category->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
            if (!($categoryI18n instanceof ConnectorCategoryI18n)) {
                Logger::write('skip categoryI18n ' . $locale . ':' . get_class($categoryI18n));                
                continue;
            }

            Logger::write('process categoryI18n ' . $categoryId . ' ' . $locale);

            $singleton = \Mage::getSingleton('catalog/category');
            $singleton->setId($categoryId);
            $singleton->setStoreId($storeId);

            $singleton->setName($categoryI18n->getName());
            $singleton->setUrlKey($categoryI18n->getUrlPath());
            \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'name');
            \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'url_key');

            if ($categoryI18n->getDescription() !== '') {
                $singleton->setDescription((string)$categoryI18n->getDescription());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'description');
            }
            if ($categoryI18n->getMetaDescription() !== '') {
                $singleton->setMetaDescription((string)$categoryI18n->getMetaDescription());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_description');
            }
            if ($categoryI18n->getMetaKeywords() !== '') {
                $singleton->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_keywords');
            }
            if ($categoryI18n->getTitleTag() !== '') {
                $singleton->setMetaTitle((string)$categoryI18n->getTitleTag());
                \Mage::getModel('catalog/category')->getResource()->saveAttribute($singleton, 'meta_title');
            }
        }

        return $result;
    }

    public function existsByHost($hostId)
    {
        $collection = \Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToFilter('jtl_erp_id', $hostId);

        Logger::write('existsByHost: ' . $hostId, Logger::ERROR, 'general');

        return $collection->getSize() > 0;
    }

    public function push($category)
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        $hostId = $category->getId()->getHost();

        // Skip empty objects
        if ($hostId == 0)
            return null;

        Logger::write('push category', Logger::ERROR, 'general');
        if ($this->existsByHost($hostId))
            $result = $this->update($category);
        else
            $result = $this->insert($category);
        return $result;
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $rootCategory = \Mage::getModel('catalog/category')
                ->load($this->rootCategoryId);
            
            $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
                ->addFieldToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
                ->addAttributeToFilter('jtl_erp_id',
                    array(
                        array('eq' => 0),
                        array('null' => true)
                    ),
                    'left'
                )->load();

            return $categoryCollection->count();
        }
        catch (Exception $e) {
            return 0;
        }
    }

    public function pull(QueryFilter $filter)
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);

        Magento::getInstance()->setCurrentStore($defaultStoreId);

        $rootCategory = \Mage::getModel('catalog/category')
            ->load($this->rootCategoryId);

        $categoryCollection = \Mage::getResourceModel('catalog/category_collection')
            ->addFieldToFilter('path', array('like' => $rootCategory->getPath() . '/%'))
            ->addAttributeToFilter('jtl_erp_id',
                array(
                    array('eq' => 0),
                    array('null' => true)
                ),
                'left'
            )
            ->load();

        $categoryIds = array();
        foreach ($categoryCollection as $category) {
            $categoryIds  = array_merge_recursive($categoryIds, explode(',', $category->getAllChildren()));
        }

        // Apply query filter
        if ($filter->isLimit()) {
            $categoryIds = array_splice($categoryIds, 0, $filter->getLimit());
        }

        $result = array();
        foreach ($categoryIds as $categoryId) {
            $model = \Mage::getModel('catalog/category')
                ->load($categoryId);

            $category = new ConnectorCategory();
            $category
                ->setId(new Identity($model->entity_id, $model->jtl_erp_id))
                ->setIsActive(true)
                ->setSort(intval($model->position));

            if ($model->parent_id != $this->rootCategoryId) {
                $parentModel = \Mage::getModel('catalog/category')
                    ->load($model->parent_id);
                $category->setParentCategoryId(new Identity($model->parent_id, $parentModel->getJtlErpId()));
            }

            foreach ($stores as $locale => $storeId) {
                $model = \Mage::getModel('catalog/category');
                $model->setStoreId($storeId);
                $model->load($categoryId);

                $categoryI18n = new ConnectorCategoryI18n();
                $categoryI18n
                    ->setLanguageIso(LocaleMapper::localeToLanguageIso($locale))
                    ->setCategoryId(new Identity($categoryId, $model->getJtlErpId()))
                    ->setName($model->getName())
                    ->setUrlPath($model->getUrlKey())
                    ->setDescription($model->getDescription());
                    //->setMetaDescription($model->getMetaDescription())
                    //->setMetaKeywords($model->getMetaKeywords())
                    //->setTitleTag($model->getMetaTitle());

                $category->addI18n($categoryI18n);
            }

            $result[] = $category;
        }

        return $result;
    }
}
