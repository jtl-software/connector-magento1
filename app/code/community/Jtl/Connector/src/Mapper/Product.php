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
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Product as ConnectorProduct;
use jtl\Connector\Model\Product2Category as ConnectorProduct2Category;
use jtl\Connector\Model\ProductI18n as ConnectorProductI18n;
use jtl\Connector\Model\ProductPrice as ConnectorProductPrice;
use jtl\Connector\Model\ProductPriceItem as ConnectorProductPriceItem;
use jtl\Connector\Model\ProductStockLevel as ConnectorProductStockLevel;
use jtl\Connector\Model\ProductVariation as ConnectorProductVariation;
use jtl\Connector\Model\ProductVariationI18n as ConnectorProductVariationI18n;
use jtl\Connector\Model\ProductVariationValue as ConnectorProductVariationValue;
use jtl\Connector\Model\ProductVariationValueExtraCharge as ConnectorProductVariationValueExtraCharge;
use jtl\Connector\Model\ProductVariationValueI18n as ConnectorProductVariationValueI18n;
use jtl\Connector\Result\Transaction;

/**
 * Description of Product
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Product
{
    private $stores;
    private $defaultLocale;
    private $defaultStoreId;

    private $websites;

    public function __construct()
    {
        Magento::getInstance();

        $this->stores = Magento::getInstance()->getStoreMapping();
        $this->defaultLocale = key($this->stores);
        $this->defaultStoreId = current($this->stores);

        $this->websites = array();
        foreach (\Mage::app()->getWebsites() as $website)
        {
            $this->websites[] = $website->getId();
        }

        Logger::write('default locale: ' . $this->defaultLocale, Logger::DEBUG);
        Logger::write('default Store ID: ' . $this->defaultStoreId, Logger::DEBUG);
    }

    private function isParent(ConnectorProduct $product)
    {
        return ($product->getIsMasterProduct());
    }

    private function isChild(ConnectorProduct $product)
    {
        return ((count($product->getVariations()) > 0) && ($product->getMasterProductId()->getHost() > 0));
    }

    private function insert(ConnectorProduct $product)
    {
        Logger::write('insert product', Logger::DEBUG);

        $result = new ConnectorProduct();
        $identity = $product->getId();
        $hostId = $identity->getHost();

        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();
        
        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product');

        $firstStore = reset($this->stores);
        $model->setWebsiteIds($this->websites);
        $model->setStoreId($firstStore);

        $model->setJtlErpId($identity->getHost());
        $model->setIsRecurring(0);

        $taxClassId = $this->getTaxClassIdByRate($product->getVat());
        $model->setTaxClassId($taxClassId);
        $model->setStatus(1);

        if ($this->isParent($product)) {
            Logger::write(sprintf('PRODUCT "%s" TYPE: varcombi parent', $product->getSku()), Logger::DEBUG);

            // Varcombi parent
            $model->setTypeId('configurable');

            $attributeSetId = $this->getAttributeSetForProduct($product);
            $model->setAttributeSetId($attributeSetId);
            $this->updateConfigurableData($model, $product);

            // Reload model
            $model = \Mage::getModel('catalog/product')
                ->load($model->getId());
        }
        elseif ($this->isChild($product)) {
            Logger::write(sprintf('PRODUCT "%s" TYPE: varcombi child', $product->getSku()), Logger::DEBUG);

            // Varcombi child
            $model->setTypeId('simple');

            $attributeSetId = $this->getAttributeSetForProduct($product);
            $model->setAttributeSetId($attributeSetId);
            $this->updateVariationValues($model, $product);

            // We do not want to see varcombi childs in the catalog, so change
            // the visibility accordingly
            $model->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }
        else {
            Logger::write(sprintf('PRODUCT "%s" TYPE: simple product', $product->getSku()), Logger::DEBUG);

            // Simple product
            $model->setTypeId('simple');

            // Set default attribute set ID
            $defaultAttributeSetId = \Mage::getSingleton('eav/config')
                ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
                ->getDefaultAttributeSetId();

            $model->setAttributeSetId($defaultAttributeSetId);
        }

        /* *** Begin Product *** */
        $model->setSku($product->getSku());
        $model->setMsrp($product->getRecommendedRetailPrice());
        $model->setWeight($product->getProductWeight());
        $model->save();

        /* *** Begin StockLevel *** */
        $this->updateProductStockLevel($model, $product);

        $this->updateProductPrices($model, $product);
        $result->setId(new Identity($model->entity_id, $model->jtl_erp_id));

        // Create fake array to trick Magento into not updating tier prices during
        // this function any further
        $model->setTierPrice(array('website_id' => 0));
        $model->setGroupPrice(array('website_id' => 0));

        $this->updateProductI18ns($model, $product);

        /* *** Begin Product2Category *** */
        $product2Categories = $product->getCategories();
        $categoryIds = array_map(function($product2Category) {
            $category = \Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('jtl_erp_id', $product2Category->getCategoryId()->getHost())
                ->getFirstItem();

            return $category->entity_id;
        }, $product2Categories);
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        Logger::write('update with category IDs . ' . var_export($categoryIds, true), Logger::DEBUG);
        $model->save();
        /* *** End Product2Category *** */

        // die('error (todo)');
        return $result;
    }

    private function update(ConnectorProduct $product)
    {
        Logger::write('update product', Logger::DEBUG);
        $result = new ConnectorProduct();

        $identity = $product->getId();
        $hostId = $identity->getHost();

        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();
        
        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product')
            ->loadByAttribute('jtl_erp_id', $hostId);
        $productId = $model->entity_id;
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);

        $model->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        if ($this->isParent($product)) {
            Logger::write(sprintf('PRODUCT "%s" TYPE: varcombi parent', $product->getSku()), Logger::DEBUG);

            // Varcombi parent
            $model->setTypeId('configurable');

            $attributeSetId = $this->getAttributeSetForProduct($product);
            $model->setAttributeSetId($attributeSetId);
            $this->updateConfigurableData($model, $product, true);
        }
        elseif ($this->isChild($product)) {
            Logger::write(sprintf('PRODUCT "%s" TYPE: varcombi child', $product->getSku()), Logger::DEBUG);

            // Varcombi child
            $model->setTypeId('simple');

            $attributeSetId = $this->getAttributeSetForProduct($product);
            $model->setAttributeSetId($attributeSetId);
            $this->updateVariationValues($model, $product);

            // We do not want to see varcombi childs in the catalog, so change
            // the visibility accordingly
            $model->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        }
        else {
            Logger::write(sprintf('PRODUCT "%s" TYPE: simple product', $product->getSku()), Logger::DEBUG);

            // Simple product
            $model->setTypeId('simple');

            // Set default attribute set ID
            $defaultAttributeSetId = \Mage::getSingleton('eav/config')
                ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
                ->getDefaultAttributeSetId();

            $model->setAttributeSetId($defaultAttributeSetId);
        }

        /* *** Begin Product *** */
        $taxClassId = $this->getTaxClassIdByRate($product->getVat());
        $model->setTaxClassId($taxClassId);

        $model->setMsrp($product->getRecommendedRetailPrice());
        $model->setWeight($product->getProductWeight());

        /* *** Begin StockLevel *** */
        if (!$this->isParent($product)) {
            $this->updateProductStockLevel($model, $product);
        }

        $this->updateProductPrices($model, $product);
        $result->setId(new Identity($model->entity_id, $model->jtl_erp_id));

        // Create fake array to trick Magento into not updating tier prices during
        // this function any further
        $model->setTierPrice(array('website_id' => 0));
        $model->setGroupPrice(array('website_id' => 0));

        $this->updateProductI18ns($model, $product);

        /* *** Begin Product2Category *** */
        $product2Categories = $product->getCategories();
        $categoryIds = array_map(function($product2Category) {
            $category = \Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('jtl_erp_id', $product2Category->getCategoryId()->getHost())
                ->getFirstItem();

            return $category->entity_id;
        }, $product2Categories);
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        Logger::write('update with category IDs . ' . var_export($categoryIds, true), Logger::DEBUG);
        $model->save();
        /* *** End Product2Category *** */

        // die('error (todo)');
        return $result;
    }

    private function getCustomAttributesFromAttrSet($attributeSetId)
    {
        static $defaultAttributeIDs = null;
        if (is_null($defaultAttributeIDs)) {
            $defaultAttributeSetId = \Mage::getSingleton('eav/config')
                ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
                ->getDefaultAttributeSetId();

            $attributes = \Mage::getModel('catalog/product_attribute_api')
                ->items($defaultAttributeSetId);

            $defaultAttributeIDs = array_map(function($item) {
                return $item['attribute_id'];
            }, $attributes);
        }

        $attributes = \Mage::getModel('catalog/product_attribute_api')
            ->items($attributeSetId);

        $attributes = array_filter($attributes, function($attr) use ($defaultAttributeIDs) {
            return !in_array($attr['attribute_id'], $defaultAttributeIDs);
        });

        sort($attributes);
        return $attributes;
    }

    private function getVariationTitlesForProduct(ConnectorProduct $product)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);

        $titles = array();
        foreach ($product->getVariations() as $variation) {
            $variationI18n = ArrayTools::filterOneByLanguage($variation->getI18ns(), $defaultLanguageIso);
            if ($variationI18n === null)
                $variationI18n = reset($variation->getI18ns());

            if ($variationI18n != null)
                $titles[$variation->getId()->getHost()] = trim($variationI18n->getName());
        }

        asort($titles);
        return $titles;
    }

    private function updateAttributeValues(array $variations)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);

        $product = \Mage::getModel('catalog/product');
        
        Logger::write(sprintf('process %u variations...', count($variations)), Logger::DEBUG);
        foreach ($variations as $variation) {
            $attribute = $this->findAttributeByVariation($variation);

            if (!is_null($attribute)) {
                $attribute->setEntityType($product->getResource());
                if (!$attribute->getSourceModel()) {
                    $attribute->setSourceModel('eav/entity_attribute_source_table');
                }

                $values = $attribute
                    ->getSource()
                    ->getAllOptions(false);

                foreach ($variation->getValues() as $variationValue) {
                    $defaultVariationValueI18n = ArrayTools::filterOneByLanguage($variationValue->getI18ns(), $defaultLanguageIso);
                    if ($defaultVariationValueI18n === null)
                        $defaultVariationValueI18n = reset($variationValue->getI18ns());
                    $matches = array_filter($values, function ($value) use ($defaultVariationValueI18n) {
                        return ($value['label'] === $defaultVariationValueI18n->getName());
                    });

                    // Value found
                    if ($matches)
                        continue;

                    Logger::write(sprintf('value "%s" not found', $variationValue->getId()->getHost()), Logger::DEBUG);

                    $attribute_model = \Mage::getModel('eav/entity_attribute');
                    $attribute_options_model = \Mage::getModel('eav/entity_attribute_source_table');

                    $attribute_table = $attribute_options_model->setAttribute($attribute);
                    $options = $attribute_options_model->getAllOptions(false);
                    Logger::write(var_export($options, true), Logger::DEBUG);

                    $stores = Magento::getInstance()->getStoreMapping();
                    $newAttributeValue = array('option' => array());
                    $newAttributeValue['option'] = array(
                        \Mage_Core_Model_App::ADMIN_STORE_ID => $defaultVariationValueI18n->getName()
                    );
                    foreach ($stores as $locale => $storeId) {
                        $variationValueI18n = ArrayTools::filterOneByLanguage($variationValue->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
                        if ($variationValueI18n === null) {
                            $i18ns = $variationValue->getI18ns();
                            $variationValueI18n = reset($i18ns);
                        }

                        $newAttributeValue['option'][$storeId] = $variationValueI18n->getName();
                    }
                    $result = array('value' => $newAttributeValue);
                    $attribute->setData('option', $result);
                    $attribute->save();
                }
            }
            else {
                // Is there an error in the matrix?
                Logger::write('Spurious attribute not found: ' . $variationI18n->getName(), Logger::DEBUG);
                throw new Exception('Spurious attribute not found: ' . $variationI18n->getName());
            }
        }
    }

    private function findAttributeByVariation(ConnectorProductVariation $variation)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);
        $variationI18n = ArrayTools::filterOneByLanguageOrFirst($variation->getI18ns(), $defaultLanguageIso);

        $attributes = \Mage::getModel('eav/entity_attribute')
            ->getCollection()
            ->addFieldToFilter('frontend_label', $variationI18n->getName());

        if ($attributes->count() > 0)
            return $attributes->getFirstItem();

        return NULL;
    }

    private static function getAttributeCodeForVariationName($attributeName)
    {
        $attributeCode = strtolower(str_replace(' ', '_', $attributeName));
        $attributeCode = str_replace(
            array('ä', 'ö', 'ü', 'ß'),
            array('ae', 'oe', 'ue', 'ss'),
            $attributeCode
        );

        return substr($attributeCode, 0, 30);
    }

    private function getAttributeSetForProduct(ConnectorProduct $product)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);

        $defaultAttributeSetId = \Mage::getSingleton('eav/config')
            ->getEntityType(\Mage_Catalog_Model_Product::ENTITY)
            ->getDefaultAttributeSetId();
        Logger::write('default attr set ID: ' . $defaultAttributeSetId, Logger::DEBUG);

        $productEntityTypeId = \Mage::getModel('eav/entity')
            ->setType('catalog_product')
            ->getTypeId();

        $setCollection = \Mage::getModel('catalog/product_attribute_set_api')->items();

        $variationTitles = $this->getVariationTitlesForProduct($product);
        $variationTitleList = implode(',', $variationTitles);
        Logger::write('Looking for variation set: ' . $variationTitleList, Logger::DEBUG);

        foreach ($setCollection as $attributeSet)
        {
            // Skip default attribute set because it does not contain any
            // custom attributes
            if ($attributeSet['set_id'] == $defaultAttributeSetId)
                continue;

            $attributes = $this->getCustomAttributesFromAttrSet($attributeSet['set_id']);
            $attrNames = array_map(function ($attr) use ($product) {
                $label = \Mage::getResourceModel('catalog/product')
                    ->getAttribute($attr['code'])
                    ->getFrontendLabel();
                return trim($label);
            }, $attributes);
            sort($attrNames);

            $attrNameList = implode(',', $attrNames);
            Logger::write('attr set ID: ' . $attributeSet['set_id'] . ' - list: ' . $attrNameList, Logger::DEBUG);

            if (count($attributes) == 0)
                continue;

            // we have found a compatible attr set
            if (strtolower($attrNameList) === strtolower($variationTitleList)) {
                Logger::write('found compatible attr set with ID ' . $attributeSet['set_id'], Logger::DEBUG);
                Logger::write('check attribute values for existance...', Logger::DEBUG);

                $this->updateAttributeValues($product->getVariations());

                return $attributeSet['set_id'];
            }
        }

        Logger::write('no compatible attribute set found - creating one...', Logger::DEBUG);

        // Loop through all variations and check for appropriate attributes
        $attributeSetAttributes = array();
        foreach ($product->getVariations() as $variation) {
            $attribute = $this->findAttributeByVariation($variation);

            if (!is_null($attribute)) {
                Logger::write('Attribute found - code: ' . $attribute['attribute_code'], Logger::DEBUG);
                $attributeSetAttributes[] = $attribute['attribute_code'];
            }
            else {
                $attributeName = $variationTitles[$variation->getId()->getHost()];
                $attributeCode = self::getAttributeCodeForVariationName($attributeName);

                Logger::write('Creating attribute: ' . $attributeCode, Logger::DEBUG);

                // Collect all frontend labels
                $frontendLabels = array(
                    \Mage_Core_Model_App::ADMIN_STORE_ID => $attributeName
                );
                foreach ($this->stores as $locale => $storeId) {
                    $variationI18n = ArrayTools::filterOneByLanguage($variation->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
                    if (!($variationI18n instanceof ConnectorProductVariationI18n))
                        continue;

                    $frontendLabels[$storeId] = $variationI18n->getName();
                }

                $attributeData = array(
                    'attribute_code' => $attributeCode,
                    'is_global' => 1,
                    'is_visible' => 1,
                    'is_searchable' => 0,
                    'is_filterable' => 0,
                    'is_comparable' => 1,
                    'is_visible_on_front' => 1,
                    'is_html_allowed_on_front' => 0,
                    'is_used_for_price_rules' => 0,
                    'is_filterable_in_search' => 0,
                    'used_in_product_listing' => 1,
                    'used_for_sort_by' => 1,
                    'is_configurable' => 1,
                    'frontend_input' => 'select',
                    'is_wysiwyg_enabled' => 0,
                    'is_unique' => 0,
                    'is_required' => 0,
                    'is_visible_in_advanced_search' => 0,
                    'is_visible_on_checkout' => 1,
                    'frontend_label' => $frontendLabels,
                    'apply_to' => array()
                );

                $attrModel = \Mage::getModel('catalog/resource_eav_attribute');
                $attributeData['backend_type'] = $attrModel->getBackendTypeByInput($attributeData['frontend_input']);
                $attrModel->addData($attributeData);
                $attrModel->setEntityTypeId($productEntityTypeId);
                $attrModel->setIsUserDefined(1);
                $attrModel->save();

                $attributeSetAttributes[] = $attrModel->getAttributeCode();
            }
        }

        // Update attribute values
        $this->updateAttributeValues($product->getVariations());

        // Create attribute set containing the attributes
        $attrSet = \Mage::getModel('eav/entity_attribute_set');
        $attrSet->setAttributeSetName(sprintf(
            'Variationskombination "%s"',
            implode(',', $variationTitles)
        ));
        $attrSet->setEntityTypeId($productEntityTypeId);
        $attrSet->save();

        $attrSet->initFromSkeleton($defaultAttributeSetId);
        $attrSet->save();

        $defaultGroup = \Mage::getModel('eav/entity_attribute_group')
            ->getCollection()
            ->addFieldToFilter('attribute_set_id', $attrSet->getId())
            ->setOrder('sort_order', ASC)
            ->getFirstItem();

        $i = 1000;
        foreach ($attributeSetAttributes as $attributeCode) {
            $attrModel = \Mage::getResourceModel('eav/entity_attribute_collection')
                ->setCodeFilter($attributeCode)
                ->getFirstItem();

            $newItem = \Mage::getModel('eav/entity_attribute')
                ->setEntityTypeId($productEntityTypeId)
                ->setAttributeSetId($attrSet->getId())
                ->setAttributeGroupId($defaultGroup->getId())
                ->setAttributeId($attrModel->getId())
                ->setSortOrder($i)
                ->save();

            $i += 10;

            Logger::write(sprintf(
                'Add attribute "%s" to attribute set "%s" in group "%s"',
                $attrModel->getAttributeCode(),
                $attrSet->getAttributeSetName(),
                $defaultGroup->getAttributeGroupName()
            ), Logger::DEBUG);
        }
        $attrSet->save();

        return $attrSet->getId();
    }

    private function findOptionValueIdByName($attribute, $valueName)
    {
        Magento::getInstance()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);

        $product = \Mage::getModel('catalog/product');
        $attribute->setEntityType($product->getResource());
        if (!$attribute->getSourceModel()) {
            $attribute->setSourceModel('eav/entity_attribute_source_table');
        }
        $values = $attribute
            ->getSource()
            ->getAllOptions(false);

        $matches = array_filter($values, function ($value) use ($valueName) {
            return ($value['label'] === $valueName);
        });

        if (!$matches) {
            throw new \Exception('Attribute value ' . $valueName . ' not found');
        }

        $match = reset($matches);
        return $match['value'];
    }

    private function updateVariationValues(\Mage_Catalog_Model_Product $model, ConnectorProduct $product)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);

        foreach ($product->getVariations() as $variation) {
            $variationValues = $variation->getValues();
            $variationValue = reset($variationValues);

            $defaultVariationValueI18n = ArrayTools::filterOneByLanguage($variationValue->getI18ns(), $defaultLanguageIso);
            if ($defaultVariationValueI18n === null)
                $defaultVariationValueI18n = reset($variationValue->getI18ns());

            $attribute = $this->findAttributeByVariation($variation);
            $optionValueId = $this->findOptionValueIdByName($attribute, $defaultVariationValueI18n->getName());

            Logger::write(sprintf('attr "%s" => value "%s"', $attribute->getAttributeCode(), $optionValueId), Logger::DEBUG);
            $model->setData($attribute->getAttributeCode(), $optionValueId);
        }
    }

    private function updateConfigurableData(\Mage_Catalog_Model_Product $model, ConnectorProduct $product, $isUpdating = false)
    {
        $defaultLanguageIso = LocaleMapper::localeToLanguageIso($this->defaultLocale);

        $attributeIDs = array();
        $configurableProductsData = array();
        $childProductIDs = array();
        foreach ($product->getVarCombinations() as $varCombination) {
            $childProduct = \Mage::getModel('catalog/product')
                ->loadByAttribute('jtl_erp_id', $varCombination->getProductId()->getHost());
            if (is_null($childProduct) || ($childProduct === false))
                throw new \Exception('Child product has to be inserted before the parent may be pushed.');

            $childProductId = $childProduct->getId();
            $childProductIDs[$childProductId] = 1;

            $variationId = $varCombination->getProductVariationId();
            $variationValueId = $varCombination->getProductVariationValueId();

            if (!array_key_exists($childProductId, $configurableProductsData)) {
                $configurableProductsData[$childProductId] = array();
            }

            $variations = $product->getVariations();

            foreach ($variations as $variation) {
                $attribute = $this->findAttributeByVariation($variation);

                if ($variation->getId()->getHost() === $variationId->getHost()) {
                    $variationValues = $variation->getValues();
                    foreach ($variationValues as $variationValue) {
                        if ($variationValue->getId()->getHost() === $variationValueId->getHost()) {
                            $defaultVariationValueI18n = ArrayTools::filterOneByLanguage($variationValue->getI18ns(), $defaultLanguageIso);
                            if ($defaultVariationValueI18n === null)
                                $defaultVariationValueI18n = reset($variationValue->getI18ns());

                            $optionValueId = $this->findOptionValueIdByName($attribute, $defaultVariationValueI18n->getName());
                            $attributeId = (int)$attribute->getId();

                            if (!in_array($attributeId, $attributeIDs))
                                $attributeIDs[] = $attributeId;

                            $configurableProductsData[$childProductId][] = array(
                                'label' => $defaultVariationValueI18n->getName(),
                                'attribute_id' => $attributeId,
                                'value_index' => $optionValueId,
                                'is_percent' => '0',
                                'pricing_value' => 0
                            );
                        }
                    }
                }
            }
        }
        $model->getTypeInstance()->setUsedProductAttributeIds($attributeIDs);
        $model->setCanSaveConfigurableAttributes(true);

        if (!$isUpdating) {
            $configurableAttributesData = $model->getTypeInstance()->getConfigurableAttributesAsArray();
            $model->setConfigurableAttributesData($configurableAttributesData);
        }

        $model->setConfigurableProductsData($configurableProductsData);
        $model->setCanSaveCustomOptions(true);

        $model->save();
    }

    public function existsByHost($hostId)
    {
        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('jtl_erp_id', $hostId);

        Logger::write('existsByHost: ' . $hostId, Logger::DEBUG, 'general');

        return $collection->getSize() > 0;
    }

    public function push($product)
    {
        Magento::getInstance();        
        $stores = Magento::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);

        $hostId = $product->getId()->getHost();

        // Skip empty objects
        if ($hostId == 0)
            return null;

        Logger::write('push product', Logger::DEBUG, 'general');
        if ($this->existsByHost($hostId))
            $result = $this->update($product);
        else
            $result = $this->insert($product);
        return $result;
    }

    private function magentoToConnector(\Mage_Catalog_Model_Product $productItem)
    {
        Magento::getInstance();        
        $stores = Magento::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);
        
        $created_at = new \DateTime($productItem->created_at);

        $product = new ConnectorProduct();
        $product->setId(new Identity($productItem->entity_id, $productItem->jtl_erp_id));
        $product->setMasterProductId(!is_null($productItem->parent_id) ? new Identity($productItem->parent_id) : new Identity(''));
        // $product->setPartsListId(null);
        $product->setSku($productItem->sku);
        $product->setRecommendedRetailPrice((double)$productItem->msrp);
        $product->setMinimumOrderQuantity((double)($productItem->use_config_min_sale_qty == 1 ? 0 : $productItem->min_sale_qty));
        $product->setPackagingQuantity(1.0);
        $product->setVat((double)$this->getTaxRateByClassId($productItem->tax_class_id));
        $product->setShippingWeight(0.0);
        $product->setProductWeight(0.0);
        $product->setIsMasterProduct($productItem->type_id == 'configurable');
        $product->setIsNewProduct(false);
        $product->setIsTopProduct(false);
        $product->setPermitNegativeStock(false);
        $product->setConsiderVariationStock(false);
        $product->setConsiderBasePrice(false);
        $product->setCreationDate($created_at);
        $product->setAvailableFrom($created_at);
        $product->setIsBestBefore(false);

        $stockItem = \Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($productItem);

        $stockLevel = new ConnectorProductStockLevel();
        $stockLevel->setProductId($product->getId());
        $stockLevel->setStockLevel(doubleval($stockItem->qty));
        $product->setStockLevel($stockLevel);
        $product->setIsDivisible($stockItem->is_qty_decimal == '1');
        $product->setConsiderStock($stockItem->getManageStock() == '1');
        $product->setMinimumOrderQuantity((int)$stockItem->getMinSaleQty());
        $product->setPermitNegativeStock(in_array($stockItem->getBackorders(), array(\Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY, \Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY)));
        // $product->setPackagingUnit($stockItem->getQtyIncrements());

        // ProductI18n
        foreach ($stores as $locale => $storeId) {
            Magento::getInstance()->setCurrentStore($storeId);

            $productModel = \Mage::getModel('catalog/product')
                ->load($productItem->entity_id);

            $productI18n = new ConnectorProductI18n();
            $productI18n->setLanguageIso(LocaleMapper::localeToLanguageIso($locale));
            $productI18n->setProductId(new Identity($productItem->entity_id));
            $productI18n->setName($productModel->getName());
            $productI18n->setUrlPath($productModel->getUrlPath());
            $productI18n->setDescription($productModel->getDescription());
            $productI18n->setShortDescription($productModel->getShortDescription());
            $productI18n->setMetaDescription($productModel->getMetaDescription());
            $productI18n->setMetaKeywords($productModel->getMetaKeyword());
            $productI18n->setTitleTag($productModel->getMetaTitle());

            $product->addI18n($productI18n);
        }

        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();

        // ProductPrice
        $productPrice = new ConnectorProductPrice();
        $productPrice->setCustomerGroupId(new Identity($defaultCustomerGroupId)); // TODO: Insert configured default customer group
        $productPrice->setProductId(new Identity($productItem->entity_id, $productItem->jtl_erp_id));

        $productPriceItem = new ConnectorProductPriceItem();
        $productPriceItem->setNetPrice($productItem->price / (1 + $product->getVat() / 100.0));
        $productPriceItem->setQuantity(max(0, (int)$productItem->min_sale_qty));
        $productPrice->addItem($productPriceItem);

        $product->addPrice($productPrice);

        // ProductVariation
        if (in_array($productItem->getTypeId(), array('configurable'))) {
            $productAttributeOptions = array();
            $typeInstance = $productItem->getTypeInstance(false);
            $productAttributeOptions = $typeInstance->getConfigurableAttributesAsArray($productItem);

            Logger::write('options: ' . json_encode($productAttributeOptions), Logger::DEBUG);

            // Iterate over all variations
            $variations = array();
            foreach ($productAttributeOptions as $attributeIndex => $attributeOption) {
                $productVariation = new ConnectorProductVariation();
                $productVariation
                    ->setId(new Identity($attributeOption['id']))
                    ->setProductId(new Identity($productItem->entity_id))
                    ->setSort((int)$attributeOption['position']);

                $attrModel = \Mage::getModel('catalog/resource_eav_attribute')
                    ->load($attributeOption['attribute_id']);

                foreach ($stores as $locale => $storeId) {
                    $productVariationI18n = new ConnectorProductVariationI18n();
                    $productVariationI18n
                        ->setLanguageIso(LocaleMapper::localeToLanguageIso($locale))
                        ->setName($attrModel->getStoreLabel($storeId))
                        ->setProductVariationId(new Identity($attributeOption['id']));

                    $productVariation->addI18n($productVariationI18n);
                }

                $valueLabels = array();
                foreach ($stores as $locale => $storeId) {
                    $attribute = \Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeOption['attribute_code'])
                        ->setStoreId($storeId);
                    if (!$attribute->getSourceModel()) {
                        $attribute->setSourceModel('eav/entity_attribute_source_table');
                    }

                    $valueLabels[$locale] = $attribute
                        ->getSource()
                        ->getAllOptions(false);
                }

                foreach ($attributeOption['values'] as $valueIndex => $value) {
                    $productVariationValue = new ConnectorProductVariationValue();
                    $productVariationValue
                        ->setId(new Identity($value['value_id']))
                        ->setProductVariationId(new Identity($attributeOption['id']))
                        ->setSort($valueIndex);

                    foreach ($stores as $locale => $storeId) {
                        $productVariationValueI18n = new ConnectorProductVariationValueI18n();
                        $productVariationValueI18n
                            ->setProductVariationValueId(new Identity($value['value_id']))
                            ->setLanguageIso(LocaleMapper::localeToLanguageIso($locale))
                            ->setName($valueLabels[$locale][$valueIndex]['label']);

                        $productVariationValue->addI18n($productVariationValueI18n);
                    }

                    $productVariationValueExtraCharge = new ConnectorProductVariationValueExtraCharge();
                    $productVariationValueExtraCharge
                        ->setProductVariationValueId(new Identity($value['value_id']))
                        ->setExtraChargeNet($value['pricing_value'] / (1 + $product->getVat() / 100.0));
                    $productVariationValue->addExtraCharge($productVariationValueExtraCharge);

                    $productVariation->addValue($productVariationValue);
                }

                $product->addVariation($productVariation);
            }
        }

        // Product2Category
        $category_ids = $productItem->getCategoryIds();

        foreach ($category_ids as $id) {
            $category = \Mage::getModel('catalog/category')
                ->load($id);

            $product2Category = new ConnectorProduct2Category();
            $product2Category->setId(new Identity(sprintf('%u-%u', $productItem->entity_id, $id)));
            $product2Category->setCategoryId(new Identity($id, $category->jtl_erp_id));
            $product2Category->setProductId(new Identity($productItem->entity_id, $productItem->jtl_erp_id));

            $product->addCategory($product2Category);
        }

        return $product;
    }

    public function pull(QueryFilter $filter)
    {
        Magento::getInstance();        
        $stores = Magento::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);
        Magento::getInstance()->setCurrentStore($defaultStoreId);

        $products = \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('jtl_erp_id',
                array(
                    array('eq' => 0),
                    array('null' => true)
                ),
                'left'
            )
            ->joinTable('catalog/product_relation', 'child_id=entity_id', array(
                'parent_id' => 'parent_id'
            ), null, 'left')
            ->addAttributeToSort('parent_id', 'ASC')
            ->setPageSize(25)
            ->setCurPage(1);

        $result = array();
        foreach ($products as $productItem) {
            $productItem->load();
            
            $product = $this->magentoToConnector($productItem);

            if (!is_null($product)) {
                $result[] = $product;
            }
        }

        return $result;
    }

    private function pullChildProducts(QueryFilter $filter)
    {
        Magento::getInstance();        
        $stores = Magento::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);
        Magento::getInstance()->setCurrentStore($defaultStoreId);
        
        $parentId = $filter->getFilter('parentId');
        $product = \Mage::getModel('catalog/product')->load($parentId);
        if (is_null($product)) {
            return array();
        }

        $childProducts = \Mage::getModel('catalog/product_type_configurable')
                    ->getUsedProducts(null,$product);  

        $result = array();
        foreach ($childProducts as $productItem) {            
            $product = $this->magentoToConnector($productItem);

            if (!is_null($product)) {
                $result[] = $product;
            }
        }

        return $result;
    }

    public function processStockLevelChange(ProductStockLevel $stockLevel)
    {
        $identity = $stockLevel->getProductId();
        $hostId = $identity->getHost();

        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product')
            ->loadByAttribute('jtl_erp_id', $hostId);

        if (is_null($model->entity_id))
            return false;

        $stockItem = \Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($model);
        $stockItem->setQty($stockLevel->getStockLevel());
        $stockItem->setIsInStock($stockLevel->getStockLevel() > 0 ? 1 : 0);
        $stockItem->save();

        return true;
    }

    public function processPrices(array $prices)
    {
        if (count($prices) == 0)
            return false;

        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();

        $firstPrice = reset($prices);
        $identity = $firstPrice->getProductId();
        $hostId = $identity->getHost();

        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product')
            ->loadByAttribute('jtl_erp_id', $hostId);

        if (is_null($model->entity_id))
            return false;

        /* *** Begin ProductPrice *** */
        // Insert default price
        $defaultGroupPrices = ArrayTools::filterOneByItemHostIdOrFirst($prices, 0, 'customerGroupId');

        $defaultGroupPriceItems = $defaultGroupPrices->getItems();
        $defaultProductPrice = ArrayTools::filterOneByItemKeyOrFirst($defaultGroupPriceItems, 0, 'quantity');

        if ($defaultProductPrice instanceof ConnectorProductPriceItem) {
            Logger::write('default price: ' . $defaultProductPrice->getNetPrice(), Logger::DEBUG);
            Logger::write('gross: ' . ($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)), Logger::DEBUG);
            Logger::write('product tax class ID: ' . $model->getTaxClassId(), Logger::DEBUG);
            $model->setPrice($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0));
        }
        else {
            return null;
        }

        // Tier prices and group prices (i.e. tier price with qty == 0)
        // Clear all tier prices and group prices first (are you f***king kidding me?)
        // 
        // (thanks to http://www.catgento.com/how-to-set-tier-prices-programmatically-in-magento/)
        $dbc = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = \Mage::getSingleton('core/resource');
        $table = $resource->getTableName('catalog/product').'_tier_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        $table = $resource->getTableName('catalog/product').'_group_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);

        $tierPrice = array();
        $groupPrice = array();
        foreach ($prices as $currentPrice) {
            if ($currentPrice->getCustomerGroupId()->getHost() === 0)
                continue;

            foreach ($currentPrice->getItems() as $currentPriceItem) {
                if ($currentPriceItem->getQuantity() > 0) {
                    // Tier price (qty > 0)
                    $tierPrice[] = array(
                        'website_id' => \Mage::app()->getStore()->getWebsiteId(),
                        'cust_group' => (int)$currentPrice->getCustomerGroupId()->getEndpoint(),
                        'price_qty' => $currentPriceItem->getQuantity(),
                        'price' => $currentPriceItem->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)
                    );
                }
                else {
                    // Group price (qty == 0)
                    $groupPrice[] = array(
                        'website_id' => \Mage::app()->getStore()->getWebsiteId(),
                        'all_groups' => (int)$currentPrice->getCustomerGroupId()->getEndpoint() == 0 ? 1 : 0,
                        'cust_group' => (int)$currentPrice->getCustomerGroupId()->getEndpoint(),
                        'price' => $currentPriceItem->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)
                    );
                }
            }
        }
        Logger::write('set tier prices', Logger::DEBUG);
        Logger::write(var_export($tierPrice, true), Logger::DEBUG);
        $model->setTierPrice($tierPrice);
        Logger::write('set group prices', Logger::DEBUG);
        $model->setGroupPrice($groupPrice);
        Logger::write('save', Logger::DEBUG);
        $model->save();
    }

    private function updateProductPrices(\Mage_Catalog_Model_Product $model, ConnectorProduct $product)
    {
        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();

        $prices = $product->getPrices();

        // Insert default price
        $defaultGroupPrices = ArrayTools::filterOneByItemHostIdOrFirst($prices, 0, 'customerGroupId');

        $defaultGroupPriceItems = $defaultGroupPrices->getItems();
        $defaultProductPrice = ArrayTools::filterOneByItemKeyOrFirst($defaultGroupPriceItems, 0, 'quantity');

        if ($defaultProductPrice instanceof ConnectorProductPriceItem) {
            Logger::write('default price: ' . $defaultProductPrice->getNetPrice(), Logger::DEBUG);
            Logger::write('gross: ' . ($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)), Logger::DEBUG);
            Logger::write('product tax class ID: ' . $model->getTaxClassId(), Logger::DEBUG);
            $model->setPrice($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0));
        }
        else {
            Logger::write('oops');
            throw new \Exception('unexpected product price item: ' . var_export($defaultProductPrice));
        }

        // Tier prices and group prices (i.e. tier price with qty == 0)
        // Clear all tier prices and group prices first (are you f***king kidding me?)
        // 
        // (thanks to http://www.catgento.com/how-to-set-tier-prices-programmatically-in-magento/)
        Logger::write('before clean', Logger::DEBUG);
        $dbc = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = \Mage::getSingleton('core/resource');
        $table = $resource->getTableName('catalog/product').'_tier_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        $table = $resource->getTableName('catalog/product').'_group_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        Logger::write('prices cleaned', Logger::DEBUG);

        $tierPrice = array();
        $groupPrice = array();
        $websiteId = \Mage::app()->getStore()->getWebsiteId();
        foreach ($product->getPrices() as $currentPrice) {
            if ($currentPrice->getCustomerGroupId()->getHost() === 0)
                continue;

            foreach ($currentPrice->getItems() as $currentPriceItem) {
                if ($currentPriceItem->getQuantity() > 0) {
                    // Tier price (qty > 0)
                    $tierPrice[] = array(
                        'website_id' => $websiteId,
                        'cust_group' => (int)$currentPrice->getCustomerGroupId()->getEndpoint(),
                        'price_qty' => $currentPriceItem->getQuantity(),
                        'price' => $currentPriceItem->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)
                    );
                }
                else {
                    // Group price (qty == 0)
                    $groupPrice[] = array(
                        'website_id' => $websiteId,
                        'all_groups' => (int)$currentPrice->getCustomerGroupId()->getEndpoint() == 0 ? 1 : 0,
                        'cust_group' => (int)$currentPrice->getCustomerGroupId()->getEndpoint(),
                        'price' => $currentPriceItem->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)
                    );
                }
            }
        }
        Logger::write('set tier prices:' . var_export($tierPrice, true), Logger::DEBUG);
        $model->setTierPrice($tierPrice);
        Logger::write('set group prices:' . var_export($groupPrice, true), Logger::DEBUG);
        $model->setGroupPrice($groupPrice);
        Logger::write('save', Logger::DEBUG);
        $model->save();
    }

    private function updateProductI18ns(\Mage_Catalog_Model_Product $model, ConnectorProduct $product)
    {
        Logger::write('begin admin store i18n', Logger::DEBUG);

        // Reload model
        $tempProduct = \Mage::getModel('catalog/product')
            ->load($model->getId());

        // Admin Store ID (default language)
        $productI18n = ArrayTools::filterOneByLanguage($product->getI18ns(), LocaleMapper::localeToLanguageIso($this->defaultLocale));
        if ($productI18n === null)
            $productI18n = reset($product->getI18ns());

        if ($productI18n instanceof ConnectorProductI18n) {
            $tempProduct->setName($productI18n->getName());

            if (strlen($productI18n->getShortDescription()) > 0)
                $tempProduct->setShortDescription($productI18n->getShortDescription());
            else
                $tempProduct->setShortDescription("&nbsp;");

            if (strlen($productI18n->getDescription()) > 0)
                $tempProduct->setDescription($productI18n->getDescription() ?: ' ');
            else
                $tempProduct->setDescription("&nbsp;");

            $tempProduct->setMetaTitle($productI18n->getTitleTag());
            $tempProduct->setMetaDescription($productI18n->getMetaDescription());
            $tempProduct->setMetaKeyword($productI18n->getMetaKeywords());
        }
        $tempProduct->save();

        Logger::write('begin productI18n', Logger::DEBUG);
        foreach ($this->stores as $locale => $storeId) {
            $productI18n = ArrayTools::filterOneByLanguage($product->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
            if (!($productI18n instanceof ConnectorProductI18n))
                continue;

            $tempProduct = \Mage::getModel('catalog/product')
                ->load($model->getId());

            $tempProduct->setStoreId($storeId);
            $tempProduct->setName($productI18n->getName());

            if (strlen($productI18n->getShortDescription()) > 0)
                $tempProduct->setShortDescription($productI18n->getShortDescription());
            else
                $tempProduct->setShortDescription("&nbsp;");

            if (strlen($productI18n->getDescription()) > 0)
                $tempProduct->setDescription($productI18n->getDescription() ?: ' ');
            else
                $tempProduct->setDescription("&nbsp;");

            $tempProduct->setMetaTitle($productI18n->getTitleTag());
            $tempProduct->setMetaDescription($productI18n->getMetaDescription());
            $tempProduct->setMetaKeyword($productI18n->getMetaKeywords());

            $tempProduct->save();

            Logger::write('productI18n ' . $locale, Logger::DEBUG);
        }
        Logger::write('end productI18n', Logger::DEBUG);
    }

    private function updateProductStockLevel(\Mage_Catalog_Model_Product $model, ConnectorProduct $product)
    {
        $model->save();

        $tempProduct = \Mage::getModel('catalog/product')
            ->load($model->entity_id);
        if ($this->isParent($product)) {
            $tempProduct->setStockData(array( 
                'use_config_manage_stock' => 0,
                'is_in_stock' => 1,
                'manage_stock' => 0,
                'use_config_notify_stock_qty' => 0,
                'can_back_in_stock' => 1,
                'qty' => 99999999.9999
            ));
        }
        else {
            $tempProduct->setStockData(array(
                'use_config_manage_stock' => 0,
                'is_in_stock' => (!$product->getConsiderStock() || ($product->getStockLevel()->getStockLevel() > 0)) ? 1 : 0,
                'qty' => $product->getStockLevel()->getStockLevel(),
                'is_qty_decimal' => $product->getIsDivisible() ? 1 : 0,
                'manage_stock' => $product->getConsiderStock() ? 1 : 0,
                'use_config_notify_stock_qty' => 0,
                'use_config_backorders' => 0,
                'backorders' => ($product->getPermitNegativeStock() ? \Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY : \Mage_CatalogInventory_Model_Stock::BACKORDERS_NO)
            ));
        }

        $tempProduct->save();
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $productModel = \Mage::getModel('catalog/product');
            $productCollection = $productModel->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('jtl_erp_id',
                    array(
                        array('eq' => 0),
                        array('null' => true)
                    ),
                    'left'
                )
                ->addAttributeToSort('parent_id', 'ASC');

            return $productCollection->count();
        }
        catch (Exception $e) {
            return 0;
        }
    }

    protected function getTaxRateByClassId($taxClassId)
    {
        $taxRateMapping = Magento::getInstance()->getTaxRateMapping();
        return $taxRateMapping[$taxClassId];
    }

    protected function getTaxClassIdByRate($taxRate)
    {
        $taxRateMapping = Magento::getInstance()->getTaxRateMapping();

        $taxClassId = array_search($taxRate, $taxRateMapping);
        if ($taxClassId === false) {
            $keys = array_keys($taxRateMapping);
            return $keys[0];
        }

        return $taxClassId;
    }
}
