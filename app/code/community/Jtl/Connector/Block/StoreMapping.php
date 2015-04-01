<?php

class Jtl_Connector_Block_StoreMapping extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected $storeOptions;
    protected $localeOptions;

    public function __construct()
    {
        // create columns
        $this->addColumn('store', array(
            'label' => Mage::helper('adminhtml')->__('Store view'),
            'size' => 50
        ));
        $this->addColumn('locale', array(
            'label' => Mage::helper('adminhtml')->__('Store locale'),
            'size' => 50
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add store mapping');

        parent::__construct();

        $this->setTemplate('jtl/connector/system/config/form/field/array_dropdown.phtml');

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

        if (in_array($columnName, array('store', 'locale'))) {
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