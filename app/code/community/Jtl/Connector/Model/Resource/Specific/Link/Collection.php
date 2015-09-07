<?php

class Jtl_Connector_Model_Resource_Specific_Link_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->_init('jtl_connector/specific_link');
    }
}
