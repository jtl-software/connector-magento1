<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Core\Logger\Logger;
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

    public function __construct()
    {
        Magento::getInstance();

        $this->stores = MapperDatabase::getInstance()->getStoreMapping();
        $this->defaultLocale = key($this->stores);
        $this->defaultStoreId = current($this->stores);
    }

    private function insert(ConnectorCategory $category, CategoryContainer $container)
    {
        Logger::write('insert category');
        $result = new CategoryContainer();

        $identity = $category->getId();
        $categoryId = $identity->getEndpoint();

        $model = \Mage::getModel('catalog/category');

        // Set parent category
        $parentCategoryId = $category->getParentCategoryId()->getEndpoint() ?: \Mage::app()->getStore()->getRootCategoryId();
        $parentCategory = \Mage::getModel('catalog/category')->load($parentCategoryId);
        $model->setPath($parentCategory->getPath());              

        // Insert default language
        Logger::write('insert categoryi18ns');
        Logger::write($container->getCategoryI18ns());
        $categoryI18n = ArrayTools::filterOneByLocale($container->getCategoryI18ns(), $locale);
        if ($categoryI18n === null)
            $categoryI18n = reset($container->getCategoryI18ns());

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
        $model->save();

        $result->addIdentity('category', new Identity($model->getId(), $category->getId()->getHost()));
        
        foreach ($this->stores as $locale => $storeId) {
            $categoryI18n = ArrayTools::filterOneByLocale($container->getCategoryI18ns(), $locale);
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

    private function update(ConnectorCategory $category, CategoryContainer $container)
    {
        Logger::write('update category');
        $result = new CategoryContainer();

        $identity = $category->getId();
        $categoryId = $identity->getEndpoint();

        $model = \Mage::getModel('catalog/category')->load($categoryId);
        $result->addIdentity('category', $identity);
        
        foreach ($this->stores as $locale => $storeId) {
            $categoryI18n = ArrayTools::filterOneByLocale($container->getCategoryI18ns(), $locale);
            if (!($categoryI18n instanceof ConnectorCategoryI18n)) {
                Logger::write('skip categoryI18n ' . $locale);                
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

    public function push(CategoryContainer $container)
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        $category = $container->getMainModel();
        if ($category->getId()->getEndpoint() === '')
            $result = $this->insert($category, $container);
        else
            $result = $this->update($category, $container);
        return $result;
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
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

            return count($categoryIds);
        }
        catch (Exception $e) {
            return 0;
        }
    }

    public function pull()
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        Magento::getInstance()->setCurrentStore($defaultStoreId);

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

        $result = array();
        foreach ($categoryIds as $categoryId) {
            $container = new CategoryContainer();

            $model = \Mage::getModel('catalog/category')
                ->load($categoryId);

            $category = new ConnectorCategory();
            $category->_id = $model->entity_id;
            $category->_parentCategoryId = $model->parent_id != $rootCategoryId ? $model->parent_id : null;
            $category->_sort = $model->position;

            $container->add('category', $category->getPublic(array('_fields')));

            $categoryI18n = new ConnectorCategoryI18n();
            $categoryI18n->_localeName = $defaultLocale;
            $categoryI18n->_categoryId = $categoryId;
            $categoryI18n->_name = $model->getName();
            $categoryI18n->_urlPath = $model->getUrlKey();
            $categoryI18n->_description = $model->getDescription();
            $categoryI18n->_metaDescription = $model->getMetaDescription();
            $categoryI18n->_metaKeywords = $model->getMetaKeywords();
            $categoryI18n->_titleTag = $model->getMetaTitle();                

            $container->add('category_i18n', $categoryI18n->getPublic(array('_fields')));

            foreach ($stores as $locale => $storeId) {
                $model = \Mage::getModel('catalog/category');
                $model->setStoreId($storeId);
                $model->load($categoryId);

                $categoryI18n = new ConnectorCategoryI18n();
                $categoryI18n->_localeName = $locale;
                $categoryI18n->_categoryId = $categoryId;
                $categoryI18n->_name = $model->getName();
                $categoryI18n->_url = $model->getUrlKey();
                $categoryI18n->_description = $model->getDescription();
                $categoryI18n->_metaDescription = $model->getMetaDescription();
                $categoryI18n->_metaKeywords = $model->getMetaKeywords();
                $categoryI18n->_titleTag = $model->getMetaTitle();                

                $container->add('category_i18n', $categoryI18n->getPublic(array('_fields')));
            }

            $result[] = $container->getPublic(array('items'), array('_fields'));
        }

        return $result;
    }
}
