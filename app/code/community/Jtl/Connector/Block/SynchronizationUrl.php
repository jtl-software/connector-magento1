<?php

class Jtl_Connector_Block_SynchronizationUrl extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $baseUrl = Mage::app()->getStore(1)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $connectorUrl = sprintf('%sjtlconnector/', $baseUrl);

        return '<strong>' . $connectorUrl . '</strong>';
    }
}
