<?php

namespace jtl\Connector\Magento;

use jtl\Connector\Authentication\ITokenLoader;

class TokenLoader implements ITokenLoader 
{
    public function load()
    {
        return \Mage::helper('core')->decrypt(\Mage::getStoreConfig('jtl_connector/general/auth_token'));
    }
}
