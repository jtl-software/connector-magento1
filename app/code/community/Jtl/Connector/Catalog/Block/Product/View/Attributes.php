<?php

class Jtl_Connector_Catalog_Block_Product_View_Attributes extends Mage_Catalog_Block_Product_View_Attributes
{
    // Not sure why mage product_view_attributes block extends Mage_Core_Block_Template instead of say
    // Mage_Catalog_Block_Product_View_Abstract, but it means that setProduct($product) won't work, so
    // I've had to add it here.
    public function setProduct($product)
    {
        $this->_product = $product;
        return $this;
    }

    /**
     * Hide non-set attribute values in the frontend
     *
     * @param array $excludeAttr optional array of attribute codes to exclude them from additional data array
     * @return array
     */
    public function getAdditionalData(array $excludeAttr = array())
    {
        $data = array();
        $product = $this->getProduct();
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront() && !in_array($attribute->getAttributeCode(), $excludeAttr)) {
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    continue;
                } elseif ((string)$value == '') {
                    $value = Mage::helper('catalog')->__('No');
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = Mage::app()->getStore()->convertPrice($value, true);
                }

                if (is_string($value) && strlen($value)) {
                    $data[$attribute->getAttributeCode()] = array(
                        'label' => $attribute->getStoreLabel(),
                        'value' => $value,
                        'code'  => $attribute->getAttributeCode()
                    );
                }
            }
        }
        return $data;
    }}