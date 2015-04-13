<?php

$installer = $this;

$installer->startSetup();

$categoryEntityTypeId     = $installer->getEntityTypeId('catalog_category');
$categoryAttributeSetId   = $installer->getDefaultAttributeSetId($categoryEntityTypeId);
$categoryAttributeGroupId = $installer->getDefaultAttributeGroupId($categoryEntityTypeId, $categoryAttributeSetId);
$installer->addAttribute('catalog_category', 'jtl_erp_id', array(
    'type'           => 'int',
    'label'          => 'JTL-Wawi-ID',
    'input'          => 'text',
    'global'         => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'        => true,
    'required'       => false,
    'user_defined'   => false,
    'default'        => 0
));

$installer->addAttribute('catalog_category', 'jtl_erp_image_id', array(
    'type'           => 'int',
    'label'          => 'JTL-Wawi-Bild-ID',
    'input'          => 'text',
    'global'         => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'        => true,
    'required'       => false,
    'user_defined'   => false,
    'default'        => 0
));

$productEntityTypeId     = $installer->getEntityTypeId('catalog_product');
$productAttributeSetId   = $installer->getDefaultAttributeSetId($productEntityTypeId);
$productAttributeGroupId = $installer->getDefaultAttributeGroupId($productEntityTypeId, $productAttributeSetId);
$installer->addAttribute('catalog_product', 'jtl_erp_id', array(
    'type'           => 'int',
    'label'          => 'JTL-Wawi-ID',
    'input'          => 'text',
    'global'         => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'        => true,
    'required'       => false,
    'user_defined'   => false,
    'used_in_product_listing' => true,
    'unique'         => false,
    'apply_to'       => '',
    'default'        => 0
));

$installer->addAttribute('catalog_product', 'jtl_erp_variation_hash', array(
    'type'           => 'varchar',
    'label'          => 'JTL-Wawi-Produktvariations-Hash',
    'input'          => 'text',
    'global'         => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'        => true,
    'required'       => false,
    'user_defined'   => false,
    'used_in_product_listing' => true,
    'unique'         => false,
    'apply_to'       => '',
    'default'        => 0
));

$installer->endSetup();

$setup = new Mage_Customer_Model_Entity_Setup('core_setup');

$customerEntityTypeId     = $installer->getEntityTypeId('customer');
$customerAttributeSetId   = $installer->getDefaultAttributeSetId($customerEntityTypeId);
$customerAttributeGroupId = $installer->getDefaultAttributeGroupId($customerEntityTypeId, $customerAttributeSetId);
$installer->addAttribute('customer', 'jtl_erp_id', array(
    'input'         => 'text',
    'type'          => 'int',
    'label'         => 'JTL-Wawi-ID',
    'visible'       => 1,
    'required'      => 0,
    'user_defined'  => 1
));

$setup->endSetup();

$setup = new Mage_Sales_Model_Resource_Setup('core_setup');
// $setup->addAttributeToGroup(
//     $customerEntityTypeId,
//     $customerAttributeSetId,
//     $customerAttributeGroupId,
//     'jtl_erp_id',
//     999
// );

$setup->addAttribute('quote', 'jtl_erp_id', array(
    'type'             => 'int',
    'label'            => 'JTL-Wawi-ID',
    'backend_input'    => 'text',
    'frontend_input'   => 'text',
    'visible'          => true,
    'required'         => false,
    'visible_on_front' => true,
    'user_defined'     => false,
    'default'          => 0
));

$setup->addAttribute('order', 'jtl_erp_id', array(
    'type'             => 'int',
    'label'            => 'JTL-Wawi-ID',
    'backend_input'    => 'text',
    'frontend_input'   => 'text',
    'visible'          => true,
    'required'         => false,
    'visible_on_front' => true,
    'user_defined'     => false,
    'default'          => 0
));

$setup->endSetup();
