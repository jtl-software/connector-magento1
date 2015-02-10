<?php

$installer = $this;

$installer->startSetup();

// $installer->addAttribute('catalog_product', 'jtl_erp_id', array(
//     'backend' => '',
//     'frontend' => '',
//     'class' => '',
//     'default' => '',

// ));

$installer->endSetup();
 
$installer->installEntities();
