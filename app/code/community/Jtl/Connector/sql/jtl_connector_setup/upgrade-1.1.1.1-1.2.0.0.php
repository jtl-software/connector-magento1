<?php

$installer = new Mage_Core_Model_Resource_Setup('core_setup');
$installer->startSetup();
$sql = "
    DROP TABLE IF EXISTS {$this->getTable('jtl_connector_link_specific')};
    CREATE TABLE {$this->getTable('jtl_connector_link_specific')} (
      `id` int(11) unsigned NOT NULL auto_increment,
      `attribute_code` varchar(255) NOT NULL default '',
      `jtl_erp_id` int(11) NOT NULL default '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
error_log($sql);
$installer->run($sql);
$installer->endSetup();
