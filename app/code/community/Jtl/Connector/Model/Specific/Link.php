<?php

class Jtl_Connector_Model_Specific_Link extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->_init('jtl_connector/specific_link');
    }
}
