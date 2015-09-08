<?php

class Jtl_Connector_Model_Specific_Link extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('jtl_connector/specific_link');
    }
}
