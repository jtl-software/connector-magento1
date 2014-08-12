<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Magento\Magento;
use jtl\Connector\Magento\Mapper\Database as MapperDatabase;
use jtl\Connector\Model\Currency as ConnectorCurrency;
use jtl\Connector\Model\CustomerGroup as ConnectorCustomerGroup;
use jtl\Connector\Model\CustomerGroupI18n as ConnectorCustomerGroupI18n;
use jtl\Connector\Model\GlobalData as ConnectorGlobalData;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Language as ConnectorLanguage;

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

		$globalData = new ConnectorGlobalData();

		$languages = $this->pullLanguages();
        foreach ($languages as $language)
            $globalData->addLanguage($language);

		$currencies = $this->pullCurrencies();
        foreach ($currencies as $currency)
            $globalData->addCurrency($currency);

		$customerGroups = $this->pullCustomerGroups();
        foreach ($customerGroups as $customerGroup)
            $globalData->addCustomerGroup($customerGroup);

		return array($globalData->getPublic());
	}

	public function pullCustomerGroups()
	{
		Magento::getInstance();
        $stores = MapperDatabase::getInstance()->getStoreMapping();

		$defaultCustomerGroupId = \Mage::getStoreConfig('customer/create_account/default_group', \Mage::app()->getStore());
		$groups = \Mage::getResourceModel('customer/group_collection');

		$result = array();
		foreach ($groups as $group) {
			if ($group->customer_group_id == 0)
				continue;

			$customerGroup = new ConnectorCustomerGroup();

			$customerGroup->setId(new Identity($group->customer_group_id, null));
			$customerGroup->setIsDefault($group->customer_group_id == $defaultCustomerGroupId);

			$result[] = $customerGroup;
		}

        foreach ($stores as $locale => $store_id) {
            Magento::getInstance()->setCurrentStore($store_id);

            foreach ($result as $customerGroup) {
                $model = \Mage::getModel('customer/group')->load($customerGroup->getId()->getEndpoint());

                $customerGroupI18n = new ConnectorCustomerGroupI18n();
                $customerGroupI18n->setLocaleName($locale);
                $customerGroupI18n->setCustomerGroupId(new Identity($model->getCustomerGroupId(), null));
                $customerGroupI18n->setName($model->getCustomerGroupCode());

                $customerGroup->addI18n($customerGroupI18n);
            }
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

        	$currency = new ConnectorCurrency();
            $currency
                ->setId(new Identity(strtolower($currencyCode), null))
                ->setName($shopCurrency->getName())
                // ->setIso($shopCurrency->getShortName())
                ->setNameHtml($shopCurrency->getShortName())
                ->setDelimiterCent(',')
                ->setDelimiterThousand('.')
                ->setHasCurrencySignBeforeValue(false)
                ->setIsDefault(($currency->_iso === $defaultCurrencyCode))
                ->setFactor($defaultCurrency->getRate($shopCurrency->getShortName()));

			$result[] = $currency;
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
        	$language->setId(new Identity($store_id, null));
            $language->setLocaleName($localeName);
			$locale = new \Zend_Locale($localeName);

			$language->setNameGerman($locale->getTranslation($locale->getLanguage(), 'language', 'de'));
			$language->setNameEnglish($locale->getTranslation($locale->getLanguage(), 'language', 'en'));

			$result[] = $language;
        }

        return $result;
	}
}
