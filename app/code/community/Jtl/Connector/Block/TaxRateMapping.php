<?php

class Jtl_Connector_Block_TaxRateMapping extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected $taxClassOptions;
    protected $taxRateOptions;

    public function __construct()
    {
        // create columns
        $this->addColumn('taxClass', array(
            'label' => Mage::helper('adminhtml')->__('Product Tax Class'),
            'size' => 50
        ));
        $this->addColumn('taxRate', array(
            'label' => Mage::helper('adminhtml')->__('Tax Rate'),
            'size' => 50
        ));

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add tax rate mapping');

        parent::__construct();

        $this->setTemplate('jtl/connector/system/config/form/field/array_dropdown.phtml');

        $taxClassList = Mage::getResourceModel('tax/class_collection')
            ->addFieldToFilter('class_type', Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
            ->load()
            ->toOptionArray();

        $this->taxClassOptions = array();
        foreach ($taxClassList as $att => $innerArray) {
            $this->taxClassOptions[$innerArray['value']] = $innerArray['label'];
        }
        asort($this->taxClassOptions);


        $defaultCountryCode = \Mage::getStoreConfig('general/country/default');
        $defaultCountry = \Mage::getModel('directory/country')->loadByCode($defaultCountryCode);

        $taxRateList = \Mage::getResourceModel('tax/calculation_rate_collection')
            ->addFieldToFilter('tax_country_id', $defaultCountry->getId())
            ->load()
            ->toOptionArray();

        $this->taxRateOptions = array();
        foreach ($taxRateList as $att => $innerArray) {
            $this->taxRateOptions[$innerArray['value']] = $innerArray['label'];
        }
        asort($this->taxRateOptions);
    }

    protected function _renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new Exception('Wrong column name specified.');
        }

        $column = $this->_columns[$columnName];
        $inputName = $this->getElement()->getName() . '[#{_id}][' . $columnName . ']';

        if (in_array($columnName, array('taxClass', 'taxRate'))) {
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

