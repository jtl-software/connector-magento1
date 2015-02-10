<?php

/**
 * @copyright 2010-2015 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */

class Jtl_Connector_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        // $authToken = Mage::helper('core')->decrypt(Mage::getStoreConfig('jtl_connector/general/auth_token'));

        defined('CONNECTOR_DIR') || define("CONNECTOR_DIR", __DIR__);

        include (__DIR__ . "/../src/bootstrap.php");
    }    
}
