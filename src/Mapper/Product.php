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
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Product as ConnectorProduct;
use jtl\Connector\Model\Product2Category as ConnectorProduct2Category;
use jtl\Connector\Model\ProductI18n as ConnectorProductI18n;
use jtl\Connector\Model\ProductPrice as ConnectorProductPrice;
use jtl\Connector\Model\ProductVariation as ConnectorProductVariation;
use jtl\Connector\Model\ProductVariationValue as ConnectorProductVariationValue;
use jtl\Connector\ModelContainer\ProductContainer;
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

    private function insert(ConnectorProduct $product, ProductContainer $container)
    {
        Logger::write('insert product');
        $result = new ProductContainer();

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
        Logger::write('price: ' . var_export($defaultProductPrice, true));
        Logger::write(var_export($defaultProductPrice instanceof ConnectorProductPrice, true));
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
        }, $container->getProduct2Categories());
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        $model->save();
        /* *** End Product2Category *** */


        // die('error (todo)');
        return $result;
    }

    private function update(ConnectorProduct $product, ProductContainer $container)
    {
        Logger::write('update product');
        $result = new ProductContainer();

        $identity = $product->getId();
        $productId = $identity->getEndpoint();

        \Mage::app()->setCurrentStore(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model = \Mage::getModel('catalog/product')
            ->load($productId);
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);

        /* *** Begin Product *** */
        $model->setMsrp($product->getRecommendedRetailPrice());
        $model->setWeight($product->getProductWeight());

        /* *** Begin ProductPrice *** */
        // Insert default price
        $productPrices = ArrayTools::filterByItemKey($container->getProductPrices(), 1, '_quantity');
        $defaultCustomerGroupId = Magento::getInstance()->getDefaultCustomerGroupId();
        $defaultProductPrice = ArrayTools::filterOneByEndpointId($productPrices, $defaultCustomerGroupId, 'customerGroupId');
        if (!($defaultProductPrice instanceof ConnectorProductPrice))
            $defaultProductPrice = reset($productPrices);

        if ($defaultProductPrice instanceof ConnectorProductPrice)
            $model->setPrice($defaultProductPrice->getNetPrice() * (1.0 + $product->getVat() / 100.0));
        $model->save();

        /* *** Begin ProductI18n *** */

        // Admin Store ID (default language)
        $productI18n = ArrayTools::filterOneByLocale($container->getProductI18ns(), $this->defaultLocale);
        if ($productI18n === null)
            $productI18n = reset($container->getProductI18ns());

        if ($productI18n instanceof ConnectorProductI18n) {
            $model->setName($productI18n->getName());
            $model->setShortDescription($productI18n->getShortDescription());
            $model->setDescription($productI18n->getDescription());
        }
        $model->save();
        $result->addIdentity('product', new Identity($model->getId(), $product->getId()->getHost()));


        foreach ($this->stores as $locale => $storeId) {
            $productI18n = ArrayTools::filterOneByLocale($container->getProductI18ns(), $locale);
            if (!($productI18n instanceof ConnectorProductI18n))
                continue;

            $model = \Mage::getModel('catalog/product')
                ->load($productId);

            $model->setStoreId($storeId);
            $model->setName($productI18n->getName());
            $model->setShortDescription($productI18n->getShortDescription());
            $model->setDescription($productI18n->getDescription());
            $model->save();
        }
        /* *** End ProductI18n *** */

        /* *** Begin Product2Category *** */
        $product2Categories = $container->getProduct2Categories();
        Logger::write('product2Categories' . var_export($product2Categories, true));
        $categoryIds = array_map(function($product2Category) {
            Logger::write('product2category: ' . var_export($product2Category, true));
            return $product2Category->getCategoryId()->getEndpoint();
        }, $product2Categories);
        $model->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID);
        $model->setCategoryIds($categoryIds);
        Logger::write('update with category IDs . ' . var_export($categoryIds, true));
        $model->save();
        /* *** End Product2Category *** */

        // die('error (todo)');
        return $result;
    }

    public function push(ProductContainer $container)
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        $product = $container->getMainModel();
        if ($product->getId()->getEndpoint() === '')
            $result = $this->insert($product, $container);
        else
            $result = $this->update($product, $container);
        return $result;
    }

    public function pull()
    {
        Magento::getInstance();        
        
        $stores = MapperDatabase::getInstance()->getStoreMapping();
        reset($stores);
        $defaultLocale = key($stores);
        $defaultStoreId = array_shift($stores);

        Magento::getInstance()->setCurrentStore($defaultStoreId);

        $products = \Mage::getResourceModel('catalog/product_collection');
        /*$productCollection = $productModel->getCollection()
            ->addAttributeToSelect('*')
            ->joinTable('cataloginventory/stock_item', 'product_id=entity_id', array(
                'qty' => 'qty',
                'is_qty_decimal' => 'is_qty_decimal',
                'use_config_min_sale_qty' => 'use_config_min_sale_qty',
                'min_sale_qty' => 'min_sale_qty',
                'notify_stock_qty'=>'notify_stock_qty',
                'use_config' => 'use_config_notify_stock_qty',
                'low_stock_date' => 'low_stock_date'
            )); */

        $result = array();
        foreach ($products as $productItem) {
            $productItem->load();

            $container = new ProductContainer();

            $created_at = new \DateTime($productItem->created_at);

            $product = new ConnectorProduct();
            $product->_id = $productItem->entity_id;
            $product->_masterProductId = null;
            $product->_setArticleId = null;
            $product->_sku = $productItem->sku;
            $product->_recommendedRetailPrice = (double)$productItem->msrp;
            $product->_minimumOrderQuantity = (double)($productItem->use_config_min_sale_qty == 1 ? 0 : $productItem->min_sale_qty);
            $product->_takeOffQuantity = 1.0;
            $product->_vat = $this->getTaxRateByClassId($productItem->tax_class_id);
            $product->_basePriceDivisor = 0.0;
            $product->_packagingUnit = 0.0;
            $product->_shippingWeight = 0.0;
            $product->_productWeight = 0.0;
            $product->_isMasterProduct = false;
            $product->_isNew = false;
            $product->_isTopProduct = false;
            $product->_permitNegativeStock = false;
            $product->_considerVariationStock = false;
            $product->_considerBasePrice = false;
            $product->_created = $created_at->format('c');
            $product->_availableFrom = $created_at->format('c');
            $product->_bestBefore = false;

            $product->_inflowQuantity = 0.0;
            $product->_supplierStockLevel = 0.0;

            $stockItem = \Mage::getModel('cataloginventory/stock_item')
                ->loadByProduct($productItem);
            $product->_stockLevel = $stockItem->qty;
            $product->_isDivisible = $stockItem->is_qty_decimal == '1';
            $product->_considerStock = $stockItem->getManageStock() == '1';
            $product->_minimumOrderQuantity = $stockItem->getMinSaleQty();
            $product->_permitNegativeStock = $stockItem->getBackorders() == \Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY;
            $product->_packagingUnit = $stockItem->getQtyIncrements();

            $container->add('product', $product->getPublic(array('_fields')));

            // ProductI18n
            $productI18n = new ConnectorProductI18n();
            $productI18n->_localeName = $defaultLocale;
            $productI18n->_productId = $productItem->entity_id;
            $productI18n->_name = $productItem->getName();
            $productI18n->_url = $productItem->getUrlPath();
            $productI18n->_description = $productItem->getDescription();
            $productI18n->_shortDescription = $productItem->getShortDescription();
            $container->add('product_i18n', $productI18n->getPublic(array('_fields')));

            foreach ($stores as $locale => $storeId) {
                Magento::getInstance()->setCurrentStore($storeId);

                $productModel = \Mage::getModel('catalog/product')
                    ->load($productItem->entity_id);

                $productI18n = new ConnectorProductI18n();
                $productI18n->_localeName = $locale;
                $productI18n->_productId = $productItem->entity_id;
                $productI18n->_name = $productModel->getName();
                $productI18n->_url = $productModel->getUrlPath();
                $productI18n->_description = $productModel->getDescription();
                $productI18n->_shortDescription = $productModel->getShortDescription();

                $container->add('product_i18n', $productI18n->getPublic(array('_fields')));
            }

            // ProductPrice
            $productPrice = new ConnectorProductPrice();
            $productPrice->_id = $productItem->entity_id . '-' . max(1, (int)$productItem->min_sale_qty);
            $productPrice->_customerGroupId = null;
            $productPrice->_productId = $productItem->entity_id;
            $productPrice->_netPrice = $productItem->price / (1 + $product->_vat / 100.0);
            $productPrice->_quantity = max(1, (int)$productItem->min_sale_qty);
            $container->add('product_price', $productPrice->getPublic(array('_fields')));

            // Product2Category
            $productModel = \Mage::getModel('catalog/product')
                ->load($productItem->entity_id);
            $category_ids = $productModel->getCategoryIds();

            foreach ($category_ids as $id) {
                $product2Category = new ConnectorProduct2Category();
                $product2Category->_id = null;
                $product2Category->_categoryId = $id;
                $product2Category->_productId = $productItem->entity_id;

                $container->add('product2_category', $product2Category->getPublic(array('_fields')));
            }

            $result[] = $container->getPublic(array('items'), array('_fields'));
        }

        return $result;
    }

    public function getAvailableCount()
    {
        Magento::getInstance();

        try {
            $productModel = \Mage::getModel('catalog/product');
            $productCollection = $productModel->getCollection()
                ->addAttributeToSelect('*');

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

        if (!is_null($percent))
            $taxRates[$taxClassId] = $percent;

        return $percent;
    }
}
