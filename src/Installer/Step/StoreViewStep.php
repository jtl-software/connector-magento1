<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento\Installer
 */
namespace jtl\Connector\Magento\Installer\Step;

use \jtl\Connector\Installer\Step\FormStep;
use \jtl\Connector\Magento\Mapper\Database as MappingDatabase;
use \jtl\Magento\Magento;

/**
 * Description of MappingDatabaseStep
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class StoreViewStep extends FormStep
{
    protected $_template = 'step_storeview';

	protected $_locales = array(
		'ar_AE' => 'Arabic (United Arab Emirates)',
		'ar_BH' => 'Arabic (Bahrain)',
		'ar_DZ' => 'Arabic (Algeria)',
		'ar_EG' => 'Arabic (Egypt)',
		'ar_IQ' => 'Arabic (Iraq)',
		'ar_JO' => 'Arabic (Jordan)',
		'ar_KW' => 'Arabic (Kuwait)',
		'ar_LB' => 'Arabic (Lebanon)',
		'ar_LY' => 'Arabic (Libya)',
		'ar_MA' => 'Arabic (Morocco)',
		'ar_OM' => 'Arabic (Oman)',
		'ar_QA' => 'Arabic (Qatar)',
		'ar_SA' => 'Arabic (Saudi Arabia)',
		'ar_SD' => 'Arabic (Sudan)',
		'ar_SY' => 'Arabic (Syria)',
		'ar_TN' => 'Arabic (Tunisia)',
		'ar_YE' => 'Arabic (Yemen)',
		'be_BY' => 'Belarusian (Belarus)',
		'bg_BG' => 'Bulgarian (Bulgaria)',
		'ca_ES' => 'Catalan (Spain)',
		'cs_CZ' => 'Czech (Czech Republic)',
		'da_DK' => 'Danish (Denmark)',
		'de_AT' => 'German (Austria)',
		'de_CH' => 'German (Switzerland)',
		'de_DE' => 'German (Germany)',
		'de_LU' => 'German (Luxembourg)',
		'el_CY' => 'Greek (Cyprus)',
		'el_GR' => 'Greek (Greece)',
		'en_AU' => 'English (Australia)',
		'en_CA' => 'English (Canada)',
		'en_GB' => 'English (United Kingdom)',
		'en_IE' => 'English (Ireland)',
		'en_IN' => 'English (India)',
		'en_MT' => 'English (Malta)',
		'en_NZ' => 'English (New Zealand)',
		'en_PH' => 'English (Philippines)',
		'en_SG' => 'English (Singapore)',
		'en_US' => 'English (United States)',
		'en_ZA' => 'English (South Africa)',
		'es_AR' => 'Spanish (Argentina)',
		'es_BO' => 'Spanish (Bolivia)',
		'es_CL' => 'Spanish (Chile)',
		'es_CO' => 'Spanish (Colombia)',
		'es_CR' => 'Spanish (Costa Rica)',
		'es_DO' => 'Spanish (Dominican Republic)',
		'es_EC' => 'Spanish (Ecuador)',
		'es_ES' => 'Spanish (Spain)',
		'es_GT' => 'Spanish (Guatemala)',
		'es_HN' => 'Spanish (Honduras)',
		'es_MX' => 'Spanish (Mexico)',
		'es_NI' => 'Spanish (Nicaragua)',
		'es_PA' => 'Spanish (Panama)',
		'es_PE' => 'Spanish (Peru)',
		'es_PR' => 'Spanish (Puerto Rico)',
		'es_PY' => 'Spanish (Paraguay)',
		'es_SV' => 'Spanish (El Salvador)',
		'es_US' => 'Spanish (United States)',
		'es_UY' => 'Spanish (Uruguay)',
		'es_VE' => 'Spanish (Venezuela)',
		'et_EE' => 'Estonian (Estonia)',
		'fi_FI' => 'Finnish (Finland)',
		'fr_BE' => 'French (Belgium)',
		'fr_CA' => 'French (Canada)',
		'fr_CH' => 'French (Switzerland)',
		'fr_FR' => 'French (France)',
		'fr_LU' => 'French (Luxembourg)',
		'ga_IE' => 'Irish (Ireland)',
		'hi_IN' => 'Hindi (India)',
		'hr_HR' => 'Croatian (Croatia)',
		'hu_HU' => 'Hungarian (Hungary)',
		'in_ID' => 'Indonesian (Indonesia)',
		'is_IS' => 'Icelandic (Iceland)',
		'it_CH' => 'Italian (Switzerland)',
		'it_IT' => 'Italian (Italy)',
		'iw_IL' => 'Hebrew (Israel)',
		'ja_JP' => 'Japanese (Japan)',
		'ko_KR' => 'Korean (South Korea)',
		'lt_LT' => 'Lithuanian (Lithuania)',
		'lv_LV' => 'Latvian (Latvia)',
		'mk_MK' => 'Macedonian (Macedonia)',
		'ms_MY' => 'Malay (Malaysia)',
		'mt_MT' => 'Maltese (Malta)',
		'nl_BE' => 'Dutch (Belgium)',
		'nl_NL' => 'Dutch (Netherlands)',
		'no_NO' => 'Norwegian (Norway)',
		'pl_PL' => 'Polish (Poland)',
		'pt_BR' => 'Portuguese (Brazil)',
		'pt_PT' => 'Portuguese (Portugal)',
		'ro_RO' => 'Romanian (Romania)',
		'ru_RU' => 'Russian (Russia)',
		'sk_SK' => 'Slovak (Slovakia)',
		'sl_SI' => 'Slovenian (Slovenia)',
		'sq_AL' => 'Albanian (Albania)',
		'sr_BA' => 'Serbian (Bosnia and Herzegovina)',
		'sr_CS' => 'Serbian (Serbia and Montenegro)',
		'sr_ME' => 'Serbian (Montenegro)',
		'sr_RS' => 'Serbian (Serbia)',
		'sv_SE' => 'Swedish (Sweden)',
		'th_TH' => 'Thai (Thailand)',
		'tr_TR' => 'Turkish (Turkey)',
		'uk_UA' => 'Ukrainian (Ukraine)',
		'vi_VN' => 'Vietnamese (Vietnam)',
		'zh_CN' => 'Chinese (China)',
		'zh_HK' => 'Chinese (Hong Kong)',
		'zh_SG' => 'Chinese (Singapore)',
		'zh_TW' => 'Chinese (Taiwan)'
	);

    public function run()
    {
        Magento::getInstance();
        $websites = \Mage::app()->getWebsites();
        $stores = array();
		$locales = array();
		foreach ($websites as $website) {
			$siteStores = array_filter($website->getStores(), function($store) {
				return is_object($store) && ($store->getIsActive() == 1);
			});

			$stores[$website->getId()] = $siteStores;

			foreach ($siteStores as $store) {
				$locales[$store->getId()] = \Mage::getStoreConfig('general/locale/code', $store->getId());
			}
		}
        
        $this->addParameter('websites', $websites);
        $this->addParameter('stores', $stores);
        $this->addParameter('locales', $locales);

		$this->addParameter('available_locales', $this->_locales);

		// if ($this->validateFormData() && is_array($_POST['locale']))
		if (is_array($_POST['locale'])) {
			$this->addParameter('selected_locales', $_POST['locale']);
		}
		else {
			// Generate suggested locale mapping
			$selectedLocales = array();
			foreach ($stores as $siteId => $storeList) {
				foreach ($storeList as $storeId => $store) {
					$locale = $locales[$storeId];

					if (array_search($locale, $selectedLocales) === FALSE) {
						$selectedLocales[$storeId] = $locale;
					}
				}
			}
			$this->addParameter('selected_locales', $selectedLocales);
		}
        
        return parent::run();
    }
    
    protected function processFormData()
    {
        // Get SQLite database object
        $database = MappingDatabase::getInstance();
        
		$database->exec('DELETE FROM locale');

		// Write locale mapping
		$mapping = (array)$_POST['locale'];
		foreach ($mapping as $storeId => $locale) {
			// Skip store view not to be used
			if (trim($locale) == '')
				continue;

			$database->exec(sprintf('INSERT INTO locale (locale, magento_store) VALUES ("%s", "%u")', $database->escapeString($locale), $database->escapeString($storeId)));
		}

        // Advance to next step
        $this->_installer->advance();
    }

    protected function validateFormData()
    {
        return true;
    }    
}
