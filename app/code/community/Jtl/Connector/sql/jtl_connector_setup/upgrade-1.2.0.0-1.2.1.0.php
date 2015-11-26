<?php

$installer = new Mage_Core_Model_Resource_Setup('core_setup');
$installer->startSetup();
$sql = "
    DROP TABLE IF EXISTS {$this->getTable('jtl_connector_link_image')};
    CREATE TABLE {$this->getTable('jtl_connector_link_image')} (
      `id` int(11) unsigned NOT NULL auto_increment,
      `relation_type` varchar(255) NOT NULL default '',
      `foreign_key` varchar(255) NOT NULL default '0',
      `image_id` varchar(255) NOT NULL default '0',
      `jtl_erp_id` int(11) NOT NULL default '0',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
$installer->run($sql);
$installer->endSetup();
