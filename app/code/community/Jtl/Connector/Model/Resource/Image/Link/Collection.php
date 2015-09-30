<?php

class Jtl_Connector_Model_Resource_Image_Link_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('jtl_connector/image_link');
    }
}
