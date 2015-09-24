<?php

class Jtl_Connector_Model_Resource_Specificvalue_Link extends Mage_Core_Model_Resource_Db_Abstract
{
    public function _construct()
    {
        $this->_init('jtl_connector/specificvalue_link', 'id');
    }

    public function truncate()
    {
        $this->_getWriteAdapter()->query('TRUNCATE TABLE ' . $this->getMainTable());
        return $this;
    }
}
