<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Magento\Magento;
use jtl\Connector\Model\CrossSellingGroup as ConnectorCrossSellingGroup;
use jtl\Connector\Model\CrossSellingGroupI18n as ConnectorCrossSellingGroupI18n;
use jtl\Connector\Model\Currency as ConnectorCurrency;
use jtl\Connector\Model\CustomerGroup as ConnectorCustomerGroup;
use jtl\Connector\Model\CustomerGroupI18n as ConnectorCustomerGroupI18n;
use jtl\Connector\Model\GlobalData as ConnectorGlobalData;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Language as ConnectorLanguage;
use jtl\Connector\Model\TaxRate as ConnectorTaxRate;

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
        $stores = Magento::getInstance()->getStoreMapping();

		$globalData = new ConnectorGlobalData();

		$languages = $this->pullLanguages();
        foreach ($languages as $language)
            $globalData->addLanguage($language);

        $crossSellingGroups = $this->pullCrossSellingGroups();
        foreach ($crossSellingGroups as $crossSellingGroup)
            $globalData->addCrossSellingGroup($crossSellingGroup);

		$currencies = $this->pullCurrencies();
        foreach ($currencies as $currency)
            $globalData->addCurrency($currency);

		$customerGroups = $this->pullCustomerGroups();
        foreach ($customerGroups as $customerGroup)
            $globalData->addCustomerGroup($customerGroup);

        $taxRates = $this->pullTaxRates();
        foreach ($taxRates as $taxRate)
            $globalData->addTaxRate($taxRate);

		return array($globalData);
	}

    public function pullCrossSellingGroups()
    {
        $result = array();

        $xsellGroup = new ConnectorCrossSellingGroup();
        $xsellGroup->setId(new Identity('xsell'));
        $xsellGroupI18n = new ConnectorCrossSellingGroupI18n();
        $xsellGroupI18n
            ->setCrossSellingGroupId(new Identity('xsell'))
            ->setLanguageIso('ger')
            ->setName('CrossSelling')
            ->setDescription('Definiert CrossSelling-Produkte');
        $xsellGroup->addI18n($xsellGroupI18n);
        $result[] = $xsellGroup;

        $upsellGroup = new ConnectorCrossSellingGroup();
        $upsellGroup->setId(new Identity('upsell'));
        $upsellGroupI18n = new ConnectorCrossSellingGroupI18n();
        $upsellGroupI18n
            ->setCrossSellingGroupId(new Identity('upsell'))
            ->setLanguageIso('ger')
            ->setName('Upselling')
            ->setDescription('Definiert Upselling-Produkte');
        $upsellGroup->addI18n($upsellGroupI18n);
        $result[] = $upsellGroup;

        return $result;
    }

	public function pullCustomerGroups()
	{
		Magento::getInstance();
        $stores = Magento::getInstance()->getStoreMapping();

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
                $customerGroupI18n->setLanguageIso(LocaleMapper::localeToLanguageIso($locale));
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
                ->setIso($shopCurrency->getShortName())
                ->setNameHtml($shopCurrency->getShortName())
                ->setDelimiterCent(',')
                ->setDelimiterThousand('.')
                ->setHasCurrencySignBeforeValue(false)
                ->setIsDefault(($currency->getIso() === $defaultCurrencyCode))
                ->setFactor($defaultCurrency->getRate($shopCurrency->getShortName()));

			$result[] = $currency;
		}

        return $result;
	}

	public function pullLanguages()
	{
		Magento::getInstance();
        $stores = Magento::getInstance()->getStoreMapping();

        $result = array();
        foreach ($stores as $localeName => $store_id) {
            $store = \Mage::getModel('core/store')
                ->load($store_id);
            $group = $store->getGroup();
            $defaultStoreId = $group->getDefaultStoreId();
            $website = $store->getWebsite();

        	$language = new ConnectorLanguage();
        	$language->setId(new Identity($store_id, null));
            $language->setLanguageIso(LocaleMapper::localeToLanguageIso($localeName));
            $language->setIsDefault($website->getIsDefault() && ($store_id === $defaultStoreId));
			$locale = new \Zend_Locale($localeName);

			$language->setNameGerman($locale->getTranslation($locale->getLanguage(), 'language', 'de'));
			$language->setNameEnglish($locale->getTranslation($locale->getLanguage(), 'language', 'en'));

			$result[] = $language;
        }

        return $result;
	}

    public function pullTaxRates()
    {
        Magento::getInstance();

        $defaultCountryCode = \Mage::getStoreConfig('general/country/default');
        $defaultCountry = \Mage::getModel('directory/country')->loadByCode($defaultCountryCode);

        $taxRates = \Mage::getResourceModel('tax/calculation_rate_collection')
            ->addFieldToFilter('tax_country_id', $defaultCountry->getId());

        $result = array();
        foreach ($taxRates as $item) {
            if ((double)$item->getRate() < 1e-3)
                continue;

            $taxRate = new ConnectorTaxRate();
            $taxRate->setId(new Identity($item->getId()));
            $taxRate->setPriority(0);
            $taxRate->setRate((double)$item->getRate());

            $result[] = $taxRate;
        }

        return $result;
    }
}
