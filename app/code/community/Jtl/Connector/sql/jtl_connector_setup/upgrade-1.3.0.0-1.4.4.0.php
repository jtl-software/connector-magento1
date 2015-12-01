<?php

$installer = new Mage_Core_Model_Resource_Setup('core_setup');
$installer->startSetup();
$sql = "
    ALTER TABLE {$this->getTable('jtl_connector_link_image')}
      CHANGE `foreign_key` `foreign_key` INT(11) DEFAULT 0  NOT NULL,
      ADD COLUMN `endpoint_id` INT(11) DEFAULT 0  NOT NULL AFTER `foreign_key`;
    ";
$installer->run($sql);
$installer->endSetup();
