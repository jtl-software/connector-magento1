<?php

$installer = $this;

$installer->startSetup();

$categoryEntityTypeId     = $installer->getEntityTypeId('catalog_category');
$categoryAttributeSetId   = $installer->getDefaultAttributeSetId($categoryEntityTypeId);
$categoryAttributeGroupId = $installer->getDefaultAttributeGroupId($categoryEntityTypeId, $categoryAttributeSetId);
$installer->addAttribute('catalog_category', 'jtl_erp_id', array(
    'type'          => 'int',
    'label'         => 'JTL-Wawi-ID',
    'input'         => 'text',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'       => false,
    'required'      => false,
    'user_defined'  => true,
    'default'       => 0,
    'group'         => 'General Information'
));

$productEntityTypeId     = $installer->getEntityTypeId('catalog_product');
$productAttributeSetId   = $installer->getDefaultAttributeSetId($productEntityTypeId);
$productAttributeGroupId = $installer->getDefaultAttributeGroupId($productEntityTypeId, $productAttributeSetId);
$installer->addAttribute('catalog_product', 'jtl_erp_id', array(
    'type'          => 'int',
    'label'         => 'JTL-Wawi-ID',
    'input'         => 'text',
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible'       => false,
    'required'      => false,
    'user_defined'  => true,
    'default'       => 0,
    'group'         => 'General'
));


$installer->endSetup();
$installer->installEntities();
