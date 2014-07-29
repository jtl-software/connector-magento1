<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use \jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use \jtl\Connector\Model\Currency as ConnectorCurrency;
use \jtl\Connector\Model\CustomerGroup as ConnectorCustomerGroup;
use \jtl\Connector\Model\CustomerGroupI18n as ConnectorCustomerGroupI18n;
use \jtl\Connector\Model\Language as ConnectorLanguage;
use \jtl\Connector\ModelContainer\GlobalDataContainer;
use \jtl\Magento\Magento;

/**
 * Description of GlobalData
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class GlobalData
{
	public function pull()
	{
        $stores = MapperDatabase::getInstance()->getStoreMapping();

		$container = new GlobalDataContainer();

		$languages = $this->pullLanguages();
		foreach ($languages as $language) {
			$container->add('language', $language);
		}
		$currencies = $this->pullCurrencies();
		foreach ($currencies as $currency) {
			$container->add('currency', $currency);
		}
		$customerGroups = $this->pullCustomerGroups();
		foreach ($customerGroups as $customerGroup) {
			$container->add('customer_group', $customerGroup);
		}
		$customerGroupI18ns = $this->pullCustomerGroupI18ns();
		foreach ($customerGroupI18ns as $customerGroupI18n) {
			$container->add('customer_group_i18n', $customerGroupI18n);
		}

		$result = array(
			$container->getPublic(array('items'), array('_fields'))
		);

		return $result;
	}

	public function pullCustomerGroupI18ns()
	{
		Magento::getInstance();
        $stores = MapperDatabase::getInstance()->getStoreMapping();

        $groups = \Mage::getResourceModel('customer/group_collection');
        $result = array();
        foreach ($stores as $locale => $store_id) {
        	Magento::getInstance()->setCurrentStore($store_id);
			
			foreach ($groups as $group) {
				if ($group->customer_group_id == 0)
					continue;

				$customerGroupI18n = new ConnectorCustomerGroupI18n();
				$customerGroupI18n->_localeName = $locale;
				$customerGroupI18n->_customerGroupId = $group->customer_group_id;
				$customerGroupI18n->_name = $group->customer_group_code;

				$result[] = $customerGroupI18n->getPublic(array('_fields'));
			}
        }

        return $result;
	}

	public function pullCustomerGroups()
	{
		Magento::getInstance();

		$defaultCustomerGroupId = \Mage::getStoreConfig('customer/create_account/default_group', \Mage::app()->getStore());
		$groups = \Mage::getResourceModel('customer/group_collection');

		$result = array();
		foreach ($groups as $group) {
			if ($group->customer_group_id == 0)
				continue;

			$customerGroup = new ConnectorCustomerGroup();

			$customerGroup->_id = $group->customer_group_id;
			$customerGroup->_isDefault = ($group->customer_group_id == $defaultCustomerGroupId);

			$result[] = $customerGroup->getPublic(array('_fields'));
		}

        return $result;
	}

	public function pullCurrencies()
	{
		Magento::getInstance();

        $defaultCurrency = \Mage::app()->getWebsite()->getDefaultStore()->getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->getCurrencyCode();

        $defaultLocale = \Mage::app()->getLocale();
		$currencies = \Mage::getModel('directory/currency')->getConfigAllowCurrencies();

		$result = array();
		foreach ($currencies as $currencyCode) {
			$shopCurrency = $defaultLocale->currency($currencyCode);
			$locale = \Mage::getModel('core/locale')->setLocale($shopCurrency->getLocale());
			$priceFormat = $locale->getJsPriceFormat();

        	$currency = new ConnectorCurrency();
        	$currency->_id = strtolower($currencyCode);
			$currency->_name = $shopCurrency->getName();
			$currency->_iso = $shopCurrency->getShortName();
			$currency->_nameHtml = htmlentities(mb_convert_encoding($shopCurrency->getSymbol(), 'ISO-8859-15', 'UTF-8'), ENT_COMPAT, 'ISO-8859-15');
			$currency->_delimiterCent = ',';
			$currency->_delimiterThousand = '.';
			$currency->_hasCurrencySignBeforeValue = false;
			$currency->_isDefault = ($currency->_iso === $defaultCurrencyCode);
			$currency->_factor = $defaultCurrency->getRate($currency->_iso);

			$result[] = $currency->getPublic(array('_fields'));
		}

        return $result;
	}

	public function pullLanguages()
	{
		Magento::getInstance();
        $stores = MapperDatabase::getInstance()->getStoreMapping();

        $result = array();
        foreach ($stores as $localeName => $store_id) {
        	$language = new ConnectorLanguage();
        	$language->_id = $store_id;
        	$language->_localeName = $localeName;
			$locale = new \Zend_Locale($localeName);

			$language->_nameGerman = $locale->getTranslation($locale->getLanguage(), 'language', 'de');
			$language->_nameEnglish = $locale->getTranslation($locale->getLanguage(), 'language', 'en');

			$result[] = $language->getPublic(array('_fields'));
        }

        return $result;
	}
}
