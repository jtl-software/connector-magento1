<?php

/**
 * @copyright 2010-2015 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */

class Jtl_Connector_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        defined('CONNECTOR_DIR') || define("CONNECTOR_DIR", __DIR__ . '/../');
        include (CONNECTOR_DIR . "/src/bootstrap.php");
    }    
}
