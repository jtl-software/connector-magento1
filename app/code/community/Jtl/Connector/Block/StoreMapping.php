<?php

class Jtl_Connector_Block_StoreMapping extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected $storeOptions;
    protected $localeOptions;
    protected $websiteOptions;

    public function __construct()
    {
        // create columns
        $this->addColumn('website', array(
            'label' => Mage::helper('adminhtml')->__('Website'),
            'size' => 50
        ));
        $this->addColumn('store', array(
            'label' => Mage::helper('adminhtml')->__('Store View'),
            'size' => 50
        ));
        $this->addColumn('locale', array(
            'label' => Mage::helper('adminhtml')->__('Locale'),
            'size' => 50
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add store mapping');

        parent::__construct();

        $this->setTemplate('jtl/connector/system/config/form/field/array_dropdown.phtml');

        $websiteList = Mage::getResourceModel('core/website_collection')
            ->load()
            ->toOptionArray();

        $this->websiteOptions = array();
        foreach ($websiteList as $att => $innerArray) {
            $this->websiteOptions[$innerArray['value']] = $innerArray['label'];
        }
        asort($this->websiteOptions);

        $storeList = Mage::getResourceModel('core/store_collection')
            ->load()
            ->toOptionArray();

        $this->storeOptions = array();
        foreach ($storeList as $att => $innerArray) {
            $this->storeOptions[$innerArray['value']] = $innerArray['label'];
        }
        asort($this->storeOptions);

        $localeList = Mage::app()->getLocale()->getOptionLocales();
        $this->localeOptions = array();
        foreach ($localeList as $att => $innerArray) {
            $this->localeOptions[$innerArray['value']] = $innerArray['label'];
        }
        asort($this->localeOptions);
    }

    protected function _renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new Exception('Wrong column name specified.');
        }

        $column = $this->_columns[$columnName];
        $inputName = $this->getElement()->getName() . '[#{_id}][' . $columnName . ']';

        if (in_array($columnName, array('website', 'store', 'locale'))) {
            $optionList = $columnName . 'Options';
            $rendered = '<select name="' . $inputName . '">';
            foreach ($this->{$optionList} as $att => $name) {
                $rendered .= '<option value="' . $att . '">' . $name . '</option>';
            }
            $rendered .= '</select>';
        } else {
            return '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' . ($column['size'] ? 'size="' . $column['size'] . '"' : '') . '/>';
        }

        return $rendered;
    }
}
