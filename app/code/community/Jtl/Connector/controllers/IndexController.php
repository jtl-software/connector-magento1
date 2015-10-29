<?php

/**
 * @copyright 2010-2015 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */

class Jtl_Connector_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        defined('CONNECTOR_DIR') || define('CONNECTOR_DIR', __DIR__ . '/../');
        defined('LOG_DIR') || define('LOG_DIR', Mage::getBaseDir('var') . '/log/jtlconnector/');
        if (!file_exists(LOG_DIR)) {
            mkdir(LOG_DIR);
        }

        include (CONNECTOR_DIR . "/src/bootstrap.php");
    }
}
