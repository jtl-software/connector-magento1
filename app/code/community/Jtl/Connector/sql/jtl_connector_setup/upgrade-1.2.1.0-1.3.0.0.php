<?php

$installer = new Mage_Core_Model_Resource_Setup('core_setup');
$installer->startSetup();
$sql = "
    DROP TABLE IF EXISTS {$this->getTable('jtl_connector_link_specificvalue')};
    CREATE TABLE {$this->getTable('jtl_connector_link_specificvalue')} (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `option_id` int(10) unsigned NOT NULL DEFAULT '0',
      `jtl_erp_id` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `link_specificvalue_option_id` (`option_id`),
      CONSTRAINT `link_specificvalue_option_id` FOREIGN KEY (`option_id`) REFERENCES `eav_attribute_option` (`option_id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
error_log($sql);
$installer->run($sql);
$installer->endSetup();
