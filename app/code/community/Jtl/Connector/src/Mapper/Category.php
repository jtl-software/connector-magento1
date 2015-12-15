<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Utilities\ArrayTools;
use jtl\Connector\Model\Category as ConnectorCategory;
use jtl\Connector\Model\CategoryI18n as ConnectorCategoryI18n;
use jtl\Connector\Model\Identity;
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

        $this->rootCategoryId = \Mage::app()->getWebsite()->getDefaultStore()->getRootCategoryId();

        $this->stores = Magento::getInstance()->getStoreMapping();
        $this->defaultLocale = key($this->stores);
        $this->defaultStoreId = current($this->stores);
    }

    private function handlePredefinedFunctionAttributes(ConnectorCategory $category, \Mage_Catalog_Model_Category $model)
    {
        $model->save();
        $model->load($model->getId());

        foreach ($category->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $attributeI18n) {
                // Allow "is_active" to be set by category attribute
                $allowedBoolValues = array('0', '1', 0, 1, false, true, 'false', 'true');
                $normalizedAttributeName = strtolower($attributeI18n->getName());
                if (in_array($normalizedAttributeName, array('isactive', 'is_active'))) {
                    if (!in_array($attributeI18n->getValue(), $allowedBoolValues, true))
                        continue;

                    $model->setIsActive(((bool) $attributeI18n->getValue()) ? 1 : 0);
                }

                if ($normalizedAttributeName === 'is_anchor') {
                    if (!in_array($attributeI18n->getValue(), $allowedBoolValues, true))
                        continue;

                    $model->setIsAnchor(((bool) $attributeI18n->getValue()) ? 1 : 0);
                }

                if ($normalizedAttributeName === 'include_in_navigation') {
                    if (!in_array($attributeI18n->getValue(), $allowedBoolValues, true))
                        continue;

                    $model->setIncludeInMenu(((bool) $attributeI18n->getValue()) ? 1 : 0);
                }
            }
        }
    }

    private function insert(ConnectorCategory $category)
    {
        Logger::write('insert category', Logger::ERROR, 'general');
        $result = new ConnectorCategory();

        $identity = $category->getId();
        $categoryId = $identity->getEndpoint();
        $hostId = $identity->getHost();

        if ($hostId == 0)
            return;

        $model = \Mage::getModel('catalog/category');

        // Set parent category
        Logger::write('parent host : ' . $category->getParentCategoryId()->getHost(), Logger::ERROR, 'general');
        if ((int)$category->getParentCategoryId()->getHost() == 0) {
            $parentCategory = \Mage::getModel('catalog/category')
                ->load($this->rootCategoryId);
        }
        else {
            $parentCategoryHostId = $category->getParentCategoryId()->getHost();
            $parentCategory = \Mage::getModel('catalog/category')
                ->loadByAttribute('jtl_erp_id', $parentCategoryHostId);
        }

        if ($parentCategory === false)
            return null;
        
        $model->setPath($parentCategory->getPath());              

        // Insert default language
        Logger::write('insert categoryi18ns');
        $i18ns = $category->getI18ns();
        $categoryI18n = reset($i18ns);

        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setIsActive($category->getIsActive());

        $this->handlePredefinedFunctionAttributes($category, $model);

        if ($categoryI18n instanceof ConnectorCategoryI18n) {
            $model->setName($categoryI18n->getName() !== '' ? $categoryI18n->getName() : 'Kategorie "' . $categoryI18n->getName() . '"');
            $model->setUrlKey($categoryI18n->getUrlPath());
            $model->setDescription((string)$categoryI18n->getDescription());
            $model->setMetaDescription((string)$categoryI18n->getMetaDescription());
            $model->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
            $model->setMetaTitle((string)$categoryI18n->getTitleTag());
        }
        $model->setJtlErpId($hostId);
        $model->save();

        $result->setId(new Identity($model->getId(), $hostId));
        
        foreach ($this->stores as $locale => $storeId) {
            $i18ns = $category->getI18ns();
            $categoryI18n = ArrayTools::filterOneByLanguage($i18ns, LocaleMapper::localeToLanguageIso($locale));
            if (!($categoryI18n instanceof ConnectorCategoryI18n)) {
                Logger::write('skip categoryI18n ' . $locale);                
                continue;
            }

            \Mage::app()->setCurrentStore($storeId);
            $model = \Mage::getModel('catalog/category')
                ->loadByAttribute('jtl_erp_id', $hostId);
            $model->setName($categoryI18n->getName());
            $model->setUrlKey($categoryI18n->getUrlPath());

            if ($categoryI18n->getDescription() !== '') {
                $model->setDescription((string)$categoryI18n->getDescription());
            }
            if ($categoryI18n->getMetaDescription() !== '') {
                $model->setMetaDescription((string)$categoryI18n->getMetaDescription());
            }
            if ($categoryI18n->getMetaKeywords() !== '') {
                $model->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
            }
            if ($categoryI18n->getTitleTag() !== '') {
                $model->setMetaTitle((string)$categoryI18n->getTitleTag());
            }

            $model->save();
        }

        return $result;
    }

    private function update(ConnectorCategory $category)
    {
        Logger::write('update category');
        $result = new ConnectorCategory();

        $identity = $category->getId();
        $hostId = $identity->getHost();

        $model = \Mage::getModel('catalog/category')
            ->loadByAttribute('jtl_erp_id', $hostId);
        $result->setId(new Identity($model->getId(), $category->getId()->getHost()));
        $model->setIsActive($category->getIsActive());

        $this->handlePredefinedFunctionAttributes($category, $model);

        $model->save();
        
        foreach ($this->stores as $locale => $storeId) {
            $categoryI18n = ArrayTools::filterOneByLanguageOrFirst($category->getI18ns(), LocaleMapper::localeToLanguageIso($locale));

            Logger::write('process categoryI18n for category #' . $model->getId() . ' ' . $locale);

            \Mage::app()->setCurrentStore($storeId);
            $model = \Mage::getModel('catalog/category')
                ->loadByAttribute('jtl_erp_id', $hostId);
            $model->setName($categoryI18n->getName());
            $model->setUrlKey($categoryI18n->getUrlPath());

            if ($categoryI18n->getDescription() !== '') {
                $model->setDescription((string)$categoryI18n->getDescription());
            }
            if ($categoryI18n->getMetaDescription() !== '') {
                $model->setMetaDescription((string)$categoryI18n->getMetaDescription());
            }
            if ($categoryI18n->getMetaKeywords() !== '') {
                $model->setMetaKeywords((string)$categoryI18n->getMetaKeywords());
            }
            if ($categoryI18n->getTitleTag() !== '') {
                $model->setMetaTitle((string)$categoryI18n->getTitleTag());
            }

            $model->save();
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
        
        $stores = Magento::getInstance()->getStoreMapping();
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
        
        $stores = Magento::getInstance()->getStoreMapping();
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
            ->addAttributeToSort('level', 'asc');

        // Apply query filter
        if ($filter->isLimit()) {
            $categoryCollection->setPageSize($filter->getLimit())->setCurPage(1);
        }

        $categoryCollection->load();

        $result = array();
        foreach ($categoryCollection as $model) {
            $categoryId = $model->entity_id;

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
