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

    public function __construct()
    {
        Magento::getInstance();

        $this->stores = MapperDatabase::getInstance()->getStoreMapping();
        $this->defaultLocale = key($this->stores);
        $this->defaultStoreId = current($this->stores);

        Logger::write('default locale: ' . $this->defaultLocale);
        Logger::write('default Store ID: ' . $this->defaultStoreId);
    }

    private function insert(ConnectorProduct $product)
    {
        Logger::write('insert product');
        $result = new ConnectorProduct();

        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);

        $model = \Mage::getModel('catalog/product');

        $productI18n = ArrayTools::filterOneByLocale($container->getProductI18ns(), $this->defaultLocale);
        if ($productI18n === null)
            $productI18n = reset($container->getProductI18ns());

        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);

        // Insert default price
        $productPrices = ArrayTools::filterByItemKey($container->getProductPrices(), 1, '_quantity');
        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();
        $defaultProductPrice = ArrayTools::filterOneByEndpointId($productPrices, $defaultCustomerGroupId, 'customerGroupId');
        if (!($defaultProductPrice instanceof ConnectorProductPrice))
            $defaultProductPrice = reset($productPrices);

        if ($productI18n instanceof ConnectorProductI18n)
            $model->setName($productI18n->getName());
        $model->setSku($product->getSku());
        $model->setAttributeSetId(4);
        $model->setHasOptions(0);
        $model->setRequiredOptions(0);
        $model->setVisibility(\Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        $model->setStatus(1);
        $model->setTaxClassId(1);
        $model->setEnableGoogleCheckout(1);
        $model->setTypeId('simple');
        $model->setMsrp($product->getRecommendedRetailPrice());
        $model->setWeight($product->getProductWeight());
        if ($defaultProductPrice instanceof ConnectorProductPrice)
            $model->setPrice($defaultProductPrice->getNetPrice() * (1.0 + $product->getVat() / 100.0));
        $model->setWeight($product->getProductWeight());
        $model->setManageStock($product->getConsiderStock() === true ? '1' : '0');
        $model->setQty($product->getStockLevel());
        $model->setIsQtyDecimal($product->getIsDivisible() ? '1' : '0');
        $model->setMinSaleQty($product->getMinimumOrderQuantity());
        $model->setUseConfigMinSaleQty(is_null($product->getMinimumOrderQuantity()) ? '1' : '0');
        $model->save();
        $productId = $model->getId();
        $result->addIdentity('product', new Identity($model->getId(), $product->getId()->getHost()));

        foreach ($this->stores as $locale => $storeId) {
            \Mage::app()->setCurrentStore($storeId);

            $model = \Mage::getModel('catalog/product')
                ->load($productId);

            // Add product to website
            $websiteIds = $model->getWebsiteIds();
            if (!in_array(\Mage::app()->getStore()->getWebsiteId(), $websiteIds)) {
                $websiteIds[] = \Mage::app()->getStore()->getWebsiteId();
                $model->setStoreId($storeId);
                $model->setWebsiteIds($websiteIds);
                $model->save();
            }


            $productI18n = ArrayTools::filterOneByLocale($container->getProductI18ns(), $locale);
            if (!($productI18n instanceof ConnectorProductI18n))
                continue;

            $model->setStoreId($storeId);
            $model->setName($productI18n->getName());
            $model->setShortDescription($productI18n->getShortDescription());
            $model->setDescription($productI18n->getDescription());
            $model->save();
        }

        /* *** Begin Product2Category *** */
        $categoryIds = array_map(function($product2Category) {
            return $product2Category->getCategoryId()->getEndpoint();
        }, $product->getCategories());
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        $model->save();
        /* *** End Product2Category *** */


        // die('error (todo)');
        return $result;
    }

    private function update(ConnectorProduct $product)
    {
        Logger::write('update product');
        $result = new ConnectorProduct();

        $identity = $product->getId();
        $hostId = $identity->getHost();

        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();
        
        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product')
            ->loadByAttribute('jtl_erp_id', $hostId);
        $productId = $model->entity_id;
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);

        /* *** Begin Product *** */
        $model->setMsrp($product->getRecommendedRetailPrice());
        $model->setWeight($product->getProductWeight());

        /* *** Begin StockLevel *** */
        $stockItem = \Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($model);
        $stockItem->setQty($product->getStockLevel()->getStockLevel());
        $stockItem->save();

        /* *** Begin ProductPrice *** */
        // Insert default price
        $defaultGroupPrices = ArrayTools::filterOneByItemEndpointId($product->getPrices(), $defaultCustomerGroupId, 'customerGroupId');
        if (!($defaultGroupPrices instanceof ConnectorProductPrice)) {
            $defaultGroupPrices = reset($product->getPrices());
        }

        $defaultGroupPriceItems = $defaultGroupPrices->getItems();
        $defaultProductPrice = ArrayTools::filterOneByItemKey($defaultGroupPriceItems, 0, 'quantity');
        if (!($defaultProductPrice instanceof ConnectorProductPriceItem))
            $defaultProductPrice = reset($defaultGroupPrices);

        if ($defaultProductPrice instanceof ConnectorProductPriceItem) {
            Logger::write('default price: ' . $defaultProductPrice->getNetPrice());
            Logger::write('gross: ' . ($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0)));
            Logger::write('product tax class ID: ' . $model->getTaxClassId());
            $model->setPrice($defaultProductPrice->getNetPrice() * (1.0 + $this->getTaxRateByClassId($model->tax_class_id) / 100.0));
        }
        else {
            die(var_dump($defaultProductPrice));
        }

        // Tier prices and group prices (i.e. tier price with qty == 0)
        // Clear all tier prices and group prices first (are you f***king kidding me?)
        // 
        // (thanks to http://www.catgento.com/how-to-set-tier-prices-programmatically-in-magento/)
        $dbc = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = \Mage::getSingleton('core/resource');
        $table = $resource->getTableName('catalog/product').'_tier_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        Logger::write("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        $table = $resource->getTableName('catalog/product').'_group_price';
        $dbc->query("DELETE FROM $table WHERE entity_id = " . $model->entity_id);
        Logger::write("DELETE FROM $table WHERE entity_id = " . $model->entity_id);

        $tierPrice = array();
        $groupPrice = array();
        foreach ($product->getPrices() as $currentPrice) {
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
        Logger::write('set tier prices');
        $model->setTierPrice($tierPrice);
        Logger::write('set group prices');
        $model->setGroupPrice($groupPrice);
        Logger::write('save');
        $model->save();

        // Set fake array to trick Magento into not updating tier prices during
        // this function any further
        $model->setTierPrice(array('website_id' => 0));
        $model->setGroupPrice(array('website_id' => 0));

        /* *** Begin ProductI18n *** */
        Logger::write('begin admin store i18n');

        // Admin Store ID (default language)
        $productI18n = ArrayTools::filterOneByLanguage($product->getI18ns(), LocaleMapper::localeToLanguageIso($this->defaultLocale));
        if ($productI18n === null)
            $productI18n = reset($product->getI18ns());

        if ($productI18n instanceof ConnectorProductI18n) {
            $model->setName($productI18n->getName());
            $model->setShortDescription($productI18n->getShortDescription());
            $model->setDescription($productI18n->getDescription());
        }
        $model->save();
        $result->setId(new Identity($model->entity_id, $model->jtl_erp_id));

        Logger::write('begin productI18n');
        foreach ($this->stores as $locale => $storeId) {
            $productI18n = ArrayTools::filterOneByLanguage($product->getI18ns(), LocaleMapper::localeToLanguageIso($locale));
            if (!($productI18n instanceof ConnectorProductI18n))
                continue;

            $model = \Mage::getModel('catalog/product')
                ->load($productId);

            $model->setStoreId($storeId);
            $model->setName($productI18n->getName());
            $model->setShortDescription($productI18n->getShortDescription());
            $model->setDescription($productI18n->getDescription());
            $model->save();

            Logger::write('productI18n ' . $locale);
        }
        Logger::write('end productI18n');
        /* *** End ProductI18n *** */

        /* *** Begin Product2Category *** */
        $product2Categories = $product->getCategories();
        Logger::write('product2Categories' . var_export($product2Categories, true));
        $categoryIds = array_map(function($product2Category) {
            Logger::write('product2category: ' . var_export($product2Category, true));

            $category = \Mage::getResourceModel('catalog/category_collection')
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('jtl_erp_id', $product2Category->getCategoryId()->getHost())
                ->getFirstItem();

            return $category->entity_id;
        }, $product2Categories);
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        Logger::write('update with category IDs . ' . var_export($categoryIds, true));
        $model->save();
        /* *** End Product2Category *** */

        // die('error (todo)');
        return $result;
    }

    public function existsByHost($hostId)
    {
        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('jtl_erp_id', $hostId);

        Logger::write('existsByHost: ' . $hostId, Logger::ERROR, 'general');

        return $collection->getSize() > 0;
    }

    public function push($product)
    {
        Magento::getInstance();        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);

        $hostId = $product->getId()->getHost();

        // Skip empty objects
        if ($hostId == 0)
            return null;

        Logger::write('push product', Logger::ERROR, 'general');
        if ($this->existsByHost($hostId))
            $result = $this->update($product);
        else
            $result = $this->insert($product);
        return $result;
    }

    private function magentoToConnector(\Mage_Catalog_Model_Product $productItem)
    {
        Magento::getInstance();        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        $defaultStoreId = reset($stores);
        $defaultLocale = key($stores);
        
        $created_at = new \DateTime($productItem->created_at);

        $product = new ConnectorProduct();
        $product->setId(new Identity($productItem->entity_id, $productItem->jtl_erp_id));
        $product->setMasterProductId(!is_null($productItem->parent_id) ? new Identity($productItem->parent_id) : null);
        // $product->setPartsListId(null);
        $product->setSku($productItem->sku);
        $product->setRecommendedRetailPrice((double)$productItem->msrp);
        $product->setMinimumOrderQuantity((double)($productItem->use_config_min_sale_qty == 1 ? 0 : $productItem->min_sale_qty));
        $product->setPackagingQuantity(1.0);
        $product->setVat($this->getTaxRateByClassId($productItem->tax_class_id));
        $product->setShippingWeight(0.0);
        $product->setProductWeight(0.0);
        $product->setIsMasterProduct(false);
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
        $product->setPermitNegativeStock($stockItem->getBackorders() == \Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY);
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

            Logger::write('options: ' . json_encode($productAttributeOptions));

            // Iterate over all variations
            $variations = array();
            foreach ($productAttributeOptions as $attributeIndex => $attributeOption) {
                $productVariation = new ConnectorProductVariation();
                $productVariation
                    ->setId(new Identity($attributeOption['id']))
                    ->setProductId(new Identity($productItem->entity_id))
                    ->setSort((int)$attributeOption['position']);

                // TODO: Load real attribute type
                $productVariation->setType('select');

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
                    $valueLabels[$locale] = \Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeOption['attribute_code'])
                        ->setStoreId($storeId)
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
        $stores = MapperDatabase::getInstance()->getStoreMapping();
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
            ->addAttributeToSort('parent_id', 'ASC');

        $result = array();
        foreach ($products as $productItem) {
            $productItem->load();
            
            $product = $this->magentoToConnector($productItem);
            $product->setMasterProductId(new Identity(''));

            if (!is_null($product)) {
                $result[] = $product;
            }
        }

        return $result;
    }

    private function pullChildProducts(QueryFilter $filter)
    {
        Magento::getInstance();        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
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

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $productModel = \Mage::getModel('catalog/product');
            $productCollection = $productModel->getCollection()
                ->addAttributeToSelect('*')
                ->joinTable('catalog/product_relation', 'child_id=entity_id', array(
                    'parent_id' => 'parent_id'
                ), null, 'left')
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
        static $taxRates = array();

        if (array_key_exists($taxClassId, $taxRates))
            return $taxRates[$taxClassId];

        $store = \Mage::app()->getStore();
        $request = \Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
        $percent = \Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxClassId));

        Logger::write(sprintf('store %u percent for tax class %u', $percent, $taxClassId));

        if (!is_null($percent))
            $taxRates[$taxClassId] = $percent;

        return $percent;
    }
}
