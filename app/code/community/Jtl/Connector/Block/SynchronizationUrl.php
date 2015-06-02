<?php

class Jtl_Connector_Block_SynchronizationUrl extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml($element) {
        $sslEnabled = (int)Mage::getStoreConfig('web/secure/use_in_frontend');
        
        if ($sslEnabled === 1) {
            $baseUrl = Mage::getStoreConfig('web/secure/base_url');
        }
        else {
            $baseUrl = Mage::getStoreConfig('web/unsecure/base_url');
        }

        $connectorUrl = sprintf('%sjtlconnector/', $baseUrl);

        return '<strong>' . $connectorUrl . '</strong>';
    }
}
